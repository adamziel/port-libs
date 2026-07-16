<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicodeCMap = static function (array $entries): string {
    $body = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . count($entries) . " beginbfchar\n";

    foreach ($entries as $sourceHex => $text) {
        $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', (string) $text);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode page-resource generation-boundary CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceGenerationBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /SharedForm Do Q';
$parentForm = 'BT /F1 12 Tf 12 24 Td (Parent generation form leak) Tj ET';
$staleForm = 'BT /F1 12 Tf 12 24 Td (Stale generation form leak) Tj ET';
$parentCMap = $toUnicodeCMap([
    '41' => 'Parent generation font leak',
]);
$staleCMap = $toUnicodeCMap([
    '41' => 'Stale generation font leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources 12 1 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentGenerationFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($parentCMap) . " >>\nstream\n{$parentCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($parentForm) . " >>\nstream\n{$parentForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StaleGenerationFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /SharedForm 7 0 R >> >>\nendobj\n"
    . "12 0 obj\n<< /Font << /F1 8 0 R >> /XObject << /SharedForm 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];

if ($lines !== ['A'] || $plainText !== 'A') {
    throw new RuntimeException('Expected generation-mismatched page /Resources to fail closed before WordPress import.');
}

echo '<!-- markerpdf-page-resource-generation-boundary ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'generation-mismatched page /Resources references fail closed before stale resource reuse or parent inheritance',
    'generation_mismatch_fails_closed' => ($resources['status'] ?? null) === 'unresolved_or_malformed'
        && ($resources['resource_object'] ?? null) === 12
        && ($resources['resource_generation'] ?? null) === 1,
    'stale_generation_resource_excluded' => !str_contains($plainText, 'Stale generation'),
    'parent_resource_not_inherited_after_malformed_page_resource' => !str_contains($plainText, 'Parent generation'),
    'visible_text_fallback_only' => $lines === ['A'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
