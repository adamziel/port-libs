<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$coverText = 'BT /F1 12 Tf 72 720 Td (Cover outline structure page remains visible) Tj ET';
$targetText = 'BT /F1 12 Tf 72 720 Td (Target outline page remains visible) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 6 /Trans 16 0 R /AA << /O 15 0 R >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 9 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Migration Plan) /Parent 5 0 R /Dest /PlanTarget /Next 9 0 R /First 7 0 R /Last 7 0 R /Count -1 /C [0 .2 .8] /F 3 >>\nendobj\n"
    . "7 0 obj\n<< /Title (Implementation Detail) /Parent 6 0 R /Dest [4 0 R /FitH 620] /Count 0 /C [0.5 0.25 0] /F 1 >>\nendobj\n"
    . "8 0 obj\n<< /Names [(PlanTarget) [4 0 R /XYZ 90 700 0]] >>\nendobj\n"
    . "9 0 obj\n<< /Title (Action Plan Target) /Parent 5 0 R /Prev 6 0 R /A << /S /GoTo /D /PlanTarget >> /F 2 >>\nendobj\n"
    . "15 0 obj\n<< /S /URI /URI (https://example.com/page-open-outline-structure) >>\nendobj\n"
    . "16 0 obj\n<< /S /Split /D .5 /Dm /V /M /I /Di 270 /SS .75 /B false >>\nendobj\n"
    . "20 0 obj\n<< /Nums [0 << /S /r /P (cover-) /St 1 >> 1 << /S /D /P (Section ) /St 3 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetText) . " >>\nstream\n{$targetText}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$outlineRows = $outlineExtractor->getOutlineStructureDestinationPageContext($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

if (count($outlineRows) !== 3) {
    throw new RuntimeException('Expected three outline structure rows.');
}
if (($outlineRows[0]['structure_state'] ?? null) !== 'collapsed' || ($outlineRows[0]['text_color_hex'] ?? null) !== '#0033cc') {
    throw new RuntimeException('Expected collapsed styled outline parent metadata.');
}
if (($outlineRows[0]['target_page_transition']['style'] ?? null) !== 'Split') {
    throw new RuntimeException('Expected outline destination rows to carry target page transition context.');
}
if (($navigation['outline'][0]['outline_object'] ?? null) !== 6 || ($navigation['outline'][0]['structure_state'] ?? null) !== 'collapsed') {
    throw new RuntimeException('Expected composite navigation rows to include outline structure metadata.');
}
if (str_contains($plainText, 'Migration Plan') || str_contains($plainText, 'PlanTarget') || str_contains($plainText, 'page-open-outline-structure')) {
    throw new RuntimeException('Expected outline dictionaries and page actions to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-structure-destination-page-context-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-structure-destination-page-context-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'outline dictionary Count/F/C structure is exposed with resolved destination page labels, transitions, and review-only page actions',
    'outline_titles' => array_column($outlineRows, 'title'),
    'outline_objects' => array_column($outlineRows, 'outline_object'),
    'outline_states' => array_column($outlineRows, 'structure_state'),
    'outline_counts' => array_column($outlineRows, 'outline_count'),
    'outline_style_flags' => array_column($outlineRows, 'style_flags'),
    'outline_text_colors' => array_map(
        static fn (array $row): ?string => $row['text_color_hex'] ?? null,
        $outlineRows
    ),
    'target_page_labels' => array_column($outlineRows, 'page_label'),
    'target_transition_styles' => array_map(
        static fn (array $row): ?string => $row['target_page_transition']['style'] ?? null,
        $outlineRows
    ),
    'target_page_action_safeties' => array_column($outlineRows[0]['target_page_actions'] ?? [], 'safety'),
    'navigation_outline_has_structure_context' => ($navigation['outline'][0]['outline_object'] ?? null) === 6
        && ($navigation['outline'][0]['structure_state'] ?? null) === 'collapsed',
    'visible_text_excludes_outline_structure' => !str_contains($plainText, 'Migration Plan')
        && !str_contains($plainText, 'PlanTarget')
        && !str_contains($plainText, 'page-open-outline-structure'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($pages as $page) {
    echo '<!-- wp:separator {"className":"markerpdf-page-break","metadata":{"name":"PDF page '
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '"}} -->' . "\n";
    echo '<hr class="wp-block-separator has-alpha-channel-opacity markerpdf-page-break"/>' . "\n";
    echo "<!-- /wp:separator -->\n\n";
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($page['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($outlineRows as $row) {
    echo '<li data-marker-outline-object="' . htmlspecialchars((string) $row['outline_object'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-state="' . htmlspecialchars($row['structure_state'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-page="' . htmlspecialchars($row['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-color="' . htmlspecialchars((string) ($row['text_color_hex'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="false">'
        . htmlspecialchars($row['title'] . ' -> ' . $row['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
