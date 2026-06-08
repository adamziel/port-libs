<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress direct root traversal intro) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress direct root traversal appendix) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines << /Type /Outlines /First 6 0 R /Last 7 0 R 9 0 R /Count 2 >> /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Direct Root Chapter) /Dest /DirectStart /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (WordPress Direct Root Appendix) /Prev 6 0 R /Dest /DirectAppendix /Next 9 0 R /A 12 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Title (WordPress Direct Root Decoy) /Prev 7 0 R /Dest /DirectDecoy /A 13 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (wordpress-direct-root-review.pdf) /D (direct-appendix) /NewWindow true >>\nendobj\n"
    . "13 0 obj\n<< /S /GoToR /F (wordpress-direct-root-decoy.pdf) /D (direct-decoy) >>\nendobj\n"
    . "20 0 obj\n<< /Names [(DirectAppendix) [4 0 R /Fit] (DirectDecoy) [4 0 R /FitB] (DirectStart) [3 0 R /FitH 720]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
$lightweight = $textExtractor->extractOutlineMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$lightweightEncoded = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

if (($outline['item_count'] ?? null) !== 0 || ($outline['titles'] ?? []) !== []) {
    throw new RuntimeException('Expected malformed direct outline-root traversal operands to suppress document outline items.');
}
if ($toc !== [] || ($navigation['outline'] ?? []) !== [] || ($navigation['outline_action_review_actions'] ?? []) !== [] || $remoteActions !== [] || ($lightweight['pdf_toc'] ?? []) !== []) {
    throw new RuntimeException('Expected malformed direct outline-root traversal operands to suppress TOC and navigation rows.');
}
foreach ([$metadataEncoded, $navigationEncoded, $lightweightEncoded] as $encoded) {
    if (!is_string($encoded)
        || str_contains($encoded, 'WordPress Direct Root Chapter')
        || str_contains($encoded, 'WordPress Direct Root Appendix')
        || str_contains($encoded, 'WordPress Direct Root Decoy')
        || str_contains($encoded, 'wordpress-direct-root-review.pdf')
        || str_contains($encoded, 'wordpress-direct-root-decoy.pdf')
    ) {
        throw new RuntimeException('Expected malformed direct outline-root titles and actions to stay out of review metadata.');
    }
}
if ($plainText !== "WordPress direct root traversal intro\nWordPress direct root traversal appendix"
    || str_contains($plainText, 'WordPress Direct Root Chapter')
    || str_contains($plainText, 'WordPress Direct Root Decoy')
) {
    throw new RuntimeException('Expected direct outline-root metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-direct-root-traversal-operand-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-direct-root-traversal-operand-currentbase',
    'support_component' => 'native-pdf-direct-outline-root-traversal-operand-boundary',
    'native_boundary' => 'direct catalog /Outlines root First/Last/Count operands must be single values before WordPress TOC/navigation import',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'first_item_object' => $outline['first_item_object'] ?? null,
    'last_item_object' => $outline['last_item_object'] ?? null,
    'declared_visible_count' => $outline['declared_visible_count'] ?? null,
    'imported_item_count' => $outline['item_count'] ?? null,
    'toc_rows' => count($toc),
    'navigation_rows' => count($navigation['outline'] ?? []),
    'remote_action_rows' => count($remoteActions),
    'lightweight_toc_rows' => count($lightweight['pdf_toc'] ?? []),
    'malformed_direct_root_traversal_rejected' => ($outline['item_count'] ?? null) === 0
        && $toc === []
        && ($navigation['outline'] ?? []) === [],
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'WordPress Direct Root Chapter')
        && !str_contains($plainText, 'WordPress Direct Root Decoy'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

foreach (explode("\n", $plainText) as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul></ul></nav>\n<!-- /wp:navigation -->\n";
