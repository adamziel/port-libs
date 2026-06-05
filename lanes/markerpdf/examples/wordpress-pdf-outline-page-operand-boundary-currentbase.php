<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline page operand boundary intro body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline page operand boundary appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 9 0 R /Count 4 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Page Operand Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Import Invalid Numeric Dest Page Operand) /Parent 5 0 R /Prev 6 0 R /Dest [99 /FitH 640] /Next 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Title (Import Invalid Numeric Action Page Operand) /Parent 5 0 R /Prev 7 0 R /A 12 0 R /Next 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Title (Import Page Operand Appendix) /Parent 5 0 R /Prev 8 0 R /Dest /AppendixTarget >>\nendobj\n"
    . "12 0 obj\n<< /S /GoTo /D [88 /FitR 10 20 300 700] >>\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$outline = $metadata['document_outline'] ?? [];
$items = $outline['items'] ?? [];
$tocTitles = array_column($toc, 'title');
$navigationTitles = array_column($navigation['outline'] ?? [], 'title');
$actionRows = $navigation['outline_action_review_actions'] ?? [];
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$expectedNavigationTitles = ['Import Page Operand Chapter', 'Import Page Operand Appendix'];

if ($tocTitles !== $expectedNavigationTitles || $navigationTitles !== $expectedNavigationTitles) {
    throw new RuntimeException('Expected only in-range outline destinations in WordPress navigation rows.');
}
if (($outline['resolved_destination_count'] ?? null) !== 2 || ($outline['unresolved_destination_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected invalid numeric page operands to remain unresolved document metadata.');
}
if (array_column($items, 'destination_resolved') !== [true, false, false, true]) {
    throw new RuntimeException('Expected invalid numeric page operands to stay unpromoted.');
}
if (array_column($actionRows, 'safety') !== ['unsupported-action-review']) {
    throw new RuntimeException('Expected invalid local GoTo action to remain a non-executing review row.');
}
if (!is_string($encodedNavigation) || str_contains($encodedNavigation, '"page":99') || str_contains($encodedNavigation, '"page":88')) {
    throw new RuntimeException('Expected invalid numeric page indexes to stay out of navigation review pages.');
}
if (str_contains($plainText, 'Import Page Operand Chapter')
    || str_contains($plainText, 'Import Invalid Numeric Dest Page Operand')
    || str_contains($plainText, 'Import Invalid Numeric Action Page Operand')
    || str_contains($plainText, 'Import Page Operand Appendix')
) {
    throw new RuntimeException('Expected outline metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-page-operand-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-page-operand-boundary-currentbase',
    'support_component' => 'native-pdf-outline-page-operand-boundary-review',
    'native_boundary' => 'local numeric outline page operands must resolve inside the document page count before TOC/navigation promotion',
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => $tocTitles,
    'navigation_titles' => $navigationTitles,
    'outline_objects' => array_column($items, 'outline_object'),
    'destination_resolved' => array_column($items, 'destination_resolved'),
    'resolved_destination_count' => $outline['resolved_destination_count'] ?? null,
    'unresolved_destination_count' => $outline['unresolved_destination_count'] ?? null,
    'invalid_dest_page_operand_unpromoted' => !is_string($encodedNavigation) || !str_contains($encodedNavigation, '"page":99'),
    'invalid_action_page_operand_unpromoted' => !is_string($encodedNavigation) || !str_contains($encodedNavigation, '"page":88'),
    'invalid_action_review_safety' => array_column($actionRows, 'safety'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Page Operand Chapter')
        && !str_contains($plainText, 'Import Invalid Numeric Dest Page Operand')
        && !str_contains($plainText, 'Import Invalid Numeric Action Page Operand')
        && !str_contains($plainText, 'Import Page Operand Appendix'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline page-operand-boundary review\"><ul>\n";
foreach ($navigation['outline'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-page="' . (int) ($item['page'] ?? 0)
        . '" data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
