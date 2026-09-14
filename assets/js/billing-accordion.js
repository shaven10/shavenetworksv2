document.addEventListener('DOMContentLoaded', function () {
    var groups = Array.prototype.slice.call(document.querySelectorAll('.billing-customer-group'));
    var searchForm = document.getElementById('billing-search-form');
    var listFilterInput = document.getElementById('billing-list-filter');

    function setGroupState(group, expanded) {
        group.classList.toggle('is-expanded', expanded);
        group.classList.toggle('is-collapsed', !expanded);

        var toggle = group.querySelector('.billing-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
    }

    function expandAll() {
        groups.forEach(function (group) {
            setGroupState(group, true);
        });
    }

    function collapseAll() {
        groups.forEach(function (group) {
            setGroupState(group, false);
        });
    }

    groups.forEach(function (group) {
        var toggle = group.querySelector('.billing-toggle');
        var header = group.querySelector('.billing-group-header');

        if (toggle) {
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                setGroupState(group, !group.classList.contains('is-expanded'));
            });
        }

        if (header) {
            header.addEventListener('click', function (e) {
                if (e.target.closest('a, button')) return;
                setGroupState(group, !group.classList.contains('is-expanded'));
            });
        }
    });

    var expandAllBtn = document.getElementById('billing-expand-all');
    var collapseAllBtn = document.getElementById('billing-collapse-all');

    if (expandAllBtn) expandAllBtn.addEventListener('click', expandAll);
    if (collapseAllBtn) collapseAllBtn.addEventListener('click', collapseAll);

    if (searchForm && listFilterInput) {
        var searchTimer = null;
        var lastSubmitted = listFilterInput.value;
        var focusKey = 'billingSearchFocus';

        try {
            if (sessionStorage.getItem(focusKey) === '1') {
                sessionStorage.removeItem(focusKey);
                listFilterInput.focus();
                var caret = listFilterInput.value.length;
                if (typeof listFilterInput.setSelectionRange === 'function') {
                    listFilterInput.setSelectionRange(caret, caret);
                }
            }
        } catch (err) {}

        function submitSearch() {
            var next = listFilterInput.value.trim();
            if (next === lastSubmitted.trim()) {
                return;
            }

            try {
                sessionStorage.setItem(focusKey, '1');
            } catch (err) {}

            searchForm.submit();
        }

        searchForm.addEventListener('submit', function () {
            try {
                sessionStorage.setItem(focusKey, '1');
            } catch (err) {}
        });

        listFilterInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(submitSearch, 400);
        });

        listFilterInput.addEventListener('search', function () {
            clearTimeout(searchTimer);
            submitSearch();
        });
    }

    var deleteModal = document.getElementById('billing-delete-modal');
    var deleteForm = document.getElementById('billing-delete-form');

    if (deleteModal && deleteForm) {
        var titleEl = document.getElementById('billing-delete-title');
        var messageEl = document.getElementById('billing-delete-message');
        var submitEl = document.getElementById('billing-delete-submit');
        var confirmEl = document.getElementById('billing-delete-confirm');
        var scopeEl = document.getElementById('billing-delete-scope');
        var billIdEl = document.getElementById('billing-delete-bill-id');
        var customerIdEl = document.getElementById('billing-delete-customer-id');

        function escapeHtml(value) {
            var div = document.createElement('div');
            div.textContent = value || '';
            return div.innerHTML;
        }

        function openDeleteModal(button) {
            var scope = button.getAttribute('data-delete-scope') || 'bill';
            var customerName = escapeHtml(button.getAttribute('data-customer-name') || 'this subscriber');
            var accountNumber = escapeHtml(button.getAttribute('data-account-number') || '');
            var billNumber = escapeHtml(button.getAttribute('data-bill-number') || '');
            var billCount = escapeHtml(button.getAttribute('data-bill-count') || '0');

            scopeEl.value = scope;
            billIdEl.value = button.getAttribute('data-bill-id') || '';
            customerIdEl.value = button.getAttribute('data-customer-id') || '';
            confirmEl.value = '';

            if (scope === 'customer') {
                titleEl.textContent = 'Delete Subscriber Bills';
                messageEl.innerHTML = 'Delete all <strong>' + billCount + '</strong> bill(s) for <strong>'
                    + customerName + '</strong>'
                    + (accountNumber ? ' <span class="text-muted">(' + accountNumber + ')</span>' : '')
                    + '?';
                submitEl.textContent = 'Delete All Bills';
            } else {
                titleEl.textContent = 'Delete Bill';
                messageEl.innerHTML = 'Delete bill <strong>' + billNumber + '</strong> for <strong>'
                    + customerName + '</strong>'
                    + (accountNumber ? ' <span class="text-muted">(' + accountNumber + ')</span>' : '')
                    + '?';
                submitEl.textContent = 'Delete Bill';
            }

            deleteModal.hidden = false;
            document.body.classList.add('modal-open');
            confirmEl.focus();
        }

        function closeDeleteModal() {
            deleteModal.hidden = true;
            document.body.classList.remove('modal-open');
        }

        document.querySelectorAll('[data-open-billing-delete]').forEach(function (button) {
            button.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                openDeleteModal(button);
            });
        });

        deleteModal.querySelectorAll('[data-close-billing-delete]').forEach(function (button) {
            button.addEventListener('click', closeDeleteModal);
        });

        deleteModal.addEventListener('click', function (event) {
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !deleteModal.hidden) {
                closeDeleteModal();
            }
        });
    }
});
