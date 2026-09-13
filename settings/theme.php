<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner');

$pageTitle = 'Theme Manager';
$currentPage = 'theme_manager';
$presets = getThemePresets();
$theme = loadAppTheme();
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'reset') {
        saveAppTheme(getDefaultTheme());
        logActivity('theme_reset', 'Reset theme to defaults');
        flash('success', 'Theme reset to default settings.');
        redirect('/settings/theme.php');
    }

    saveAppTheme([
        'preset'       => $_POST['preset'] ?? 'default',
        'mode'         => $_POST['mode'] ?? 'light',
        'primary'      => $_POST['primary'] ?? '',
        'primary_dark' => $_POST['primary_dark'] ?? '',
        'sidebar_bg'   => $_POST['sidebar_bg'] ?? '',
        'accent'       => $_POST['accent'] ?? '',
        'radius'       => $_POST['radius'] ?? 8,
        'font_family'  => $_POST['font_family'] ?? 'system',
    ]);

    logActivity('theme_update', 'Updated application theme');
    flash('success', 'Theme saved successfully.');
    redirect('/settings/theme.php');
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Theme Manager</h1>
        <p>Customize colors, appearance, and layout styling for the entire application</p>
    </div>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/settings/api.php" class="btn btn-outline btn-sm">API Settings</a>
        <a href="<?= APP_URL ?>/settings/database.php" class="btn btn-outline btn-sm">Database Tools</a>
    </div>
</div>

<form method="POST" class="card card-form" id="theme-form">
    <input type="hidden" name="action" value="save">

    <div class="theme-layout">
        <div class="theme-controls">
            <div class="card-header"><h2>Appearance</h2></div>

            <div class="form-group">
                <label>Color Mode</label>
                <div class="mode-toggle">
                    <label class="mode-option">
                        <input type="radio" name="mode" value="light" <?= $theme['mode'] === 'light' ? 'checked' : '' ?>>
                        <span>☀️ Light</span>
                    </label>
                    <label class="mode-option">
                        <input type="radio" name="mode" value="dark" <?= $theme['mode'] === 'dark' ? 'checked' : '' ?>>
                        <span>🌙 Dark</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="preset">Theme Preset</label>
                <select name="preset" id="preset">
                    <?php foreach ($presets as $key => $preset): ?>
                    <option value="<?= e($key) ?>" <?= $theme['preset'] === $key ? 'selected' : '' ?>>
                        <?= e($preset['label']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-hint" id="preset-desc"><?= e($presets[$theme['preset']]['description'] ?? '') ?></span>
            </div>

            <div class="form-group">
                <label for="font_family">Font Style</label>
                <select name="font_family" id="font_family">
                    <option value="system" <?= $theme['font_family'] === 'system' ? 'selected' : '' ?>>IBM Plex Sans (Recommended)</option>
                    <option value="native" <?= $theme['font_family'] === 'native' ? 'selected' : '' ?>>System UI</option>
                    <option value="serif" <?= $theme['font_family'] === 'serif' ? 'selected' : '' ?>>Source Serif</option>
                    <option value="mono" <?= $theme['font_family'] === 'mono' ? 'selected' : '' ?>>IBM Plex Mono</option>
                </select>
                <span class="form-hint">Plex Sans is optimized for screen reading and dense data tables.</span>
            </div>

            <div class="form-group">
                <label for="radius">Corner Radius (<span id="radius-value"><?= (int) $theme['radius'] ?></span>px)</label>
                <input type="range" name="radius" id="radius" min="4" max="16" step="1"
                       value="<?= (int) $theme['radius'] ?>">
            </div>

            <div class="card-header" style="margin-top:8px"><h2>Custom Colors</h2></div>
            <p class="form-hint" style="margin-bottom:12px">Override preset colors or pick your own brand palette.</p>

            <div class="color-grid">
                <div class="form-group">
                    <label for="primary">Primary Color</label>
                    <div class="color-input-wrap">
                        <input type="color" id="primary-picker" value="<?= e($theme['primary']) ?>">
                        <input type="text" name="primary" id="primary" value="<?= e($theme['primary']) ?>" pattern="#[0-9a-fA-F]{6}">
                    </div>
                </div>
                <div class="form-group">
                    <label for="primary_dark">Primary Dark</label>
                    <div class="color-input-wrap">
                        <input type="color" id="primary_dark-picker" value="<?= e($theme['primary_dark']) ?>">
                        <input type="text" name="primary_dark" id="primary_dark" value="<?= e($theme['primary_dark']) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="sidebar_bg">Sidebar Background</label>
                    <div class="color-input-wrap">
                        <input type="color" id="sidebar_bg-picker" value="<?= e($theme['sidebar_bg']) ?>">
                        <input type="text" name="sidebar_bg" id="sidebar_bg" value="<?= e($theme['sidebar_bg']) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="accent">Accent Color</label>
                    <div class="color-input-wrap">
                        <input type="color" id="accent-picker" value="<?= e($theme['accent']) ?>">
                        <input type="text" name="accent" id="accent" value="<?= e($theme['accent']) ?>">
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Theme</button>
                <button type="button" class="btn btn-outline" id="apply-preset-btn">Apply Preset Colors</button>
            </div>
        </div>

        <div class="theme-preview-panel">
            <div class="card-header"><h2>Live Preview</h2></div>
            <div class="theme-preview" id="theme-preview">
                <div class="preview-sidebar">
                    <div class="preview-brand">
                        <span class="preview-logo">SN</span>
                        <div>
                            <strong>SHAVEN Networks</strong>
                            <small>ISP Billing</small>
                        </div>
                    </div>
                    <div class="preview-nav-item active">Dashboard</div>
                    <div class="preview-nav-item">Customers</div>
                    <div class="preview-nav-item">Billing</div>
                </div>
                <div class="preview-main">
                    <div class="preview-topbar">
                        <span>Dashboard</span>
                        <span class="preview-badge">3</span>
                    </div>
                    <div class="preview-stats">
                        <div class="preview-stat"><strong>128</strong><span>Customers</span></div>
                        <div class="preview-stat"><strong>₱45k</strong><span>Revenue</span></div>
                    </div>
                    <div class="preview-card">
                        <h4>Sample Card</h4>
                        <p>Preview how cards, text, and buttons will look.</p>
                        <button type="button" class="btn btn-primary btn-sm">Primary Button</button>
                        <button type="button" class="btn btn-outline btn-sm">Outline</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<form method="POST" class="theme-reset-form" onsubmit="return confirm('Reset theme to default settings?')">
    <input type="hidden" name="action" value="reset">
    <button type="submit" class="btn btn-outline">Reset to Default</button>
</form>

<script>
window.THEME_PRESETS = <?= getThemePresetJson() ?>;
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
