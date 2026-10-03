<?php
/* Excerpt from the wltziff.nl WordPress theme (inc/reviews.php) — not the full source. */

/**
 * Reviews for released projects (wLt AxisNex): a star rating, a name and a few words — no account, no email.
 * Stored as WordPress comments of type 'wlt_review' on the project, so they show up (and can be deleted) under
 * Comments in the admin. One review per visitor: a salted hash of the IP and a random browser token (both only
 * ever stored hashed) are kept with the review, and a second one from either is refused. Against spam: a hidden
 * honeypot field, a minimum time on the form, no links, length limits, WordPress' disallowed words (those go to
 * moderation instead), and a site-wide cap per hour so a flood from many addresses still stops.
 */
if (!defined('ABSPATH')) { exit; }

/** Projects that take reviews (slug => display name); keeps the REST route from being used for anything else. */
function wlt_reviews_projects() {
    return array('axisnex' => 'wLt AxisNex');
}

const WLT_REVIEW_TYPE = 'wlt_review';

function wlt_reviews_post($slug) {
    if (!isset(wlt_reviews_projects()[$slug])) { return null; }
    $post = get_page_by_path($slug, OBJECT, 'wlt_project');
    return $post && $post->post_status === 'publish' ? $post : null;
}

/** Salted hash: the raw value (IP, browser token) is never stored. */
function wlt_review_hash($value, $slug) {
    return substr(hash_hmac('sha256', $value . '|' . $slug, wp_salt('nonce')), 0, 32);
}

/** Has this visitor (same IP, or same browser token) already reviewed the project? */
function wlt_review_exists($post_id, array $hashes) {
    $hashes = array_values(array_filter($hashes));
    if (!$hashes) { return false; }
    return (bool) get_comments(array(
        'post_id' => $post_id, 'type' => WLT_REVIEW_TYPE, 'status' => 'all', 'count' => true,
        'meta_query' => array('relation' => 'OR',
            array('key' => 'wlt_voter', 'value' => $hashes, 'compare' => 'IN'),
            array('key' => 'wlt_device', 'value' => $hashes, 'compare' => 'IN'),
        ),
    ));
}

/** Published reviews, newest first, plus the average and the 5…1 star spread. Kept until a review comes in. */
function wlt_reviews_summary($post_id) {
    $key = 'wlt_reviews_' . $post_id;
    $summary = get_transient($key);
    if (is_array($summary)) { return $summary; }
    $comments = get_comments(array('post_id' => $post_id, 'type' => WLT_REVIEW_TYPE, 'status' => 'approve', 'number' => 300, 'orderby' => 'comment_date_gmt', 'order' => 'DESC'));
    $reviews = array();
    $spread = array(5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0);
    foreach ($comments as $comment) {
        $rating = max(1, min(5, (int) get_comment_meta($comment->comment_ID, 'wlt_rating', true)));
        $spread[$rating]++;
        $reviews[] = array(
            'name' => $comment->comment_author,
            'rating' => $rating,
            'text' => $comment->comment_content,
            'date' => mysql2date('c', $comment->comment_date_gmt . ' +0000', false),
            'when' => mysql2date('j M Y', $comment->comment_date, false),
        );
    }
    $count = count($reviews);
    $summary = array(
        'count' => $count,
        'average' => $count ? round(array_sum(array_column($reviews, 'rating')) / $count, 1) : 0,
        'spread' => $spread,
        'reviews' => $reviews,
    );
    set_transient($key, $summary, DAY_IN_SECONDS);
    return $summary;
}

function wlt_reviews_flush($post_id) {
    delete_transient('wlt_reviews_' . $post_id);
    // LiteSpeed keeps project pages for 15 minutes: show the new review on the page right away.
    do_action('litespeed_purge_post', $post_id);
}
// Approving, holding or deleting one in the admin changes the list too.
add_action('wp_set_comment_status', function ($comment_id) { $c = get_comment($comment_id); if ($c && $c->comment_type === WLT_REVIEW_TYPE) { wlt_reviews_flush($c->comment_post_ID); } });
add_action('deleted_comment', function ($comment_id, $c) { if ($c && $c->comment_type === WLT_REVIEW_TYPE) { wlt_reviews_flush($c->comment_post_ID); } }, 10, 2);
add_action('edit_comment', function ($comment_id) { $c = get_comment($comment_id); if ($c && $c->comment_type === WLT_REVIEW_TYPE) { wlt_reviews_flush($c->comment_post_ID); } });

