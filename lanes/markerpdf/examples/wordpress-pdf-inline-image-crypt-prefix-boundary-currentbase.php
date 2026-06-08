<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

$runLengthLiteralEncode = static function (string $bytes): string {
    if ($bytes === '') {
        return chr(128);
    }

    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 128) {
        $chunk = substr($bytes, $offset, 128);
        $encoded .= chr(strlen($chunk) - 1) . $chunk;
    }

    return $encoded . chr(128);
};

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$dictionary = '/W 1 /H 1 /F [/Crypt /RL] /DP [<< /Name /Identity >> null]';
$payloadPrefix = $runLengthLiteralEncode('W');
$payload = $payloadPrefix
    . 'ZZ EI BT /F1 12 Tf 72 690 Td (Identity Crypt Prefix Inline Noise) Tj ET rawtail';
$content = "BT /F1 12 Tf 72 720 Td (Before Identity Crypt Prefix Inline Image) Tj ET\n"
    . "BI {$dictionary} ID {$payload}\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After Identity Crypt Prefix Inline Image) Tj ET";
$lines = $extractor->extractTextLines($pdfWithContent($content));
$plainText = implode("\n", $lines);
$review = $renderer->inlineImageReviewPlan($dictionary, $payloadPrefix, [], 1);

$payloadExcluded = $lines === [
    'Before Identity Crypt Prefix Inline Image',
    'After Identity Crypt Prefix Inline Image',
]
    && !str_contains($plainText, 'Identity Crypt Prefix Inline Noise')
    && !str_contains($plainText, 'ZZ EI')
    && !str_contains($plainText, 'rawtail');

if (
    !$payloadExcluded
    || ($review['image_filters'] ?? []) !== ['Crypt', 'RunLengthDecode']
    || ($review['image_filter_details'][0]['decode_parms']['identity'] ?? false) !== true
    || ($review['inline_image']['native_raster_decode'] ?? false) !== true
    || ($review['inline_image']['unsupported_filters'] ?? ['unexpected']) !== []
) {
    throw new RuntimeException('Inline Identity Crypt prefix boundary smoke did not exclude payload text.');
}

$metadata = [
    'source' => 'native-pdf-inline-image-crypt-prefix-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.images.extract.extract_images',
    'identity_crypt_prefix_before_native_eod' => true,
    'inline_filter_details' => $review['image_filter_details'],
    'inline_payload_excluded_from_text' => $payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-inline-image-crypt-prefix-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
