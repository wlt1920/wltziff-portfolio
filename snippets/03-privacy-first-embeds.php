<?php
/*
 * Click-to-load YouTube / Spotify embeds
 * Excerpt from the wltziff.nl WordPress theme — nothing loads from the provider (no cookies, no IP sent) until the visitor presses play; oEmbed answers are cached.
 * Not the full source: shown to illustrate how it's built.
 */

/**
 * The provider's embed code for a media link. wp_oembed_get() asks the provider over the network every time (about
 * half a second each for Spotify and YouTube), so the answer is kept: a week when it worked, an hour when it didn't.
 */
function wlt_oembed($url) {
    $key = 'wlt_oembed_' . md5($url);
    $html = get_transient($key);
    if ($html === false) {
        $html = (string) wp_oembed_get(esc_url_raw($url));
        set_transient($key, $html, $html ? WEEK_IN_SECONDS : HOUR_IN_SECONDS);
    }
    return $html;
}
/** $poster: the image behind the play button (defaults to the page's featured image). */
function wlt_embed($url, $poster = null) {
    if (!$url) { return; }
    $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
    if (!in_array($host, array('youtube.com', 'www.youtube.com', 'youtu.be', 'open.spotify.com', 'vimeo.com', 'www.vimeo.com'), true)) { return; }
    $html = wlt_oembed($url);
    if (!$html) { echo '<a class="button" href="' . esc_url($url) . '">Open media ' . wlt_arrow() . '</a>'; return; }
    // Privacy: nothing loads from the provider (no cookies, no IP sent) until the visitor presses play (main.js).
    $html = str_replace(array('www.youtube.com/embed/', 'youtube.com/embed/'), 'www.youtube-nocookie.com/embed/', $html);
    $html = preg_replace('/(src="[^"]*youtube-nocookie\.com\/embed\/[^"?]+)\?/', '$1?autoplay=1&rel=0&', $html);
    $provider = str_contains($host, 'spotify') ? 'Spotify' : (str_contains($host, 'vimeo') ? 'Vimeo' : 'YouTube');
    if ($poster === null) { $poster = is_singular() && has_post_thumbnail() ? get_the_post_thumbnail_url(null, 'large') : ''; }
    // Spotify players are built with Spotify's iFrame API (main.js) so the page knows when music plays.
    $spotify_uri = $provider === 'Spotify' && preg_match('#/(artist|track|album|playlist)/([A-Za-z0-9]{22})#', $url, $m) ? "spotify:{$m[1]}:{$m[2]}" : '';
    ?><div class="media-embed media-facade" data-media-facade<?php echo $spotify_uri ? ' data-spotify-uri="' . esc_attr($spotify_uri) . '"' : ''; ?><?php echo $poster ? ' style="--poster:url(\'' . esc_url($poster) . '\')"' : ''; ?>>
        <button type="button" class="media-facade-play" data-media-play>
            <span class="media-facade-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M8 5.5v13l11-6.5z"/></svg></span>
            <span class="media-facade-label"><?php echo $provider === 'Spotify' ? 'Load Spotify player' : 'Play video'; ?></span>
        </button>
        <p class="media-facade-note">Loads from <?php echo esc_html($provider); ?>, which may set cookies. <a href="<?php echo esc_url(wlt_page_url('cookies')); ?>">Cookie policy</a></p>
        <template><?php echo $html; // phpcs:ignore -- oEmbed HTML from the allowed providers above. ?></template>
    </div><?php
}
