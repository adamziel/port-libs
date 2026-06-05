<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$coverContent = 'BT /F1 12 Tf 72 720 Td (WordPress titleless bridge current body) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (WordPress titleless bridge stale body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Checklist) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 8 0 R /A 12 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Parent 5 0 R /Prev 6 0 R /Next 9 0 R /A 13 0 R /Dest /StaleBridgeTarget >>\nendobj\n"
    . "9 0 obj\n<< /Title (Stale External Appendix) /Parent 5 0 R /Prev 8 0 R /Dest /StaleTarget /A 14 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoTo /D [3 0 R /FitH 720] >>\nendobj\n"
    . "13 0 obj\n<< /S /JavaScript /JS (app.alert\\('wordpress titleless bridge action leak'\\)) >>\nendobj\n"
    . "14 0 obj\n<< /S /GoToR /F (stale-titleless-bridge.pdf) /D (stale-titleless-dest) /NewWindow true >>\nendobj\n"
    . "20 0 obj\n<< /Names [(StaleBridgeTarget) [4 0 R /Fit] (StaleTarget) [4 0 R /XYZ 10 20 0]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($coverContent) . " >>\nstream\n{$coverContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
$lightweightMetadata = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$encodedLightweight = json_encode($lightweightMetadata, JSON_UNESCAPED_SLASHES);

if (($metadata['document_outline']['titles'] ?? []) !== ['Import Checklist']) {
    throw new RuntimeException('Expected titleless bridge to stop document outline metadata before stale appendix.');
}
if (array_column($toc, 'title') !== ['Import Checklist']) {
    throw new RuntimeException('Expected titleless bridge to stop TOC rows before stale appendix.');
}
if (array_column($navigation['outline'] ?? [], 'title') !== ['Import Checklist']) {
    throw new RuntimeException('Expected titleless bridge to stop navigation review before stale appendix.');
}
if (($lightweightMetadata['pdf_toc'] ?? []) !== [['title' => 'Import Checklist', 'level' => 1, 'page' => 0]]) {
    throw new RuntimeException('Expected lightweight outline metadata to keep the current import checklist only.');
}
if ($remoteActions !== []) {
    throw new RuntimeException('Expected stale remote outline actions behind titleless bridge to be excluded.');
}
if (!is_string($encodedMetadata) || str_contains($encodedMetadata, 'Stale External Appendix') || str_contains($encodedMetadata, 'stale-titleless-bridge.pdf')) {
    throw new RuntimeException('Expected stale titleless-bridge rows to stay out of document metadata.');
}
if (!is_string($encodedNavigation) || str_contains($encodedNavigation, 'wordpress titleless bridge action leak')) {
    throw new RuntimeException('Expected titleless bridge action payload to stay out of navigation review.');
}
if (!is_string($encodedLightweight) || str_contains($encodedLightweight, 'Stale External Appendix')) {
    throw new RuntimeException('Expected stale titleless-bridge rows to stay out of lightweight metadata.');
}
if (str_contains($plainText, 'Import Checklist')
    || str_contains($plainText, 'Stale External Appendix')
    || str_contains($plainText, 'stale-titleless-bridge.pdf')
    || str_contains($plainText, 'wordpress titleless bridge action leak')
) {
    throw new RuntimeException('Expected outline metadata and actions to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-titleless-bridge-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-titleless-bridge-boundary-currentbase',
    'support_component' => 'native-pdf-outline-title-guard',
    'native_boundary' => 'outline sibling traversal stops at matched items without /Title before stale TOC/navigation/action metadata',
    'outline_titles' => $metadata['document_outline']['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'lightweight_toc_titles' => array_column($lightweightMetadata['pdf_toc'] ?? [], 'title'),
    'stale_outline_excluded' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale External Appendix'),
    'stale_remote_action_excluded' => $remoteActions === []
        && is_string($encodedNavigation)
        && !str_contains($encodedNavigation, 'stale-titleless-bridge.pdf'),
    'titleless_action_excluded' => is_string($encodedNavigation)
        && !str_contains($encodedNavigation, 'wordpress titleless bridge action leak'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Checklist')
        && !str_contains($plainText, 'Stale External Appendix'),
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
