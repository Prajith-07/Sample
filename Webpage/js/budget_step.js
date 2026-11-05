/**
 * Step 5: Budget & Itemized Financial Plan Controller
 * iTech Seed Grand Challenge 2027
 * PSG iTech Launchpad: Innozone & Startupzone
 */

document.addEventListener('DOMContentLoaded', () => {
  const budgetForm = document.getElementById('budgetForm');
  const tableBody = document.getElementById('budgetTableBody');
  const addRowBtn = document.getElementById('addBudgetRowBtn');
  const budgetAlertBox = document.getElementById('budgetAlertBox');
  const submitBtn = document.getElementById('budgetSubmitBtn');

  const grandTotalEl = document.getElementById('grandTotalDisplay');
  const ceilingStatusEl = document.getElementById('ceilingStatusDisplay');

  const MAX_GRANT = 100000.00;

  // Allowed Categories
  const CATEGORIES = [
    'Components, sensors, boards',
    'Raw material',
    'Fabrication and testing',
    'Software licences, cloud credits, datasets',
    'Field trials, user surveys, limited local travel',
    'Patent search and filing (with IPR cell approval)'
  ];

  // Format currency in Indian Rupees
  function formatINR(val) {
    const num = isNaN(val) ? 0 : Number(val);
    return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  // 1. Recalculate Row Amounts and Overall Totals
  function recalculateBudget() {
    let grandTotal = 0;
    const rows = tableBody.querySelectorAll('tr.budget-row');

    rows.forEach(row => {
      const qtyInput = row.querySelector('.budget-item-qty');
      const unitCostInput = row.querySelector('.budget-item-unit-cost');
      const amountInput = row.querySelector('.budget-item-amount');
      const warningBadge = row.querySelector('.high-cost-warning');

      const qty = parseFloat(qtyInput.value) || 0;
      const unitCost = parseFloat(unitCostInput.value) || 0;
      const rowAmount = Math.max(0, qty * unitCost);

      amountInput.value = formatINR(rowAmount);
      amountInput.dataset.rawAmount = rowAmount.toFixed(2);
      grandTotal += rowAmount;

      // Single item > ₹15,000 coordinator approval warning
      if (unitCost > 15000 || rowAmount > 15000) {
        if (warningBadge) warningBadge.style.display = 'inline-flex';
      } else {
        if (warningBadge) warningBadge.style.display = 'none';
      }
    });

    // Update Grand Total Display
    if (grandTotalEl) {
      grandTotalEl.textContent = formatINR(grandTotal);
    }

    // Update Ceiling Status Display & Button State
    if (ceilingStatusEl) {
      if (grandTotal > MAX_GRANT) {
        const exceededBy = grandTotal - MAX_GRANT;
        ceilingStatusEl.className = 'budget-status-pill status-exceeded';
        ceilingStatusEl.innerHTML = `
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
          Exceeds ₹1,00,000 ceiling by ${formatINR(exceededBy)}
        `;
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.title = "Total requested grant cannot exceed ₹1,00,000.00";
        }
      } else {
        const remaining = MAX_GRANT - grandTotal;
        ceilingStatusEl.className = 'budget-status-pill status-ok';
        ceilingStatusEl.innerHTML = `
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          ${formatINR(remaining)} remaining of ₹1,00,000 ceiling
        `;
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.removeAttribute('title');
        }
      }
    }
  }

  function round2(num) {
    return Math.round((num + Number.EPSILON) * 100) / 100;
  }

  // 2. Re-index Row Numbers and Update Remove Button States
  function reindexRows() {
    const rows = tableBody.querySelectorAll('tr.budget-row');
    rows.forEach((row, idx) => {
      const numSpan = row.querySelector('.row-num');
      if (numSpan) numSpan.textContent = idx + 1;

      const removeBtn = row.querySelector('.btn-remove-row');
      if (removeBtn) {
        removeBtn.disabled = (rows.length <= 1);
      }
    });
    recalculateBudget();
  }

  // 3. Add New Row Function
  function addBudgetRow(data = {}) {
    const row = document.createElement('tr');
    row.className = 'budget-row';

    const rowNum = tableBody.querySelectorAll('tr.budget-row').length + 1;
    const initialQty = data.quantity !== undefined ? data.quantity : 1;
    const initialUnitCost = data.unit_cost !== undefined ? data.unit_cost : '';
    const initialAmount = (parseFloat(initialQty) || 0) * (parseFloat(initialUnitCost) || 0);

    let categoryOptionsHtml = `<option value="">Select Category...</option>`;
    CATEGORIES.forEach(cat => {
      const selected = data.category === cat ? 'selected' : '';
      categoryOptionsHtml += `<option value="${escapeHtml(cat)}" ${selected}>${escapeHtml(cat)}</option>`;
    });

    row.innerHTML = `
      <td class="col-num">
        <span class="row-num">${rowNum}</span>
      </td>
      <td class="col-item">
        <input type="text" class="nature-input budget-item-name" 
               placeholder="e.g. ESP32-S3 Microcontroller Board"
               value="${escapeHtml(data.item_name || '')}" required>
        <span class="high-cost-warning" style="display: none;">
          Item > ₹15,000: Needs coordinator approval
        </span>
      </td>
      <td class="col-cat">
        <select class="nature-select budget-item-category" required>
          ${categoryOptionsHtml}
        </select>
      </td>
      <td class="col-qty">
        <input type="number" class="nature-input budget-item-qty" 
               min="1" step="1" value="${initialQty}" required>
      </td>
      <td class="col-unit-cost">
        <div class="currency-input-wrap">
          <span class="currency-prefix">₹</span>
          <input type="number" class="nature-input budget-item-unit-cost" 
                 min="0" step="0.01" placeholder="0.00" 
                 value="${initialUnitCost}" required>
        </div>
      </td>
      <td class="col-amount">
        <input type="text" class="nature-input budget-item-amount" 
               readonly value="${formatINR(initialAmount)}" 
               data-raw-amount="${initialAmount.toFixed(2)}">
      </td>
      <td class="col-purpose">
        <input type="text" class="nature-input budget-item-purpose" 
               placeholder="e.g. Primary edge compute and RF node"
               value="${escapeHtml(data.purpose || '')}" required>
      </td>
      <td class="col-action">
        <button type="button" class="btn-remove-row" aria-label="Remove item" title="Remove item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
      </td>
    `;

    // Attach Event Listeners to New Row
    const qtyInput = row.querySelector('.budget-item-qty');
    const unitCostInput = row.querySelector('.budget-item-unit-cost');
    const removeBtn = row.querySelector('.btn-remove-row');

    qtyInput.addEventListener('input', recalculateBudget);
    qtyInput.addEventListener('change', recalculateBudget);
    unitCostInput.addEventListener('input', recalculateBudget);
    unitCostInput.addEventListener('change', recalculateBudget);

    removeBtn.addEventListener('click', () => {
      const rows = tableBody.querySelectorAll('tr.budget-row');
      if (rows.length > 1) {
        row.remove();
        reindexRows();
      }
    });

    tableBody.appendChild(row);
    reindexRows();
  }

  function escapeHtml(text) {
    if (!text) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // 4. Attach Add Row Button Listener
  if (addRowBtn) {
    addRowBtn.addEventListener('click', (e) => {
      e.preventDefault();
      addBudgetRow();
    });
  }

  // 5. Initialize Existing Rows or Default First Row
  const initialRows = tableBody.querySelectorAll('tr.budget-row');
  if (initialRows.length === 0) {
    addBudgetRow();
  } else {
    // Attach listeners to pre-rendered rows from PHP
    initialRows.forEach(row => {
      const qtyInput = row.querySelector('.budget-item-qty');
      const unitCostInput = row.querySelector('.budget-item-unit-cost');
      const removeBtn = row.querySelector('.btn-remove-row');

      if (qtyInput) {
        qtyInput.addEventListener('input', recalculateBudget);
        qtyInput.addEventListener('change', recalculateBudget);
      }
      if (unitCostInput) {
        unitCostInput.addEventListener('input', recalculateBudget);
        unitCostInput.addEventListener('change', recalculateBudget);
      }
      if (removeBtn) {
        removeBtn.addEventListener('click', () => {
          const rows = tableBody.querySelectorAll('tr.budget-row');
          if (rows.length > 1) {
            row.remove();
            reindexRows();
          }
        });
      }
    });
    reindexRows();
  }

  // 6. Form Submission via AJAX
  if (budgetForm) {
    budgetForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      if (budgetAlertBox) {
        budgetAlertBox.style.display = 'none';
        budgetAlertBox.innerHTML = '';
      }

      const clientErrors = [];
      const rows = tableBody.querySelectorAll('tr.budget-row');
      const itemsPayload = [];
      let totalSum = 0;

      if (rows.length === 0) {
        clientErrors.push("Please enter at least one budget line item.");
      }

      rows.forEach((row, idx) => {
        const nameEl = row.querySelector('.budget-item-name');
        const catEl = row.querySelector('.budget-item-category');
        const qtyEl = row.querySelector('.budget-item-qty');
        const costEl = row.querySelector('.budget-item-unit-cost');
        const purEl = row.querySelector('.budget-item-purpose');

        const name = nameEl ? nameEl.value.trim() : '';
        const cat = catEl ? catEl.value.trim() : '';
        const qty = qtyEl ? parseInt(qtyEl.value, 10) : 0;
        const unitCost = costEl ? parseFloat(costEl.value) : -1;
        const purpose = purEl ? purEl.value.trim() : '';

        const rowNum = idx + 1;

        if (!name) {
          clientErrors.push(`<strong>Item #${rowNum}</strong>: Description/Item name is required.`);
        }
        if (!cat) {
          clientErrors.push(`<strong>Item #${rowNum}</strong>: Category selection is required.`);
        }
        if (isNaN(qty) || qty < 1) {
          clientErrors.push(`<strong>Item #${rowNum}</strong>: Quantity must be at least 1.`);
        }
        if (isNaN(unitCost) || unitCost < 0) {
          clientErrors.push(`<strong>Item #${rowNum}</strong>: Unit cost must be a valid non-negative number.`);
        }
        if (!purpose) {
          clientErrors.push(`<strong>Item #${rowNum}</strong>: Purpose of the item is required.`);
        }

        const rowAmount = round2(Math.max(0, qty) * Math.max(0, unitCost));
        totalSum += rowAmount;

        itemsPayload.push({
          item_order: rowNum,
          item_name: name,
          category: cat,
          quantity: qty,
          unit_cost: unitCost,
          amount: rowAmount,
          purpose: purpose
        });
      });

      if (totalSum > MAX_GRANT) {
        clientErrors.push(`<strong>Budget Exceeded</strong>: Total requested amount (${formatINR(totalSum)}) exceeds the ₹1,00,000.00 grant ceiling.`);
      }

      if (totalSum <= 0 && clientErrors.length === 0) {
        clientErrors.push("Total requested grant amount must be greater than ₹0.00.");
      }

      if (clientErrors.length > 0) {
        showBudgetAlert('error', clientErrors);
        window.scrollTo({ top: 180, behavior: 'smooth' });
        return;
      }

      const teamIdInput = document.getElementById('teamIdInput');
      const teamId = teamIdInput ? parseInt(teamIdInput.value, 10) : 0;

      const payload = {
        team_id: teamId,
        items: itemsPayload,
        total_amount: round2(totalSum)
      };

      // Loading state
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = `
          <svg class="spinner-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation: spin 0.8s linear infinite;">
            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
            <path d="M12 2a10 10 0 0 1 10 10"></path>
          </svg>
          <span>Saving Budget & Financial Plan...</span>
        `;
      }

      try {
        const res = await fetch('api/save_budget_step.php', {
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
          showStep5SuccessModal(resData);
        } else {
          window.scrollTo({ top: 120, behavior: 'smooth' });
          const errors = resData.errors || [resData.message || 'An error occurred while saving the budget.'];
          showBudgetAlert('error', errors);
        }
      } catch (err) {
        console.error('Submission failed:', err);
        showBudgetAlert('error', ['Network communication error. Please check your connection and try again.']);
        window.scrollTo({ top: 120, behavior: 'smooth' });
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = `
            <span>Save & Continue to Step 6</span>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
          `;
        }
      }
    });
  }

  function showBudgetAlert(type, messages) {
    if (!budgetAlertBox) return;
    budgetAlertBox.style.display = 'block';
    budgetAlertBox.className = type === 'error' ? 'alert-card alert-error' : 'alert-card alert-success';

    let html = `
      <div class="alert-icon-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <span>Please review the following budget requirements:</span>
      </div>
      <ul class="alert-list">
    `;
    messages.forEach(msg => {
      html += `<li>${msg}</li>`;
    });
    html += `</ul>`;

    budgetAlertBox.innerHTML = html;
  }

  function showStep5SuccessModal(data) {
    const totalFormatted = formatINR(data.total_budget || 0);

    const modalHtml = `
      <div class="success-modal-overlay" id="step5Modal">
        <div class="success-modal-card glass-panel">
          <div class="modal-celebrate-badge">Step 5 Completed</div>
          <h2 class="modal-title">Budget & Financial Plan Recorded</h2>
          <p class="modal-sub">
            Your itemized expenditure breakdown and component development budget have been validated and saved.
          </p>

          <div class="modal-summary-grid">
            <div class="summary-pill">Total Budget: <strong>${totalFormatted}</strong></div>
            <div class="summary-pill">Ceiling Status: <strong>Within ₹1,00,000 Cap</strong></div>
            <div class="summary-pill">Line Items: <strong>Verified</strong></div>
          </div>

          <div class="modal-actions">
            <a href="submissions.php" class="btn-modal-secondary">
              View Coordinator Directory
            </a>
            <a href="register.php?step=6&team_id=${encodeURIComponent(data.team_id)}" class="btn-modal-primary" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
              Continue to Step 6: Market & Impact &rarr;
            </a>
          </div>
        </div>
      </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
  }
});
