/**
 * iTech Seed Grand Challenge 2027
 * Registration Portal - Step 6: Market Potential & Societal Impact
 * Tooltip Popups & AJAX Persistence
 */

document.addEventListener('DOMContentLoaded', () => {
  const marketForm = document.getElementById('marketForm');
  const marketAlertBox = document.getElementById('marketAlertBox');
  const marketSubmitBtn = document.getElementById('marketSubmitBtn');

  // ─── 1. Corner Tooltip System ───────────────────────────────────────────────
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

  // Auto-dismiss on outside click
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.info-tooltip-wrap')) {
      closeAllTooltips();
    }
  });

  // Auto-dismiss on typing or input
  document.addEventListener('input', closeAllTooltips);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' || e.key.length === 1) {
      closeAllTooltips();
    }
  });

  // Auto-dismiss on field focus
  document.querySelectorAll('input, textarea, select').forEach(field => {
    field.addEventListener('focus', closeAllTooltips);
  });

  // ─── 2. Form Submission via AJAX ────────────────────────────────────────────
  if (marketForm) {
    marketForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      if (marketAlertBox) {
        marketAlertBox.style.display = 'none';
        marketAlertBox.innerHTML = '';
      }

      // Client-side Validation
      const clientErrors = [];

      const targetUsersInput = document.getElementById('targetUsersInput');
      const targetUsersText = targetUsersInput ? targetUsersInput.value.trim() : '';
      if (!targetUsersText) {
        clientErrors.push('<strong>Target Users or Customers</strong>: Please describe who will use or buy your solution.');
      } else if (targetUsersText.length < 20) {
        clientErrors.push('<strong>Target Users or Customers</strong>: Please provide a more detailed description (minimum 20 characters).');
      }

      const marketNeedInput = document.getElementById('marketNeedInput');
      const marketNeedText = marketNeedInput ? marketNeedInput.value.trim() : '';
      if (!marketNeedText) {
        clientErrors.push('<strong>Size of the Need</strong>: Please describe the market size, frequency, and current cost.');
      } else if (marketNeedText.length < 20) {
        clientErrors.push('<strong>Size of the Need</strong>: Please provide a more descriptive estimate (minimum 20 characters).');
      }

      const commercialisationInput = document.getElementById('commercialisationInput');
      const commercialisationText = commercialisationInput ? commercialisationInput.value.trim() : '';
      if (!commercialisationText) {
        clientErrors.push('<strong>Route to Commercialisation</strong>: Please describe your path from PoC to product or startup.');
      } else if (commercialisationText.length < 20) {
        clientErrors.push('<strong>Route to Commercialisation</strong>: Please provide a more detailed commercialisation plan (minimum 20 characters).');
      }

      const societalImpactInput = document.getElementById('societalImpactInput');
      const societalImpactText = societalImpactInput ? societalImpactInput.value.trim() : '';
      if (!societalImpactText) {
        clientErrors.push('<strong>Impact</strong>: Please describe the benefit to industry or society and how it will be measured.');
      } else if (societalImpactText.length < 20) {
        clientErrors.push('<strong>Impact</strong>: Please provide a more detailed impact statement (minimum 20 characters).');
      }

      if (clientErrors.length > 0) {
        window.scrollTo({ top: 120, behavior: 'smooth' });
        showMarketAlert('error', clientErrors);
        return;
      }

      // Submit via AJAX
      const originalHtml = marketSubmitBtn.innerHTML;
      marketSubmitBtn.disabled = true;
      marketSubmitBtn.innerHTML = `
        <span>Saving Step 6...</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation: spin 1s linear infinite;">
          <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
          <path d="M12 2a10 10 0 0 1 10 10" stroke-opacity="1"></path>
        </svg>
      `;

      try {
        const formData = new FormData(marketForm);
        const payload = {
          team_id:                 parseInt(formData.get('team_id')) || 0,
          target_users:            formData.get('target_users') || '',
          market_need_size:        formData.get('market_need_size') || '',
          commercialisation_route: formData.get('commercialisation_route') || '',
          societal_impact:         formData.get('societal_impact') || ''
        };

        const res = await fetch('api/save_market_step.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const resData = await res.json();

        if (res.ok && resData.success) {
          window.scrollTo({ top: 0, behavior: 'smooth' });
          showStep6SuccessModal(resData);
        } else {
          window.scrollTo({ top: 120, behavior: 'smooth' });
          const errors = resData.errors || [resData.message || 'An error occurred while saving Market & Impact details.'];
          showMarketAlert('error', errors);
        }
      } catch (err) {
        window.scrollTo({ top: 120, behavior: 'smooth' });
        showMarketAlert('error', [
          'Network or Server connection error. Please ensure Apache and MySQL are running in XAMPP.'
        ]);
      } finally {
        marketSubmitBtn.disabled = false;
        marketSubmitBtn.innerHTML = originalHtml;
      }
    });
  }

  // ─── 3. Alert Banner ─────────────────────────────────────────────────────────
  function showMarketAlert(type, messages) {
    if (!marketAlertBox) return;
    marketAlertBox.style.display = 'flex';
    marketAlertBox.className = `form-alert-banner alert-${type}`;
    marketAlertBox.innerHTML = `
      <div class="alert-icon-col">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
      </div>
      <div class="alert-content-col">
        <h4 class="alert-title">Please Review Requirements:</h4>
        <ul class="alert-list">
          ${messages.map(m => `<li>${m}</li>`).join('')}
        </ul>
      </div>
    `;
  }

  // ─── 4. Step 6 Success Modal ──────────────────────────────────────────────
  function showStep6SuccessModal(data) {
    const modalHtml = `
      <div class="success-modal-overlay" id="step6Modal">
        <div class="success-modal-card glass-panel">
          <div class="modal-celebrate-badge">Step 6 Completed</div>
          <h2 class="modal-title">Market Potential &amp; Societal Impact Saved</h2>
          <p class="modal-sub">
            Your target customers, market size, commercialisation route, and societal impact have all been recorded successfully.
          </p>

          <div class="modal-summary-grid">
            <div class="summary-pill">Target Users: <strong>Recorded</strong></div>
            <div class="summary-pill">Market Size: <strong>Recorded</strong></div>
            <div class="summary-pill">Route to Market: <strong>Recorded</strong></div>
            <div class="summary-pill">Impact: <strong>Recorded</strong></div>
          </div>

          <div class="modal-actions">
            <a href="submissions.php" class="btn-modal-secondary">
              View Coordinator Directory
            </a>
            <a href="register.php?step=7&team_id=${encodeURIComponent(data.team_id)}" class="btn-modal-primary" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
              Continue to Step 7: Mentor Approval &rarr;
            </a>
          </div>
        </div>
      </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
  }

});
