/**
 * Move the CourseCompliance warning into the main course content.
 *
 * @module     tool_coursecompliance/course_indicator
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    const placeIndicator = (indicatorId) => {
        const indicator = document.getElementById(indicatorId);
        if (!indicator) {
            return true;
        }

        const courseContent = document.querySelector('#region-main .course-content');
        if (courseContent && courseContent.parentNode) {
            courseContent.parentNode.insertBefore(indicator, courseContent);
            return true;
        }

        const main = document.querySelector('#region-main');
        if (main) {
            const mainContent = main.querySelector('[role="main"]');
            if (mainContent) {
                mainContent.insertBefore(indicator, mainContent.firstChild);
            } else {
                main.insertBefore(indicator, main.firstChild);
            }
            return true;
        }
        return false;
    };

    const init = (indicatorId) => {
        const move = () => placeIndicator(indicatorId);
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', move, {once: true});
        } else {
            move();
        }

        window.setTimeout(move, 100);
        window.setTimeout(move, 500);
    };

    return {init: init};
});