add_action('rest_api_init', function () {
    register_rest_route('wlt/v1', '/reviews/(?P<slug>[a-z0-9-]+)', array(
        array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => function (WP_REST_Request $request) {
                $slug = (string) $request['slug'];
                $post = wlt_reviews_post($slug);
                if (!$post) { return new WP_Error('reviews', 'Unknown project.', array('status' => 404)); }
                $device = sanitize_key((string) $request->get_param('device'));
                $summary = wlt_reviews_summary($post->ID);
                $summary['mine'] = wlt_review_exists($post->ID, array(wlt_review_hash(wlt_visitor_ip() ?: 'local', $slug), $device ? wlt_review_hash('d:' . $device, $slug) : ''));
                return $summary;
            },
        ),
        array(
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => 'wlt_reviews_submit',
        ),
    ));
});

function wlt_reviews_submit(WP_REST_Request $request) {
    $slug = (string) $request['slug'];
    $post = wlt_reviews_post($slug);
    if (!$post) { return new WP_Error('reviews', 'Unknown project.', array('status' => 404)); }
    $fail = function ($message, $status = 400) { return new WP_Error('reviews', $message, array('status' => $status)); };

    // Bots: the hidden field is filled in, or the form is sent faster than anyone can read and type.
    if (trim((string) $request->get_param('website')) !== '' || (int) $request->get_param('elapsed') < 4000) {
        return $fail('Your review could not be sent. Please try again in a moment.');
    }
    $name = trim(preg_replace('/\s+/u', ' ', sanitize_text_field((string) $request->get_param('name'))));
    $text = trim(preg_replace("/\n{3,}/", "\n\n", sanitize_textarea_field((string) $request->get_param('text'))));
    $rating = (int) $request->get_param('rating');
    $device = sanitize_key((string) $request->get_param('device'));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 40) { return $fail('Please enter a name of 2 to 40 characters.'); }
    if ($rating < 1 || $rating > 5) { return $fail('Please choose 1 to 5 stars.'); }
    if (mb_strlen($text) < 10 || mb_strlen($text) > 1000) { return $fail('Please write between 10 and 1000 characters.'); }
    if (preg_match('~https?://|www\.|\b[a-z0-9-]+\.(com|net|org|ru|xyz|io|info|top|click|shop|link|ly)\b|<|>~i', $name . ' ' . $text)) {
        return $fail('Links aren’t allowed in reviews.');
    }

    $ip = wlt_visitor_ip();
    $voter = wlt_review_hash($ip ?: 'local', $slug);
    $device_hash = strlen($device) >= 16 ? wlt_review_hash('d:' . $device, $slug) : '';
    if (wlt_review_exists($post->ID, array($voter, $device_hash))) {
        return $fail('You’ve already reviewed ' . wlt_reviews_projects()[$slug] . ' — thank you!', 409);
    }
    // A flood from many addresses: at most 30 new reviews per hour across the site.
    $hour = 'wlt_reviews_hour_' . gmdate('YmdH');
    $sent = (int) get_transient($hour);
    if ($sent >= 30) { return $fail('Lots of reviews are coming in right now. Please try again in an hour.', 429); }
    set_transient($hour, $sent + 1, HOUR_IN_SECONDS);

    // Words from Settings → Discussion → Disallowed comment keys: kept for a look in the admin instead.
    $approved = wp_check_comment_disallowed_list($name, '', '', $text, '', '') ? 0 : 1;
    $comment_id = wp_insert_comment(array(
        'comment_post_ID' => $post->ID,
        'comment_author' => $name,
        'comment_content' => $text,
        'comment_type' => WLT_REVIEW_TYPE,
        'comment_approved' => $approved,
        'comment_author_IP' => '', // never stored; only the salted hash below
        'comment_agent' => '',
        'comment_meta' => array('wlt_rating' => $rating, 'wlt_voter' => $voter, 'wlt_device' => $device_hash),
    ));
    if (!$comment_id) { return $fail('Your review could not be saved. Please try again.', 500); }
    wlt_reviews_flush($post->ID);
    $summary = wlt_reviews_summary($post->ID);
    $summary['mine'] = true;
    $summary['held'] = !$approved;
    return $summary;
}

