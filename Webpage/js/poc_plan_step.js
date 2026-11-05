/**
 * Step 4: PoC Plan & 6-Month Milestones Controller
 * iTech Seed Grand Challenge 2027
 * PSG iTech Launchpad: Innozone & Startupzone
 */

document.addEventListener('DOMContentLoaded', () => {
  const pocForm = document.getElementById('pocPlanForm');
  const pocAlertBox = document.getElementById('pocAlertBox');
  const submitBtn = document.getElementById('pocSubmitBtn');

  // 1. Corner Information Tooltips (Auto-dismiss on outside click, typing, focus, or escape)
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
  document.querySelectorAll('input, textarea').forEach(field => {
    field.addEventListener('focus', closeAllTooltips);
  });

  // 2. Step 4 Form Submission via AJAX
  if (pocForm) {
    pocForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      if (pocAlertBox) {
        pocAlertBox.style.display = 'none';
        pocAlertBox.innerHTML = '';
      }

      // Client-side Validation
      const clientErrors = [];

      const deliverablesInput = document.getElementById('deliverablesInput');
      const deliverablesVal = deliverablesInput ? deliverablesInput.value.trim() : '';
      if (!deliverablesVal) {
        clientErrors.push("<strong>Deliverables</strong>: Please describe the tangible assets that will exist at the conclusion of the grant.");
      } else if (deliverablesVal.length < 15) {
        clientErrors.push("<strong>Deliverables</strong>: Please provide a slightly more descriptive summary (minimum 15 characters).");
      }

      const testMethodInput = document.getElementById('testMethodInput');
      const testMethodVal = testMethodInput ? testMethodInput.value.trim() : '';
      if (!testMethodVal) {
        clientErrors.push("<strong>Test Method</strong>: Please describe your testing methodology and operational benchmarks.");
      } else if (testMethodVal.length < 15) {
        clientErrors.push("<strong>Test Method</strong>: Please provide a slightly more descriptive summary (minimum 15 characters).");
      }

      const successRateInput = document.getElementById('successRateInput');
      const successRateVal = successRateInput ? successRateInput.value.trim() : '';
      if (!successRateVal) {
        clientErrors.push("<strong>Success Rate / Measure</strong>: Please enter target numbers or criteria that show the project works.");
      } else if (successRateVal.length < 10) {
        clientErrors.push("<strong>Success Rate / Measure</strong>: Please provide measurable criteria (minimum 10 characters).");
      }

      // 6-Month Milestone Table Validation
      const milestoneData = {};
      const standardMonths = [
        '',
        'Month 1 (Jan 2027)',
        'Month 2 (Feb 2027)',
        'Month 3 (Mar 2027): Milestone 1 Review',
        'Month 4 (Apr 2027)',
        'Month 5 (May 2027)',
        'Month 6 (Jun 2027): Demo Day and Closure'
      ];

      for (let m = 1; m <= 6; m++) {
        const descEl = document.getElementById(`milestone_desc_${m}`);
        const outEl = document.getElementById(`milestone_output_${m}`);
        const verEl = document.getElementById(`milestone_verify_${m}`);

        const desc = descEl ? descEl.value.trim() : '';
        const output = outEl ? outEl.value.trim() : '';
        const verify = verEl ? verEl.value.trim() : '';

        if (!desc) {
          clientErrors.push(`<strong>${standardMonths[m]}</strong>: Milestone objective is required.`);
        }
        if (!output) {
          clientErrors.push(`<strong>${standardMonths[m]}</strong>: Tangible output or deliverable is required.`);
        }
        if (!verify) {
          clientErrors.push(`<strong>${standardMonths[m]}</strong>: Verification method is required.`);
        }

        milestoneData[m] = {
          month_number: m,
          month_label: standardMonths[m],
          milestone_desc: desc,
          output_deliverable: output,
          verification_method: verify
        };
      }

      if (clientErrors.length > 0) {
        showPocAlert('error', clientErrors);
        window.scrollTo({ top: 180, behavior: 'smooth' });
        return;
      }

      // Prepare Payload
      const teamIdInput = document.getElementById('teamIdInput');
      const teamId = teamIdInput ? parseInt(teamIdInput.value, 10) : 0;

      const payload = {
        team_id: teamId,
        deliverables: deliverablesVal,
        test_method: testMethodVal,
        success_rate: successRateVal,
        milestones: milestoneData
      };

      // Set Loading State
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = `
          <svg class="spinner-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation: spin 0.8s linear infinite;">
            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
            <path d="M12 2a10 10 0 0 1 10 10"></path>
          </svg>
          <span>Saving PoC Plan & Milestones...</span>
        `;
      }

      try {
        const res = await fetch('api/save_poc_plan_step.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(payload)
        });

        const resData = await res.json();

        if (res.ok && resData.success) {
          window.scrollTo({ top: 0, behavior: 'smooth' });
          showStep4SuccessModal(resData);
        } else {
          window.scrollTo({ top: 120, behavior: 'smooth' });
          const errors = resData.errors || [resData.message || 'An error occurred while saving the PoC Plan & Milestones.'];
          showPocAlert('error', errors);
        }
      } catch (err) {
        console.error('Submission failed:', err);
        showPocAlert('error', ['Network communication error. Please check your connection and try again.']);
        window.scrollTo({ top: 120, behavior: 'smooth' });
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = `
            <span>Save & Continue to Step 5</span>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
          `;
        }
      }
    });
  }

  function showPocAlert(type, messages) {
    if (!pocAlertBox) return;
    pocAlertBox.style.display = 'block';
    pocAlertBox.className = type === 'error' ? 'alert-card alert-error' : 'alert-card alert-success';

    let html = `
      <div class="alert-icon-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <span>Please review the following requirements:</span>
      </div>
      <ul class="alert-list">
    `;
    messages.forEach(msg => {
      html += `<li>${msg}</li>`;
    });
    html += `</ul>`;

    pocAlertBox.innerHTML = html;
  }

  function showStep4SuccessModal(data) {
    const modalHtml = `
      <div class="success-modal-overlay" id="step4Modal">
        <div class="success-modal-card glass-panel">
          <div class="modal-celebrate-badge">Step 4 Completed</div>
          <h2 class="modal-title">PoC Plan & Milestones Recorded</h2>
          <p class="modal-sub">
            Your project deliverables, testing protocols, success measures, and 6-month roadmap (Jan–Jun 2027) have been securely saved.
          </p>

          <div class="modal-summary-grid">
            <div class="summary-pill">Deliverables: <strong>Logged</strong></div>
            <div class="summary-pill">Testing Protocol: <strong>Defined</strong></div>
            <div class="summary-pill">Success Criteria: <strong>Recorded</strong></div>
            <div class="summary-pill">6-Month Roadmap: <strong>6 Milestones Set</strong></div>
          </div>

          <div class="modal-actions">
            <a href="submissions.php" class="btn-modal-secondary">
              View Coordinator Directory
            </a>
            <a href="register.php?step=5&team_id=${encodeURIComponent(data.team_id)}" class="btn-modal-primary" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
              Continue to Step 5: Budget (₹1L) &rarr;
            </a>
          </div>
        </div>
      </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
  }
});
