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

namespace tool_coursecompliance\local\activity;

/**
 * Date conversions for the activity operations UI.
 *
 * @package    tool_coursecompliance
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class date_helper {
    /**
     * Convert timestamp to HTML datetime-local value in the user's timezone.
     *
     * @param int $timestamp Unix timestamp or 0.
     * @return string
     */
    public static function to_input_value(int $timestamp): string {
        if (!$timestamp) {
            return '';
        }
        $date = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(\core_date::get_user_timezone_object());
        return $date->format('Y-m-d\\TH:i');
    }

    /**
     * Parse HTML datetime-local value using the user's timezone.
     *
     * @param string $value HTML datetime-local value.
     * @return int Unix timestamp or 0 for blank.
     */
    public static function from_input_value(string $value): int {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $timezone = \core_date::get_user_timezone_object();
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value, $timezone);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
            throw new \moodle_exception('invaliddatetime', 'tool_coursecompliance');
        }
        return $date->getTimestamp();
    }

    /**
     * Shift a date while preserving local wall-clock semantics where possible.
     *
     * @param int $timestamp Original timestamp.
     * @param int $amount Signed amount.
     * @param string $unit hour, day, week, or month.
     * @return int Shifted timestamp.
     */
    public static function shift(int $timestamp, int $amount, string $unit): int {
        if (!$timestamp || !$amount) {
            return $timestamp;
        }
        if (!in_array($unit, ['hour', 'day', 'week', 'month'], true)) {
            throw new \coding_exception('Unsupported date shift unit.');
        }
        $date = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(\core_date::get_user_timezone_object());
        $modifier = ($amount >= 0 ? '+' : '') . $amount . ' ' . $unit . (abs($amount) === 1 ? '' : 's');
        $shifted = $date->modify($modifier);
        if (!$shifted) {
            throw new \moodle_exception('invaliddatetime', 'tool_coursecompliance');
        }
        return $shifted->getTimestamp();
    }
}
