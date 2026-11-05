(() => {
    // 1. Password Visibility Toggle
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

    // 2. Real-time Form Validation & Submit Button Activation
    document.querySelectorAll('form[data-auth-form]').forEach((form) => {
        const submitBtn = form.querySelector('.auth-submit');
        if (!submitBtn) return;

        let emailAvailable = true;
        let emailDebounceTimer = null;
        const leadEmailInput = form.querySelector('#team-leader-email');
        const feedbackEl = form.querySelector('#email-duplicate-feedback');

        if (leadEmailInput && feedbackEl) {
            leadEmailInput.addEventListener('input', () => {
                clearTimeout(emailDebounceTimer);
                const emailVal = leadEmailInput.value.trim().toLowerCase();

                if (!emailVal || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
                    feedbackEl.style.display = 'none';
                    emailAvailable = true;
                    checkFormValidity();
                    return;
                }

                emailDebounceTimer = setTimeout(async () => {
                    try {
                        const res = await fetch(`api/check_duplicate.php?field=college_email&value=${encodeURIComponent(emailVal)}`);
                        const data = await res.json();
                        
                        if (data && data.available === false) {
                            emailAvailable = false;
                            feedbackEl.style.display = 'block';
                            feedbackEl.style.color = '#dc2626';
                            feedbackEl.innerHTML = `Email is already registered in DB. Please <a href="login.php" style="color:#15803d; font-weight:700; text-decoration:underline;">move to the Login page</a> to sign in.`;
                            leadEmailInput.style.borderColor = '#ef4444';
                        } else {
                            emailAvailable = true;
                            feedbackEl.style.display = 'block';
                            feedbackEl.style.color = '#15803d';
                            feedbackEl.innerHTML = `✓ College email is available for registration.`;
                            leadEmailInput.style.borderColor = '#22c55e';
                        }
                    } catch (e) {
                        emailAvailable = true;
                        feedbackEl.style.display = 'none';
                    }
                    checkFormValidity();
                }, 400);
            });
        }

        function checkFormValidity() {
            let isValid = true;
            const requiredInputs = form.querySelectorAll('input[required]');
            requiredInputs.forEach((inp) => {
                if (!inp.value.trim()) {
                    isValid = false;
                }
            });

            // If signup form, verify password confirmation and domain
            const pwdInput = form.querySelector('input[name="team_password"]');
            const confirmInput = form.querySelector('input[name="team_password_confirm"]');
            if (pwdInput && confirmInput) {
                if (pwdInput.value && confirmInput.value && pwdInput.value !== confirmInput.value) {
                    isValid = false;
                }
            }

            if (!emailAvailable) {
                isValid = false;
            }

            submitBtn.disabled = !isValid;
        }

        form.addEventListener('input', checkFormValidity);
        // Initial check
        checkFormValidity();
    });
})();
