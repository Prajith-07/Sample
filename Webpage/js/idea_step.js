/**
 * iTech Seed Grand Challenge 2027
 * Registration Portal - Step 2: The Idea (Innovation & Problem Identification)
 * Live Character Limiter Engine (Spaces Excluded) & Tooltip Handlers
 */

document.addEventListener('DOMContentLoaded', () => {
  const ideaForm = document.getElementById('ideaRegistrationForm');
  const ideaAlertBox = document.getElementById('ideaAlertBox');
  const saveIdeaBtn = document.getElementById('saveIdeaBtn');

  // Textarea Configurations: Field ID, Error Message ID, Min Chars, Max Chars
  const charConfigs = {
    problem_statement: {
      inputId: 'problemStatementInput',
      errorId: 'problemCharError',
      minChars: 150,
      maxChars: 250,
      fieldName: 'Problem Statement'
    },
    existing_solutions: {
      inputId: 'existingSolutionsInput',
      errorId: 'existingCharError',
      minChars: 100,
      maxChars: 200,
      fieldName: 'Existing Solutions and the Gap'
    },
    proposed_solution: {
      inputId: 'proposedSolutionInput',
      errorId: 'proposedCharError',
      minChars: 200,
      maxChars: 300,
      fieldName: 'Proposed Solution'
    }
  };

  // Helper: Count characters EXCLUDING all whitespace (spaces, tabs, newlines)
  function countNonSpaceChars(text) {
    if (!text) return 0;
    return text.replace(/\s/g, '').length;
  }

  // Helper: Clamp text so non-space character count does not exceed maxChars
  function clampNonSpaceChars(text, maxChars) {
    if (!text) return '';
    let nonSpaceCount = 0;
    let result = '';
    for (let i = 0; i < text.length; i++) {
      const ch = text[i];
      if (/\s/.test(ch)) {
        result += ch;
      } else {
        if (nonSpaceCount < maxChars) {
          result += ch;
          nonSpaceCount++;
        } else {
          // Reached maximum allowed non-space characters
        }
      }
    }
    return result;
  }

  // Setup Character Counters, Hard Maximum Caps, and Red Error Indications
  Object.keys(charConfigs).forEach(key => {
    const config = charConfigs[key];
    const textarea = document.getElementById(config.inputId);
    const errorEl = document.getElementById(config.errorId);

    if (!textarea || !errorEl) return;

    // Hard Stop on Keyboard: Prevent typing regular non-space characters beyond maxChars
    textarea.addEventListener('keydown', (e) => {
      // Allow control/modifier keys, backspace, delete, tab, enter, arrow keys
      if (e.ctrlKey || e.metaKey || e.altKey || e.key.length !== 1) {
        return;
      }
      // Allow space characters (spaces are not counted as characters)
      if (/\s/.test(e.key)) {
        return;
      }
      // If user has selected text in textarea that will be replaced, allow key
      if (textarea.selectionStart !== textarea.selectionEnd) {
        return;
      }
      // Check if current non-space count already reached maximum
      const currentCount = countNonSpaceChars(textarea.value);
      if (currentCount >= config.maxChars) {
        e.preventDefault();
      }
    });

    function handleCharCount() {
      let count = countNonSpaceChars(textarea.value);

      // Enforce hard maximum limit (truncate excess characters on paste/drop)
      if (count > config.maxChars) {
        const prevScroll = textarea.scrollTop;
        textarea.value = clampNonSpaceChars(textarea.value, config.maxChars);
        textarea.scrollTop = prevScroll;
        count = config.maxChars;
      }

      // Display red text ONLY when condition is not met (count < minChars)
      // When requirement is met, hide completely (no box, no badge)
      if (count < config.minChars) {
        errorEl.textContent = `Minimum ${config.minChars} characters required`;
        errorEl.style.display = 'block';
        textarea.classList.add('input-invalid');
      } else {
        errorEl.textContent = '';
        errorEl.style.display = 'none';
        textarea.classList.remove('input-invalid');
      }
    }

    // Attach real-time input and paste events
    textarea.addEventListener('input', handleCharCount);
    textarea.addEventListener('paste', () => setTimeout(handleCharCount, 20));
    textarea.addEventListener('blur', handleCharCount);

    // Initial evaluation for draft prefill or fresh load
    handleCharCount();
  });

  // PoC Radio Card Selection Visual Feedback
  const pocCards = document.querySelectorAll('.poc-radio-card');
  pocCards.forEach(card => {
    const radio = card.querySelector('input[type="radio"]');
    if (!radio) return;

    if (radio.checked) {
      card.classList.add('poc-card-active');
    }

    card.addEventListener('click', () => {
      pocCards.forEach(c => c.classList.remove('poc-card-active'));
      card.classList.add('poc-card-active');
      radio.checked = true;
    });
  });

  // Tooltip accessible click toggle and auto-dismissal
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

  // Step 2 Form Submission via AJAX
  if (ideaForm) {
    ideaForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      if (ideaAlertBox) {
        ideaAlertBox.style.display = 'none';
        ideaAlertBox.innerHTML = '';
      }

      // Client-side Character Count Checks before sending
      const clientErrors = [];
      Object.keys(charConfigs).forEach(key => {
        const config = charConfigs[key];
        const textarea = document.getElementById(config.inputId);
        const count = textarea ? countNonSpaceChars(textarea.value) : 0;

        if (count < config.minChars) {
          clientErrors.push(`<strong>${config.fieldName}</strong>: Minimum characters not reached. Currently entered: ${count} characters (excluding spaces). Minimum required: ${config.minChars} characters.`);
        }
      });

      // Verify PoC type selected
      const selectedPoc = ideaForm.querySelector('input[name="poc_type"]:checked');
      if (!selectedPoc) {
        clientErrors.push("<strong>Type of PoC</strong>: Please select whether your proof-of-concept is Hardware, Software, or Both.");
      }

      if (clientErrors.length > 0) {
        window.scrollTo({ top: 120, behavior: 'smooth' });
        showIdeaAlert('error', clientErrors);
        return;
      }

      // Submit via AJAX
      const originalText = saveIdeaBtn.innerHTML;
      saveIdeaBtn.disabled = true;
      saveIdeaBtn.innerHTML = `<span>Validating & Saving Step 2...</span>`;

      try {
        const formData = new FormData(ideaForm);
        const payload = {
          team_id: formData.get('team_id') || 0,
          project_title: formData.get('project_title') || '',
          problem_statement: formData.get('problem_statement') || '',
          existing_solutions: formData.get('existing_solutions') || '',
          proposed_solution: formData.get('proposed_solution') || '',
          poc_type: formData.get('poc_type') || ''
        };

        const res = await fetch('api/save_idea_step.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const resData = await res.json();

        if (res.ok && resData.success) {
          window.scrollTo({ top: 0, behavior: 'smooth' });
          showStep2SuccessModal(resData);
        } else {
          window.scrollTo({ top: 120, behavior: 'smooth' });
          const errors = resData.errors || [resData.message || 'An error occurred while saving the idea.'];
          showIdeaAlert('error', errors);
        }
      } catch (err) {
        window.scrollTo({ top: 120, behavior: 'smooth' });
        showIdeaAlert('error', [
          'Network or Server connection error. Please ensure Apache and MySQL are running in XAMPP.'
        ]);
      } finally {
        saveIdeaBtn.disabled = false;
        saveIdeaBtn.innerHTML = originalText;
      }
    });
  }

  function showIdeaAlert(type, messages) {
    if (!ideaAlertBox) return;
    ideaAlertBox.style.display = 'flex';
    ideaAlertBox.className = `form-alert-banner alert-${type}`;

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
    ideaAlertBox.innerHTML = html;
  }

  function showStep2SuccessModal(data) {
    const modalHtml = `
      <div class="success-modal-overlay" id="step2Modal">
        <div class="success-modal-card glass-panel">
          <div class="modal-celebrate-badge">Step 2 Completed</div>
          <h2 class="modal-title">The Idea & Innovation Details Saved</h2>
          <p class="modal-sub">
            Your technical proposal problem statement, novelty, and PoC classification have been recorded.
          </p>

          <div class="modal-summary-grid">
            <div class="summary-pill">Innovation Rubric: <strong>10 Marks Eligible</strong></div>
            <div class="summary-pill">Character Counts: <strong>Verified (Pass)</strong></div>
            <div class="summary-pill">Status: <strong>Draft Saved</strong></div>
          </div>

          <div class="modal-actions">
            <a href="submissions.php" class="btn-modal-secondary">
              View Coordinator Directory
            </a>
            <button type="button" class="btn-modal-primary" onclick="window.location.href='register.php?step=3&team_id=' + ${data.team_id}">
              Continue to Step 3: Readiness & TRL ➔
            </button>
          </div>
        </div>
      </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
  }
});
