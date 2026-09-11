/**
 * Limit native Moodle availability selectors to exam/resit Blueprint targets.
 *
 * @module     tool_coursecompliance/availability_filter
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    const filterSelect = (select, allowed, labels) => {
        let changed = false;
        Array.from(select.options).forEach((option) => {
            if (option.value !== '0' && !allowed.has(option.value)) {
                option.remove();
                changed = true;
                return;
            }
            if (option.value !== '0' && Object.prototype.hasOwnProperty.call(labels, option.value) &&
                    option.textContent !== labels[option.value]) {
                option.textContent = labels[option.value];
                changed = true;
            }
        });
        return changed;
    };

    const applyFilter = (config) => {
        const allowedCmids = new Set((config.allowedcmids || []).map(String));
        const allowedGradeids = new Set((config.allowedgradeids || []).map(String));
        const cmlabels = config.cmlabels || {};
        const gradelabels = config.gradelabels || {};

        let changed = false;
        document.querySelectorAll('.availability_completion select[name="cm"]').forEach((select) => {
            changed = filterSelect(select, allowedCmids, cmlabels) || changed;
        });
        document.querySelectorAll('.availability_grade select[name="id"]').forEach((select) => {
            changed = filterSelect(select, allowedGradeids, gradelabels) || changed;
        });
        if (changed && window.M && M.core_availability && M.core_availability.form &&
                M.core_availability.form.rootList) {
            M.core_availability.form.update();
        }
    };

    const init = (config) => {
        const observer = new MutationObserver(() => applyFilter(config));
        observer.observe(document.body, {childList: true, subtree: true});
        applyFilter(config);
        window.setTimeout(() => applyFilter(config), 250);
        window.setTimeout(() => applyFilter(config), 1000);
    };

    return {init: init};
});
