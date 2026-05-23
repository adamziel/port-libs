<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\ConversionFinalizer;
use PortLibs\MarkerPDF\MarkerSettings;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$line = static function (string $text, string $spanId, array $bbox, string $font, float $fontSize): array {
    return [
        'bbox' => $bbox,
        'spans' => [[
            'span_id' => $spanId,
            'text' => $text,
            'font' => $font,
            'font_weight' => 400.0,
            'font_size' => $fontSize,
            'bbox' => $bbox,
        ]],
    ];
};

$bodyLines = [];
for ($index = 0; $index < 8; $index++) {
    $top = 72.0 + ($index * 16.0);
    $bodyLines[] = $line(
        'Imported handbook prose line ' . ($index + 1) . ' stays paragraph content.',
        'body_' . $index,
        [72.0, $top, 430.0, $top + 12.0],
        'Body',
        12.0
    );
}

$codeLines = [];
foreach ([
    ['// source: PDF appendix', 72.0],
    ['// target: Gutenberg code block', 72.0],
    ['// cleaner: marker.cleaners.code', 72.0],
    ['function import_pdf() {', 72.0],
    ['return true;', 86.0],
    ['}', 72.0],
    ['// done', 72.0],
] as $index => [$text, $left]) {
    $top = 230.0 + ($index * 9.0);
    $codeLines[] = $line(
        $text,
        'code_' . $index,
        [$left, $top, $left + (strlen($text) * 7.0), $top + 7.0],
        'Mono',
        8.0
    );
}

$result = (new ConversionFinalizer())->finalizePages(
    [[
        'pnum' => 0,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'blocks' => [
            [
                'block_type' => 'Text',
                'bbox' => [72.0, 72.0, 430.0, 196.0],
                'lines' => $bodyLines,
            ],
            [
                'block_type' => 'Text',
                'bbox' => [72.0, 230.0, 320.0, 296.0],
                'lines' => $codeLines,
            ],
        ],
    ]],
    [],
    new MarkerSettings(['EXTRACT_IMAGES' => false])
);

echo '<!-- markerpdf:block-stats ' . htmlspecialchars(json_encode($result['metadata']['block_stats'], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

$parts = preg_split('/```/', trim($result['text'])) ?: [];
foreach ($parts as $index => $part) {
    $part = trim($part);
    if ($part === '') {
        continue;
    }

    if ($index % 2 === 1) {
        echo "<!-- wp:code -->\n";
        echo '<pre class="wp-block-code"><code>' . htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code></pre>\n";
        echo "<!-- /wp:code -->\n\n";
        continue;
    }

    foreach (preg_split('/\n{2,}/', $part) ?: [] as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }

        echo "<!-- wp:paragraph -->\n";
        echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
        echo "<!-- /wp:paragraph -->\n\n";
    }
}
