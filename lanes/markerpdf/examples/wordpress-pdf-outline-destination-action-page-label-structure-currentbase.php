<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$coverText = 'BT /F1 12 Tf 72 720 Td (Cover outline label structure page remains visible) Tj ET';
$targetText = 'BT /F1 12 Tf '
    . '/ChapterTitle << /MCID 0 >> BDC 72 720 Td (Destination heading from structure) Tj EMC '
    . '/ChapterBody << /MCID 1 >> BDC 72 700 Td (Destination body from structure) Tj EMC ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /MarkInfo << /Marked true >> /StructTreeRoot 50 0 R /PageLabels 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 7 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Chapter action target) /Parent 5 0 R /Dest /ChapterAction /F 2 >>\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "8 0 obj\n<< /Names [(ChapterAction) 9 0 R (ChapterView) [4 0 R /FitH 680]] >>\nendobj\n"
    . "9 0 obj\n<< /S /GoTo /D /ChapterView /Next [10 0 R 11 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /S /URI /URI (https://example.com/chapter-action-review) >>\nendobj\n"
    . "11 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden label structure script'\\)) >>\nendobj\n"
    . "20 0 obj\n<< /Nums [0 << /S /r /P (front-) /St 1 >> 1 << /S /D /P (Chapter ) /St 12 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetText) . " >>\nstream\n{$targetText}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /StructTreeRoot /RoleMap 60 0 R /ParentTree 55 0 R /K [52 0 R 53 0 R] >>\nendobj\n"
    . "52 0 obj\n<< /Type /StructElem /S /ChapterTitle /P 50 0 R /K 0 >>\nendobj\n"
    . "53 0 obj\n<< /Type /StructElem /S /ChapterBody /P 50 0 R /K 1 >>\nendobj\n"
    . "55 0 obj\n<< /Nums [0 [52 0 R 53 0 R]] >>\nendobj\n"
    . "60 0 obj\n<< /ChapterTitle /H1 /ChapterBody /P >>\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$actions = $navigation['outline_action_review_actions'];
$plainText = $textExtractor->extractPlainText($pdf);

if (count($actions) !== 3) {
    throw new RuntimeException('Expected GoTo, URI, and JavaScript review rows from the destination action dictionary.');
}
if (array_column($actions, 'destination_action_target_page_label') !== ['Chapter 12', 'Chapter 12', 'Chapter 12']) {
    throw new RuntimeException('Expected destination action rows to preserve the target PageLabel.');
}
if (($actions[0]['destination_action_target_structure_mcids'] ?? []) !== [0, 1]) {
    throw new RuntimeException('Expected destination action rows to summarize target structure MCIDs.');
}
if (($actions[0]['destination_action_target_structure_raw_roles'] ?? []) !== ['ChapterTitle', 'ChapterBody']) {
    throw new RuntimeException('Expected destination action rows to summarize raw StructElem roles.');
}
if (($actions[0]['destination_action_target_structure_text'] ?? []) !== ['Destination heading from structure', 'Destination body from structure']) {
    throw new RuntimeException('Expected destination action rows to summarize tagged target text.');
}
if (
    str_contains($plainText, 'Chapter action target')
    || str_contains($plainText, 'ChapterAction')
    || str_contains($plainText, 'ChapterView')
    || str_contains($plainText, 'chapter-action-review')
    || str_contains($plainText, 'hidden label structure script')
) {
    throw new RuntimeException('Expected outline destination-action review operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-destination-action-page-label-structure-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-destination-action-page-label-structure-review',
    'native_boundary' => 'outline destination action rows expose target PageLabels and compact StructElem summaries without executing PDF actions',
    'navigation_sources' => $navigation['source'],
    'destination_action_names' => array_column($actions, 'destination_action_name'),
    'target_page_labels' => array_column($actions, 'destination_action_target_page_label'),
    'target_page_numbers' => array_column($actions, 'destination_action_target_page_number'),
    'target_structure_mcids' => $actions[0]['destination_action_target_structure_mcids'] ?? [],
    'target_structure_raw_roles' => $actions[0]['destination_action_target_structure_raw_roles'] ?? [],
    'target_structure_roles' => $actions[0]['destination_action_target_structure_roles'] ?? [],
    'target_structure_text' => $actions[0]['destination_action_target_structure_text'] ?? [],
    'visible_text_excludes_outline_action_review_operands' => !str_contains($plainText, 'ChapterAction')
        && !str_contains($plainText, 'ChapterView')
        && !str_contains($plainText, 'chapter-action-review')
        && !str_contains($plainText, 'hidden label structure script'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($actions as $action) {
    echo '<li data-marker-destination-action="' . htmlspecialchars((string) ($action['destination_action_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-target-label="' . htmlspecialchars((string) ($action['destination_action_target_page_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-target-roles="' . htmlspecialchars(implode(',', $action['destination_action_target_structure_roles'] ?? []), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-action-safety="' . htmlspecialchars((string) ($action['safety'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="false">'
        . htmlspecialchars((string) ($action['action_type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n\n";

echo "<!-- wp:heading {\"level\":1} -->\n";
echo "<h1>Destination heading from structure</h1>\n";
echo "<!-- /wp:heading -->\n\n";
echo "<!-- wp:paragraph -->\n";
echo "<p>Destination body from structure</p>\n";
echo "<!-- /wp:paragraph -->\n";
