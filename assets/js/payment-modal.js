document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('payment-form');
    var modal = document.getElementById('payment-modal');
    if (!form || !modal) return;

    var openBtn = document.getElementById('open-payment-modal');
    var closeBtn = document.getElementById('close-payment-modal');
    var cancelBtn = document.getElementById('cancel-payment-modal');
    var confirmBtn = document.getElementById('confirm-payment-submit');
    var billSelect = document.getElementById('bill_id');
    var amountInput = document.getElementById('amount');
    var methodSelect = document.getElementById('payment_method');
    var referenceInput = document.getElementById('reference_number');
    var notesInput = document.getElementById('notes');

    var methodLabels = {
        cash: 'Cash',
        gcash: 'GCash',
        bank_transfer: 'Bank Transfer',
        check: 'Check'
    };

    function formatMoney(value) {
        return '₱' + parseFloat(value || 0).toFixed(2);
    }

    function getSelectedBillOption() {
        return billSelect.options[billSelect.selectedIndex];
    }

    function validateForm() {
        if (!form.reportValidity()) {
            return false;
        }
        var option = getSelectedBillOption();
        var amount = parseFloat(amountInput.value || '0');
        var balance = parseFloat(option && option.dataset.balance ? option.dataset.balance : '0');

        if (!option || !option.value) {
            alert('Please select a bill.');
            return false;
        }
        if (amount <= 0) {
            alert('Please enter a valid amount.');
            return false;
        }
        if (amount > balance + 0.01) {
            alert('Amount cannot exceed the outstanding balance of ' + formatMoney(balance) + '.');
            return false;
        }
        return true;
    }

    function populateModal() {
        var option = getSelectedBillOption();
        document.getElementById('confirm-customer').textContent = option.dataset.customer || '—';
        document.getElementById('confirm-account').textContent = option.dataset.account || '—';
        document.getElementById('confirm-bill').textContent = option.dataset.billNumber || '—';
        document.getElementById('confirm-amount').textContent = formatMoney(amountInput.value);
        document.getElementById('confirm-method').textContent = methodLabels[methodSelect.value] || methodSelect.value;
        document.getElementById('confirm-reference').textContent = referenceInput.value.trim() || '—';
        document.getElementById('confirm-notes').textContent = notesInput.value.trim() || '—';
    }

    function openModal() {
        if (!validateForm()) return;
        populateModal();
        modal.hidden = false;
        document.body.classList.add('modal-open');
        confirmBtn.focus();
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

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
