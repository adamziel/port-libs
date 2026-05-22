<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\ConversionFinalizer;
use PortLibs\MarkerPDF\MarkerSettings;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$line = static function (string $text, string $spanId, array $bbox, string $font = 'Body', float $weight = 400.0): array {
    return [
        'bbox' => $bbox,
        'spans' => [[
            'span_id' => $spanId,
            'text' => $text,
            'font' => $font,
            'font_weight' => $weight,
            'font_size' => 12.0,
            'bbox' => $bbox,
        ]],
    ];
};

$pages = [[
    'pnum' => 0,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'images' => [['bytes' => 'png-bytes', 'alt' => 'Reviewed chart crop']],
    'blocks' => [
        [
            'block_type' => 'Page-header',
            'bbox' => [72.0, 24.0, 260.0, 36.0],
            'lines' => [$line('Confidential migration packet', 'header_0', [72.0, 24.0, 260.0, 36.0])],
        ],
        [
            'block_type' => 'Title',
            'bbox' => [72.0, 54.0, 280.0, 78.0],
            'lines' => [$line('data liberation checklist', 'title_0', [72.0, 54.0, 280.0, 78.0], 'Heading-Bold', 700.0)],
        ],
        [
            'block_type' => 'Text',
            'bbox' => [72.0, 112.0, 430.0, 124.0],
            'lines' => [[
                'bbox' => [72.0, 112.0, 430.0, 124.0],
                'spans' => [
                    ['span_id' => 'body_0', 'text' => 'Imported ', 'font' => 'Body', 'font_weight' => 400.0, 'font_size' => 12.0, 'bbox' => [72.0, 112.0, 126.0, 124.0]],
                    ['span_id' => 'body_1', 'text' => 'review notes', 'font' => 'Body-Bold', 'font_weight' => 700.0, 'font_size' => 12.0, 'bbox' => [126.0, 112.0, 210.0, 124.0]],
                    ['span_id' => 'body_2', 'text' => ' stay block-ready.', 'font' => 'Body', 'font_weight' => 400.0, 'font_size' => 12.0, 'bbox' => [210.0, 112.0, 340.0, 124.0]],
                ],
            ]],
        ],
        [
            'block_type' => 'List-item',
            'bbox' => [90.0, 150.0, 260.0, 162.0],
            'lines' => [$line('• Confirm media crops', 'bullet_0', [90.0, 150.0, 260.0, 162.0])],
        ],
    ],
]];

$result = (new ConversionFinalizer())->finalizePages(
    $pages,
    ['header_0'],
    new MarkerSettings(['EXTRACT_IMAGES' => true])
);

echo '<!-- markerpdf:computed-toc ' . htmlspecialchars(json_encode($result['metadata']['computed_toc'], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo '<!-- markerpdf:images ' . htmlspecialchars(json_encode(array_keys($result['images']), JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach (preg_split('/\n{2,}/', trim($result['text'])) ?: [] as $chunk) {
    $chunk = trim($chunk);
    if ($chunk === '') {
        continue;
    }

    if (str_starts_with($chunk, '#')) {
        $level = max(1, min(6, strspn($chunk, '#')));
        $text = trim(ltrim($chunk, '# '));
        echo '<!-- wp:heading {"level":' . $level . '} -->' . "\n";
        echo '<h' . $level . '>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h' . $level . ">\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    if (str_starts_with($chunk, '- ')) {
        echo "<!-- wp:list -->\n<ul>\n";
        foreach (preg_split('/\R+/', $chunk) ?: [] as $line) {
            $item = preg_replace('/^\s*-\s*/', '', $line) ?? $line;
            echo '<li>' . htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
        }
        echo "</ul>\n<!-- /wp:list -->\n\n";
        continue;
    }

    $html = htmlspecialchars($chunk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html) ?? $html;
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . $html . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
