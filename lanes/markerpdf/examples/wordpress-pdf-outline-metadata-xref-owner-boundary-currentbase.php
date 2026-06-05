<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress current xref owner outline intro) Tj ET';
$targetContent = 'BT /F1 12 Tf 72 720 Td (WordPress current xref owner outline target) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>');
$addObject(4, '<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>');
$addObject(5, '<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>');
$addObject(6, '<< /Title (WordPress Current XRef Chapter) /Parent 5 0 R /Dest /CurrentStart /Next 7 0 R /C [0 .2 .6] /F 2 >>');
$addObject(7, '<< /Title (WordPress Current XRef Appendix) /Parent 5 0 R /Prev 6 0 R /A 12 0 R >>');
$addObject(12, '<< /S /GoTo /D /CurrentTarget /Next 13 0 R >>');
$addObject(13, '<< /S /URI /URI (https://example.com/current-wordpress-xref-outline-review) >>');
$addObject(20, '<< /Names [(CurrentStart) [3 0 R /FitH 720] (CurrentTarget) [4 0 R /XYZ 144 640 0]] >>');
$addObject(30, "<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream");
$addObject(31, "<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream");

$xrefOffset = strlen($pdf);
$maxObject = 31;
$pdf .= "xref\n0 " . ($maxObject + 1) . "\n"
    . "0000000000 65535 f \n";
for ($objectNumber = 1; $objectNumber <= $maxObject; $objectNumber++) {
    $pdf .= isset($offsets[$objectNumber])
        ? sprintf("%010d 00000 n \n", $offsets[$objectNumber])
        : "0000000000 00000 f \n";
}
$pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\n"
    . "5 0 obj\n<< /Type /Outlines /First 70 0 R /Last 70 0 R /Count 1 >>\nendobj\n"
    . "70 0 obj\n<< /Title (Stale WordPress XRef Outline) /Parent 5 0 R /A 71 0 R >>\nendobj\n"
    . "71 0 obj\n<< /S /JavaScript /JS (app.alert\\('stale wordpress xref outline action'\\)) >>\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($outline['titles'] ?? []) !== ['WordPress Current XRef Chapter', 'WordPress Current XRef Appendix']) {
    throw new RuntimeException('Expected xref-selected outline metadata titles.');
}
if (array_column($toc, 'title') !== ['WordPress Current XRef Chapter', 'WordPress Current XRef Appendix']) {
    throw new RuntimeException('Expected xref-selected TOC titles.');
}
if (array_column($navigation['outline'] ?? [], 'title') !== ['WordPress Current XRef Chapter', 'WordPress Current XRef Appendix']) {
    throw new RuntimeException('Expected xref-selected navigation titles.');
}
if (($outline['items'][0]['text_color_hex'] ?? null) !== '#003399') {
    throw new RuntimeException('Expected current outline text color review metadata.');
}
if (!is_string($encoded) || str_contains($encoded, 'Stale WordPress XRef Outline')) {
    throw new RuntimeException('Expected stale unindexed outline metadata to be excluded.');
}
if (!is_string($navigationEncoded) || str_contains($navigationEncoded, 'stale wordpress xref outline action')) {
    throw new RuntimeException('Expected stale unindexed outline action metadata to be excluded.');
}
if (str_contains($plainText, 'WordPress Current XRef Chapter') || str_contains($plainText, 'Stale WordPress XRef Outline')) {
    throw new RuntimeException('Expected outline titles to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-metadata-xref-owner-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-xref-owner-boundary-currentbase',
    'support_component' => 'native-pdf-classic-xref-outline-owner-review',
    'native_boundary' => 'classic xref-selected outline objects own WordPress TOC/navigation metadata before unindexed duplicate outline objects',
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'outline_text_colors' => array_values(array_filter(array_map(
        static fn (array $item): ?string => $item['text_color_hex'] ?? null,
        $outline['items'] ?? []
    ))),
    'resolved_destination_count' => $outline['resolved_destination_count'] ?? null,
    'stale_unindexed_outline_excluded' => is_string($encoded) && !str_contains($encoded, 'Stale WordPress XRef Outline'),
    'stale_unindexed_action_excluded' => is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale wordpress xref outline action'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'WordPress Current XRef Chapter')
        && !str_contains($plainText, 'Stale WordPress XRef Outline'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-object="' . (int) ($item['outline_object'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-color="' . htmlspecialchars((string) ($item['text_color_hex'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
