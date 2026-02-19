/**
 * OFFICER DASHBOARD CONTROLLER
 * Handles: Search, Sort, and Loan Actions via Event Delegation
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Table Search
    const searchInput = document.getElementById('loanSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', () => {
            const filter = searchInput.value.toUpperCase();
            const table = document.getElementById('loansTable');
            if (!table) return;
            const trs = table.tBodies[0].rows;
            for (let tr of trs) {
                let show = false;
                for (let td of tr.cells) {
                    if (td.textContent.toUpperCase().includes(filter)) {
                        show = true;
                        break;
                    }
                }
                tr.style.display = show ? '' : 'none';
            }
        });
    }

    // 2. Table Sort
    window.sortTable = function(tableId, col) {
        const table = document.getElementById(tableId);
        if (!table) return;
        let rows = Array.from(table.tBodies[0].rows);
        let asc = table.getAttribute('data-sort') !== 'asc';
        rows.sort((a, b) => {
            let x = a.cells[col].innerText.toLowerCase().replace(/[^0-9.]/g, '');
            let y = b.cells[col].innerText.toLowerCase().replace(/[^0-9.]/g, '');
            return (parseFloat(x) > parseFloat(y) ? 1 : -1) * (asc ? 1 : -1);
        });
        rows.forEach(r => table.tBodies[0].appendChild(r));
        table.setAttribute('data-sort', asc ? 'asc' : 'desc');
    }

    // 3. Modal Logic via Event Delegation
    let currentLoanId = null;
    let currentAction = null;
    const modal = document.getElementById('remarksModal');
    const remarksInput = document.getElementById('officerRemarks');
    const table = document.getElementById('loansTable');
    if (table) {
        table.addEventListener('click', function(e) {
            const actionBtn = e.target.closest('.action-btn');
            const remarkBtn = e.target.closest('.remark-btn');

            if (actionBtn) {
                currentLoanId = actionBtn.dataset.id;
                currentAction = actionBtn.dataset.action;
                const existingRemarks = actionBtn.closest('tr').querySelector('.remark-btn').dataset.remarks;

                document.getElementById('modalLoanText').textContent =
                    `Action: ${currentAction.toUpperCase()} | Loan #${currentLoanId}`;
                remarksInput.value = existingRemarks;
                modal.style.display = 'block';
            } 
            else if (remarkBtn) {
                currentLoanId = remarkBtn.dataset.id;
                currentAction = 'remark_only';

                document.getElementById('modalLoanText').textContent =
                    `Internal Notes | Loan #${currentLoanId}`;
                remarksInput.value = remarkBtn.dataset.remarks || '';
                modal.style.display = 'block';
            }
        });
    }

    // Close Modal
    document.getElementById('cancelModal').onclick = () => modal.style.display = 'none';
    window.onclick = (event) => { if (event.target == modal) modal.style.display = 'none'; };

    // 4. Submit Action
    document.getElementById('confirmAction').onclick = function() {
        const btn = this;
        const remarks = remarksInput.value;
        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = 'Processing...';

        fetch('loan_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `loan_id=${currentLoanId}&loan_action=${currentAction}&admin_remarks=${encodeURIComponent(remarks)}`
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                if(currentAction !== 'remark_only') location.reload();
                else {
                    const btnUpdate = document.querySelector(`#loanRow-${currentLoanId} .remark-btn`);
                    if(btnUpdate) btnUpdate.dataset.remarks = remarks;
                    alert('Notes updated successfully');
                    modal.style.display = 'none';
                }
            } else alert('Error: ' + data.error);
        })
        .catch(err => alert('Network error. Check console.'))
        .finally(() => {
            btn.disabled = false;
            btn.textContent = originalText;
        });
    };
});
