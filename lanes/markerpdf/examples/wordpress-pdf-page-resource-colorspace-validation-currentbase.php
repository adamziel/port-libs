<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$inlineImagePayload = "\x01EI BT /F1 12 Tf 72 660 Td (ColorSpace validation payload noise) Tj ET \x02\x03";
$content = "BT /F1 12 Tf 72 720 Td (Before ColorSpace validation) Tj ET\n"
    . "BI /W 1 /H 1 /CS /GoodIndirectArray /BPC 8 ID\n"
    . $inlineImagePayload . "\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After ColorSpace validation) Tj ET";

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /ColorSpace << "
    . "/GoodName /DeviceRGB "
    . "/GoodArray [/Indexed /DeviceRGB 0 <00>] "
    . "/GoodIndirectName 11 0 R "
    . "/GoodIndirectArray 12 0 R "
    . "/BadString (ColorSpace validation string decoy) "
    . "/BadNumber 99 "
    . "/BadDictionary << /Private (ColorSpace validation dictionary decoy) >> "
    . "/BadNull null "
    . ">> >>\nendobj\n"
    . "11 0 obj\n/DeviceRGB\nendobj\n"
    . "12 0 obj\n[/CalRGB << /WhitePoint [1 1 1] >>]\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'Before ColorSpace validation',
    'After ColorSpace validation',
];
$expectedColorSpaces = ['GoodName', 'GoodArray', 'GoodIndirectName', 'GoodIndirectArray'];

if ($lines !== $expected || $plainText !== implode("\n", $expected)) {
    throw new RuntimeException('Expected inherited ColorSpace validation to preserve only visible WordPress paragraphs.');
}

if (($resources['color_space_names'] ?? null) !== $expectedColorSpaces) {
    throw new RuntimeException('Expected WordPress review metadata to keep only valid inherited ColorSpace operands.');
}

foreach (['ColorSpace validation payload noise', 'BadString', 'BadNumber', 'BadDictionary', 'BadNull'] as $forbidden) {
    if (str_contains($plainText, $forbidden)) {
        throw new RuntimeException('Invalid ColorSpace resource content leaked into WordPress import text.');
    }
}

echo '<!-- markerpdf-page-resource-colorspace-validation-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-colorspace-validation-currentbase',
    'native_boundary' => 'inherited page ColorSpace resources accept name and array operands only',
    'resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'resource_object' => $resources['resource_object'] ?? null,
    'color_space_names' => $resources['color_space_names'] ?? [],
    'invalid_color_space_entries_excluded' => true,
    'visible_paragraph_count' => count($lines),
    'inline_image_payload_excluded_from_text' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
