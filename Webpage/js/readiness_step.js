/**
 * iTech Seed Grand Challenge 2027
 * Registration Portal - Step 3: Readiness (Technology Maturity & IP Status)
 * Interactive Radio Expanders, Tooltip Popups & AJAX Persistence
 */

document.addEventListener('DOMContentLoaded', () => {
  const readinessForm = document.getElementById('readinessRegistrationForm');
  const readinessAlertBox = document.getElementById('readinessAlertBox');
  const saveReadinessBtn = document.getElementById('saveReadinessBtn');

  const fundingDetailsBlock = document.getElementById('fundingDetailsBlock');
  const fundingRadios = document.querySelectorAll('input[name="prior_funding_has"]');
  const radioCards = document.querySelectorAll('.radio-option-card');

  // 1. Toggle Prior Funding Expandable Block
  function syncFundingState() {
    const selectedRadio = document.querySelector('input[name="prior_funding_has"]:checked');
    const isYes = selectedRadio && selectedRadio.value === 'Yes';

    radioCards.forEach(card => {
      const radio = card.querySelector('input[type="radio"]');
      if (radio && radio.checked) {
        card.classList.add('radio-card-active');
      } else {
        card.classList.remove('radio-card-active');
      }
    });

    if (fundingDetailsBlock) {
      if (isYes) {
        fundingDetailsBlock.style.display = 'block';
      } else {
        fundingDetailsBlock.style.display = 'none';
      }
    }
  }

  fundingRadios.forEach(radio => {
    radio.addEventListener('change', syncFundingState);
  });

  radioCards.forEach(card => {
    card.addEventListener('click', () => {
      const radio = card.querySelector('input[type="radio"]');
      if (radio && !radio.checked) {
        radio.checked = true;
        syncFundingState();
      }
    });
  });

  // Initial Sync
  syncFundingState();

  // 2. Corner Information Tooltips (Auto-dismiss on outside click, typing, focus, or escape)
  function closeAllTooltips() {
    document.querySelectorAll('.info-tooltip-pop').forEach(pop => {
      pop.classList.remove('tooltip-visible');
      pop.classList.add('tooltip-force-hidden');
    });
    document.querySelectorAll('.glass-panel').forEach(p => {
      p.classList.remove('panel-elevated');
      p.style.removeProperty('z-index');
    });
  }

  const tooltipWraps = document.querySelectorAll('.info-tooltip-wrap');
  tooltipWraps.forEach(wrap => {
    const btn = wrap.querySelector('.info-icon-btn');
    const pop = wrap.querySelector('.info-tooltip-pop');
    if (!btn || !pop) return;

    // Reset suppression flag on mouseenter and elevate panel stacking
    wrap.addEventListener('mouseenter', () => {
      pop.classList.remove('tooltip-force-hidden');
      const panel = wrap.closest('.glass-panel');
      if (panel) {
        panel.classList.add('panel-elevated');
        panel.style.zIndex = '1000';
      }
    });

    wrap.addEventListener('mouseleave', () => {
      if (!pop.classList.contains('tooltip-visible')) {
        const panel = wrap.closest('.glass-panel');
        if (panel) {
          panel.classList.remove('panel-elevated');
          panel.style.removeProperty('z-index');
        }
      }
    });

    // Toggle on button click
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const isCurrentlyOpen = pop.classList.contains('tooltip-visible') && !pop.classList.contains('tooltip-force-hidden');
      closeAllTooltips();
      if (!isCurrentlyOpen) {
        pop.classList.remove('tooltip-force-hidden');
        pop.classList.add('tooltip-visible');
        const panel = wrap.closest('.glass-panel');
        if (panel) {
          panel.classList.add('panel-elevated');
          panel.style.zIndex = '1000';
        }
      }
    });
  });

  // Dismiss tooltip when clicking anywhere else on screen
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.info-tooltip-wrap')) {
      closeAllTooltips();
    }
  });

  // Dismiss tooltip immediately when starting to type in any input/textarea
  document.addEventListener('input', closeAllTooltips);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' || e.key.length === 1) {
      closeAllTooltips();
    }
  });

  // Dismiss tooltip when user focuses into any input or textarea
  document.querySelectorAll('input, textarea, select').forEach(field => {
    field.addEventListener('focus', closeAllTooltips);
  });

  // 3. Step 3 Form Submission via AJAX
  if (readinessForm) {
    readinessForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      if (readinessAlertBox) {
        readinessAlertBox.style.display = 'none';
        readinessAlertBox.innerHTML = '';
      }

      // Client-side Validation
      const clientErrors = [];

      const trlSelect = document.getElementById('trlLevelSelect');
      if (!trlSelect || !trlSelect.value) {
        clientErrors.push("<strong>Current Technology Readiness Level (TRL)</strong>: Please select your project's current maturity stage from 1 to 9.");
      }

      const workDoneInput = document.getElementById('workDoneInput');
      const workDoneText = workDoneInput ? workDoneInput.value.trim() : '';
      if (!workDoneText) {
        clientErrors.push("<strong>Work Done So Far</strong>: Please describe your progress to date (simulations, prototypes, surveys, or literature).");
      } else if (workDoneText.length < 15) {
        clientErrors.push("<strong>Work Done So Far</strong>: Please provide a slightly more descriptive summary of work done (minimum 15 characters).");
      }

      const selectedFundingRadio = document.querySelector('input[name="prior_funding_has"]:checked');
      const isYes = selectedFundingRadio && selectedFundingRadio.value === 'Yes';

      if (isYes) {
        const agencyInput = document.getElementById('fundingAgencyInput');
        const amountInput = document.getElementById('fundingAmountInput');
        const overlapInput = document.getElementById('fundingOverlapInput');

        if (!agencyInput || !agencyInput.value.trim()) {
          clientErrors.push("<strong>Prior Prize or Funding</strong>: Please enter the Funding Agency or Competition Name.");
        }
        if (!amountInput || !amountInput.value.trim()) {
          clientErrors.push("<strong>Prior Prize or Funding</strong>: Please specify the Amount Received.");
        }
        if (!overlapInput || !overlapInput.value.trim()) {
          clientErrors.push("<strong>Prior Prize or Funding</strong>: Please explain the work/budget overlap with this grant.");
        }
      }

      const ipSelect = document.getElementById('ipStatusSelect');
      if (!ipSelect || !ipSelect.value) {
        clientErrors.push("<strong>IP Status</strong>: Please select the current intellectual property status of your project.");
      }

      if (clientErrors.length > 0) {
        window.scrollTo({ top: 120, behavior: 'smooth' });
        showReadinessAlert('error', clientErrors);
        return;
      }

      // Submit via AJAX
      const originalText = saveReadinessBtn.innerHTML;
      saveReadinessBtn.disabled = true;
      saveReadinessBtn.innerHTML = `<span>Validating & Saving Step 3...</span>`;

      try {
        const formData = new FormData(readinessForm);
        const payload = {
          team_id: formData.get('team_id') || 0,
          trl_level: formData.get('trl_level') || 0,
          work_done_so_far: formData.get('work_done_so_far') || '',
          prior_funding_has: formData.get('prior_funding_has') || 'No',
          prior_funding_agency: formData.get('prior_funding_agency') || '',
          prior_funding_amount: formData.get('prior_funding_amount') || '',
          prior_funding_overlap: formData.get('prior_funding_overlap') || '',
          ip_status: formData.get('ip_status') || ''
        };

        const res = await fetch('api/save_readiness_step.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const resData = await res.json();

        if (res.ok && resData.success) {
          window.scrollTo({ top: 0, behavior: 'smooth' });
          showStep3SuccessModal(resData);
        } else {
          window.scrollTo({ top: 120, behavior: 'smooth' });
          const errors = resData.errors || [resData.message || 'An error occurred while saving readiness details.'];
          showReadinessAlert('error', errors);
        }
      } catch (err) {
        window.scrollTo({ top: 120, behavior: 'smooth' });
        showReadinessAlert('error', [
          'Network or Server connection error. Please ensure Apache and MySQL are running in XAMPP.'
        ]);
      } finally {
        saveReadinessBtn.disabled = false;
        saveReadinessBtn.innerHTML = originalText;
      }
    });
  }

  function showReadinessAlert(type, messages) {
    if (!readinessAlertBox) return;
    readinessAlertBox.style.display = 'flex';
    readinessAlertBox.className = `form-alert-banner alert-${type}`;

    let html = `
      <div class="alert-icon-col">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
      </div>
      <div class="alert-content-col">
        <h4 class="alert-title">Please Review Requirements:</h4>
        <ul class="alert-list">
          ${messages.map(m => `<li>${m}</li>`).join('')}
        </ul>
      </div>
    `;
    readinessAlertBox.innerHTML = html;
  }

  function showStep3SuccessModal(data) {
    const modalHtml = `
      <div class="success-modal-overlay" id="step3Modal">
        <div class="success-modal-card glass-panel">
          <div class="modal-celebrate-badge">Step 3 Completed</div>
          <h2 class="modal-title">Readiness & Technology Maturity Saved</h2>
          <p class="modal-sub">
            Your project's current TRL stage, work done to date, prior funding status, and IP classification have been recorded.
          </p>

          <div class="modal-summary-grid">
            <div class="summary-pill">TRL Assessment: <strong>Recorded</strong></div>
            <div class="summary-pill">Prior Funding: <strong>Disclosed</strong></div>
            <div class="summary-pill">IP Status: <strong>Verified</strong></div>
          </div>

          <div class="modal-actions">
            <a href="submissions.php" class="btn-modal-secondary">
              View Coordinator Directory
            </a>
            <a href="register.php?step=4&team_id=${encodeURIComponent(data.team_id)}" class="btn-modal-primary" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
              Continue to Step 4: Milestones &rarr;
            </a>
          </div>
        </div>
      </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
  }
});
