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
 * Legacy Assessment Auditor route.
 *
 * The standalone auditor was merged into the readiness report in 0.7.
 * This route remains to preserve bookmarks and links from older versions.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();
require_capability('tool/coursecompliance:view', context_system::instance());

$scanid = required_param('scanid', PARAM_INT);
$type = optional_param('assessmenttype', '', PARAM_ALPHANUMEXT);
$params = ['scanid' => $scanid];
if (in_array($type, ['exam', 'resit1', 'resit2'], true)) {
    $params['assessmenttype'] = $type;
}
redirect(new moodle_url('/admin/tool/coursecompliance/readiness.php', $params));
