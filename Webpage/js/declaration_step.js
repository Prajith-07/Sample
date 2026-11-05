/**
 * iTech Seed Grand Challenge 2027
 * Registration Portal - Step 7: Declaration & Online Mentor Approval
 * Client-Side Validation, E-Signature, Tooltip Guidelines, AJAX Submission, Copy Link & Resend Controls
 * PSG iTech Launchpad: Innozone & Startupzone
 */

document.addEventListener('DOMContentLoaded', () => {
  const declarationForm = document.getElementById('declarationForm');
  const alertBox = document.getElementById('declarationAlertBox');
  const submitBtn = document.getElementById('declarationSubmitBtn');

  const sigInput = document.getElementById('leadSignatureInput');
  const sigDesc = document.getElementById('leadSignatureDescription');

  // ─── 1. Signature Input Description Auto-Vanish on Typing ───────────────────
  if (sigInput && sigDesc) {
    const updateDescVisibility = () => {
      if (sigInput.value.trim().length > 0) {
        sigDesc.style.opacity = '0';
        sigDesc.style.maxHeight = '0';
        sigDesc.style.marginTop = '0';
        sigDesc.style.overflow = 'hidden';
      } else {
        sigDesc.style.opacity = '1';
        sigDesc.style.maxHeight = '50px';
        sigDesc.style.marginTop = '0.4rem';
      }
    };

    sigInput.addEventListener('input', updateDescVisibility);
    // Initial check (in case field is prefilled on reload)
    updateDescVisibility();
  }

  // ─── 2. Corner Information Tooltips (Auto-dismiss on outside click, typing, focus, or escape) ──
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

  // ─── 3. Form Submission via AJAX ───────────────────────────────────────────
  if (declarationForm) {
    declarationForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      if (alertBox) {
        alertBox.style.display = 'none';
        alertBox.innerHTML = '';
      }

      // Checkboxes validation
      const chkOrig = document.getElementById('chkOriginality')?.checked;
      const chkOver = document.getElementById('chkNoOverlap')?.checked;
      const chkAgree = document.getElementById('chkGrantAgreement')?.checked;
      const chkIp = document.getElementById('chkIpPolicy')?.checked;
      const chkMentor = document.getElementById('chkMentorGuidance')?.checked;
      const sigName = sigInput?.value.trim() || '';

      const errors = [];
      if (!chkOrig) errors.push('You must certify that the idea is original and not plagiarised.');
      if (!chkOver) errors.push('You must confirm that no undisclosed duplicate funding exists for this PoC.');
      if (!chkAgree) errors.push('You must undertake to sign the grant agreement and register under DPIIT and Udyam.');
      if (!chkIp) errors.push('You must agree to the institutional IPR policy in the names of inventors and PSG iTech.');
      if (!chkMentor) errors.push('You must confirm that designated mentors have agreed to guide the team.');
      if (!sigName || sigName.length < 3) errors.push('Please enter your binding digital signature in format: Team_Lead_Name DD/MM/YYYY.');

      if (errors.length > 0) {
        showDeclarationAlert('error', errors);
        window.scrollTo({ top: 120, behavior: 'smooth' });
        return;
      }

      const originalHtml = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = `
        <span>Dispatching Mentor Approval Links...</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation: spin 1s linear infinite;">
          <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
          <path d="M12 2a10 10 0 0 1 10 10" stroke-opacity="1"></path>
        </svg>
      `;

      try {
        const formData = new FormData(declarationForm);
        const payload = {
          team_id:                          parseInt(formData.get('team_id')) || 0,
          lead_declared_originality:        chkOrig ? 1 : 0,
          lead_declared_no_funding_overlap: chkOver ? 1 : 0,
          lead_declared_grant_agreement:    chkAgree ? 1 : 0,
          lead_declared_ip_policy:          chkIp ? 1 : 0,
          lead_declared_mentor_guidance:    chkMentor ? 1 : 0,
          lead_signature_name:              sigName
        };

        const res = await fetch('api/save_declaration_step.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const resData = await res.json();

        if (res.ok && resData.success) {
          showStep7SuccessModal(resData);
        } else {
          const apiErrors = resData.errors || [resData.message || 'Error saving declaration.'];
          showDeclarationAlert('error', apiErrors);
          window.scrollTo({ top: 120, behavior: 'smooth' });
        }
      } catch (err) {
        showDeclarationAlert('error', ['Network communication error. Please ensure Apache and MySQL are running in XAMPP.']);
        window.scrollTo({ top: 120, behavior: 'smooth' });
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
      }
    });
  }

  // ─── 4. Copy Mentor Approval Link to Clipboard ─────────────────────────────
  document.querySelectorAll('.btn-copy-mentor-link').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      const url = btn.getAttribute('data-link');
      if (!url) return;

      try {
        await navigator.clipboard.writeText(url);
        const originalText = btn.innerHTML;
        btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Copied!`;
        btn.style.background = '#dcfce7';
        btn.style.color = '#15803d';
        setTimeout(() => {
          btn.innerHTML = originalText;
          btn.style.background = '';
          btn.style.color = '';
        }, 2200);
      } catch (err) {
        prompt('Copy this secure mentor endorsement link:', url);
      }
    });
  });

  // ─── 5. Resend Email or Update Mentor Address ──────────────────────────────
  document.querySelectorAll('.btn-resend-mentor').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      const mentorId = btn.getAttribute('data-mentor-id');
      const teamId = btn.getAttribute('data-team-id');
      const mentorName = btn.getAttribute('data-mentor-name');
      const currentEmail = btn.getAttribute('data-mentor-email');

      const targetEmail = prompt(`Resend mentor consent link to ${mentorName}:\n(You can keep or edit the email address below)`, currentEmail);
      if (targetEmail === null) return; // User cancelled

      if (!targetEmail || !targetEmail.includes('@')) {
        alert('Please enter a valid email address.');
        return;
      }

      btn.disabled = true;
      const originalText = btn.innerHTML;
      btn.innerHTML = `Sending...`;

      try {
        const res = await fetch('api/resend_mentor_email.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            team_id: parseInt(teamId),
            mentor_id: parseInt(mentorId),
            email: targetEmail.trim()
          })
        });

        const data = await res.json();
        if (res.ok && data.success) {
          alert(`Success: ${data.message}`);
          window.location.reload();
        } else {
          alert(data.message || 'Error dispatching email.');
        }
      } catch (err) {
        alert('Network communication error. Please check server status.');
      } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
      }
    });
  });

  // ─── 6. Alert Banner ───────────────────────────────────────────────────────
  function showDeclarationAlert(type, messages) {
    if (!alertBox) return;
    alertBox.style.display = 'flex';
    alertBox.className = `form-alert-banner alert-${type}`;
    alertBox.innerHTML = `
      <div class="alert-icon-col">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
      </div>
      <div class="alert-content-col">
        <h4 class="alert-title">Please Review Mandatory Undertakings:</h4>
        <ul class="alert-list">
          ${messages.map(m => `<li>${m}</li>`).join('')}
        </ul>
      </div>
    `;
  }

  // ─── 7. Step 7 Success Modal ─────────────────────────────────────────────
  function showStep7SuccessModal(data) {
    let mentorsHtml = '';
    if (data.mentors && data.mentors.length > 0) {
      mentorsHtml = `
        <div style="background: rgba(255,255,255,0.7); border: 1px solid #d4e5d9; border-radius: 10px; padding: 1.25rem; margin: 1.25rem 0; text-align: left;">
          <h4 style="margin: 0 0 0.75rem 0; font-size: 0.92rem; color: var(--text-forest); font-family: var(--font-heading);">Dispatched Mentor Links:</h4>
          ${data.mentors.map(m => `
            <div style="padding: 0.6rem 0; border-bottom: 1px solid #eef4f0; display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
              <div>
                <strong>${m.name}</strong> (${m.mentor_type})<br>
                <span style="color: var(--text-muted); font-size: 0.8rem;">${m.email}</span>
              </div>
              <span style="background: rgba(184, 237, 69, 0.15); color: #22c55e; border: 1px solid rgba(184, 237, 69, 0.35); padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                Dispatched
              </span>
            </div>
          `).join('')}
        </div>
      `;
    }

    const modalHtml = `
      <div class="success-modal-overlay" id="step7Modal">
        <div class="success-modal-card glass-panel" style="max-width: 620px;">
          <div class="modal-celebrate-badge">Step 7 Completed</div>
          <h2 class="modal-title">Declaration Submitted &amp; Mentor Links Sent!</h2>
          <p class="modal-sub">
            Your team's binding e-signature has been recorded. Secure endorsement request links have been generated and dispatched to your Faculty Mentor and Industry Mentor.
          </p>

          ${mentorsHtml}

          <div style="background: rgba(34, 197, 94, 0.12); border: 1px solid #86efac; border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 1.5rem; font-size: 0.86rem; color: #166534; text-align: left;">
            <strong>Next Step:</strong> As soon as both your Faculty Mentor and Industry Mentor open their link and complete their online endorsement, your application automatically moves to the Screening Committee!
          </div>

          <div class="modal-actions" style="justify-content: center;">
            <button type="button" class="btn-modal-primary" onclick="window.location.reload();">
              View Live Mentor Status Dashboard &rarr;
            </button>
            <a href="submissions.php" class="btn-modal-secondary">
              View Submissions Directory
            </a>
          </div>
        </div>
      </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
  }
});
