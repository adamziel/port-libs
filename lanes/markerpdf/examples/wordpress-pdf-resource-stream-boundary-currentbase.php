<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicodeCMap = static function (string $text): string {
    $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', $text);
    if ($encoded === false) {
        throw new RuntimeException('Unable to encode resource-stream CMap text.');
    }

    return "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<41> <" . strtoupper(bin2hex($encoded)) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceStreamBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /StreamForm Do Q q /ParentForm Do Q';
$streamForm = 'BT /F1 12 Tf 12 24 Td (Stream resource form leak) Tj ET';
$parentForm = 'BT /F1 12 Tf 12 24 Td (Parent resource form leak) Tj ET';
$streamCMap = $toUnicodeCMap('Stream resource font leak');
$parentCMap = $toUnicodeCMap('Parent resource font leak');
$resourcePayload = 'BT /F1 12 Tf 1 1 Td (resource-stream payload leak) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources 12 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StreamResourceFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($streamForm) . " >>\nstream\n{$streamForm}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentResourceFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($streamCMap) . " >>\nstream\n{$streamCMap}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($parentCMap) . " >>\nstream\n{$parentCMap}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /ParentForm 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($parentForm) . " >>\nstream\n{$parentForm}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Length " . strlen($resourcePayload) . " /Font << /F1 5 0 R >> /XObject << /StreamForm 6 0 R >> >>\nstream\n{$resourcePayload}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];

if ($lines !== ['A'] || ($resources['status'] ?? null) !== 'unresolved_or_malformed') {
    throw new RuntimeException('Expected malformed page Resources stream object to fail closed before WordPress import.');
}

$flags = [
    'source' => 'native-pdf-page-resource-stream-boundary',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'Explicit page /Resources stream objects are malformed resource dictionaries; they fail closed before font maps, Form XObjects, or parent inheritance',
    'resource_status' => $resources['status'] ?? null,
    'resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'resource_object' => $resources['resource_object'] ?? null,
    'stream_resource_dictionary_promoted' => str_contains($plainText, 'Stream resource'),
    'parent_resource_inherited_after_malformed_stream' => str_contains($plainText, 'Parent resource'),
    'stream_payload_promoted' => str_contains($plainText, 'resource-stream payload leak'),
];

echo '<!-- markerpdf:pdf-resource-stream-boundary-currentbase ' . htmlspecialchars(json_encode(
    $flags,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
