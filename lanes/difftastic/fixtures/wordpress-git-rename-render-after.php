<?php

function acme_render_modern_card(array $attrs): string
{
    return '<section class="wp-block-acme-card is-modern">' . wp_kses_post($attrs['title']) . '</section>';
}
