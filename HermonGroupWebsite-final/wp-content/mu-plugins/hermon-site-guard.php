<?php
/**
 * Plugin Name: HERMON Site Guard
 * Description: Hardening, intrusion alerts and front-end fixes for hermongroup.co.mz. Must-use plugin: cannot be deactivated from the dashboard.
 * Version:     1.0.0
 * Author:      HERMON Group, S.A
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ------------------------------------------------------------------
 * 1. Hardening
 * ------------------------------------------------------------------ */

// XML-RPC is a common brute-force and pingback-abuse entry point; nothing on this site uses it.
add_filter('xmlrpc_enabled', '__return_false');
add_filter('xmlrpc_methods', '__return_empty_array');
remove_action('wp_head', 'rsd_link');

// Application passwords give API access that bypasses the login screen and its rate limiting.
add_filter('wp_is_application_passwords_available', '__return_false');

// Don't advertise the WordPress version.
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');

// Stop username discovery through ?author=N and the public users REST endpoint.
add_action('template_redirect', function () {
    if (!is_user_logged_in() && (isset($_GET['author']) || is_author())) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
});
add_filter('rest_endpoints', function ($endpoints) {
    if (!is_user_logged_in()) {
        foreach (array_keys($endpoints) as $route) {
            if (strpos($route, '/wp/v2/users') === 0) {
                unset($endpoints[$route]);
            }
        }
    }
    return $endpoints;
});

// Generic login error so attackers can't tell whether a username exists.
add_filter('login_errors', function () {
    return __('Incorrect username or password.');
});

/* ------------------------------------------------------------------
 * 2. Intrusion alerts (sent to the site admin e-mail)
 * ------------------------------------------------------------------ */

function hermon_guard_alert($subject, $lines) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'cli/cron';
    $user = wp_get_current_user();
    $body = implode("\n", (array) $lines) . "\n\n"
        . 'Site: ' . home_url('/') . "\n"
        . 'When: ' . wp_date('Y-m-d H:i:s') . "\n"
        . 'By: ' . ($user->exists() ? $user->user_login : '(not logged in)') . " - IP $ip\n\n"
        . "If you did not do this, the site may be compromised: change passwords and check the users and plugins.";
    wp_mail(get_option('admin_email'), '[HERMON Site Guard] ' . $subject, $body);
}

add_action('user_register', function ($user_id) {
    $u = get_userdata($user_id);
    if ($u && in_array('administrator', (array) $u->roles, true)) {
        hermon_guard_alert('New administrator created', 'New administrator account: ' . $u->user_login . ' <' . $u->user_email . '>');
    }
});
add_action('set_user_role', function ($user_id, $role) {
    if ($role === 'administrator') {
        $u = get_userdata($user_id);
        hermon_guard_alert('User promoted to administrator', 'Account promoted to administrator: ' . ($u ? $u->user_login : $user_id));
    }
}, 10, 2);
add_action('add_user_role', function ($user_id, $role) {
    if ($role === 'administrator') {
        $u = get_userdata($user_id);
        hermon_guard_alert('User promoted to administrator', 'Account promoted to administrator: ' . ($u ? $u->user_login : $user_id));
    }
}, 10, 2);
add_action('activated_plugin', function ($plugin) {
    hermon_guard_alert('Plugin activated', 'Plugin activated: ' . $plugin);
});

// Daily scan: PHP files where WordPress never puts them (the webshell locations used in the 2026 attack)
// and any change to the list of administrators or must-use plugins.
add_action('init', function () {
    if (!wp_next_scheduled('hermon_guard_daily_scan')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'hermon_guard_daily_scan');
    }
});
add_action('hermon_guard_daily_scan', 'hermon_guard_daily_scan');

