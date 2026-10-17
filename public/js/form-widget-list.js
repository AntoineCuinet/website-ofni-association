// LIST widgets of generated forms (templates/form_widget/_theme.html.twig):
// "+" adds an entry from the list's prototype, "−" removes one, never going below data-min.
// Lists can be nested: each has its own prototype placeholder and only handles its direct entries.
(() => {
    const entriesOf = (list) => Array.from(
        list.querySelector(':scope > [data-widget-list-entries]').children
    ).filter((entry) => entry.matches('[data-widget-list-entry]'));

    const refresh = (list) => {
        const entries = entriesOf(list);
        const min = parseInt(list.dataset.min, 10) || 0;
        entries.forEach((entry) => {
            entry.querySelector(':scope > [data-widget-list-remove]').hidden = entries.length <= min;
        });
    };

    document.addEventListener('click', (event) => {
        const add = event.target.closest('[data-widget-list-add]');
        if (add) {
            const list = add.closest('[data-widget-list]');
            const index = parseInt(list.dataset.index, 10);
            list.dataset.index = index + 1;
            const template = document.createElement('template');
            template.innerHTML = list.dataset.prototype.split(list.dataset.prototypeName).join(index).trim();
            const entry = template.content.firstElementChild;
            list.querySelector(':scope > [data-widget-list-entries]').appendChild(entry);
            entry.querySelectorAll('[data-widget-list]').forEach(refresh);
            refresh(list);
            entry.querySelector('input, select, textarea')?.focus();
            return;
        }

        const remove = event.target.closest('[data-widget-list-remove]');
        if (remove) {
            const entry = remove.closest('[data-widget-list-entry]');
            const list = entry.closest('[data-widget-list]');
            entry.remove();
            refresh(list);
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-widget-list]').forEach(refresh);
    });
})();
