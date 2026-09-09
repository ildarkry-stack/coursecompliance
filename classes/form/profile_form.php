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
 * Classes for the Course compliance plugin.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating and editing compliance profiles.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profile_form extends \moodleform {
    /**
     * Define form fields.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('text', 'name', get_string('profilename', 'tool_coursecompliance'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement(
            'textarea',
            'description',
            get_string('profiledescription', 'tool_coursecompliance'),
            ['rows' => 4, 'cols' => 70]
        );
        $mform->setType('description', PARAM_TEXT);
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'tool_coursecompliance'));

        $mform->addElement('header', 'targets', get_string('profiletargets', 'tool_coursecompliance'));
        foreach (['exam', 'resit1', 'resit2'] as $type) {
            $mform->addElement('text', $type . 'idnumber', get_string($type . 'idnumber', 'tool_coursecompliance'));
            $mform->setType($type . 'idnumber', PARAM_ALPHANUMEXT);
        }
        $mform->addElement('advcheckbox', 'requireexam', get_string('requireexam', 'tool_coursecompliance'));
        $mform->addElement('advcheckbox', 'requireresit1', get_string('requireresit1', 'tool_coursecompliance'));
        $mform->addElement('advcheckbox', 'requireresit2', get_string('requireresit2', 'tool_coursecompliance'));

        $mform->addElement('header', 'coursechecks', get_string('coursechecks', 'tool_coursecompliance'));
        $mform->addElement('advcheckbox', 'requirecompletion', get_string('requirecompletion', 'tool_coursecompliance'));
        $mform->addElement('advcheckbox', 'requireteacher', get_string('requireteacher', 'tool_coursecompliance'));

        $mform->addElement('header', 'quizchecks', get_string('quizchecks', 'tool_coursecompliance'));
        $mform->addElement('text', 'examattempts', get_string('expectedattempts', 'tool_coursecompliance'), ['size' => 6]);
        $mform->setType('examattempts', PARAM_INT);
        $mform->addHelpButton('examattempts', 'expectedattempts', 'tool_coursecompliance');
        $mform->addElement(
            'duration',
            'examtimelimit',
            get_string('expectedtimelimit', 'tool_coursecompliance'),
            ['optional' => true]
        );
        $mform->addElement('text', 'examgrade', get_string('expectedquizgrade', 'tool_coursecompliance'), ['size' => 8]);
        $mform->setType('examgrade', PARAM_FLOAT);
        $mform->addElement('advcheckbox', 'requiregradepass', get_string('requiregradepass', 'tool_coursecompliance'));
        $mform->addElement('text', 'mingradepass', get_string('mingradepass', 'tool_coursecompliance'), ['size' => 8]);
        $mform->setType('mingradepass', PARAM_FLOAT);
        $mform->disabledIf('mingradepass', 'requiregradepass');
        $mform->addElement('text', 'minquestions', get_string('minquestions', 'tool_coursecompliance'), ['size' => 6]);
        $mform->setType('minquestions', PARAM_INT);
        $mform->addElement('text', 'maxquestions', get_string('maxquestions', 'tool_coursecompliance'), ['size' => 6]);
        $mform->setType('maxquestions', PARAM_INT);
        $mform->addElement('advcheckbox', 'requireshuffleanswers', get_string('requireshuffleanswers', 'tool_coursecompliance'));

        $mform->addElement('header', 'reviewchecks', get_string('reviewchecks', 'tool_coursecompliance'));
        $mform->addElement('advcheckbox', 'hideanswerswhileopen', get_string('hideanswerswhileopen', 'tool_coursecompliance'));
        $mform->addElement(
            'advcheckbox',
            'hidecorrectnesswhileopen',
            get_string('hidecorrectnesswhileopen', 'tool_coursecompliance')
        );

        $mform->addElement('header', 'accesschecks', get_string('accesschecks', 'tool_coursecompliance'));
        $mform->addElement('advcheckbox', 'requirepassword', get_string('requirepassword', 'tool_coursecompliance'));
        $mform->addElement('advcheckbox', 'requiresubnet', get_string('requiresubnet', 'tool_coursecompliance'));
        $mform->addElement(
            'text',
            'expectedbrowsersecurity',
            get_string('expectedbrowsersecurity', 'tool_coursecompliance'),
            ['size' => 32]
        );
        $mform->setType('expectedbrowsersecurity', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('expectedbrowsersecurity', 'expectedbrowsersecurity', 'tool_coursecompliance');

        $mform->addElement('header', 'overridechecks', get_string('overridechecks', 'tool_coursecompliance'));
        $mform->addElement('advcheckbox', 'warnoverrides', get_string('warnoverrides', 'tool_coursecompliance'));
        $mform->addElement(
            'text',
            'maxoverrideattempts',
            get_string('maxoverrideattempts', 'tool_coursecompliance'),
            ['size' => 6]
        );
        $mform->setType('maxoverrideattempts', PARAM_INT);
        $mform->addElement(
            'duration',
            'maxoverrideextension',
            get_string('maxoverrideextension', 'tool_coursecompliance'),
            ['optional' => true]
        );

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validate form values.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array<string, string>
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((int)$data['maxquestions'] > 0 && (int)$data['maxquestions'] < (int)$data['minquestions']) {
            $errors['maxquestions'] = get_string('invalidquestionrange', 'tool_coursecompliance');
        }
        if ((float)$data['examgrade'] < 0 || (float)$data['mingradepass'] < 0) {
            $errors['examgrade'] = get_string('positivevalueexpected', 'tool_coursecompliance');
        }
        $seen = [];
        foreach (['examidnumber', 'resit1idnumber', 'resit2idnumber'] as $field) {
            $value = trim((string)($data[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            if (isset($seen[$value])) {
                $errors[$field] = get_string('duplicateprofiletarget', 'tool_coursecompliance', $value);
            }
            $seen[$value] = true;
        }
        return $errors;
    }
}
