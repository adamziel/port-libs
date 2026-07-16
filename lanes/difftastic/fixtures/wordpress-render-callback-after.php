<?php

register_block_type(__DIR__ . '/build/card', [
    'render_callback' => static function (array $attributes): string {
        // Classic template fallback stays stable.
        return sprintf(
            '<section class="wp-block-port-card">%s</section>',
            wp_kses_post($attributes['title'] ?? ''),
        );
    },
]);
