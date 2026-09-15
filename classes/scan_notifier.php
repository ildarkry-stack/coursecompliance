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
 * Moodle messaging notifications for scans.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Sends scan completion/failure notifications to the initiating user.
 */
final class scan_notifier {
    /**
     * Send successful scan notification.
     *
     * @param \stdClass $scan Scan record.
     */
    public static function completed(\stdClass $scan): void {
        $a = (object)[
            'courses' => (int)$scan->courses,
            'critical' => (int)$scan->critical,
            'warnings' => (int)$scan->warnings,
        ];
        self::send(
            $scan,
            get_string('scannotificationsubjectcomplete', 'tool_coursecompliance'),
            get_string('scannotificationcomplete', 'tool_coursecompliance', $a)
        );
    }

    /**
     * Send failed scan notification.
     *
     * @param \stdClass $scan Scan record.
     */
    public static function failed(\stdClass $scan): void {
        $a = (object)[
            'id' => (int)$scan->id,
            'error' => trim((string)$scan->errormessage),
        ];
        self::send(
            $scan,
            get_string('scannotificationsubjectfailed', 'tool_coursecompliance'),
            get_string('scannotificationfailed', 'tool_coursecompliance', $a)
        );
    }

    /**
     * Send notification through Moodle Messaging.
     *
     * @param \stdClass $scan Scan record.
     * @param string $subject Subject.
     * @param string $body Message body.
     */
    private static function send(\stdClass $scan, string $subject, string $body): void {
        global $DB;

        $userid = (int)$scan->userid;
        if ($userid <= 0) {
            return;
        }
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user) {
            return;
        }

        $url = new \moodle_url('/admin/tool/coursecompliance/report.php', ['scanid' => (int)$scan->id]);
        $message = new \core\message\message();
        $message->component = 'tool_coursecompliance';
        $message->name = 'scan_complete';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = \html_writer::div(s($body));
        $message->smallmessage = $body;
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = get_string('viewreport', 'tool_coursecompliance');
        try {
            message_send($message);
        } catch (\Throwable $e) {
            debugging(
                'Course Compliance could not send scan notification: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }
}
