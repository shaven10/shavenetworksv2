document.addEventListener('DOMContentLoaded', function () {
    var groups = Array.prototype.slice.call(document.querySelectorAll('.billing-customer-group'));
    if (!groups.length) return;

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
});
