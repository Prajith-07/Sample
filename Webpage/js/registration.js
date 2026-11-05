/**
 * iTech Seed Grand Challenge 2027
 * Registration Portal - Step 1: Team & Account Details
 * Dynamic Slots, Real-time Validation & AJAX Duplicate Checks
 */

document.addEventListener('DOMContentLoaded', () => {
  const membersContainer = document.getElementById('membersContainer');
  const addMemberBtn = document.getElementById('addMemberBtn');
  const memberCountBadge = document.getElementById('memberCountBadge');
  const interdisciplinaryStatusBadge = document.getElementById('interdisciplinaryStatusBadge');
  const teamForm = document.getElementById('teamRegistrationForm');
  const formAlertBox = document.getElementById('formAlertBox');
  const toggleAddFacultyBtn = document.getElementById('toggleAddFacultyBtn');
  const addFacultyCard = document.getElementById('addFacultyCard');

  const MAX_MEMBERS = 4;
  const MIN_MEMBERS = 2;
  let debounceTimers = {};

  // Departments List
  const departments = [
    "Artificial Intelligence & Data Science",
    "Computer Science & Engineering",
    "Electronics & Communication",
    "Electrical & Electronics",
    "Mechanical Engineering",
    "Civil Engineering",
    "Information Technology",
    "Computer Science & Business Systems"
  ];

  // Helper: Build department options HTML
  function getDeptOptionsHtml(selectedVal = '') {
    return departments.map(d => 
      `<option value="${d}" ${d === selectedVal ? 'selected' : ''}>${d}</option>`
    ).join('');
  }

  // 1. Update Team Size and UI Badges
  function updateTeamSizeUI() {
    const cards = membersContainer.querySelectorAll('.member-slot-card');
    const currentCount = cards.length;

    if (memberCountBadge) {
      memberCountBadge.textContent = `${currentCount} / ${MAX_MEMBERS} Members`;
    }

    if (addMemberBtn) {
      if (currentCount >= MAX_MEMBERS) {
        addMemberBtn.disabled = true;
        addMemberBtn.classList.add('btn-disabled');
        addMemberBtn.textContent = `Maximum 4 Members Reached`;
      } else {
        addMemberBtn.disabled = false;
        addMemberBtn.classList.remove('btn-disabled');
        addMemberBtn.textContent = `+ Add Team Member (${currentCount + 1} of ${MAX_MEMBERS})`;
      }
    }

    // Renumber members dynamically
    cards.forEach((card, idx) => {
      const order = idx + 1;
      const orderBadge = card.querySelector('.member-order-num');
      const roleBadge = card.querySelector('.member-role-title');
      const deleteBtn = card.querySelector('.btn-remove-member');

      if (orderBadge) orderBadge.textContent = `#${order}`;

      if (idx === 0) {
        // Slot 1 is always the lead
        if (roleBadge) roleBadge.innerHTML = `Team Lead <span class="badge-pill-lead">Primary Contact</span>`;
        if (deleteBtn) deleteBtn.style.display = 'none';
      } else {
        if (roleBadge) roleBadge.textContent = `Team Member ${order}`;
        if (deleteBtn) {
          deleteBtn.style.display = (idx >= MIN_MEMBERS) ? 'inline-flex' : 'none';
        }
      }

      // Update input names for PHP associative array
      card.querySelectorAll('input, select').forEach(input => {
        const fieldName = input.getAttribute('data-field');
        if (fieldName) {
          input.name = `members[${idx}][${fieldName}]`;
        }
      });
    });

    checkInterdisciplinaryRule();
  }

  // 2. Interdisciplinary Team Live Check (Min 2 Different Departments)
  function checkInterdisciplinaryRule() {
    const deptSelects = membersContainer.querySelectorAll('select[data-field="department"]');
    const depts = new Set();

    deptSelects.forEach(sel => {
      const val = (sel.value || '').trim();
      if (val !== '') {
        depts.add(val);
      }
    });

    const isInterdisciplinary = (depts.size >= 2);

    if (interdisciplinaryStatusBadge) {
      if (isInterdisciplinary) {
        interdisciplinaryStatusBadge.className = 'diversity-pill diversity-met';
        interdisciplinaryStatusBadge.innerHTML = `
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
          <span><strong>Interdisciplinary Met:</strong> ${depts.size} departments</span>
        `;
      } else {
        interdisciplinaryStatusBadge.className = 'diversity-pill diversity-pending';
        interdisciplinaryStatusBadge.innerHTML = `
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
          <span><strong>Mandatory Rule:</strong> Interdisciplinary (min 2 depts)</span>
        `;
      }
    }

    return isInterdisciplinary;
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // 3. Create Dynamic Member Slot HTML
  function createMemberSlotHtml(index, data = null) {
    const order = index + 1;
    const isLead = (index === 0);

    const mName = data ? (data.full_name || '') : '';
    const mRoll = data ? (data.roll_number || '') : '';
    const mDept = data ? (data.department || '') : '';
    const mYear = data ? (data.year_of_study || '') : '';
    let mEmail = data ? (data.college_email || '') : '';
    if (!mEmail && isLead && window.stagedLeaderEmail) {
      mEmail = window.stagedLeaderEmail;
    }
    const mPhone = data ? (data.phone_number || '') : '';
    const mGender = data ? (data.gender || '') : '';

    const card = document.createElement('div');
    card.className = `member-slot-card glass-panel ${isLead ? 'lead-slot-card' : 'regular-slot-card'}`;
    card.setAttribute('data-order', order);

    card.innerHTML = `
      <div class="slot-card-header">
        <div class="slot-header-left">
          <span class="member-order-num">#${order}</span>
          <h3 class="member-role-title">
            ${isLead ? 'Team Lead <span class="badge-pill-lead">Primary Contact</span>' : `Team Member ${order}`}
          </h3>
        </div>
        <div class="slot-header-right">
          ${isLead ? `
            <span class="lead-info-tag">
              Team Lead
            </span>
          ` : `
            <button type="button" class="btn-remove-member" title="Remove this member">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
              Remove
            </button>
          `}
        </div>
      </div>

      <div class="slot-grid-inputs">
        <!-- Full Name -->
        <div class="form-group">
          <label class="form-label">Full Name <span class="req">*</span></label>
          <input type="text" data-field="full_name" name="members[${index}][full_name]" 
                 class="nature-input member-name" placeholder="e.g. Anandha Kumar S" value="${escapeHtml(mName)}" required>
        </div>

        <!-- Roll Number / College ID -->
        <div class="form-group">
          <label class="form-label">Roll Number <span class="req">*</span></label>
          <input type="text" data-field="roll_number" name="members[${index}][roll_number]" 
                 class="nature-input member-roll ajax-check" placeholder="e.g. 23AD01 or 231405" 
                 pattern="[A-Za-z0-9-]+" maxlength="12" value="${escapeHtml(mRoll)}" required>
          <div class="field-feedback"></div>
          <span class="field-hint">e.g. 23AD01, 21CS05, or numeric ID</span>
        </div>

        <!-- Department -->
        <div class="form-group">
          <label class="form-label">Department <span class="req">*</span></label>
          <select data-field="department" name="members[${index}][department]" class="nature-input" required>
            <option value="" disabled ${!mDept ? 'selected' : ''}>Select Department</option>
            ${getDeptOptionsHtml(mDept)}
          </select>
        </div>

        <!-- Year of Study -->
        <div class="form-group">
          <label class="form-label">Year of Study <span class="req">*</span></label>
          <select data-field="year_of_study" name="members[${index}][year_of_study]" class="nature-input" required>
            <option value="" disabled ${!mYear ? 'selected' : ''}>Select Year</option>
            <option value="II" ${mYear === 'II' ? 'selected' : ''}>II Year</option>
            <option value="III" ${mYear === 'III' ? 'selected' : ''}>III Year</option>
            <option value="Final" ${mYear === 'Final' ? 'selected' : ''}>Final Year</option>
          </select>
        </div>

        <!-- Email Address -->
        <div class="form-group">
          <label class="form-label">${isLead ? 'Team Leader Email' : 'Member Email'} <span class="req">*</span></label>
          <input type="email" data-field="college_email" name="members[${index}][college_email]" 
                 class="nature-input member-email ajax-check" 
                 placeholder="${isLead ? 'e.g. yourname@gmail.com or college email' : 'e.g. member@psgitech.ac.in'}" 
                 value="${escapeHtml(mEmail)}"
                 required>
          <div class="field-feedback"></div>
          <span class="field-hint">${isLead ? 'Official login & contact email for Team Leader' : 'College or active email'}</span>
        </div>

        <!-- Phone Number -->
        <div class="form-group">
          <label class="form-label">Mobile Phone Number <span class="req">*</span></label>
          <input type="tel" data-field="phone_number" name="members[${index}][phone_number]" 
                 class="nature-input member-phone ajax-check" placeholder="10-digit number" maxlength="10" value="${escapeHtml(mPhone)}" required>
          <div class="field-feedback"></div>
        </div>

        <!-- Gender -->
        <div class="form-group">
          <label class="form-label">Gender <span class="req">*</span></label>
          <select data-field="gender" name="members[${index}][gender]" class="nature-input member-gender" required>
            <option value="" disabled ${!mGender ? 'selected' : ''}>Select Gender</option>
            <option value="Female" ${mGender === 'Female' ? 'selected' : ''}>Female</option>
            <option value="Male" ${mGender === 'Male' ? 'selected' : ''}>Male</option>
          </select>
        </div>
      </div>
    `;

    // Attach remove event listener
    const removeBtn = card.querySelector('.btn-remove-member');
    if (removeBtn) {
      removeBtn.addEventListener('click', () => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(-10px)';
        setTimeout(() => {
          card.remove();
          updateTeamSizeUI();
        }, 250);
      });
    }

    // Attach department change event
    const deptSel = card.querySelector('select[data-field="department"]');
    if (deptSel) {
      deptSel.addEventListener('change', checkInterdisciplinaryRule);
    }

    // Attach roll number numbers-only restriction
    const rollInput = card.querySelector('input[data-field="roll_number"]');
    if (rollInput) {
      rollInput.addEventListener('input', () => {
        rollInput.value = rollInput.value.replace(/\D/g, '').slice(0, 12);
      });
    }

    // Attach AJAX duplicate checking
    card.querySelectorAll('.ajax-check').forEach(input => {
      attachDuplicateChecker(input);
    });

    return card;
  }

  // 4. Real-time AJAX Duplicate Checker
  function attachDuplicateChecker(input) {
    const fieldName = input.getAttribute('data-field') || input.name;
    const feedbackEl = input.parentElement.querySelector('.field-feedback');

    function checkField() {
      const val = input.value.trim();
      if (!val) {
        if (feedbackEl) feedbackEl.innerHTML = '';
        input.classList.remove('input-valid', 'input-invalid');
        return;
      }

      const isLeadSlot = (input.closest('.member-slot-card')?.getAttribute('data-order') === '1');

      // Check for internal duplicate within current form first
      if (fieldName === 'roll_number' || fieldName === 'college_email' || fieldName === 'phone_number') {
        const allSameFields = Array.from(membersContainer.querySelectorAll(`input[data-field="${fieldName}"]`));
        const matching = allSameFields.filter(f => f.value.trim().toLowerCase() === val.toLowerCase());
        if (matching.length > 1) {
          showFeedback(input, feedbackEl, false, `Duplicate within this team: this ${fieldName.replace('_', ' ')} is entered in multiple slots.`);
          return;
        }
      }

      // Format validations
      if (fieldName === 'roll_number') {
        if (!/^[A-Za-z0-9-]+$/.test(val)) {
          showFeedback(input, feedbackEl, false, 'Roll Number must contain letters, numbers, or hyphens (e.g. 23AD01).');
          return;
        }
      }
      if (fieldName === 'college_email') {
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
          showFeedback(input, feedbackEl, false, 'Please enter a valid email address.');
          return;
        }

        // Team Leader slot: verify against leader's staged signup email
        if (isLeadSlot) {
          if (window.stagedLeaderEmail && val.toLowerCase() === window.stagedLeaderEmail.toLowerCase()) {
            showFeedback(input, feedbackEl, true, 'Team Leader email verified');
            return;
          }
        }
      }

      // Query database via API
      if (feedbackEl) {
        feedbackEl.innerHTML = `<span class="feedback-checking">Verifying...</span>`;
      }

      const checkUrl = `api/check_duplicate.php?field=${encodeURIComponent(fieldName)}&value=${encodeURIComponent(val)}${isLeadSlot ? '&is_lead=1' : ''}`;

      fetch(checkUrl)
        .then(res => res.json())
        .then(data => {
          if (data.available) {
            showFeedback(input, feedbackEl, true, data.message || 'Available');
          } else {
            showFeedback(input, feedbackEl, false, data.message);
          }
        })
        .catch(() => {
          if (feedbackEl) feedbackEl.innerHTML = '';
        });
    }

    input.addEventListener('input', () => {
      if (fieldName === 'phone_number') {
        input.value = input.value.replace(/\D/g, '').slice(0, 10);
      }
      if (fieldName === 'roll_number') {
        input.value = input.value.replace(/\D/g, '').slice(0, 12);
      }
      clearTimeout(debounceTimers[fieldName + input.name]);
      debounceTimers[fieldName + input.name] = setTimeout(checkField, 500);
    });

    input.addEventListener('blur', checkField);
  }

  function showFeedback(input, feedbackEl, isValid, message) {
    if (!feedbackEl) return;
    if (isValid) {
      input.classList.remove('input-invalid');
      input.classList.add('input-valid');
      feedbackEl.className = 'field-feedback feedback-success';
      feedbackEl.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg> ${message}`;
    } else {
      input.classList.remove('input-valid');
      input.classList.add('input-invalid');
      feedbackEl.className = 'field-feedback feedback-error';
      feedbackEl.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg> ${message}`;
    }
  }

  // Attach duplicate check to Team Name
  const teamNameInput = document.getElementById('teamNameInput');
  if (teamNameInput) {
    attachDuplicateChecker(teamNameInput);
  }

  // 5. Add Member Button Handler
  if (addMemberBtn) {
    addMemberBtn.addEventListener('click', () => {
      const currentCount = membersContainer.querySelectorAll('.member-slot-card').length;
      if (currentCount < MAX_MEMBERS) {
        const newCard = createMemberSlotHtml(currentCount);
        newCard.style.opacity = '0';
        newCard.style.transform = 'translateY(15px)';
        membersContainer.appendChild(newCard);
        
        // Trigger reflow & animation
        setTimeout(() => {
          newCard.style.transition = 'all 0.3s cubic-bezier(0.16, 1, 0.3, 1)';
          newCard.style.opacity = '1';
          newCard.style.transform = 'translateY(0)';
          newCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 20);

        updateTeamSizeUI();
      }
    });
  }

  // 6. Additional Faculty Toggle Handler
  if (toggleAddFacultyBtn && addFacultyCard) {
    toggleAddFacultyBtn.addEventListener('click', () => {
      const isHidden = addFacultyCard.style.display === 'none';
      if (isHidden) {
        addFacultyCard.style.display = 'block';
        toggleAddFacultyBtn.textContent = `- Remove Additional Faculty Mentor`;
        toggleAddFacultyBtn.classList.add('btn-toggle-active');
      } else {
        addFacultyCard.style.display = 'none';
        // Clear optional fields
        addFacultyCard.querySelectorAll('input, select').forEach(i => i.value = '');
        toggleAddFacultyBtn.textContent = `+ Add Co-Faculty Mentor (Optional)`;
        toggleAddFacultyBtn.classList.remove('btn-toggle-active');
      }
    });
  }

  // 7. Initial Render: Use saved members from DB or create default slots
  if (membersContainer && membersContainer.children.length === 0) {
    if (window.existingMembers && window.existingMembers.length > 0) {
      window.existingMembers.forEach((m, idx) => {
        membersContainer.appendChild(createMemberSlotHtml(idx, m));
      });
      // If only 1 member recorded, ensure Member 2 slot is present
      if (window.existingMembers.length < MIN_MEMBERS) {
        for (let i = window.existingMembers.length; i < MIN_MEMBERS; i++) {
          membersContainer.appendChild(createMemberSlotHtml(i));
        }
      }
    } else {
      membersContainer.appendChild(createMemberSlotHtml(0)); // Slot 1 (Team Lead)
      membersContainer.appendChild(createMemberSlotHtml(1)); // Slot 2 (Member 2)
    }
    updateTeamSizeUI();

    // If Team Leader email is available, display verified feedback immediately
    const leadEmailInput = membersContainer.querySelector('.lead-slot-card input[data-field="college_email"]');
    if (leadEmailInput && leadEmailInput.value) {
      const fb = leadEmailInput.parentElement.querySelector('.field-feedback');
      showFeedback(leadEmailInput, fb, true, 'Team Leader email verified');
    }

    // If non-leader member logged in, enforce view-only mode
    if (window.isTeamLead === false) {
      if (addMemberBtn) addMemberBtn.style.display = 'none';
      membersContainer.querySelectorAll('.btn-remove-member').forEach(b => b.style.display = 'none');
      document.querySelectorAll('#teamRegistrationForm input, #teamRegistrationForm select').forEach(el => {
        el.setAttribute('disabled', 'disabled');
      });
      const saveBtn = document.getElementById('saveContinueBtn');
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.style.opacity = '0.5';
        saveBtn.textContent = 'View Only (Leader Authorized)';
      }
    }
  }

  // Ensure all phone inputs (including mentor phones) strictly allow max 10 digits
  document.querySelectorAll('input[type="tel"]').forEach(phoneInput => {
    phoneInput.setAttribute('maxlength', '10');
    phoneInput.addEventListener('input', () => {
      phoneInput.value = phoneInput.value.replace(/\D/g, '').slice(0, 10);
    });
  });

  // Ensure all roll number inputs allow alphanumeric roll numbers (e.g. 23AD01)
  document.querySelectorAll('input[data-field="roll_number"]').forEach(rollInput => {
    rollInput.setAttribute('maxlength', '12');
    rollInput.addEventListener('input', () => {
      rollInput.value = rollInput.value.replace(/[^A-Za-z0-9-]/g, '').slice(0, 12);
    });
  });

  // 8. Form Submission via AJAX (Save & Continue)
  if (teamForm) {
    teamForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      if (formAlertBox) {
        formAlertBox.style.display = 'none';
        formAlertBox.innerHTML = '';
      }

      // Check interdisciplinary team rule client-side
      if (!checkInterdisciplinaryRule()) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        showFormAlert('error', [
          "<strong>Mandatory Interdisciplinary Rule:</strong> Your team must be interdisciplinary, comprising students from at least two different departments."
        ]);
        return;
      }

      // Validate Roll Number (numbers only) & Email for all members
      const memberCards = membersContainer.querySelectorAll('.member-slot-card');
      const formatErrors = [];
      memberCards.forEach((card, idx) => {
        const roleLabel = (idx === 0) ? "Team Lead" : `Member ${idx + 1}`;
        const rollVal = (card.querySelector('[data-field="roll_number"]')?.value || '').trim();
        const emailVal = (card.querySelector('[data-field="college_email"]')?.value || '').trim();

        if (!/^\d+$/.test(rollVal)) {
          formatErrors.push(`<strong>${roleLabel}:</strong> Roll number must contain numbers only (digits 0–9).`);
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
          formatErrors.push(`<strong>${roleLabel}:</strong> Please enter a valid email address.`);
        }
      });

      if (formatErrors.length > 0) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        showFormAlert('error', formatErrors);
        return;
      }

      const submitBtn = document.getElementById('saveContinueBtn');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = `<span>Validating & Saving Step 1...</span>`;

      try {
        const formData = new FormData(teamForm);

        // Build structured JSON
        const payload = {
          team_id: formData.get('team_id') || 0,
          team_name: formData.get('team_name') || '',
          members: [],
          faculty_mentor: {
            name: formData.get('faculty_name') || '',
            department: formData.get('faculty_department') || '',
            email: formData.get('faculty_email') || '',
            phone: formData.get('faculty_phone') || ''
          },
          additional_faculty: {
            name: formData.get('add_faculty_name') || '',
            department: formData.get('add_faculty_department') || '',
            email: formData.get('add_faculty_email') || '',
            phone: formData.get('add_faculty_phone') || ''
          },
          industry_mentor: {
            name: formData.get('industry_name') || '',
            organisation: formData.get('industry_org') || '',
            designation: formData.get('industry_designation') || '',
            email: formData.get('industry_email') || '',
            phone: formData.get('industry_phone') || '',
            linkedin: formData.get('industry_linkedin') || ''
          }
        };

        // Extract members
        const memberCards = membersContainer.querySelectorAll('.member-slot-card');
        memberCards.forEach((card, idx) => {
          payload.members.push({
            is_lead: idx === 0 ? 1 : 0,
            full_name: card.querySelector('[data-field="full_name"]')?.value || '',
            roll_number: card.querySelector('[data-field="roll_number"]')?.value || '',
            department: card.querySelector('[data-field="department"]')?.value || '',
            year_of_study: card.querySelector('[data-field="year_of_study"]')?.value || '',
            college_email: card.querySelector('[data-field="college_email"]')?.value || '',
            phone_number: card.querySelector('[data-field="phone_number"]')?.value || '',
            gender: card.querySelector('[data-field="gender"]')?.value || ''
          });
        });

        const res = await fetch('api/save_team_step.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const resData = await res.json();

        if (res.ok && resData.success) {
          // Success!
          window.scrollTo({ top: 0, behavior: 'smooth' });
          showSuccessModal(resData);
        } else {
          // Validation or database errors
          window.scrollTo({ top: 0, behavior: 'smooth' });
          const errors = resData.errors || [resData.message || 'An error occurred while saving.'];
          showFormAlert('error', errors);
        }
      } catch (err) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        showFormAlert('error', [
          'Network or Server connection error. Please ensure Apache and MySQL are running in XAMPP.'
        ]);
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    });
  }

  function showFormAlert(type, messages) {
    if (!formAlertBox) return;
    formAlertBox.style.display = 'block';
    formAlertBox.className = `form-alert-banner alert-${type}`;

    let html = `
      <div class="alert-icon-col">
        ${type === 'error' ? 
          '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>' : 
          '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>'
        }
      </div>
      <div class="alert-content-col">
        <h4 class="alert-title">${type === 'error' ? 'Please Correct the Following Requirements:' : 'Success'}</h4>
        <ul class="alert-list">
          ${messages.map(m => `<li>${m}</li>`).join('')}
        </ul>
      </div>
    `;
    formAlertBox.innerHTML = html;
  }

  function showSuccessModal(data) {
    const modalHtml = `
      <div class="success-modal-overlay" id="successModal">
        <div class="success-modal-card glass-panel">
          <div class="modal-celebrate-badge">Step 1 Completed</div>
          <h2 class="modal-title">Team & Account Details Registered</h2>
          <p class="modal-sub">
            Your team <strong>${data.team_name}</strong> has been registered in the Launchpad portal.
          </p>

          <div class="modal-app-box">
            <span class="app-label">Application Reference Number</span>
            <span class="app-code">${data.application_no}</span>
            <span class="app-hint">Keep this reference ID safe for all future grant stages & mentor approvals.</span>
          </div>

          <div class="modal-summary-grid">
            <div class="summary-pill">Team Size: <strong>${data.team_size} Members</strong></div>
            <div class="summary-pill">Interdisciplinary: <strong>Verified (Pass)</strong></div>
            <div class="summary-pill">Mentors: <strong>Assigned</strong></div>
          </div>

          <div class="modal-actions">
            <a href="submissions.php" class="btn-modal-secondary">
              View Coordinator Directory
            </a>
            <button type="button" class="btn-modal-primary" onclick="window.location.href='register.php?step=2&team_id=' + ${data.team_id} + '&app_no=' + encodeURIComponent('${data.application_no}')">
              Continue to Step 2: The Idea ➔
            </button>
          </div>
        </div>
      </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
  }
});
