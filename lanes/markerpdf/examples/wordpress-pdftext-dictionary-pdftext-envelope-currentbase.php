<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, string $lead, string $tail): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'blocks' => [[
            'bbox' => [72.0, 96.0, 430.0, 118.0],
            'lines' => [[
                'bbox' => [72.0, 96.0, 430.0, 118.0],
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 96.0, 210.0, 118.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'WordPress link',
                        'bbox' => [210.0, 96.0, 294.0, 118.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => 'https://example.com/import-path',
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [294.0, 96.0, 430.0, 118.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$stalePage = $page(810, 'Stale adapter pdftext page ', ' should not import.');
$selectedPage = $page(910, 'Explicit pdftext envelope ', ' wins before stale adapter pages.');

$path = sys_get_temp_dir() . '/markerpdf-pdftext-envelope-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% pdftext envelope dictionary boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            'pages' => [
                810 => $stalePage,
            ],
            'pdftext' => [
                'metadata' => [
                    'source' => 'pdftext.dictionary_output',
                    'raw_private_payload' => 'pdftext envelope metadata payload must not cross WordPress output',
                ],
                'pages' => [
                    910 => $selectedPage,
                ],
                'raw_pdftext_payload' => 'pdftext envelope payload must not cross WordPress output',
            ],
            'raw_adapter_payload' => 'stale adapter pages payload must not cross WordPress output',
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$pdftextEnvelopeSelected = str_contains($text, 'Explicit pdftext envelope')
    && str_contains($text, 'wins before stale adapter pages')
    && !str_contains($text, 'Stale adapter pdftext page');
$payloadExcluded = !str_contains($encoded, 'pdftext envelope metadata payload')
    && !str_contains($encoded, 'pdftext envelope payload')
    && !str_contains($encoded, 'stale adapter pages payload');
$safeLinkPromoted = str_contains($text, '[WordPress link](https://example.com/import-path)');

if (
    ($result['metadata']['page_range'] ?? null) !== [0]
    || ($result['metadata']['pdftext']['source_pages'] ?? null) !== 1
    || !$pdftextEnvelopeSelected
    || !$payloadExcluded
    || !$safeLinkPromoted
) {
    throw new RuntimeException('Expected explicit pdftext envelope pages to win over stale adapter pages before WordPress import.');
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

echo '<!-- markerpdf-pdftext-dictionary-pdftext-envelope-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-pdftext-envelope-currentbase',
    'source_truth' => 'markerPDF consumes the ordered page dictionaries returned by pdftext.dictionary_output before WordPress conversion; native caches that name that authoritative page list pdftext must unwrap it before stale adapter pages',
    'support_component' => 'pdf-text-dictionary-core-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'source_pages' => $result['metadata']['pdftext']['source_pages'] ?? null,
    'selected_page_count' => $result['metadata']['pdftext']['pages'] ?? null,
    'pdftext_envelope_selected' => $pdftextEnvelopeSelected,
    'safe_span_link_promoted' => $safeLinkPromoted,
    'payload_excluded' => $payloadExcluded,
    'executes_python_or_models' => false,
    'executes_ocr' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
