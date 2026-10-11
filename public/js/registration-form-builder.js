// Admin builder for event registration forms (templates/event_registration/admin_form.html.twig).
// Rows keep their collection index so each stays bound to the same field;
// the display order is written to each row's hidden "position" input.
document.addEventListener('DOMContentLoaded', () => {
    // Closing date only applies to "Ouvertes jusqu'au…"
    const status = document.querySelector('[data-registration-status]');
    const closesAt = document.querySelector('[data-registration-closes-at]');
    if (status && closesAt) {
        const toggleClosesAt = () => {
            closesAt.hidden = status.value !== 'until';
        };
        status.addEventListener('change', toggleClosesAt);
        toggleClosesAt();
    }

    const list = document.querySelector('[data-field-list]');
    if (!list) return;

    const rows = () => Array.from(list.querySelectorAll('[data-field-row]'));

    const updatePositions = () => {
        rows().forEach((row, index) => {
            row.querySelector('[data-field-position]').value = index;
        });
    };

    // Options textarea only makes sense for dropdowns
    const toggleChoices = (row) => {
        const type = row.querySelector('[data-field-type]');
        const choices = row.querySelector('[data-field-choices]');
        choices.hidden = type.value !== 'choice';
    };

    rows().forEach(toggleChoices);
    updatePositions();

    document.querySelector('[data-field-add]').addEventListener('click', () => {
        const index = parseInt(list.dataset.index, 10);
        list.dataset.index = index + 1;
        const template = document.createElement('template');
        template.innerHTML = list.dataset.prototype.replace(/__name__/g, index).trim();
        const row = template.content.firstElementChild;
        list.appendChild(row);
        toggleChoices(row);
        updatePositions();
        row.querySelector('input[type="text"]').focus();
    });

    list.addEventListener('change', (event) => {
        if (event.target.matches('[data-field-type]')) {
            toggleChoices(event.target.closest('[data-field-row]'));
        }
    });

    list.addEventListener('click', (event) => {
        const row = event.target.closest('[data-field-row]');
        if (!row) return;

        if (event.target.closest('[data-field-remove]')) {
            row.remove();
        } else if (event.target.closest('[data-field-up]') && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling);
        } else if (event.target.closest('[data-field-down]') && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row);
        } else {
            return;
        }
        updatePositions();
    });
});
