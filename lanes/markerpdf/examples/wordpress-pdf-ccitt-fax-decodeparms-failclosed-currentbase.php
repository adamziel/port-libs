<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = "BT /F1 12 Tf 72 720 Td (Before invalid CCITT review) Tj ET\n"
    . "q 20 0 0 2 72 700 cm /BadFax Do Q\n"
    . "q 10 0 0 1 72 680 cm /BadAlias Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (After invalid CCITT review) Tj ET';
$faxPayload = 'BT /F1 12 Tf 72 720 Td (Invalid WordPress CCITT Fax Payload Noise) Tj ET';
$aliasPayload = strtoupper(bin2hex('BT /F1 12 Tf 72 700 Td (Invalid WordPress CCF Alias Payload Noise) Tj ET')) . '>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /BadFax 5 0 R /BadAlias 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 20 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K /TwoD /Columns -4 /Rows 99 0 R /BlackIs1 /Maybe /EndOfBlock true /DamagedRowsBeforeError -1 >> /Length " . strlen($faxPayload) . " >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 10 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 1 /Filter [/ASCIIHexDecode /CCF] /DecodeParms [null << /Columns /Wide /Rows -1 /EndOfLine /No >>] /Length " . strlen($aliasPayload) . " >>\nstream\n{$aliasPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$faxParms = $review['entries'][0]['filter_details'][0]['decode_parms'] ?? [];
$aliasParms = $review['entries'][1]['filter_details'][1]['decode_parms'] ?? [];

if (
    ($faxParms['valid_decode_parms'] ?? null) !== false
    || ($aliasParms['valid_decode_parms'] ?? null) !== false
    || !in_array('columns', $faxParms['invalid_decode_parms_fields'] ?? [], true)
    || !in_array('end_of_line', $aliasParms['invalid_decode_parms_fields'] ?? [], true)
    || str_contains($plainText, 'Invalid WordPress CCITT Fax Payload Noise')
    || str_contains($plainText, 'Invalid WordPress CCF Alias Payload Noise')
) {
    throw new RuntimeException('CCITT DecodeParms fail-closed smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-decodeparms-failclosed-currentbase',
    'upstream_boundary' => 'pdftext/PDFium text extraction plus marker.pdf.images.render_image image handoff',
    'preview_only_filters' => [
        $review['entries'][0]['preview_only_filters'] ?? [],
        $review['entries'][1]['preview_only_filters'] ?? [],
    ],
    'ccitt_valid_decode_parms' => $faxParms['valid_decode_parms'] ?? null,
    'ccitt_invalid_fields' => $faxParms['invalid_decode_parms_fields'] ?? [],
    'ccf_valid_decode_parms' => $aliasParms['valid_decode_parms'] ?? null,
    'ccf_invalid_fields' => $aliasParms['invalid_decode_parms_fields'] ?? [],
    'payload_in_visible_text' => false,
    'native_raster_decode' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-decodeparms-failclosed-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
