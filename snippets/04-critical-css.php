<?php
/*
 * Critical CSS on the homepage
 * Excerpt from the wltziff.nl WordPress theme — the first screen's CSS is inlined and the full stylesheet loads without blocking the first paint.
 * Not the full source: shown to illustrate how it's built.
 */

/**
 * Homepage: the CSS for the first screen (header, hero, cookie notice) is inlined and the full stylesheet loads
 * without holding up the first paint. assets/css/critical-home.css is made from main.css (tools/critical.mjs);
 * it's only used while it's at least as new as main.css, so a stale copy can never style the page wrongly.
 */
function wlt_critical_css() {
    static $css = null;
    if ($css !== null) { return $css; }
    $dir = get_template_directory() . '/assets/css/';
    $css = '';
    if (is_front_page() && file_exists($dir . 'critical-home.css') && filemtime($dir . 'critical-home.css') >= filemtime($dir . 'main.css')) {
        $css = str_replace('{{assets}}', get_template_directory_uri() . '/assets', (string) file_get_contents($dir . 'critical-home.css'));
    }
    return $css;
}
add_action('wp_head', function () {
    $css = wlt_critical_css();
    if (!$css) { return; }
    // main.js waits for the full stylesheet (WLTCss.run), since it measures the page.
    echo '<style id="wlt-critical">' . $css . "</style>\n"; // phpcs:ignore -- the theme's own CSS file.
    echo "<script>window.WLTCss={ready:false,run:null,done:function(){if(this.ready)return;this.ready=true;var r=this.run;this.run=null;if(r)r();}};</script>\n";
}, 7);
add_filter('style_loader_tag', function ($tag, $handle, $href) {
    if ($handle !== 'wlt' || !wlt_critical_css()) { return $tag; }
    $url = esc_url($href);
    return '<link rel="preload" as="style" id="wlt-css" href="' . $url . '" onload="this.onload=function(){WLTCss.done()};this.rel=\'stylesheet\'">'
        . '<noscript><link rel="stylesheet" href="' . $url . '"></noscript>' . "\n";
}, 10, 3);
