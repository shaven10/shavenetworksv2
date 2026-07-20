document.addEventListener('DOMContentLoaded', function () {
    var billSelect = document.getElementById('bill_id');
    var amountInput = document.getElementById('amount');
    var balanceHint = document.getElementById('balance-hint');

    if (billSelect && amountInput) {
        function updateBalance() {
            var option = billSelect.options[billSelect.selectedIndex];
            if (option && option.dataset.balance) {
                var balance = parseFloat(option.dataset.balance);
                balanceHint.textContent = 'Outstanding balance: ₱' + balance.toFixed(2);
                if (!amountInput.value) {
                    amountInput.value = balance.toFixed(2);
                }
                amountInput.max = balance;
            } else {
                balanceHint.textContent = '';
            }
        }

        billSelect.addEventListener('change', updateBalance);
        updateBalance();
    }

    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity .5s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 500);
        }, 5000);
    });

    var notifyToggle = document.getElementById('notification-toggle');
    var notifyPanel = document.getElementById('notification-panel');
    if (notifyToggle && notifyPanel) {
        notifyToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = !notifyPanel.hidden;
            notifyPanel.hidden = isOpen;
            notifyToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            if (!isOpen && window.SNNotifications) {
                window.SNNotifications.refresh();
            }
        });

        document.addEventListener('click', function (e) {
            if (!notifyPanel.hidden && !notifyPanel.contains(e.target) && e.target !== notifyToggle && !notifyToggle.contains(e.target)) {
                notifyPanel.hidden = true;
                notifyToggle.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !notifyPanel.hidden) {
                notifyPanel.hidden = true;
                notifyToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    initMobileSidebar();
    initThemeManager();
    initNotifications();
});

function initMobileSidebar() {
    var toggle = document.getElementById('sidebar-toggle');
    var sidebar = document.getElementById('app-sidebar');
    var backdrop = document.getElementById('sidebar-backdrop');

    if (!toggle || !sidebar) {
        return;
    }

    function openSidebar() {
        document.body.classList.add('sidebar-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close menu');
        if (backdrop) {
            backdrop.hidden = false;
        }
    }

    function closeSidebar() {
        document.body.classList.remove('sidebar-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open menu');
        if (backdrop) {
            backdrop.hidden = true;
        }
    }

    toggle.addEventListener('click', function () {
        if (document.body.classList.contains('sidebar-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    sidebar.querySelectorAll('.nav-link').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.matchMedia('(max-width: 900px)').matches) {
                closeSidebar();
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) {
            closeSidebar();
        }
    });
}

function initThemeManager() {
    var form = document.getElementById('theme-form');
    if (!form || !window.THEME_PRESETS) {
        return;
    }

    var presetSelect = document.getElementById('preset');
    var presetDesc = document.getElementById('preset-desc');
    var radiusInput = document.getElementById('radius');
    var radiusValue = document.getElementById('radius-value');
    var applyPresetBtn = document.getElementById('apply-preset-btn');
    var preview = document.getElementById('theme-preview');
    var themeStyle = document.getElementById('app-theme');

    var fields = {
        primary: document.getElementById('primary'),
        primary_dark: document.getElementById('primary_dark'),
        sidebar_bg: document.getElementById('sidebar_bg'),
        accent: document.getElementById('accent')
    };

    var pickers = {
        primary: document.getElementById('primary-picker'),
        primary_dark: document.getElementById('primary_dark-picker'),
        sidebar_bg: document.getElementById('sidebar_bg-picker'),
        accent: document.getElementById('accent-picker')
    };

    function getMode() {
        var checked = form.querySelector('input[name="mode"]:checked');
        return checked ? checked.value : 'light';
    }

    function syncPickerToText(key) {
        if (pickers[key] && fields[key]) {
            pickers[key].value = fields[key].value;
        }
    }

    function syncTextToPicker(key) {
        if (pickers[key] && fields[key] && /^#[0-9a-fA-F]{6}$/.test(fields[key].value)) {
            pickers[key].value = fields[key].value;
        }
    }

    function applyPresetColors() {
        var preset = window.THEME_PRESETS[presetSelect.value];
        if (!preset) {
            return;
        }

        fields.primary.value = preset.primary;
        fields.primary_dark.value = preset.primary_dark;
        fields.sidebar_bg.value = preset.sidebar_bg;
        fields.accent.value = preset.accent;

        Object.keys(fields).forEach(function (key) {
            syncPickerToText(key);
        });

        updatePreview();
    }

    function buildPreviewCss() {
        var mode = getMode();
        var isDark = mode === 'dark';
        var primary = fields.primary.value;
        var primaryDark = fields.primary_dark.value;
        var sidebarBg = fields.sidebar_bg.value;
        var accent = fields.accent.value;
        var radius = radiusInput ? radiusInput.value : '8';
        var fontFamily = document.getElementById('font_family').value;

        var fontStack = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        if (fontFamily === 'serif') {
            fontStack = "Georgia, 'Times New Roman', serif";
        } else if (fontFamily === 'mono') {
            fontStack = "Consolas, 'Courier New', monospace";
        }

        var bg = isDark ? '#0f172a' : '#f1f5f9';
        var surface = isDark ? '#1e293b' : '#ffffff';
        var border = isDark ? '#334155' : '#e2e8f0';
        var text = isDark ? '#f1f5f9' : '#1e293b';
        var textMuted = isDark ? '#94a3b8' : '#64748b';

        return ':root {\n'
            + '    --primary: ' + primary + ';\n'
            + '    --primary-dark: ' + primaryDark + ';\n'
            + '    --accent: ' + accent + ';\n'
            + '    --bg: ' + bg + ';\n'
            + '    --surface: ' + surface + ';\n'
            + '    --border: ' + border + ';\n'
            + '    --text: ' + text + ';\n'
            + '    --text-muted: ' + textMuted + ';\n'
            + '    --sidebar-bg: ' + sidebarBg + ';\n'
            + '    --radius: ' + radius + 'px;\n'
            + '    --font-family: ' + fontStack + ';\n'
            + '    --nav-active-bg: color-mix(in srgb, ' + primary + ' 25%, transparent);\n'
            + '}';
    }

    function updatePreview() {
        var css = buildPreviewCss();

        if (preview) {
            preview.style.cssText = '';
        }

        if (themeStyle) {
            themeStyle.textContent = css;
        }

        document.documentElement.setAttribute('data-theme-mode', getMode());
        document.documentElement.setAttribute('data-theme-preset', presetSelect.value);
    }

    if (presetSelect) {
        presetSelect.addEventListener('change', function () {
            var preset = window.THEME_PRESETS[presetSelect.value];
            if (preset && presetDesc) {
                presetDesc.textContent = preset.description;
            }
            applyPresetColors();
        });
    }

    if (applyPresetBtn) {
        applyPresetBtn.addEventListener('click', applyPresetColors);
    }

    if (radiusInput && radiusValue) {
        radiusInput.addEventListener('input', function () {
            radiusValue.textContent = radiusInput.value;
            updatePreview();
        });
    }

    form.querySelectorAll('input[name="mode"]').forEach(function (input) {
        input.addEventListener('change', updatePreview);
    });

    var fontSelect = document.getElementById('font_family');
    if (fontSelect) {
        fontSelect.addEventListener('change', updatePreview);
    }

    Object.keys(fields).forEach(function (key) {
        if (pickers[key]) {
            pickers[key].addEventListener('input', function () {
                fields[key].value = pickers[key].value;
                updatePreview();
            });
        }
        if (fields[key]) {
            fields[key].addEventListener('input', function () {
                syncTextToPicker(key);
                updatePreview();
            });
        }
    });

    updatePreview();
}

function initNotifications() {
    var toggle = document.getElementById('notification-toggle');
    var panelBody = document.getElementById('notification-panel-body');
    var badge = document.getElementById('notification-badge');
    var countLabel = document.getElementById('notification-count');
    var markAllBtn = document.getElementById('notification-mark-all');

    if (!toggle || !panelBody) {
        return;
    }

    var apiUrl = (window.SN_APP && window.SN_APP.url ? window.SN_APP.url : '') + '/api/notifications.php';

    function formatCount(total) {
        return total > 99 ? '99+' : String(total);
    }

    function updateChrome(total) {
        var hasUnread = total > 0;

        toggle.classList.toggle('has-unread', hasUnread);
        toggle.setAttribute(
            'aria-label',
            hasUnread ? 'Notifications, ' + total + ' unread' : 'Notifications'
        );

        if (badge) {
            var prev = parseInt(badge.textContent, 10) || 0;
            badge.textContent = formatCount(total);
            badge.classList.toggle('is-hidden', !hasUnread);
            if (hasUnread && total !== prev) {
                badge.classList.remove('bump');
                void badge.offsetWidth;
                badge.classList.add('bump');
            }
        }

        if (countLabel) {
            countLabel.textContent = total + ' unread';
            countLabel.classList.toggle('is-hidden', !hasUnread);
        }

        if (markAllBtn) {
            markAllBtn.classList.toggle('is-hidden', !hasUnread);
        }
    }

    function bindPanelEvents() {
        panelBody.querySelectorAll('.notification-item[data-notification-key]').forEach(function (link) {
            if (link.dataset.bound) {
                return;
            }
            link.dataset.bound = '1';

            link.addEventListener('click', function (e) {
                e.preventDefault();
                var key = link.getAttribute('data-notification-key');
                var href = link.getAttribute('href');
                var entry = link.closest('.notification-entry');

                if (entry) {
                    entry.classList.add('is-read');
                }

                markRead(key).then(function () {
                    if (entry) {
                        entry.classList.add('is-removing');
                        setTimeout(function () {
                            entry.remove();
                            cleanupEmptyGroups();
                        }, 220);
                    }

                    if (href) {
                        setTimeout(function () {
                            window.location.href = href;
                        }, 180);
                    }
                });
            });
        });
    }

    function cleanupEmptyGroups() {
        panelBody.querySelectorAll('.notification-group').forEach(function (group) {
            if (!group.querySelector('.notification-entry')) {
                group.remove();
            }
        });

        if (!panelBody.querySelector('.notification-entry') && !panelBody.querySelector('.notification-empty')) {
            panelBody.innerHTML = '<div class="notification-empty" id="notification-empty-state">'
                + '<span>✓</span><p>All caught up — no new alerts.</p></div>';
        }
    }

    function applyPayload(data, updateHtml) {
        if (typeof data.total === 'number') {
            updateChrome(data.total);
        }
        if (updateHtml && typeof data.html === 'string') {
            panelBody.innerHTML = data.html;
            bindPanelEvents();
        }
    }

    function apiRequest(action, key, updateHtml) {
        var payload = { action: action };
        if (key) {
            payload.key = key;
        }

        return fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (result) {
            if (!result.success) {
                throw new Error(result.message || 'Request failed');
            }
            applyPayload(result.data, updateHtml !== false);
            return result.data;
        });
    }

    function markRead(key) {
        return apiRequest('read', key, false).catch(function () {
            return refresh();
        });
    }

    function markAllRead() {
        panelBody.querySelectorAll('.notification-entry').forEach(function (entry) {
            entry.classList.add('is-removing');
        });

        return new Promise(function (resolve) {
            setTimeout(function () {
                apiRequest('read_all', null, true).then(resolve).catch(function () {
                    refresh().then(resolve);
                });
            }, 180);
        });
    }

    function refresh() {
        return fetch(apiUrl, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.json();
        }).then(function (result) {
            if (result.success) {
                applyPayload(result.data, true);
            }
        }).catch(function () {});
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            markAllRead();
        });
    }

    bindPanelEvents();

    window.SNNotifications = {
        refresh: refresh,
        markRead: markRead,
        markAllRead: markAllRead
    };

    setInterval(refresh, 60000);
}
