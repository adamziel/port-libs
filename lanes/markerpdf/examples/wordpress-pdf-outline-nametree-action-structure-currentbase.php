<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$coverText = 'BT /F1 12 Tf 72 720 Td (Cover name tree action page remains visible) Tj ET';
$targetText = 'BT /F1 12 Tf '
    . '/NavTitle << /MCID 0 >> BDC 72 720 Td (Review heading from structure) Tj EMC '
    . '/NavBody << /MCID 1 >> BDC 72 704 Td (Review body from structure) Tj EMC ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /MarkInfo << /Marked true >> /StructTreeRoot 50 0 R /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Dur 5 /Trans 16 0 R /AA << /O 15 0 R >> /Resources << /Font << /F1 7 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Collapsed Review Target) /Parent 5 0 R /Dest /ReviewStart /First 7 0 R /Last 7 0 R /Count -1 /C [0 .35 .7] /F 3 >>\nendobj\n"
    . "7 0 obj\n<< /Title (Hidden Child Row) /Parent 6 0 R /Dest [3 0 R /Fit] >>\nendobj\n"
    . "8 0 obj\n<< /Kids [10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Limits [(A) (Z)] /Names [(ReviewStart) 9 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /S /GoTo /D [4 0 R /XYZ 72 720 1] /Next [13 0 R 14 0 R] >>\nendobj\n"
    . "13 0 obj\n<< /S /URI /URI (https://example.com/outline-structure-review) >>\nendobj\n"
    . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden outline structure script'\\)) >>\nendobj\n"
    . "15 0 obj\n<< /S /URI /URI (https://example.com/page-open-name-tree-structure) >>\nendobj\n"
    . "16 0 obj\n<< /S /Split /D .5 /Dm /V /M /I /Di 270 /SS .75 /B false >>\nendobj\n"
    . "20 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Tagged ) /St 9 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetText) . " >>\nstream\n{$targetText}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /StructTreeRoot /RoleMap 60 0 R /ParentTree 55 0 R /K [52 0 R 53 0 R] >>\nendobj\n"
    . "52 0 obj\n<< /Type /StructElem /S /NavTitle /P 50 0 R /K 0 >>\nendobj\n"
    . "53 0 obj\n<< /Type /StructElem /S /NavBody /P 50 0 R /K 1 >>\nendobj\n"
    . "55 0 obj\n<< /Nums [0 [52 0 R 53 0 R]] >>\nendobj\n"
    . "60 0 obj\n<< /NavTitle /H1 /NavBody /P >>\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$actions = $navigation['outline_action_review_actions'];
$plainText = $textExtractor->extractPlainText($pdf);

if (count($actions) !== 3) {
    throw new RuntimeException('Expected GoTo, URI, and JavaScript review rows from the name-tree action dictionary.');
}
if (($actions[0]['outline_structure_state'] ?? null) !== 'collapsed' || ($actions[0]['outline_text_color_hex'] ?? null) !== '#0059b3') {
    throw new RuntimeException('Expected outline action rows to carry collapsed outline structure metadata.');
}
if (($actions[1]['destination_action_target_structure_roles'] ?? []) !== ['H1', 'P']) {
    throw new RuntimeException('Expected chained action rows to carry target StructTree role metadata.');
}
if (
    str_contains($plainText, 'Collapsed Review Target')
    || str_contains($plainText, 'ReviewStart')
    || str_contains($plainText, 'outline-structure-review')
    || str_contains($plainText, 'hidden outline structure script')
) {
    throw new RuntimeException('Expected outline/action dictionaries and artifact text to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-nametree-action-structure-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-nametree-action-structure-review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'native_boundary' => 'name-tree destination action rows carry collapsed outline structure metadata plus target StructTree context',
    'navigation_sources' => $navigation['source'],
    'outline_action_types' => array_column($actions, 'action_type'),
    'outline_action_safeties' => array_column($actions, 'safety'),
    'outline_structure_state' => $actions[0]['outline_structure_state'] ?? null,
    'outline_style_flags' => $actions[0]['outline_style_flags'] ?? null,
    'outline_color' => $actions[0]['outline_text_color_hex'] ?? null,
    'target_page_label' => $actions[0]['destination_action_target_page_label'] ?? null,
    'target_structure_roles' => $actions[0]['destination_action_target_structure_roles'] ?? [],
    'target_tagged_text' => array_column($actions[0]['destination_action_target_tagged_content'] ?? [], 'text'),
    'visible_text_excludes_outline_action_structure' => !str_contains($plainText, 'Collapsed Review Target')
        && !str_contains($plainText, 'ReviewStart')
        && !str_contains($plainText, 'outline-structure-review')
        && !str_contains($plainText, 'hidden outline structure script'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading {\"level\":1} -->\n";
echo "<h1>Review heading from structure</h1>\n";
echo "<!-- /wp:heading -->\n\n";
echo "<!-- wp:paragraph -->\n";
echo "<p>Review body from structure</p>\n";
echo "<!-- /wp:paragraph -->\n\n";
echo "<!-- wp:list -->\n<ul>\n";
foreach ($actions as $action) {
    echo '<li data-marker-outline-state="' . htmlspecialchars((string) ($action['outline_structure_state'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-target-label="' . htmlspecialchars((string) ($action['destination_action_target_page_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-action-safety="' . htmlspecialchars((string) ($action['safety'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="false">'
        . htmlspecialchars((string) ($action['action_type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
