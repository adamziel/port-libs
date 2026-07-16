<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale trailer root page leak) Tj ET';
$currentIntro = 'BT /F1 12 Tf 72 720 Td (Current trailer root intro body) Tj ET';
$currentAppendix = 'BT /F1 12 Tf 72 720 Td (Current trailer root appendix body) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Info 10 0 R /PageMode /UseOutlines >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(5, '<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>');
$addObject(6, '<< /Title (Stale Root Outline) /Parent 5 0 R /Dest [3 0 R /Fit] /A 12 0 R >>');
$addObject(10, '<< /Title (Stale Info Title) /Author (Stale Metadata Team) /Keywords (stale outline metadata) >>');
$addObject(12, "<< /S /JavaScript /JS (app.alert\\('stale trailer root outline action'\\)) >>");
$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Outlines 25 0 R /Names << /Dests 28 0 R >> /PageMode /UseOutlines /PageLayout /OneColumn >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R 23 0 R] /Count 2 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Contents 30 0 R >>');
$addObject(23, '<< /Type /Page /Parent 21 0 R /Contents 31 0 R >>');
$addObject(25, '<< /Type /Outlines /First 26 0 R /Last 27 0 R /Count 2 >>');
$addObject(26, '<< /Title (Current Trailer Chapter) /Parent 25 0 R /Dest [22 0 R /XYZ 72 720 0] /Next 27 0 R /C [0 .25 .5] /F 2 >>');
$addObject(27, '<< /Title (Current Trailer Appendix) /Parent 25 0 R /Prev 26 0 R /A << /S /GoTo /D [23 0 R /FitH 640] /Next 29 0 R >> >>');
$addObject(28, '<< /Names [(CurrentAppendix) [23 0 R /FitH 640] (CurrentStart) [22 0 R /XYZ 72 720 0]] >>');
$addObject(29, '<< /S /URI /URI (https://example.com/current-trailer-outline-review) >>');
$addObject(30, "<< /Length " . strlen($currentIntro) . " >>\nstream\n{$currentIntro}\nendstream");
$addObject(31, "<< /Length " . strlen($currentAppendix) . " >>\nstream\n{$currentAppendix}\nendstream");
$addObject(40, '<< /Title (Current Trailer Info) /Author (Current Metadata Team) /Keywords (current outline metadata) /CreationDate (D:20260605010226Z) >>');

$xrefOffset = strlen($pdf);
$maxObject = 40;
$pdf .= "xref\n0 " . ($maxObject + 1) . "\n"
    . "0000000000 65535 f \n";
for ($objectNumber = 1; $objectNumber <= $maxObject; $objectNumber++) {
    $pdf .= isset($offsets[$objectNumber])
        ? sprintf("%010d 00000 n \n", $offsets[$objectNumber])
        : "0000000000 00000 f \n";
}
$pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 20 0 R /Info 40 0 R >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$lightweightMetadata = $textExtractor->extractOutlineMetadata($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedLightweight = json_encode($lightweightMetadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (array_column($toc, 'title') !== ['Current Trailer Chapter', 'Current Trailer Appendix']) {
    throw new RuntimeException('Expected current trailer /Root outline rows to own TOC metadata.');
}
if (($lightweightMetadata['document_info']['title'] ?? null) !== 'Current Trailer Info') {
    throw new RuntimeException('Expected current trailer /Info to own lightweight document info.');
}
if (($metadata['document_outline']['outline_root_object'] ?? null) !== 25) {
    throw new RuntimeException('Expected document metadata to summarize current outline root.');
}
if (!is_string($encodedMetadata) || str_contains($encodedMetadata, 'Stale Root Outline') || str_contains($encodedMetadata, 'Stale Info Title')) {
    throw new RuntimeException('Expected stale catalog and Info decoys to stay out of document metadata.');
}
if (!is_string($encodedLightweight) || str_contains($encodedLightweight, 'Stale Info Title')) {
    throw new RuntimeException('Expected stale Info decoy to stay out of lightweight metadata.');
}
if (!is_string($encodedNavigation) || str_contains($encodedNavigation, 'stale trailer root outline action')) {
    throw new RuntimeException('Expected stale outline action payload to stay out of navigation review.');
}
if (str_contains($plainText, 'Stale trailer root page leak') || str_contains($plainText, 'Stale Root Outline')) {
    throw new RuntimeException('Expected stale catalog content and outline metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-metadata-trailer-root-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-trailer-root-boundary-currentbase',
    'support_component' => 'native-pdf-current-trailer-root-info-outline-review',
    'native_boundary' => 'current trailer /Root and /Info references own outline/navigation metadata before stale lower-numbered catalog and Info decoys',
    'outline_root_object' => $metadata['document_outline']['outline_root_object'] ?? null,
    'outline_titles' => $metadata['document_outline']['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'lightweight_info_title' => $lightweightMetadata['document_info']['title'] ?? null,
    'document_info_title' => $metadata['title'] ?? null,
    'stale_catalog_excluded' => is_string($encodedNavigation) && !str_contains($encodedNavigation, 'Stale Root Outline'),
    'stale_info_excluded' => is_string($encodedLightweight) && !str_contains($encodedLightweight, 'Stale Info Title'),
    'stale_action_excluded' => is_string($encodedNavigation) && !str_contains($encodedNavigation, 'stale trailer root outline action'),
    'visible_text_excludes_stale_catalog' => !str_contains($plainText, 'Stale trailer root page leak')
        && !str_contains($plainText, 'Stale Root Outline'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($metadata['document_outline']['items'] ?? [] as $item) {
    echo '<li data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
