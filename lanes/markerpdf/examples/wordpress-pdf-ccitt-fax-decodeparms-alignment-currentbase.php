<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\MarkerPDF\PdfTextExtractor;

$before = 'BT /F1 12 Tf 72 720 Td (Before unaligned CCITT XObject) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After unaligned CCITT XObject) Tj ET';
$faxPayload = 'BT /F1 12 Tf 72 700 Td (Unaligned WordPress CCITT Payload Noise) Tj ET';
$encodedFaxPayload = strtoupper(bin2hex($faxPayload)) . '>';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /UnalignedFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter [/ASCIIHexDecode /CCF] /DecodeParms [<< /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock false >>] /Length " . strlen($encodedFaxPayload) . " >>\nstream\n{$encodedFaxPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$decodeParms = $entry['filter_details'][1]['decode_parms'] ?? [];

$alignmentRejected = is_array($decodeParms)
    && ($decodeParms['decode_parms_review'] ?? null) === 'unaligned_ccitt_decodeparms_fail_closed'
    && ($entry['ccitt_fax_decode_boundary']['invalid_decode_parms'] ?? false) === true;

if (
    $plainText !== "Before unaligned CCITT XObject\nAfter unaligned CCITT XObject"
    || str_contains($plainText, 'Unaligned WordPress CCITT Payload Noise')
    || !$alignmentRejected
    || ($entry['decoded_with_current_filters'] ?? true) !== false
) {
    throw new RuntimeException('CCITT DecodeParms alignment smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-decodeparms-alignment-currentbase',
    'visible_text' => $plainText,
    'stream_filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'decode_parms_review' => $decodeParms['decode_parms_review'] ?? null,
    'invalid_decode_parms_fields' => $decodeParms['invalid_decode_parms_fields'] ?? [],
    'decode_parms_alignment' => $decodeParms['decode_parms_alignment'] ?? null,
    'payload_excluded_from_visible_text' => !str_contains($plainText, 'Unaligned WordPress CCITT Payload Noise'),
    'decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-decodeparms-alignment-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
