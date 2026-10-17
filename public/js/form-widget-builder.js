// Admin editor of a list or composed widget (templates/form_widget/edit.html.twig).
// Shows the fields of the selected kind and manages the entries of a composed widget.
// Entries are renumbered in display order; each keeps its answer key in a hidden input.
document.addEventListener('DOMContentLoaded', () => {
    const nativeTypeOf = (select) => select.selectedOptions[0]?.dataset.nativeType ?? '';

    // Kind sections
    const kind = document.querySelector('[data-widget-kind]');
    const toggleSections = () => {
        document.querySelectorAll('[data-widget-section]').forEach((section) => {
            section.hidden = section.dataset.widgetSection !== kind.value;
        });
    };
    kind.addEventListener('change', toggleSections);
    toggleSections();

    // List: options only when it repeats a dropdown
    const listWidget = document.querySelector('[data-list-widget]');
    const listChoices = document.querySelector('[data-list-choices]');
    if (listWidget && listChoices) {
        const toggleListChoices = () => {
            listChoices.hidden = kind.value !== 'list' || nativeTypeOf(listWidget) !== 'choice';
        };
        listWidget.addEventListener('change', toggleListChoices);
        kind.addEventListener('change', toggleListChoices);
        toggleListChoices();
    }

    const list = document.querySelector('[data-entry-list]');
    if (!list) return;

    const rows = () => Array.from(list.querySelectorAll('[data-entry-row]'));

    // "Obligatoire" only for inputs, options only for dropdowns
    const toggleEntryOptions = (row) => {
        const type = nativeTypeOf(row.querySelector('[data-entry-widget]'));
        row.querySelector('[data-entry-required]').hidden = type === '';
        row.querySelector('[data-entry-choices]').hidden = type !== 'choice';
    };

    const renumber = () => {
        const name = list.dataset.name;
        const id = list.dataset.id;
        rows().forEach((row, index) => {
            row.querySelectorAll('[name]').forEach((input) => {
                input.name = input.name.replace(new RegExp(`^${name.replace(/[[\]]/g, '\\$&')}\\[[^\\]]+\\]`), `${name}[${index}]`);
                input.id = input.id.replace(new RegExp(`^${id}_[^_]+_`), `${id}_${index}_`);
            });
            row.querySelectorAll('label[for]').forEach((label) => {
                label.htmlFor = label.htmlFor.replace(new RegExp(`^${id}_[^_]+_`), `${id}_${index}_`);
            });
        });
    };

    rows().forEach(toggleEntryOptions);

    document.querySelector('[data-entry-add]').addEventListener('click', () => {
        const template = document.createElement('template');
        template.innerHTML = list.dataset.prototype.replace(/__name__/g, `new${Date.now()}`).trim();
        const row = template.content.firstElementChild;
        list.appendChild(row);
        toggleEntryOptions(row);
        renumber();
        row.querySelector('select').focus();
    });

    list.addEventListener('change', (event) => {
        if (event.target.matches('[data-entry-widget]')) {
            toggleEntryOptions(event.target.closest('[data-entry-row]'));
        }
    });

    list.addEventListener('click', (event) => {
        const row = event.target.closest('[data-entry-row]');
        if (!row) return;

        if (event.target.closest('[data-entry-remove]')) {
            row.remove();
        } else if (event.target.closest('[data-entry-up]') && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling);
        } else if (event.target.closest('[data-entry-down]') && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row);
        } else {
            return;
        }
        renumber();
    });
});
