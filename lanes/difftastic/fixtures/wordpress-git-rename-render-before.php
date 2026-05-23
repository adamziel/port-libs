<?php

function acme_render_legacy_card(array $attrs): string
{
    return '<section class="wp-block-acme-card is-legacy">' . esc_html($attrs['title']) . '</section>';
}
