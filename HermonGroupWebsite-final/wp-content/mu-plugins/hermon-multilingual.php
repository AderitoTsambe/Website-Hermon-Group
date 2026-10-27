<?php
/**
 * Plugin Name: HERMON Multilingual & Schema
 * Description: Language switcher shortcode [hermon_lang] (Polylang) and extra structured data: Service pages and company address/contacts.
 * Version:     1.0.0
 * Author:      HERMON Group, S.A
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ------------------------------------------------------------------
 * Language switcher: [hermon_lang]
 * Links to the translation of the current page (real URLs Google can crawl).
 * ------------------------------------------------------------------ */
add_shortcode('hermon_lang', function () {
    if (!function_exists('pll_the_languages')) {
        return '';
    }
    $langs = pll_the_languages(['raw' => 1, 'hide_if_no_translation' => 0, 'hide_if_empty' => 0]);
    if (!$langs) {
        return '';
    }
    $out = '<nav class="hermon-lang" aria-label="Language">';
    foreach ($langs as $l) {
        $out .= sprintf(
            '<a href="%s" hreflang="%s" lang="%s" class="%s"%s><img src="%s" alt="" width="20" height="14" data-no-lazy="1"><span>%s</span></a>',
            esc_url($l['url']),
            esc_attr($l['locale'] ? str_replace('_', '-', $l['locale']) : $l['slug']),
            esc_attr($l['slug']),
            $l['current_lang'] ? 'is-current' : '',
            $l['current_lang'] ? ' aria-current="true"' : '',
            esc_url($l['flag']),
            esc_html(strtoupper($l['slug']))
        );
    }
    return $out . '</nav>';
});

// x-default -> English, for visitors whose language is neither English nor Portuguese.
add_filter('pll_rel_hreflang_attributes', function ($hreflangs) {
    if (isset($hreflangs['en'])) {
        $hreflangs['x-default'] = $hreflangs['en'];
    }
    return $hreflangs;
});

add_action('wp_head', function () {
    echo '<style>.hermon-lang{display:flex;gap:10px;align-items:center}.hermon-lang a{display:inline-flex;align-items:center;gap:5px;font:600 13px/1 Montserrat,sans-serif;color:#19376D;text-decoration:none;opacity:.65}.hermon-lang a:hover,.hermon-lang a.is-current{opacity:1}.hermon-lang img{width:20px;height:14px;border-radius:2px;object-fit:cover}.hermon-lang span{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}</style>';
});

/* ------------------------------------------------------------------
 * Header (EN #44 / PT #4050 share element IDs): one row at every width.
 * Desktop: logo | menu (never wraps, even with the longer PT labels) | language pill.
 * Tablet/phone: logo | language pill | menu button - instead of the flags
 * wrapping onto a second line under an oversized logo.
 * ------------------------------------------------------------------ */
add_action('wp_head', function () {
    $h = '.elementor-element-b45dfbf';
    $row = $h . '>.e-con-inner';
    echo '<style>'
        . $row . '{flex-wrap:nowrap!important;flex-direction:row!important;align-items:center!important;gap:24px!important}'
        . $row . '>.elementor-element{--width:auto!important;width:auto!important;margin:0!important}'
        . $row . '>.elementor-element-b4eb456{flex:0 0 auto!important;align-items:flex-start!important;justify-content:center!important;padding:0!important}'
        . $row . '>.elementor-element-71eb2c9{flex:1 1 auto!important;min-width:0}'
        . $row . '>.elementor-element-3d9fcf1{flex:0 0 auto!important;padding:0!important}'
        . '.elementor-element-4ea6b7b,.elementor-element-4ea6b7b .elementor-widget-container{text-align:left!important}'
        . '.elementor-element-4ea6b7b img{width:180px!important;max-width:100%!important;height:auto!important}'
        . '@media(min-width:1025px){.elementor-element-5993a8b .hfe-nav-menu__layout-horizontal .hfe-nav-menu{flex-wrap:nowrap!important;justify-content:flex-end}'
        . '.elementor-element-5993a8b .hfe-nav-menu__layout-horizontal a.hfe-menu-item{white-space:nowrap}}'
        . $h . ' .hermon-lang{gap:2px;padding:3px;border:1px solid #dde3ee;border-radius:999px;background:#fff}'
        . $h . ' .hermon-lang a{padding:7px 10px;border-radius:999px;opacity:.5}'
        . $h . ' .hermon-lang a:hover{opacity:.85}'
        . $h . ' .hermon-lang a.is-current{background:#edf1f8;opacity:1}'
        . '@media(min-width:1025px) and (max-width:1440px){'
        . '.elementor-element-5993a8b .hfe-nav-menu__layout-horizontal a.hfe-menu-item{padding-left:10px!important;padding-right:10px!important}'
        . '}@media(min-width:1025px) and (max-width:1280px){'
        . '.elementor-element.elementor-element-b45dfbf{padding-left:20px!important;padding-right:20px!important}'
        . $row . '{gap:16px!important}'
        . '.elementor-element-4ea6b7b img{width:150px!important}'
        . '.elementor-element-5993a8b .hfe-nav-menu__layout-horizontal a.hfe-menu-item{padding-left:8px!important;padding-right:8px!important;font-size:12px!important}'
        . '}@media(min-width:1025px) and (max-width:1150px){'
        . '.elementor-element-4ea6b7b img{width:130px!important}'
        . '.elementor-element-5993a8b .hfe-nav-menu__layout-horizontal a.hfe-menu-item{padding-left:7px!important;padding-right:7px!important}'
        . '}@media(max-width:1024px){'
        . '.elementor-element.elementor-element-b45dfbf{padding:10px 20px!important}'
        . $row . '{gap:12px!important}'
        . $row . '>.elementor-element{padding:0!important}'
        . $row . '>.elementor-element-b4eb456{flex:1 1 0!important;min-width:0}'
        . $row . '>.elementor-element-3d9fcf1{order:2}'
        . $row . '>.elementor-element-71eb2c9{order:3;flex:0 0 42px!important;width:42px!important}'
        . '.elementor-element-4ea6b7b img{width:190px!important}'
        . '.elementor-element-5993a8b .hfe-nav-menu-icon{padding:10px!important;border-radius:10px!important;line-height:0}'
        . '.elementor-element-5993a8b .hfe-nav-menu-icon svg{width:20px!important;height:20px!important}'
        . '}@media(max-width:767px){'
        . '.elementor-element.elementor-element-b45dfbf{padding:8px 16px!important}'
        . $row . '{gap:8px!important}'
        . '.elementor-element-4ea6b7b img{width:150px!important}'
        . $h . ' .hermon-lang a{padding:7px}'
        . '}</style>';
});

