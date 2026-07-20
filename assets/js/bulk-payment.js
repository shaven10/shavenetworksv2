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
        return Array.prototype.slice.call(document.querySelectorAll('.bulk-bill-row'));
    }

    function rowInputs(row) {
        return {
            checkbox: row.querySelector('.bill-select'),
            amount: row.querySelector('.bill-amount-input'),
            balance: parseFloat(row.dataset.balance || row.querySelector('.bill-balance').dataset.balance || '0')
        };
    }

    function isFullyPaid(inputs) {
        if (!inputs.checkbox.checked) {
            return false;
        }

        var amount = parseFloat(inputs.amount.value || '0');
        return amount + 0.009 >= inputs.balance;
    }

    function refreshPayableRows() {
        var rows = getRows();
        rows.forEach(function (row, index) {
            var inputs = rowInputs(row);

            if (index === 0) {
                inputs.checkbox.disabled = false;
                return;
            }

            var previousRows = rows.slice(0, index);
            var canPay = previousRows.every(function (previousRow) {
                return isFullyPaid(rowInputs(previousRow));
            });

            if (!canPay) {
                inputs.checkbox.checked = false;
                inputs.amount.value = '';
                inputs.amount.disabled = true;
            }

            inputs.checkbox.disabled = !canPay;
        });

        if (selectAll) {
            var enabledRows = rows.filter(function (row) {
                return !rowInputs(row).checkbox.disabled;
            });
            var checkedEnabled = enabledRows.filter(function (row) {
                return rowInputs(row).checkbox.checked;
            });
            selectAll.checked = enabledRows.length > 0 && checkedEnabled.length === enabledRows.length;
        }
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
        refreshPayableRows();
        return total;
    }

    function syncRowState(row) {
        var inputs = rowInputs(row);
        if (!inputs.checkbox.checked || inputs.checkbox.disabled) {
            if (!inputs.checkbox.checked) {
                inputs.amount.value = '';
            }
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
            getRows().forEach(function (row, index) {
                var inputs = rowInputs(row);
                if (inputs.checkbox.disabled) {
                    return;
                }

                if (selectAll.checked) {
                    inputs.checkbox.checked = index === 0 || isFullyPaid(rowInputs(getRows()[index - 1]));
                } else {
                    inputs.checkbox.checked = false;
                }
                syncRowState(row);
            });
            updateTotal();
        });
    }

    if (fillAllBtn) {
        fillAllBtn.addEventListener('click', function () {
            getRows().forEach(function (row, index) {
                var inputs = rowInputs(row);
                if (index > 0 && !isFullyPaid(rowInputs(getRows()[index - 1]))) {
                    inputs.checkbox.checked = false;
                    inputs.amount.value = '';
                    inputs.amount.disabled = true;
                    inputs.checkbox.disabled = true;
                    return;
                }

                inputs.checkbox.disabled = false;
                inputs.checkbox.checked = true;
                inputs.amount.disabled = false;
                inputs.amount.value = inputs.balance.toFixed(2);
            });
            if (selectAll) {
                selectAll.checked = true;
            }
            updateTotal();
        });
    }

    function validateForm() {
        var selectedCount = 0;
        var invalid = false;
        var rows = getRows();

        rows.forEach(function (row) {
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

        if (!rowInputs(rows[0]).checkbox.checked) {
            alert('Pay the oldest billing period first.');
            return false;
        }

        var blockLater = false;
        for (var i = 0; i < rows.length; i++) {
            var inputs = rowInputs(rows[i]);
            var amount = parseFloat(inputs.amount.value || '0');

            if (blockLater && inputs.checkbox.checked && amount > 0) {
                alert('Fully pay earlier billing periods before paying later months.');
                return false;
            }

            if (inputs.checkbox.checked && amount > 0) {
                if (amount + 0.009 < inputs.balance) {
                    blockLater = true;
                }
            }
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

    refreshPayableRows();
});
