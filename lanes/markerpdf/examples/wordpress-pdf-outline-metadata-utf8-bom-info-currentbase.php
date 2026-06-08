<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$hexText = static fn (string $text): string => '<' . strtoupper(bin2hex("\xEF\xBB\xBF" . $text)) . '>';

$body = 'BT /F1 12 Tf 72 720 Td (UTF8 BOM outline metadata body) Tj ET';
$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title " . $hexText('UTF-8 BOM Outline Start') . " /Parent 5 0 R /Dest [3 0 R /FitH 720] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($body) . " >>\nstream\n{$body}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Title " . $hexText('UTF-8 BOM Info Title') . " /Author " . $hexText('UTF-8 BOM Metadata Team') . " /Keywords " . $hexText('UTF-8 BOM outline metadata') . " >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 40 0 R >>\n%%EOF";

$textExtractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$lightweight = $textExtractor->extractOutlineMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if (($lightweight['document_info']['title'] ?? null) !== 'UTF-8 BOM Info Title') {
    throw new RuntimeException('Expected lightweight trailer Info title to decode UTF-8 BOM.');
}
if (($lightweight['document_info']['author'] ?? null) !== 'UTF-8 BOM Metadata Team') {
    throw new RuntimeException('Expected lightweight trailer Info author to decode UTF-8 BOM.');
}
if (array_column($toc, 'title') !== ['UTF-8 BOM Outline Start']) {
    throw new RuntimeException('Expected outline title to decode UTF-8 BOM.');
}
if (!is_string($encodedLightweight) || str_contains($encodedLightweight, 'ï»¿')) {
    throw new RuntimeException('Expected lightweight review JSON to exclude mojibake BOM text.');
}
if (!is_string($encodedMetadata) || str_contains($encodedMetadata, 'ï»¿')) {
    throw new RuntimeException('Expected document metadata JSON to exclude mojibake BOM text.');
}
foreach (['UTF-8 BOM Outline Start', 'UTF-8 BOM Info Title', 'UTF-8 BOM Metadata Team', 'ï»¿'] as $hidden) {
    if (str_contains($plainText, $hidden)) {
        throw new RuntimeException('Expected outline and Info metadata to stay out of visible WordPress text.');
    }
}

echo '<!-- markerpdf-outline-metadata-utf8-bom-info-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-utf8-bom-info-currentbase',
    'support_component' => 'native-pdf-text-string-decoder',
    'native_boundary' => 'UTF-8 BOM PDF text strings in outline metadata and trailer Info decode before WordPress review JSON',
    'outline_titles' => array_column($toc, 'title'),
    'lightweight_info_title' => $lightweight['document_info']['title'] ?? null,
    'lightweight_info_author' => $lightweight['document_info']['author'] ?? null,
    'document_metadata_title' => $metadata['title'] ?? null,
    'bom_mojibake_excluded' => is_string($encodedLightweight)
        && !str_contains($encodedLightweight, 'ï»¿')
        && is_string($encodedMetadata)
        && !str_contains($encodedMetadata, 'ï»¿'),
    'visible_text_excludes_metadata' => !str_contains($plainText, 'UTF-8 BOM Outline Start')
        && !str_contains($plainText, 'UTF-8 BOM Info Title'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
