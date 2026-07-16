<?php

/**
 * Render the modern campaign block wrapper.
 *
 * Keeps saved markup safe while reviewers migrate copy.
 */
function acme_render_campaign_card(array $attributes): string
{
    return '<section class="wp-block-acme-campaign">' . esc_html($attributes['title'] ?? '') . '</section>';
}
