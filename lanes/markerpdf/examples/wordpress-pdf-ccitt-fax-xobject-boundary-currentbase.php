<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = "BT /F1 12 Tf 72 720 Td (Before CCITT Review) Tj ET\n"
    . "q 172.8 0 0 0.2 72 700 cm /Fax#20Scan Do Q\n"
    . "q 16 0 0 1 72 680 cm /AliasFax Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (After CCITT Review) Tj ET';
$faxPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Fax Payload Noise) Tj ET';
$aliasPayload = 'BT /F1 12 Tf 72 700 Td (WordPress Alias Fax Noise) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Fax#20Scan 5 0 R /AliasFax 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1728 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms 8 0 R /Decode [1 0] /Length " . strlen($faxPayload) . " >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 1 /Filter [null /CCF] /DecodeParms [null << /K 0 /Columns 16 /Rows 1 /BlackIs1 false /EndOfLine false /EndOfBlock true >>] /Length " . strlen($aliasPayload) . " >>\nstream\n{$aliasPayload}\nendstream\nendobj\n"
    . "8 0 obj\n<< /K -1 /Columns 1728 /Rows 2 /BlackIs1 true /EncodedByteAlign true /EndOfLine true /EndOfBlock false /DamagedRowsBeforeError 3 >>\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['entries'][0]['filter_details'][0]['decode_parms']['k'] ?? null) !== -1
    || ($review['entries'][1]['filter_details'][0]['filter'] ?? null) !== 'CCF'
    || str_contains($plainText, 'WordPress Fax Payload Noise')
    || str_contains($plainText, 'WordPress Alias Fax Noise')
) {
    throw new RuntimeException('CCITT Fax image XObject boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-xobject-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image RGB review handoff',
    'image_xobject_count' => $review['image_xobject_count'],
    'preview_only_filters' => [
        $review['entries'][0]['preview_only_filters'] ?? [],
        $review['entries'][1]['preview_only_filters'] ?? [],
    ],
    'ccitt_decode_parms' => $review['entries'][0]['filter_details'][0]['decode_parms'] ?? null,
    'ccf_decode_parms' => $review['entries'][1]['filter_details'][0]['decode_parms'] ?? null,
    'payload_in_visible_text' => false,
    'native_raster_decode' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-xobject-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
