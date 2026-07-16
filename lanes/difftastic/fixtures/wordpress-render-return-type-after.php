<?php

function acme_render_card(array $attributes): ?string
{
    if (empty($attributes['title'])) {
        return null;
    }

    return '<section class="wp-block-acme-card">' . esc_html($attributes['title']) . '</section>';
}
