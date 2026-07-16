<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', 'Stream font entry leak');
if ($encoded === false) {
    throw new RuntimeException('Unable to encode page-resource stream-entry smoke CMap.');
}

$cmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<41> <" . strtoupper(bin2hex($encoded)) . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /PageResourceEntryStreamBoundarySmokeCMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj T* /Span /P1 BDC (Property glyph text) Tj EMC ET q /ValidForm Do Q';
$formContent = 'BT /Fplain 12 Tf 12 24 Td (Valid stream-boundary form text) Tj ET';
$fontPayload = 'BT /F1 12 Tf 1 1 Td (stream font payload leak) Tj ET';
$propertyPayload = 'BT /F1 12 Tf 1 1 Td (stream property payload leak) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StreamFontEntry /Encoding /Identity-H /ToUnicode 6 0 R /Length " . strlen($fontPayload) . " >>\nstream\n{$fontPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /ActualText (Stream property ActualText leak) /Length " . strlen($propertyPayload) . " >>\nstream\n{$propertyPayload}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources << /Font << /Fplain 9 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /Properties << /P1 7 0 R >> /XObject << /ValidForm 8 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];

$flags = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'page-tree inherited Font and Properties stream-entry rejection before WordPress paragraph import',
    'stream_font_entry_rejected' => ($resources['font_names'] ?? null) === null
        && !str_contains($plainText, 'Stream font entry leak')
        && !str_contains($plainText, 'stream font payload leak'),
    'stream_property_entry_rejected' => ($resources['properties_names'] ?? null) === null
        && !str_contains($plainText, 'Stream property ActualText leak')
        && !str_contains($plainText, 'stream property payload leak'),
    'xobject_stream_still_valid' => ($resources['xobject_names'] ?? null) === ['ValidForm']
        && in_array('Valid stream-boundary form text', $lines, true),
    'visible_paragraph_count' => count($lines),
];

if (
    $flags['stream_font_entry_rejected'] !== true
    || $flags['stream_property_entry_rejected'] !== true
    || $flags['xobject_stream_still_valid'] !== true
    || $lines !== ['A', 'Property glyph text', 'Valid stream-boundary form text']
) {
    throw new RuntimeException('Expected page-resource stream-entry boundary smoke flags to pass.');
}

echo '<!-- markerpdf-page-resource-entry-stream-boundary-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
