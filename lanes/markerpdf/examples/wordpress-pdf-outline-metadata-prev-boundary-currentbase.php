<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$coverContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline prev boundary cover) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline prev boundary appendix) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Prev Boundary Chapter) /Parent 5 0 R /Dest /ImportChapter /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Stale WordPress Remote Outline) /Parent 5 0 R /Prev 99 0 R /A 12 0 R /Next 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Title (Untrusted WordPress Tail Outline) /Parent 5 0 R /Prev 7 0 R /Dest /AppendixTarget >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (stale-wordpress-prev-outline.pdf) /D (stale-wordpress-target) /NewWindow true >>\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ImportChapter) [3 0 R /FitH 720]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($coverContent) . " >>\nstream\n{$coverContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($outline['titles'] ?? []) !== ['Import Prev Boundary Chapter']) {
    throw new RuntimeException('Expected bad /Prev outline sibling to be excluded from document metadata.');
}
if (array_column($toc, 'title') !== ['Import Prev Boundary Chapter']) {
    throw new RuntimeException('Expected bad /Prev outline sibling to be excluded from TOC metadata.');
}
if (array_column($navigation['outline'] ?? [], 'title') !== ['Import Prev Boundary Chapter']) {
    throw new RuntimeException('Expected bad /Prev outline sibling to be excluded from navigation review.');
}
if ($remoteActions !== []) {
    throw new RuntimeException('Expected bad /Prev remote outline action to be excluded from review rows.');
}
if (($outline['item_count'] ?? null) !== 1 || ($outline['resolved_destination_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected only the current outline row to be counted.');
}
if (!is_string($encoded) || str_contains($encoded, 'Stale WordPress Remote Outline') || str_contains($encoded, 'stale-wordpress-prev-outline.pdf')) {
    throw new RuntimeException('Expected stale outline action metadata to stay out of document metadata.');
}
if (!is_string($navigationEncoded) || str_contains($navigationEncoded, 'Untrusted WordPress Tail Outline')) {
    throw new RuntimeException('Expected untrusted tail outline to stay out of navigation metadata.');
}
if (str_contains($plainText, 'Import Prev Boundary Chapter')
    || str_contains($plainText, 'Stale WordPress Remote Outline')
    || str_contains($plainText, 'Untrusted WordPress Tail Outline')
    || str_contains($plainText, 'stale-wordpress-prev-outline.pdf')
) {
    throw new RuntimeException('Expected outline metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-metadata-prev-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-prev-boundary-currentbase',
    'support_component' => 'native-pdf-catalog-outline-metadata-review',
    'native_boundary' => 'explicit mismatched outline /Prev backlinks stop /Next traversal before stale same-parent action metadata',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'declared_visible_count' => $outline['declared_visible_count'] ?? null,
    'imported_item_count' => $outline['item_count'] ?? null,
    'outline_titles' => $outline['titles'] ?? [],
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'toc_titles' => array_column($toc, 'title'),
    'remote_action_count' => count($remoteActions),
    'stale_outline_excluded' => is_string($encoded) && !str_contains($encoded, 'Stale WordPress Remote Outline'),
    'stale_remote_action_excluded' => is_string($encoded) && !str_contains($encoded, 'stale-wordpress-prev-outline.pdf'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Prev Boundary Chapter')
        && !str_contains($plainText, 'Stale WordPress Remote Outline'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-next-object="' . htmlspecialchars((string) ($item['next_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
