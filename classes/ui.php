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
 * Shared user-interface helpers.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Shared user-interface helpers.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Render the primary plugin navigation.
     *
     * @param string $active Active navigation key.
     * @return string HTML.
     */
    public static function primary_navigation(string $active): string {
        $context = \context_system::instance();
        $items = [
            'dashboard' => [
                'label' => get_string('nav_dashboard', 'tool_coursecompliance'),
                'url' => new \moodle_url('/admin/tool/coursecompliance/index.php'),
                'capability' => 'tool/coursecompliance:view',
            ],
            'actions' => [
                'label' => get_string('nav_actions', 'tool_coursecompliance'),
                'url' => new \moodle_url('/admin/tool/coursecompliance/action_center.php'),
                'capability' => 'tool/coursecompliance:view',
            ],
            'audit' => [
                'label' => get_string('nav_audit', 'tool_coursecompliance'),
                'url' => new \moodle_url('/admin/tool/coursecompliance/readiness.php'),
                'capability' => 'tool/coursecompliance:view',
            ],
            'standards' => [
                'label' => get_string('nav_standards', 'tool_coursecompliance'),
                'url' => new \moodle_url('/admin/tool/coursecompliance/profiles.php'),
                'capability' => 'tool/coursecompliance:manageprofiles',
            ],
            'session' => [
                'label' => get_string('nav_session', 'tool_coursecompliance'),
                'url' => new \moodle_url('/admin/tool/coursecompliance/session.php'),
                'capability' => 'tool/coursecompliance:view',
            ],
            'operations' => [
                'label' => get_string('nav_operations', 'tool_coursecompliance'),
                'url' => new \moodle_url('/admin/tool/coursecompliance/activities.php'),
                'capability' => 'tool/coursecompliance:view',
            ],
        ];

        if (has_capability('moodle/site:config', $context)) {
            $items['settings'] = [
                'label' => get_string('nav_settings', 'tool_coursecompliance'),
                'url' => new \moodle_url('/admin/settings.php', ['section' => 'tool_coursecompliance_settings']),
                'capability' => 'moodle/site:config',
            ];
        }

        $links = [];
        foreach ($items as $key => $item) {
            if (!has_capability($item['capability'], $context)) {
                continue;
            }
            $classes = ['nav-link'];
            $attrs = [];
            if ($key === $active) {
                $classes[] = 'active';
                $attrs['aria-current'] = 'page';
            }
            $attrs['class'] = implode(' ', $classes);
            $links[] = \html_writer::tag(
                'li',
                \html_writer::link($item['url'], $item['label'], $attrs),
                ['class' => 'nav-item']
            );
        }

        return \html_writer::tag(
            'nav',
            \html_writer::tag('ul', implode('', $links), ['class' => 'nav nav-pills flex-wrap cc-primary-nav mb-4']),
            ['aria-label' => get_string('pluginnavigation', 'tool_coursecompliance')]
        );
    }

    /**
     * Render secondary navigation for standards pages.
     *
     * @param string $active Active key.
     * @return string HTML.
     */
    public static function standards_navigation(string $active): string {
        $items = [
            'profiles' => [
                get_string('standards', 'tool_coursecompliance'),
                new \moodle_url('/admin/tool/coursecompliance/profiles.php'),
            ],
            'exceptions' => [
                get_string('exceptions', 'tool_coursecompliance'),
                new \moodle_url('/admin/tool/coursecompliance/exceptions.php'),
            ],
        ];
        return self::secondary_navigation($items, $active, 'standardsnavigation');
    }

    /**
     * Render secondary navigation for audit and quality-control pages.
     *
     * @param string $active Active key.
     * @return string HTML.
     */
    public static function audit_navigation(string $active): string {
        $items = [
            'readiness' => [
                get_string('nav_readiness', 'tool_coursecompliance'),
                new \moodle_url('/admin/tool/coursecompliance/readiness.php'),
            ],
            'matrix' => [
                get_string('nav_matrix', 'tool_coursecompliance'),
                new \moodle_url('/admin/tool/coursecompliance/matrix.php'),
            ],
            'presession' => [
                get_string('nav_presession', 'tool_coursecompliance'),
                new \moodle_url('/admin/tool/coursecompliance/presession.php'),
            ],
        ];
        return self::secondary_navigation($items, $active, 'auditnavigation');
    }

    /**
     * Render secondary navigation for activity operations pages.
     *
     * @param string $active Active key.
     * @return string HTML.
     */
    public static function operations_navigation(string $active): string {
        $items = [
            'activities' => [
                get_string('activityoperations', 'tool_coursecompliance'),
                new \moodle_url('/admin/tool/coursecompliance/activities.php'),
            ],
            'history' => [
                get_string('operationhistory', 'tool_coursecompliance'),
                new \moodle_url('/admin/tool/coursecompliance/activity_history.php'),
            ],
        ];
        return self::secondary_navigation($items, $active, 'operationsnavigation');
    }

    /**
     * Render a row of metric cards.
     *
     * @param array $metrics Metrics.
     * @return string HTML.
     */
    public static function metric_cards(array $metrics): string {
        $cards = [];
        foreach ($metrics as $metric) {
            $valueclass = 'display-6 fw-semibold mb-1';
            if (!empty($metric['class'])) {
                $valueclass .= ' ' . $metric['class'];
            }
            $body = \html_writer::div((string)$metric['value'], $valueclass);
            $body .= \html_writer::div(s($metric['label']), 'text-muted fw-semibold');
            if (!empty($metric['help'])) {
                $body .= \html_writer::div(s($metric['help']), 'small text-muted mt-1');
            }
            $card = \html_writer::div(
                \html_writer::div($body, 'card-body'),
                'card h-100 cc-metric-card ' . (!empty($metric['tone']) ? 'cc-tone-' . $metric['tone'] : '')
            );
            if (!empty($metric['url'])) {
                $card = \html_writer::link(
                    $metric['url'],
                    $card,
                    ['class' => 'text-reset text-decoration-none d-block h-100']
                );
            }
            $cards[] = $card;
        }

        if (!$cards) {
            return '';
        }

        $columns = [];
        foreach ($cards as $card) {
            $columns[] = \html_writer::div($card, 'col-12 col-sm-6 col-xl');
        }
        return \html_writer::div(implode('', $columns), 'row g-3 mb-4');
    }

    /**
     * Render a severity badge.
     *
     * @param string $severity Severity value.
     * @return string HTML.
     */
    public static function severity_badge(string $severity): string {
        $classes = [
            'critical' => 'bg-danger',
            'warning' => 'bg-warning text-dark',
            'notice' => 'bg-info text-dark',
        ];
        $class = $classes[$severity] ?? 'bg-secondary';
        $label = get_string('severity_' . $severity, 'tool_coursecompliance');
        return \html_writer::span($label, 'badge ' . $class);
    }

    /**
     * Render a compact readiness-state badge.
     *
     * @param string $status ready, notice, warning or critical.
     * @return string HTML.
     */
    public static function readiness_badge(string $status): string {
        if ($status === 'ready') {
            return \html_writer::span(get_string('ready', 'tool_coursecompliance'), 'badge bg-success');
        }
        return self::severity_badge($status);
    }

    /**
     * Render a status badge for scan/batch statuses.
     *
     * @param string $status Status value.
     * @param string $component String prefix component, status or batchstatus.
     * @return string HTML.
     */
    public static function status_badge(string $status, string $component = 'status'): string {
        $classes = [
            'complete' => 'bg-success',
            'running' => 'bg-primary',
            'queued' => 'bg-info text-dark',
            'partial' => 'bg-warning text-dark',
            'failed' => 'bg-danger',
            'preview' => 'bg-secondary',
        ];
        $identifier = $component . '_' . $status;
        $label = get_string($identifier, 'tool_coursecompliance');
        return \html_writer::span($label, 'badge ' . ($classes[$status] ?? 'bg-secondary'));
    }

    /**
     * Render a background-operation status panel with live counters.
     *
     * @param \stdClass $batch Activity batch.
     * @return string HTML.
     */
    public static function batch_status_panel(\stdClass $batch): string {
        $processed = (int)$batch->applied + (int)$batch->skipped + (int)$batch->failed;
        $total = max(0, (int)$batch->totalchanges);
        $percent = $total > 0 ? min(100, (int)round(($processed / $total) * 100)) : 0;
        if (in_array((string)$batch->status, ['complete', 'partial', 'failed'], true)) {
            $percent = 100;
        }

        $body = \html_writer::div(
            get_string('backgroundoperationstatus', 'tool_coursecompliance') . ': ' .
                self::status_badge((string)$batch->status, 'batchstatus'),
            'fw-semibold mb-2'
        );
        $body .= \html_writer::div(
            get_string('batchprogresscount', 'tool_coursecompliance', (object)[
                'processed' => $processed,
                'total' => $total,
            ]),
            'small text-muted mb-2'
        );
        $progressclass = match ((string)$batch->status) {
            'complete' => 'bg-success',
            'partial' => 'bg-warning',
            'failed' => 'bg-danger',
            default => 'bg-primary',
        };
        $body .= \html_writer::div(
            \html_writer::div('', 'progress-bar ' . $progressclass, [
                'role' => 'progressbar',
                'style' => 'width: ' . $percent . '%',
                'aria-valuenow' => $percent,
                'aria-valuemin' => 0,
                'aria-valuemax' => 100,
            ]),
            'progress mb-3',
            ['style' => 'height: 8px;']
        );

        $metrics = [
            get_string('applied', 'tool_coursecompliance') . ': ' . (int)$batch->applied,
            get_string('skipped', 'tool_coursecompliance') . ': ' . (int)$batch->skipped,
            get_string('failed', 'tool_coursecompliance') . ': ' . (int)$batch->failed,
        ];
        $body .= \html_writer::div(implode(' · ', $metrics), 'small');
        if (!empty($batch->timestarted)) {
            $body .= \html_writer::div(
                get_string('batchstartedat', 'tool_coursecompliance', userdate((int)$batch->timestarted)),
                'small text-muted mt-1'
            );
        }
        if (!empty($batch->timefinished)) {
            $body .= \html_writer::div(
                get_string('batchfinishedat', 'tool_coursecompliance', userdate((int)$batch->timefinished)),
                'small text-muted mt-1'
            );
        }

        $class = match ((string)$batch->status) {
            'complete' => 'border-success',
            'partial' => 'border-warning',
            'failed' => 'border-danger',
            'running' => 'border-primary',
            default => 'border-info',
        };
        return \html_writer::div(
            \html_writer::div($body, 'card-body'),
            'card shadow-sm mb-4 ' . $class
        );
    }

    /**
     * Render a consistent page heading with optional action.
     *
     * @param string $title Page title.
     * @param string $intro Supporting text.
     * @param string $actionhtml Optional action HTML.
     * @return string HTML.
     */
    public static function page_heading(string $title, string $intro = '', string $actionhtml = ''): string {
        $copy = \html_writer::tag('h2', $title, ['class' => 'h2']);
        if ($intro !== '') {
            $copy .= \html_writer::div(s($intro), 'cc-page-intro');
        }
        $content = \html_writer::div($copy);
        if ($actionhtml !== '') {
            $content .= \html_writer::div($actionhtml, 'cc-page-heading-action');
        }
        return \html_writer::div($content, 'cc-page-heading');
    }

    /**
     * Render a standard content panel.
     *
     * @param string $title Panel title.
     * @param string $body Panel body.
     * @param string $subtitle Optional subtitle.
     * @return string HTML.
     */
    public static function panel(string $title, string $body, string $subtitle = ''): string {
        $header = \html_writer::tag('h3', $title, ['class' => 'h5 mb-0']);
        if ($subtitle !== '') {
            $header .= \html_writer::div(s($subtitle), 'small text-muted mt-1');
        }
        return \html_writer::div(
            \html_writer::div($header, 'cc-panel-header') . \html_writer::div($body, 'cc-panel-body'),
            'cc-panel'
        );
    }

    /**
     * Render a compact section intro.
     *
     * @param string $text Introductory text.
     * @return string HTML.
     */
    public static function intro(string $text): string {
        return \html_writer::div(s($text), 'cc-page-intro mb-4');
    }

    /**
     * Render secondary tabs.
     *
     * @param array $items Items.
     * @param string $active Active key.
     * @param string $labelstring Language identifier for aria label.
     * @return string HTML.
     */
    private static function secondary_navigation(array $items, string $active, string $labelstring): string {
        $links = [];
        foreach ($items as $key => [$label, $url]) {
            $classes = ['nav-link'];
            $attrs = [];
            if ($key === $active) {
                $classes[] = 'active';
                $attrs['aria-current'] = 'page';
            }
            $attrs['class'] = implode(' ', $classes);
            $links[] = \html_writer::tag(
                'li',
                \html_writer::link($url, $label, $attrs),
                ['class' => 'nav-item']
            );
        }
        return \html_writer::tag(
            'nav',
            \html_writer::tag('ul', implode('', $links), ['class' => 'nav nav-pills flex-wrap cc-secondary-nav mb-4']),
            ['aria-label' => get_string($labelstring, 'tool_coursecompliance')]
        );
    }
}
