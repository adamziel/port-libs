<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildFixture = static function (string $label, string $prefix): array {
    $before = "BT /F1 12 Tf 72 720 Td (Before {$label}) Tj ET";
    $after = "BT /F1 12 Tf 72 680 Td (After {$label}) Tj ET";
    $leak = "BT /F1 12 Tf 72 700 Td ({$label} WordPress leak) Tj ET";
    $segmentPayload = "{$label} APP bytes before tokenizer decoy EI {$leak} still image bytes";
    $jpegPayload = $prefix
        . "\xff\xd8"
        . "\xff\xe0" . pack('n', strlen($segmentPayload) + 2) . $segmentPayload
        . "\xff\xd9";
    $content = $before . "\n"
        . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F /DCTDecode ID\n{$jpegPayload}\nEI\n"
        . $after;

    return [
        'label' => $label,
        'dictionary' => '/W 1 /H 1 /CS /RGB /BPC 8 /F /DCTDecode',
        'payload' => $jpegPayload,
        'expected' => [
            "Before {$label}",
            "After {$label}",
        ],
        'pdf' => "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "%%EOF",
    ];
};

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$fixtures = [
    $buildFixture('NUL padded inline DCT import', "\0\0"),
    $buildFixture('BOM marker-fill inline DCT import', "\xef\xbb\xbf\xff"),
];

$paragraphs = [];
$metadata = [];
foreach ($fixtures as $fixture) {
    $lines = $extractor->extractTextLines($fixture['pdf']);
    $plainText = $extractor->extractPlainText($fixture['pdf']);
    $review = $renderer->inlineImageReviewPlan($fixture['dictionary'], $fixture['payload']);
    $payloadExcluded = !str_contains($plainText, $fixture['label'] . ' WordPress leak')
        && !str_contains($plainText, $fixture['label'] . ' APP bytes')
        && !str_contains($plainText, 'tokenizer decoy EI')
        && !str_contains($plainText, 'still image bytes');
    $reviewOnly = ($review['inline_image_review_only'] ?? null) === true
        && ($review['inline_image_payload_excluded_from_text'] ?? null) === true
        && ($review['image_filters'] ?? []) === ['DCTDecode']
        && ($review['image_filter_boundary']['preview_only_filters'] ?? []) === ['DCTDecode']
        && ($review['inline_image']['native_raster_decode'] ?? true) === false;

    if ($lines !== $fixture['expected'] || !$payloadExcluded || !$reviewOnly) {
        throw new RuntimeException('Inline padded DCTDecode SOI boundary smoke failed for ' . $fixture['label']);
    }

    array_push($paragraphs, ...$lines);
    $metadata[] = [
        'label' => $fixture['label'],
        'paragraphs' => $lines,
        'payload_length' => strlen($fixture['payload']),
        'payload_excluded_from_text' => $payloadExcluded,
        'review_only_filters' => $review['inline_image']['review_only_filters'] ?? [],
        'native_raster_decode' => $review['inline_image']['native_raster_decode'] ?? null,
    ];
}

echo '<!-- markerpdf:pdf-inline-dctdecode-padded-soi-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-inline-dctdecode-padded-soi-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks inline image tokenizer',
    'inline_dctdecode_padded_soi_boundaries' => $metadata,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($paragraphs as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
