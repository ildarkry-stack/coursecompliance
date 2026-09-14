/**
 * Render administrator-only CourseCompliance status banners on the course home page.
 *
 * @module     tool_coursecompliance/course_indicator
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    const ID = 'tool-coursecompliance-course-status';

    const makeAlert = (cssClass, title, text) => {
        const alert = document.createElement('div');
        alert.className = 'alert ' + cssClass + ' mb-2';
        alert.setAttribute('role', 'status');

        const heading = document.createElement('div');
        heading.className = 'fw-bold';
        heading.textContent = title;
        alert.appendChild(heading);

        if (text) {
            const description = document.createElement('div');
            description.className = 'small mt-1';
            description.textContent = text;
            alert.appendChild(description);
        }
        return alert;
    };

    const buildIndicator = (data) => {
        const container = document.createElement('div');
        container.id = ID;
        container.className = 'tool-coursecompliance-course-status mb-3';

        if (data.ready) {
            container.appendChild(makeAlert('alert-success', data.readytitle, data.readytext));
        } else {
            if (data.critical > 0) {
                container.appendChild(makeAlert('alert-danger', data.criticaltitle, data.criticaltext));
            }
            if (data.warnings > 0) {
                container.appendChild(makeAlert('alert-warning', data.warningtitle, data.warningtext));
            }
            if (data.notices > 0) {
                container.appendChild(makeAlert('alert-info', data.noticetitle, data.noticetext));
            }
        }

        const footer = document.createElement('div');
        footer.className = 'd-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mt-2';

        const scan = document.createElement('div');
        scan.className = 'small text-muted';
        scan.textContent = data.lastscan;
        footer.appendChild(scan);

        const link = document.createElement('a');
        link.className = 'btn btn-sm btn-outline-secondary';
        link.href = data.reporturl;
        link.textContent = data.reviewlabel;
        footer.appendChild(link);
        container.appendChild(footer);

        return container;
    };

    const findTarget = () => {
        const courseContent = document.querySelector('#region-main .course-content');
        if (courseContent && courseContent.parentNode) {
            return {parent: courseContent.parentNode, before: courseContent};
        }

        const mainContent = document.querySelector('#region-main [role="main"]');
        if (mainContent) {
            const firstCourseSection = mainContent.querySelector('[data-for="course_sectionlist"], .course-content');
            return {parent: mainContent, before: firstCourseSection || mainContent.firstChild};
        }

        const main = document.querySelector('#region-main');
        if (main) {
            return {parent: main, before: main.firstChild};
        }
        return null;
    };

    const placeIndicator = (data) => {
        if (document.getElementById(ID)) {
            return true;
        }
        const target = findTarget();
        if (!target) {
            return false;
        }
        const indicator = buildIndicator(data);
        target.parent.insertBefore(indicator, target.before);
        return true;
    };

    const init = (data) => {
        const place = () => placeIndicator(data);
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', place, {once: true});
        } else {
            place();
        }

        window.setTimeout(place, 100);
        window.setTimeout(place, 500);
        window.setTimeout(place, 1200);
    };

    return {init: init};
});
