<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('DCTDecode missing-slot example payload must fit one deflate stored block.');
    }

    $s1 = 1;
    $s2 = 0;
    for ($index = 0; $index < $length; $index++) {
        $s1 = ($s1 + ord($bytes[$index])) % 65521;
        $s2 = ($s2 + $s1) % 65521;
    }

    return "\x78\x01"
        . "\x01"
        . pack('v', $length)
        . pack('v', (~$length) & 0xffff)
        . $bytes
        . pack('N', ($s2 << 16) | $s1);
};

$before = 'BT /F1 12 Tf 72 720 Td (Before DCT DecodeParms Review) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After DCT DecodeParms Review) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0\x00\x10JFIF\0missing-slot review bytes "
    . 'BT /F1 12 Tf 72 700 Td (Missing DCT DecodeParms slot payload leak) Tj ET'
    . "\xff\xd9";
$encodedPayload = $zlibStored($jpegPayload);
$imageDictionary = '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter [/FlateDecode /DCTDecode] /DecodeParms [<< /ColorTransform 1 >>] /Length ' . strlen($encodedPayload) . ' >>';
$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n{$imageDictionary}\nstream\n{$encodedPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? null;
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary);
$colorPlan = $renderer->dctDecodeImageColorPlan($imageDictionary, $jpegPayload);

$dctDecodeParms = is_array($entry) ? ($entry['filter_details'][1]['decode_parms'] ?? null) : null;
$planDctDecodeParms = $plan['image_filter_details'][1]['decode_parms'] ?? null;
$missingSlotReviewed = is_array($dctDecodeParms)
    && ($dctDecodeParms['decode_parms_review'] ?? null) === 'unaligned_dctdecode_decodeparms_fail_closed'
    && ($dctDecodeParms['decode_parms_alignment'] ?? null) === 'missing_filter_slot'
    && ($dctDecodeParms['valid_color_transform'] ?? true) === false
    && is_array($planDctDecodeParms)
    && ($planDctDecodeParms['decode_parms_review'] ?? null) === 'unaligned_dctdecode_decodeparms_fail_closed'
    && ($colorPlan['decode_parms_color_transform_valid'] ?? true) === false
    && ($colorPlan['decode_parms_color_transform_ignored'] ?? false) === true;
$payloadExcluded = !str_contains($plainText, 'Missing DCT DecodeParms slot payload leak')
    && !str_contains($plainText, 'JFIF');

if (
    $lines !== ['Before DCT DecodeParms Review', 'After DCT DecodeParms Review']
    || !$missingSlotReviewed
    || !$payloadExcluded
) {
    throw new RuntimeException('DCTDecode missing DecodeParms slot boundary was not reviewed fail-closed.');
}

echo '<!-- markerpdf:pdf-dctdecode-decodeparms-missing-slot-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-decodeparms-missing-slot',
    'upstream_boundary' => 'DCTDecode JPEG raster bytes stay image-only while DecodeParms arrays align by filter slot',
    'filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'decode_parms_review' => $dctDecodeParms['decode_parms_review'] ?? null,
    'decode_parms_alignment' => $dctDecodeParms['decode_parms_alignment'] ?? null,
    'dct_decodeparms_color_transform_valid' => $colorPlan['decode_parms_color_transform_valid'] ?? null,
    'dct_decodeparms_color_transform_ignored' => $colorPlan['decode_parms_color_transform_ignored'] ?? null,
    'payload_excluded_from_paragraphs' => $payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
