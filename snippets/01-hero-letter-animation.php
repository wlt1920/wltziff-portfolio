<?php
/*
 * Hero markup: each letter is a span with its index
 * Excerpt from the wltziff.nl WordPress theme — used by front-page.php.
 * Not the full source: shown to illustrate how it's built.
 */

// Hero: the headline comes in letter by letter, and the word after "I design & build" changes every few seconds,
// letter by letter too (main.js). Each letter is a span with its index (--i) for the stagger.
$hero_words = array('websites.', 'web apps.', 'interfaces.', 'online stores.', 'WordPress sites.');
$hero_chars = function ($text) {
    $html = '';
    foreach (mb_str_split($text) as $n => $char) { $html .= $char === ' ' ? ' ' : '<span class="hx-ch" style="--i:' . $n . '">' . esc_html($char) . '</span>'; }
    return $html;
};

// In the template:
// <span class="hx-line hx-intro" aria-hidden="true"><?php echo $hero_chars('I design & build'); ?></span>
// <span class="hx-line hx-rotate" aria-hidden="true" data-hx-words><?php foreach ($hero_words as $n => $word) { echo $hero_word($word, $n); } ?></span>
// (A screen-reader-text span carries the full sentence, so assistive tech reads it once, normally.)
