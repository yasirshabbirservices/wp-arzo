<?php

/**
 * WP Arzo Icon System
 *
 * Single source of inline SVG icons used across the plugin. Returns crisp,
 * currentColor-inheriting, 24x24 stroke icons (Lucide-style) so every status and
 * action uses a real icon — never an emoji or a default browser glyph.
 *
 * Usage:
 *   echo wp_arzo_icon('check');
 *   echo wp_arzo_icon('trash', ['class' => 'wpa-icon wpa-icon--sm', 'aria-label' => 'Delete']);
 *
 * Decorative by default (aria-hidden). Pass an 'aria-label' to make it meaningful.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('wp_arzo_icon_paths')) {
    /**
     * Inner SVG markup for each registered icon (24x24 viewBox, stroke-based).
     *
     * @return array<string,string>
     */
    function wp_arzo_icon_paths()
    {
        static $icons = null;
        if ($icons !== null) {
            return $icons;
        }

        $icons = [
            // status
            'check'        => '<polyline points="20 6 9 17 4 12"/>',
            'check-circle' => '<circle cx="12" cy="12" r="9"/><polyline points="16 10 11 15 8 12"/>',
            'x'            => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
            'x-circle'     => '<circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
            'alert'        => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            'info'         => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
            'warning'      => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
            'clock'        => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
            'dot'          => '<circle cx="12" cy="12" r="5"/>',

            // actions
            'plus'         => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
            'trash'        => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
            'edit'         => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>',
            'download'     => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
            'upload'       => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
            'copy'         => '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
            'search'       => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
            'refresh'      => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
            'external'     => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
            'chevron-down' => '<polyline points="6 9 12 15 18 9"/>',
            'chevron-right'=> '<polyline points="9 18 15 12 9 6"/>',
            'settings'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',

            // domain
            'user'         => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'users'        => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'plugin'       => '<path d="M14 7h3a2 2 0 0 1 2 2v3M10 7H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-1"/><path d="M9 3v4M15 3v4"/>',
            'theme'        => '<circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 2a10 10 0 0 0 0 20 2.5 2.5 0 0 0 2.5-2.5c0-.6-.2-1.1-.6-1.5-.4-.4-.6-.9-.6-1.5A2.5 2.5 0 0 1 15.8 14H18a4 4 0 0 0 4-4 8 8 0 0 0-8-8z"/>',
            'database'     => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
            'file'         => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
            'folder'       => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
            'bug'          => '<rect x="8" y="6" width="8" height="14" rx="4"/><path d="M19 7l-3 2M5 7l3 2M3 13h3M18 13h3M5 19l3-2M19 19l-3-2M12 2v4"/>',
            'shield'       => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
            'lock'         => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'mail'         => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
            'bolt'         => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
            'code'         => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
            'sparkles'     => '<path d="M12 3l1.9 4.6L18.5 9.5 13.9 11.4 12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"/><path d="M19 14l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"/>',
            'image'        => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
            'tools'        => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18v3h3l6.3-6.3a4 4 0 0 0 5.4-5.4l-2.7 2.7-2-2 2.7-2.7z"/>',
            'grid'         => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'key'          => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.5 12.5 6.5-6.5"/><path d="m15 4 3 3"/><path d="m18 7 2-2-2.5-2.5"/>',
            'sliders'      => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
        ];

        return $icons;
    }
}

if (!function_exists('wp_arzo_icon')) {
    /**
     * Render a registered SVG icon.
     *
     * @param string $name  Icon key (see wp_arzo_icon_paths()).
     * @param array  $attrs Optional attributes: 'class', 'aria-label', 'title', 'width', 'height'.
     * @return string SVG markup (empty string if the icon is unknown).
     */
    function wp_arzo_icon($name, $attrs = [])
    {
        $paths = wp_arzo_icon_paths();
        if (!isset($paths[$name])) {
            return '';
        }

        $class  = isset($attrs['class']) ? $attrs['class'] : 'wpa-icon';
        $size   = isset($attrs['size']) ? (int) $attrs['size'] : 24;
        $width  = isset($attrs['width']) ? (int) $attrs['width'] : $size;
        $height = isset($attrs['height']) ? (int) $attrs['height'] : $size;

        $label  = isset($attrs['aria-label']) ? $attrs['aria-label'] : '';
        $a11y   = $label !== ''
            ? 'role="img" aria-label="' . esc_attr($label) . '"'
            : 'aria-hidden="true" focusable="false"';

        return sprintf(
            '<svg class="%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" '
                . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" '
                . 'stroke-linejoin="round" %s>%s</svg>',
            esc_attr($class),
            $width,
            $height,
            $a11y,
            $paths[$name] // static, trusted markup
        );
    }
}
