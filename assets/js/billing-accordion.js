document.addEventListener('DOMContentLoaded', function () {
    var groups = Array.prototype.slice.call(document.querySelectorAll('.billing-customer-group'));
    var listFilterInput = document.getElementById('billing-list-filter');
    var listFilterEmpty = document.getElementById('billing-list-filter-empty');
    var tableWrap = document.querySelector('.billing-datatable') && document.querySelector('.billing-datatable').closest('.table-responsive');

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

    function applyListFilter() {
        if (!listFilterInput || !groups.length) {
            return;
        }

        var query = listFilterInput.value.trim().toLowerCase();
        var visibleCount = 0;

        groups.forEach(function (group) {
            var haystack = (group.getAttribute('data-search') || '').toLowerCase();
            var matches = !query || haystack.indexOf(query) !== -1;
            group.classList.toggle('is-filter-hidden', !matches);
            if (matches) {
                visibleCount += 1;
            }
        });

        if (tableWrap) {
            tableWrap.classList.toggle('is-filter-empty', query !== '' && visibleCount === 0);
        }

        if (listFilterEmpty) {
            listFilterEmpty.classList.toggle('hidden', query === '' || visibleCount > 0);
        }
    }

    if (listFilterInput) {
        listFilterInput.addEventListener('input', applyListFilter);
    }
});
