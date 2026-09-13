<?php

function getThemePresets(): array
{
    return [
        'default' => [
            'label'       => 'Ocean Blue',
            'description' => 'Classic blue accent with dark sidebar',
            'primary'     => '#2563eb',
            'primary_dark'=> '#1d4ed8',
            'sidebar_bg'  => '#0f172a',
            'accent'      => '#0891b2',
        ],
        'emerald' => [
            'label'       => 'Emerald',
            'description' => 'Fresh green tones for a clean look',
            'primary'     => '#059669',
            'primary_dark'=> '#047857',
            'sidebar_bg'  => '#064e3b',
            'accent'      => '#0d9488',
        ],
        'violet' => [
            'label'       => 'Violet',
            'description' => 'Purple accent with deep sidebar',
            'primary'     => '#7c3aed',
            'primary_dark'=> '#6d28d9',
            'sidebar_bg'  => '#2e1065',
            'accent'      => '#a855f7',
        ],
        'rose' => [
            'label'       => 'Rose',
            'description' => 'Warm rose accent with slate sidebar',
            'primary'     => '#e11d48',
            'primary_dark'=> '#be123c',
            'sidebar_bg'  => '#1e293b',
            'accent'      => '#f43f5e',
        ],
        'amber' => [
            'label'       => 'Amber',
            'description' => 'Golden accent with charcoal sidebar',
            'primary'     => '#d97706',
            'primary_dark'=> '#b45309',
            'sidebar_bg'  => '#292524',
            'accent'      => '#f59e0b',
        ],
        'slate' => [
            'label'       => 'Slate',
            'description' => 'Neutral gray professional theme',
            'primary'     => '#475569',
            'primary_dark'=> '#334155',
            'sidebar_bg'  => '#0f172a',
            'accent'      => '#64748b',
        ],
    ];
}

function getDefaultTheme(): array
{
    $preset = getThemePresets()['default'];

    return [
        'preset'      => 'default',
        'mode'        => 'light',
        'primary'     => $preset['primary'],
        'primary_dark'=> $preset['primary_dark'],
        'sidebar_bg'  => $preset['sidebar_bg'],
        'accent'      => $preset['accent'],
        'radius'      => 8,
        'font_family' => 'system',
    ];
}

function getThemeFilePath(): string
{
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir . '/theme.json';
}

function loadAppTheme(): array
{
    static $theme = null;
    if ($theme !== null) {
        return $theme;
    }

    $defaults = getDefaultTheme();
    $path = getThemeFilePath();

    if (is_file($path)) {
        $saved = json_decode(file_get_contents($path), true);
        if (is_array($saved)) {
            $theme = array_merge($defaults, $saved);
            return $theme;
        }
    }

    $theme = $defaults;
    return $theme;
}

function saveAppTheme(array $data): void
{
    $defaults = getDefaultTheme();
    $presets = getThemePresets();
    $presetKey = $data['preset'] ?? 'default';

    if (!isset($presets[$presetKey])) {
        $presetKey = 'default';
    }

    $preset = $presets[$presetKey];
    $theme = array_merge($defaults, [
        'preset'       => $presetKey,
        'mode'         => ($data['mode'] ?? 'light') === 'dark' ? 'dark' : 'light',
        'primary'      => sanitizeHexColor($data['primary'] ?? $preset['primary'], $preset['primary']),
        'primary_dark' => sanitizeHexColor($data['primary_dark'] ?? $preset['primary_dark'], $preset['primary_dark']),
        'sidebar_bg'   => sanitizeHexColor($data['sidebar_bg'] ?? $preset['sidebar_bg'], $preset['sidebar_bg']),
        'accent'       => sanitizeHexColor($data['accent'] ?? $preset['accent'], $preset['accent']),
        'radius'       => max(4, min(16, (int) ($data['radius'] ?? 8))),
        'font_family'  => in_array($data['font_family'] ?? 'system', ['system', 'native', 'serif', 'mono'], true)
            ? $data['font_family'] : 'system',
    ]);

    file_put_contents(getThemeFilePath(), json_encode($theme, JSON_PRETTY_PRINT));
}

function sanitizeHexColor(string $value, string $fallback): string
{
    $value = trim($value);
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
        return strtolower($value);
    }
    return $fallback;
}

function themeFontStack(string $family): string
{
    return match ($family) {
        'serif'  => "'Source Serif 4', Georgia, 'Times New Roman', serif",
        'mono'   => "'IBM Plex Mono', ui-monospace, Consolas, monospace",
        'native' => "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
        default  => "'IBM Plex Sans', system-ui, -apple-system, 'Segoe UI', sans-serif",
    };
}

function renderFontLinks(): string
{
    return <<<HTML
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&display=swap" rel="stylesheet">
HTML;
}

function renderThemeAttributes(): string
{
    $theme = loadAppTheme();
    $attrs = sprintf(
        'data-theme-mode="%s" data-theme-preset="%s"',
        e($theme['mode']),
        e($theme['preset'])
    );

    return $attrs;
}

function renderThemeStyles(): string
{
    $theme = loadAppTheme();
    $isDark = $theme['mode'] === 'dark';
    $font = themeFontStack($theme['font_family']);
    $radius = (int) $theme['radius'];

    $bg = $isDark ? '#0f172a' : '#f1f5f9';
    $surface = $isDark ? '#1e293b' : '#ffffff';
    $border = $isDark ? '#334155' : '#e2e8f0';
    $text = $isDark ? '#f1f5f9' : '#1e293b';
    $textMuted = $isDark ? '#94a3b8' : '#64748b';
    $sidebarText = '#cbd5e1';
    $shadow = $isDark ? '0 1px 3px rgba(0,0,0,.35)' : '0 1px 3px rgba(0,0,0,.08)';
    $inputBg = $isDark ? '#0f172a' : '#ffffff';
    $panelBg = $isDark ? '#0f172a' : '#f8fafc';
    $dangerZoneBg = $isDark ? '#1f1215' : '#fffbfb';
    $dangerZoneBorder = $isDark ? '#7f1d1d' : '#fecaca';

    $css = <<<CSS
:root {
    --primary: {$theme['primary']};
    --primary-dark: {$theme['primary_dark']};
    --accent: {$theme['accent']};
    --success: #16a34a;
    --warning: #d97706;
    --danger: #dc2626;
    --info: {$theme['accent']};
    --bg: {$bg};
    --surface: {$surface};
    --border: {$border};
    --text: {$text};
    --text-muted: {$textMuted};
    --sidebar-bg: {$theme['sidebar_bg']};
    --sidebar-text: {$sidebarText};
    --radius: {$radius}px;
    --shadow: {$shadow};
    --input-bg: {$inputBg};
    --panel-bg: {$panelBg};
    --danger-zone-bg: {$dangerZoneBg};
    --danger-zone-border: {$dangerZoneBorder};
    --font-family: {$font};
    --font-mono: 'IBM Plex Mono', ui-monospace, Consolas, monospace;
    --nav-active-bg: color-mix(in srgb, {$theme['primary']} 25%, transparent);
}
CSS;

    return $css;
}

function getThemePresetJson(): string
{
    return json_encode(getThemePresets(), JSON_UNESCAPED_UNICODE);
}
