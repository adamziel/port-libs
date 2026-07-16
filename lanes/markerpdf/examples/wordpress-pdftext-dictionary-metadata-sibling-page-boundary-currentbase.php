<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, string $lead, string $url, string $tail): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'raw_page_payload' => "WordPress metadata sibling page payload {$page} must stay hidden",
        'refs' => [[
            'page' => $page,
            'idx' => 5,
            'coord' => [72.0, 120.0],
            'raw_ref_payload' => "WordPress metadata sibling ref {$page} must stay hidden",
        ]],
        'blocks' => [[
            'bbox' => [72.0, 120.0, 480.0, 136.0],
            'raw_block_payload' => "WordPress metadata sibling block {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 120.0, 480.0, 136.0],
                'raw_line_payload' => "WordPress metadata sibling line {$page} must stay hidden",
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 120.0, 190.0, 136.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'metadata boundary link',
                        'bbox' => [190.0, 120.0, 340.0, 136.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "WordPress metadata sibling span {$page} must stay hidden",
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [340.0, 120.0, 480.0, 136.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$path = sys_get_temp_dir() . '/markerpdf-pdftext-metadata-sibling-page-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% pdftext metadata sibling page-shaped boundary smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            'dictionary_output' => [
                'metadata' => $page(
                    8900,
                    'WordPress stale metadata sibling ',
                    'https://example.com/wp-metadata-stale',
                    ' should not become a paragraph.'
                ),
                'raw_payload' => $page(
                    8901,
                    'WordPress stale raw payload sibling ',
                    'https://example.com/wp-raw-payload-stale',
                    ' should not become a paragraph.'
                ),
                '8902' => $page(
                    8902,
                    'WordPress skipped cover ',
                    'https://example.com/wp-metadata-cover',
                    ' should stay outside selected range.'
                ),
                '8903' => $page(
                    8903,
                    'WordPress selected dictionary page ',
                    'https://example.com/wp-metadata-current',
                    ' remains importable.'
                ),
            ],
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-metadata-sibling-page-boundary-currentbase',
    'source_truth' => 'markerPDF consumes ordered pdftext.dictionary_output page dictionaries; adapter metadata siblings must not become selected pages even when page-shaped',
    'selected_page_range' => $result['metadata']['page_range'] ?? null,
    'source_pages' => $result['metadata']['pdftext']['source_pages'] ?? null,
    'selected_text_imported' => str_contains($text, 'WordPress selected dictionary page'),
    'selected_link_markdown' => str_contains($text, '[metadata boundary link](https://example.com/wp-metadata-current)'),
    'metadata_sibling_text_excluded' => !str_contains($text, 'WordPress stale metadata sibling'),
    'raw_payload_sibling_text_excluded' => !str_contains($text, 'WordPress stale raw payload sibling'),
    'cover_text_excluded' => !str_contains($text, 'WordPress skipped cover'),
    'adapter_payload_excluded' => !str_contains($encoded, 'WordPress metadata sibling page payload')
        && !str_contains($encoded, 'WordPress metadata sibling ref')
        && !str_contains($encoded, 'WordPress metadata sibling block')
        && !str_contains($encoded, 'WordPress metadata sibling line')
        && !str_contains($encoded, 'WordPress metadata sibling span'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $flags['selected_page_range'] !== [1]
    || $flags['source_pages'] !== 2
    || !$flags['selected_text_imported']
    || !$flags['selected_link_markdown']
    || !$flags['metadata_sibling_text_excluded']
    || !$flags['raw_payload_sibling_text_excluded']
    || !$flags['cover_text_excluded']
    || !$flags['adapter_payload_excluded']
) {
    throw new RuntimeException('Expected page-shaped pdftext metadata siblings to stay out of WordPress import: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $paragraph) {
    $paragraph = trim($paragraph);
    if ($paragraph === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-metadata-sibling-page-boundary-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
