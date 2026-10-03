<?php
/* Excerpt from the wltziff.nl WordPress theme (inc/spotify.php) — not the full source.
   The API keys live in the WordPress database (Settings → Spotify stats), never in the code. */

/**
 * Live MOOD99 stats from the official Spotify Web API (Client Credentials flow, free developer app).
 * Keys go in Settings → Spotify stats. Data is cached for 1 hour; if the keys are missing or Spotify
 * does not answer, the thank-you block simply shows without numbers.
 * Not available through the API: monthly listeners and listener cities (Spotify for Artists only);
 * the top locations are therefore entered by hand in wlt_mood99_top_locations() (inc/soundcheck.php).
 */

function wlt_spotify_artist_id() {
    $url = wlt_setting('spotify', 'https://open.spotify.com/artist/3DY0u50CX8RHq5NY0mkvey');
    return preg_match('#artist/([A-Za-z0-9]{22})#', $url, $m) ? $m[1] : '';
}

/** Spotify API GET with an app token (cached ~1 hour). Returns decoded JSON or null. */
function wlt_spotify_get($path) {
    $id = get_option('wlt_spotify_client_id');
    $secret = get_option('wlt_spotify_client_secret');
    if (!$id || !$secret) { return null; }
    $token = get_transient('wlt_spotify_token');
    if (!$token) {
        $response = wp_remote_post('https://accounts.spotify.com/api/token', array(
            'timeout' => 8,
            'headers' => array('Authorization' => 'Basic ' . base64_encode($id . ':' . $secret)),
            'body' => array('grant_type' => 'client_credentials'),
        ));
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (empty($data['access_token'])) { return null; }
        $token = $data['access_token'];
        set_transient('wlt_spotify_token', $token, max(60, (int) ($data['expires_in'] ?? 3600) - 120));
    }
    $response = wp_remote_get('https://api.spotify.com/v1/' . ltrim($path, '/'), array('timeout' => 8, 'headers' => array('Authorization' => 'Bearer ' . $token)));
    if (wp_remote_retrieve_response_code($response) !== 200) { return null; }
    return json_decode((string) wp_remote_retrieve_body($response), true);
}
