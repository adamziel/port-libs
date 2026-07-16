<?php

register_block_type(__DIR__ . '/build/card', [
    'render_callback' => static function (array $attributes): string {
        // Keep saved markup stable for classic templates.
        return sprintf(
            '<section class="wp-block-port-card">%s</section>',
            esc_html($attributes['title'] ?? ''),
        );
    },
]);
