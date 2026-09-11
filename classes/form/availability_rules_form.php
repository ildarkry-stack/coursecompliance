<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Native Moodle availability editor wrapper for Blueprint assessment rules.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Uses Moodle core's availability editor for one exam/resit target.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class availability_rules_form extends \moodleform {
    /**
     * Define form fields.
     */
    protected function definition(): void {
        global $CFG, $OUTPUT;

        $mform = $this->_form;
        $course = $this->_customdata['course'];
        $cm = $this->_customdata['cm'] ?? null;
        $profileid = (int)$this->_customdata['profileid'];
        $assessmenttype = (string)$this->_customdata['assessmenttype'];
        $targetidnumber = (string)$this->_customdata['targetidnumber'];

        $mform->addElement('hidden', 'profileid', $profileid);
        $mform->setType('profileid', PARAM_INT);
        $mform->addElement('hidden', 'assessmenttype', $assessmenttype);
        $mform->setType('assessmenttype', PARAM_ALPHA);

        $targetinfo = (object)[
            'type' => strtoupper($assessmenttype),
            'idnumber' => $targetidnumber,
        ];
        $mform->addElement(
            'static',
            'targetinfo',
            get_string('availabilitytarget', 'tool_coursecompliance'),
            get_string('availabilitytargetinfo', 'tool_coursecompliance', $targetinfo)
        );
        $mform->addElement(
            'static',
            'dependencyinfo',
            '',
            get_string('availabilitydependencyinfo', 'tool_coursecompliance')
        );

        if (empty($CFG->enableavailability)) {
            $mform->addElement(
                'static',
                'availabilitydisabled',
                '',
                get_string('availabilitydisabledsite', 'tool_coursecompliance')
            );
            $this->add_action_buttons(true, get_string('back'));
            return;
        }

        // Keep these names identical to Moodle's standard activity form so the
        // core availability JavaScript can render the native editor unchanged.
        $mform->addElement(
            'header',
            'availabilityconditionsheader',
            get_string('restrictaccess', 'availability')
        );
        $mform->addElement(
            'textarea',
            'availabilityconditionsjson',
            get_string('accessrestrictions', 'availability'),
            ['class' => 'd-none']
        );
        $mform->setType('availabilityconditionsjson', PARAM_RAW);

        $loadingcontainer = $OUTPUT->container(
            $OUTPUT->render_from_template('core/loading', []),
            'd-flex justify-content-center py-5 icon-size-5',
            'availabilityconditions-loading'
        );
        $mform->addElement('html', $loadingcontainer);

        \core_availability\frontend::include_all_javascript($course, $cm);
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validate Moodle availability JSON using the core API.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array<string, string>
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (array_key_exists('availabilityconditionsjson', $data)) {
            \core_availability\frontend::report_validation_errors($data, $errors);
            $json = json_decode((string)$data['availabilityconditionsjson'], true);
            if (is_array($json) && $this->has_disallowed_dependency($json)) {
                $errors['availabilityconditionsjson'] = get_string(
                    'availabilityinvaliddependency',
                    'tool_coursecompliance'
                );
            }
        }
        return $errors;
    }

    /**
     * Check that activity and grade dependencies only reference assessment targets.
     *
     * @param array $node Availability tree node.
     * @return bool True when a disallowed dependency is present.
     */
    private function has_disallowed_dependency(array $node): bool {
        $type = (string)($node['type'] ?? '');
        if ($type === 'completion' && isset($node['cm'])) {
            $allowed = array_map('intval', $this->_customdata['allowedcmids'] ?? []);
            if (!in_array((int)$node['cm'], $allowed, true)) {
                return true;
            }
        }
        if ($type === 'grade' && isset($node['id'])) {
            $allowed = array_map('intval', $this->_customdata['allowedgradeids'] ?? []);
            if (!in_array((int)$node['id'], $allowed, true)) {
                return true;
            }
        }
        foreach ($node as $child) {
            if (is_array($child) && $this->has_disallowed_dependency($child)) {
                return true;
            }
        }
        return false;
    }
}
