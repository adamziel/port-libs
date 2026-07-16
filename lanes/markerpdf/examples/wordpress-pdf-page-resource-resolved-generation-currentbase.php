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
            throw new RuntimeException('Unable to encode page-resource resolved-generation smoke CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceResolvedGenerationCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /SharedForm Do Q';
$staleForm = 'BT /F1 12 Tf 12 24 Td (Stale resource generation form leak) Tj ET';
$currentForm = 'BT /F1 12 Tf 12 24 Td (Current resource generation form text) Tj ET';
$staleCMap = $toUnicodeCMap([
    '41' => 'Stale resource generation font leak',
]);
$currentCMap = $toUnicodeCMap([
    '41' => 'Current resource generation font text',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 2 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StaleResourceGeneration /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CurrentResourceGeneration /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /SharedForm 7 0 R >> >>\nendobj\n"
    . "10 2 obj\n<< /Font << /F1 8 0 R >> /XObject << /SharedForm 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expectedLines = [
    'Current resource generation font text',
    'Current resource generation form text',
];

$flags = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'inherited page /Resources exact generation provenance before WordPress paragraph rendering',
    'resolved_resource_generation_reported' => ($resources['status'] ?? null) === 'resolved'
        && ($resources['resource_owner_object'] ?? null) === 2
        && ($resources['resource_object'] ?? null) === 10
        && ($resources['resource_generation'] ?? null) === 2,
    'current_generation_resources_selected' => $lines === $expectedLines,
    'stale_resource_generation_excluded' => !str_contains($plainText, 'Stale resource generation'),
    'visible_paragraph_count' => count($lines),
];

if (
    $flags['resolved_resource_generation_reported'] !== true
    || $flags['current_generation_resources_selected'] !== true
    || $flags['stale_resource_generation_excluded'] !== true
) {
    throw new RuntimeException('Expected resolved page-resource generation smoke flags to pass.');
}

echo '<!-- markerpdf-page-resource-resolved-generation-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
