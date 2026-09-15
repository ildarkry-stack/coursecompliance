/**
 * Manage the staged course selector on the audit launch form.
 *
 * @module     tool_coursecompliance/course_selector
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    const updateHidden = (picker) => {
        const form = picker.closest('form');
        if (!form) {
            return;
        }
        const hidden = form.querySelector('input[name="courseidsjson"]');
        if (!hidden) {
            return;
        }
        const checked = Array.from(picker.querySelectorAll('[data-role="course-checkbox"]:checked'));
        hidden.value = checked.map((checkbox) => checkbox.value).join(',');
        const counter = picker.querySelector('[data-region="selected-count"]');
        if (counter) {
            counter.textContent = counter.textContent.replace(/\d+/, checked.length.toString());
        }
    };

    const visibleRows = (picker) => Array.from(picker.querySelectorAll('tbody tr')).filter(
        (row) => !row.classList.contains('d-none')
    );

    const initPicker = (picker) => {
        const search = picker.querySelector('[data-action="search"]');
        if (search) {
            search.addEventListener('input', () => {
                const query = search.value.trim().toLocaleLowerCase();
                picker.querySelectorAll('tbody tr').forEach((row) => {
                    const haystack = (row.dataset.search || '').toLocaleLowerCase();
                    row.classList.toggle('d-none', query !== '' && !haystack.includes(query));
                });
            });
        }

        picker.addEventListener('change', (event) => {
            if (event.target.matches('[data-role="course-checkbox"]')) {
                updateHidden(picker);
            }
        });
        picker.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            if (!button || button.matches('[data-action="search"]')) {
                return;
            }
            const action = button.dataset.action;
            if (action === 'select-all') {
                visibleRows(picker).forEach((row) => {
                    const checkbox = row.querySelector('[data-role="course-checkbox"]');
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            } else if (action === 'clear') {
                picker.querySelectorAll('[data-role="course-checkbox"]').forEach((checkbox) => {
                    checkbox.checked = false;
                });
            } else if (action === 'select-first') {
                const count = Number.parseInt(button.dataset.count || '0', 10);
                picker.querySelectorAll('[data-role="course-checkbox"]').forEach((checkbox) => {
                    checkbox.checked = false;
                });
                visibleRows(picker).slice(0, count).forEach((row) => {
                    const checkbox = row.querySelector('[data-role="course-checkbox"]');
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            }
            updateHidden(picker);
        });
        updateHidden(picker);
    };

    const init = () => {
        document.querySelectorAll('[data-region="cc-course-picker"]').forEach(initPicker);
    };

    return {init: init};
});
