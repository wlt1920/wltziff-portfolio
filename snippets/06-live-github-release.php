<?php
/*
 * Live release info from GitHub
 * Excerpt from the wltziff.nl WordPress theme — the AxisNex page always shows the newest version, installer and SHA-256 (cached 6 hours, with a fallback).
 * Not the full source: shown to illustrate how it's built.
 */

const WLT_AXISNEX_REPO = 'wlt1920/AxisNex';

/** The latest release: tag, date, installer URL and size, SHA-256 (from the release notes). */
function wlt_axisnex_release() {
    $fallback = array('version' => 'V1.2.4', 'date' => '2026-10-03', 'url' => 'https://github.com/' . WLT_AXISNEX_REPO . '/releases/latest', 'size' => 112036732, 'sha' => 'D27AD4AA693E45362E5B90A3A8C8E8CF5FF2E1444FDF4B183EAEA04730D237D4', 'file' => 'AxisNex-V1.2.4-Setup.exe');
    $cached = get_transient('wlt_axisnex_release');
    if (is_array($cached)) { return $cached; }
    $release = $fallback;
    $response = wp_remote_get('https://api.github.com/repos/' . WLT_AXISNEX_REPO . '/releases/latest', array('timeout' => 4, 'headers' => array('Accept' => 'application/vnd.github+json', 'User-Agent' => 'wltziff.nl')));
    $json = is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200 ? null : json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($json['tag_name'])) {
        $release['version'] = 'V' . ltrim($json['tag_name'], 'vV');
        $release['date'] = substr((string) ($json['published_at'] ?? ''), 0, 10) ?: $release['date'];
        foreach ((array) ($json['assets'] ?? array()) as $asset) {
            if (preg_match('/setup\.exe$/i', $asset['name'] ?? '')) { $release['url'] = $asset['browser_download_url']; $release['size'] = (int) $asset['size']; $release['file'] = $asset['name']; }
        }
        $release['sha'] = preg_match('/\b([A-Fa-f0-9]{64})\b/', (string) ($json['body'] ?? ''), $m) ? strtoupper($m[1]) : '';
    }
    set_transient('wlt_axisnex_release', $release, $json ? 6 * HOUR_IN_SECONDS : 30 * MINUTE_IN_SECONDS);
    return $release;
}
