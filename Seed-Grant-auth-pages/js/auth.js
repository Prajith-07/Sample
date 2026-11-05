(() => {
    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        const inputId = toggle.getAttribute('aria-controls');
        const input = inputId ? document.getElementById(inputId) : null;
        const label = toggle.querySelector('[data-password-toggle-label]');

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        toggle.addEventListener('click', () => {
            const isVisible = input.type === 'password';
            input.type = isVisible ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(isVisible));

            const actionLabel = isVisible ? 'Hide password' : 'Show password';
            toggle.setAttribute('aria-label', actionLabel);

            if (label) {
                label.textContent = actionLabel;
            }
        });
    });

    document.querySelectorAll('[data-auth-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
        });
    });
})();