/* ------------------------------------------------------------------
 * Media page: until the Instagram account is connected, Smash Balloon prints
 * "Error: No feed with the ID 1 found" to every visitor. Show a follow button instead.
 * ------------------------------------------------------------------ */
add_filter('do_shortcode_tag', function ($output, $tag) {
    if ($tag !== 'instagram-feed' || (strpos($output, 'sb_instagram') !== false && strpos($output, 'sbi_error') === false && stripos($output, 'No feed') === false)) {
        return $output;
    }
    if (current_user_can('manage_options')) {
        return $output; // admins still see the plugin's own message and setup link
    }
    $pt = function_exists('pll_current_language') && pll_current_language() === 'pt';
    return '<div style="text-align:center;padding:30px 16px"><p style="font:400 17px/1.6 Montserrat,sans-serif;color:#333;margin:0 0 18px">'
        . ($pt ? 'Acompanhe os nossos projetos e operações no Instagram.' : 'Follow our projects and operations on Instagram.')
        . '</p><a href="https://www.instagram.com/hermongroupsa" target="_blank" rel="noopener" style="display:inline-block;background:#F26A1B;color:#fff;font:700 16px Montserrat,sans-serif;padding:15px 32px;border-radius:6px;text-decoration:none">'
        . ($pt ? 'Siga-nos no Instagram' : 'Follow us on Instagram') . ' – @hermongroupsa</a></div>';
}, 99, 2); // after Smash Balloon's own do_shortcode_tag handler, which rebuilds the output

/* ------------------------------------------------------------------
 * Structured data (Yoast schema graph)
 * ------------------------------------------------------------------ */

// Company address and contacts on the Organization node.
add_filter('wpseo_schema_organization', function ($data) {
    $data['address'] = [
        '@type' => 'PostalAddress',
        'streetAddress' => 'Av. Vladimir Lenine, Nº 174, Millenium Park Building, 1st Floor – Left',
        'addressLocality' => 'Maputo',
        'addressCountry' => 'MZ',
    ];
    $data['email'] = 'info@hermongroup.co.mz';
    $data['telephone'] = '+258865724000';
    $data['foundingDate'] = '2008';
    $data['areaServed'] = ['Mozambique', 'Southern Africa'];
    $data['contactPoint'] = [
        ['@type' => 'ContactPoint', 'contactType' => 'customer service', 'telephone' => '+258865724000', 'email' => 'info@hermongroup.co.mz', 'areaServed' => 'MZ', 'availableLanguage' => ['English', 'Portuguese']],
        ['@type' => 'ContactPoint', 'contactType' => 'sales', 'telephone' => '+258844217130', 'email' => 'commercial@hermongroup.co.mz', 'areaServed' => 'MZ', 'availableLanguage' => ['English', 'Portuguese']],
    ];
    return $data;
});

// Service node on every service page (page meta _hermon_service_type).
add_filter('wpseo_schema_graph', function ($graph, $context) {
    if (!is_singular('page')) {
        return $graph;
    }
    $type = get_post_meta(get_queried_object_id(), '_hermon_service_type', true);
    if (!$type) {
        return $graph;
    }
    $url = get_permalink();
    $graph[] = [
        '@type' => 'Service',
        '@id' => $url . '#service',
        'name' => get_the_title(),
        'serviceType' => $type,
        'description' => get_post_meta(get_queried_object_id(), '_yoast_wpseo_metadesc', true),
        'url' => $url,
        'inLanguage' => str_replace('_', '-', function_exists('pll_current_language') ? pll_current_language('locale') : get_locale()),
        'provider' => ['@id' => trailingslashit(home_url()) . '#organization'],
        'areaServed' => ['@type' => 'Country', 'name' => 'Mozambique'],
        'mainEntityOfPage' => ['@id' => $url],
    ];
    return $graph;
}, 10, 2);
