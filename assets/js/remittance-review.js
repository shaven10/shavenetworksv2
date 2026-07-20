document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('remittance-review-form');
    if (!form) return;

    var confirmModal = document.getElementById('confirm-remittance-modal');
    var rejectModal = document.getElementById('reject-remittance-modal');
    var ownerNotes = document.getElementById('owner_notes');

    function openModal(modal) {
        modal.hidden = false;
        document.body.classList.add('modal-open');
    }

    function closeModal(modal) {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
    }

    function closeAllModals() {
        [confirmModal, rejectModal].forEach(function (modal) {
            if (modal) closeModal(modal);
        });
    }

    document.getElementById('open-confirm-modal').addEventListener('click', function () {
        openModal(confirmModal);
    });

    document.getElementById('open-reject-modal').addEventListener('click', function () {
        openModal(rejectModal);
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
        btn.addEventListener('click', closeAllModals);
    });

    [confirmModal, rejectModal].forEach(function (modal) {
        if (!modal) return;
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeAllModals();
        });
    });

    document.querySelectorAll('[data-review-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = btn.getAttribute('data-review-action');
            if (action === 'reject' && (!ownerNotes.value || !ownerNotes.value.trim())) {
                alert('Please provide a reason when rejecting a remittance.');
                ownerNotes.focus();
                return;
            }

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = action;
            form.appendChild(input);
            btn.disabled = true;
            form.submit();
        });
    });
});
