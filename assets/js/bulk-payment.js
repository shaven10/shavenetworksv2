document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('bulk-payment-form');
    if (!form) return;

    var modal = document.getElementById('bulk-payment-modal');
    var openBtn = document.getElementById('open-bulk-payment-modal');
    var confirmBtn = document.getElementById('confirm-bulk-payment');
    var selectAll = document.getElementById('select-all-bills');
    var fillAllBtn = document.getElementById('fill-all-balances');
    var totalEl = document.getElementById('bulk-payment-total');
    var detailsToggle = document.getElementById('bulk-modal-toggle');
    var detailsPanel = document.getElementById('bulk-modal-details');

    var methodLabels = {
        cash: 'Cash',
        gcash: 'GCash',
        bank_transfer: 'Bank Transfer',
        check: 'Check'
    };

    function formatMoney(value) {
        return '₱' + parseFloat(value || 0).toFixed(2);
    }

    function getRows() {
        return Array.prototype.slice.call(document.querySelectorAll('.bulk-payment-table tbody tr'));
    }

    function rowInputs(row) {
        return {
            checkbox: row.querySelector('.bill-select'),
            amount: row.querySelector('.bill-amount-input'),
            balance: parseFloat(row.querySelector('.bill-balance').dataset.balance || '0')
        };
    }

    function setDetailsExpanded(expanded) {
        if (!detailsToggle || !detailsPanel) return;

        detailsToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        detailsPanel.classList.toggle('is-expanded', expanded);
        detailsPanel.classList.toggle('is-collapsed', !expanded);

        var label = detailsToggle.querySelector('.modal-section-toggle-label');
        if (label) {
            label.textContent = expanded ? 'Hide bill breakdown' : 'Show bill breakdown';
        }
    }

    function updateTotal() {
        var total = 0;
        getRows().forEach(function (row) {
            var inputs = rowInputs(row);
            if (inputs.checkbox.checked) {
                total += parseFloat(inputs.amount.value || '0');
            }
        });
        if (totalEl) {
            totalEl.textContent = formatMoney(total);
        }
        return total;
    }

    function syncRowState(row) {
        var inputs = rowInputs(row);
        if (!inputs.checkbox.checked) {
            inputs.amount.value = '';
            inputs.amount.disabled = true;
        } else {
            inputs.amount.disabled = false;
        }
    }

    getRows().forEach(function (row) {
        var inputs = rowInputs(row);
        inputs.checkbox.addEventListener('change', function () {
            syncRowState(row);
            updateTotal();
        });
        inputs.amount.addEventListener('input', updateTotal);
        syncRowState(row);
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            getRows().forEach(function (row) {
                var inputs = rowInputs(row);
                inputs.checkbox.checked = selectAll.checked;
                syncRowState(row);
            });
            updateTotal();
        });
    }

    if (fillAllBtn) {
        fillAllBtn.addEventListener('click', function () {
            getRows().forEach(function (row) {
                var inputs = rowInputs(row);
                inputs.checkbox.checked = true;
                inputs.amount.disabled = false;
                inputs.amount.value = inputs.balance.toFixed(2);
            });
            if (selectAll) selectAll.checked = true;
            updateTotal();
        });
    }

    function validateForm() {
        var selectedCount = 0;
        var invalid = false;

        getRows().forEach(function (row) {
            var inputs = rowInputs(row);
            if (!inputs.checkbox.checked) return;

            selectedCount++;
            var amount = parseFloat(inputs.amount.value || '0');
            if (amount <= 0 || amount > inputs.balance + 0.01) {
                invalid = true;
            }
        });

        if (selectedCount === 0) {
            alert('Select at least one bill to pay.');
            return false;
        }
        if (invalid) {
            alert('Enter a valid amount for each selected bill.');
            return false;
        }
        return true;
    }

    function populateModal() {
        var list = document.getElementById('bulk-confirm-list');
        var count = 0;
        var total = 0;
        list.innerHTML = '';

        getRows().forEach(function (row) {
            var inputs = rowInputs(row);
            if (!inputs.checkbox.checked) return;

            var amount = parseFloat(inputs.amount.value || '0');
            var billNumber = row.querySelector('td:nth-child(2) strong').textContent;
            count++;
            total += amount;

            var li = document.createElement('li');
            li.textContent = billNumber + ' — ' + formatMoney(amount);
            list.appendChild(li);
        });

        var method = document.getElementById('payment_method');
        var reference = document.getElementById('reference_number');
        var methodText = methodLabels[method.value] || method.value;
        var referenceText = reference.value.trim();

        document.getElementById('bulk-short-count').textContent = String(count);
        document.getElementById('bulk-short-total').textContent = formatMoney(total);
        document.getElementById('bulk-short-method').textContent = methodText;

        var referenceWrap = document.getElementById('bulk-short-reference-wrap');
        if (referenceText) {
            document.getElementById('bulk-short-reference').textContent = referenceText;
            referenceWrap.hidden = false;
        } else {
            referenceWrap.hidden = true;
        }
    }

    function openModal() {
        if (!validateForm()) return;
        populateModal();
        setDetailsExpanded(false);
        modal.hidden = false;
        document.body.classList.add('modal-open');
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Confirm';
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
    }

    if (detailsToggle) {
        detailsToggle.addEventListener('click', function () {
            setDetailsExpanded(!detailsPanel.classList.contains('is-expanded'));
        });
    }

    openBtn.addEventListener('click', openModal);
    document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });

    confirmBtn.addEventListener('click', function () {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Processing...';
        form.submit();
    });
});