function hermon_guard_daily_scan() {
    $core_root = ['index.php', 'wp-activate.php', 'wp-blog-header.php', 'wp-comments-post.php', 'wp-config.php',
        'wp-config-sample.php', 'wp-cron.php', 'wp-links-opml.php', 'wp-load.php', 'wp-login.php', 'wp-mail.php',
        'wp-settings.php', 'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php'];
    $suspicious = [];

    foreach ((array) glob(ABSPATH . '*.php') as $f) {
        if (!in_array(basename($f), $core_root, true)) {
            $suspicious[] = $f;
        }
    }
    // Legitimate wp-content drop-ins (LiteSpeed Cache writes the first two).
    $dropins = ['index.php', 'advanced-cache.php', 'object-cache.php'];
    foreach ([WP_CONTENT_DIR . '/*.php', get_theme_root() . '/*.php', WP_CONTENT_DIR . '/languages/*.php'] as $pattern) {
        foreach ((array) glob($pattern) as $f) {
            if (!in_array(basename($f), $dropins, true)) {
                $suspicious[] = $f;
            }
        }
    }
    $uploads = wp_get_upload_dir()['basedir'];
    if (is_dir($uploads)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (preg_match('/\.(php\d?|phtml|phar)$/i', $file->getFilename())) {
                $suspicious[] = $file->getPathname();
            }
        }
    }

    $admins = get_users(['role' => 'administrator', 'fields' => ['user_login']]);
    $state = [
        'admins' => array_map(function ($u) { return $u->user_login; }, $admins),
        'mu' => array_map('basename', (array) glob(WPMU_PLUGIN_DIR . '/*.php')),
        'plugins' => array_keys(get_plugins()),
    ];
    sort($state['admins']);
    sort($state['mu']);
    sort($state['plugins']);
    $previous = get_option('hermon_guard_state');
    update_option('hermon_guard_state', $state, false);

    $lines = [];
    if ($suspicious) {
        $lines[] = "PHP files in places WordPress never uses (possible backdoors):\n - " . implode("\n - ", $suspicious);
    }
    if (is_array($previous)) {
        foreach (['admins' => 'Administrators', 'mu' => 'Must-use plugins', 'plugins' => 'Installed plugins'] as $key => $label) {
            $added = array_diff($state[$key], $previous[$key] ?? []);
            $removed = array_diff($previous[$key] ?? [], $state[$key]);
            if ($added) {
                $lines[] = "$label added: " . implode(', ', $added);
            }
            if ($removed) {
                $lines[] = "$label removed: " . implode(', ', $removed);
            }
        }
    }
    if ($lines) {
        hermon_guard_alert('Daily security scan found changes', $lines);
    }
}

/* ------------------------------------------------------------------
 * 3. Front-end fixes and weight reduction
 * ------------------------------------------------------------------ */

// Header Footer Elementor enqueues three Elementor Pro stylesheets by a hard-coded path that does not exist
// with Pro Elements, so every page requested three 404s.
add_action('wp_enqueue_scripts', function () {
    if (!is_dir(WP_PLUGIN_DIR . '/elementor-pro')) {
        foreach (['hfe-widget-blockquote', 'hfe-mega-menu', 'hfe-nav-menu-widget'] as $handle) {
            wp_dequeue_style($handle);
        }
    }
}, 30);

// New uploads get a <file>.<ext>.webp copy (original and every size); .htaccess serves it to browsers
// that accept WebP, so images stay light without changing any URL.
add_filter('wp_generate_attachment_metadata', function ($meta, $attachment_id) {
    $file = get_attached_file($attachment_id);
    if (!$file || !preg_match('/\.(jpe?g|png)$/i', $file) || !wp_image_editor_supports(['mime_type' => 'image/webp'])) {
        return $meta;
    }
    $paths = [$file];
    if (!empty($meta['original_image'])) {
        $paths[] = path_join(dirname($file), $meta['original_image']);
    }
    foreach ((array) ($meta['sizes'] ?? []) as $size) {
        $paths[] = path_join(dirname($file), $size['file']);
    }
    foreach (array_unique($paths) as $path) {
        $editor = wp_get_image_editor($path);
        if (is_wp_error($editor)) {
            continue;
        }
        $editor->set_quality(82);
        $saved = $editor->save($path . '.webp', 'image/webp');
        if (!is_wp_error($saved) && filesize($saved['path']) >= filesize($path)) {
            wp_delete_file($saved['path']);
        }
    }
    return $meta;
}, 10, 2);

add_action('delete_attachment', function ($attachment_id) {
    $file = get_attached_file($attachment_id);
    $meta = wp_get_attachment_metadata($attachment_id);
    if (!$file) {
        return;
    }
    $paths = [$file];
    foreach ((array) ($meta['sizes'] ?? []) as $size) {
        $paths[] = path_join(dirname($file), $size['file']);
    }
    foreach ($paths as $path) {
        if (file_exists($path . '.webp')) {
            wp_delete_file($path . '.webp');
        }
    }
});

// Emoji detection script and styles: modern browsers render emoji natively.
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('admin_print_scripts', 'print_emoji_detection_script');
remove_action('admin_print_styles', 'print_emoji_styles');
add_filter('emoji_svg_url', '__return_false');

// Head clutter.
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_shortlink_wp_head');
remove_action('wp_head', 'feed_links_extra', 3);

// No blog on this site: send post feeds and the empty blog archives to the home page.
add_action('template_redirect', function () {
    if (is_feed() || is_date() || is_category() || is_tag()) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
}, 5);
