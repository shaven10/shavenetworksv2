document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('remittance-form');
    if (!form) return;

    var modal = document.getElementById('remittance-modal');
    var openBtn = document.getElementById('open-remittance-modal');
    var confirmBtn = document.getElementById('confirm-remittance-submit');
    var selectAll = document.getElementById('select-all-payments');
    var totalEl = document.getElementById('remittance-selected-total');
    var countEl = document.getElementById('remittance-selected-count');

    function formatMoney(value) {
        return '₱' + parseFloat(value || 0).toFixed(2);
    }

    function getRows() {
        return Array.prototype.slice.call(document.querySelectorAll('.remittance-table tbody tr'));
    }

    function getSummary() {
        var total = 0;
        var count = 0;

        getRows().forEach(function (row) {
            var checkbox = row.querySelector('.payment-select');
            if (checkbox && checkbox.checked) {
                total += parseFloat(checkbox.dataset.amount || '0');
                count++;
            }
        });

        return { total: total, count: count };
    }

    function updateSummary() {
        var summary = getSummary();
        if (totalEl) totalEl.textContent = formatMoney(summary.total);
        if (countEl) countEl.textContent = summary.count + ' payment(s)';
        return summary;
    }

    getRows().forEach(function (row) {
        var checkbox = row.querySelector('.payment-select');
        if (!checkbox) return;
        checkbox.addEventListener('change', updateSummary);
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            getRows().forEach(function (row) {
                var checkbox = row.querySelector('.payment-select');
                if (checkbox) checkbox.checked = selectAll.checked;
            });
            updateSummary();
        });
    }

    function validateForm() {
        var summary = getSummary();
        if (summary.count === 0) {
            alert('Select at least one payment to remit.');
            return false;
        }
        return true;
    }

    function openModal() {
        if (!validateForm()) return;
        var summary = getSummary();
        document.getElementById('remittance-confirm-count').textContent = String(summary.count);
        document.getElementById('remittance-confirm-total').textContent = formatMoney(summary.total);
        modal.hidden = false;
        document.body.classList.add('modal-open');
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
    }

    openBtn.addEventListener('click', openModal);
    document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    confirmBtn.addEventListener('click', function () {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Submitting...';
        form.submit();
    });

    updateSummary();
});
