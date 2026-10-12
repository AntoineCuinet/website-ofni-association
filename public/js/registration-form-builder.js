// Admin page of an event registration form (templates/event_registration/admin_form.html.twig).
// The fields themselves are a library widget, edited in templates/form_widget/edit.html.twig.
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
});
