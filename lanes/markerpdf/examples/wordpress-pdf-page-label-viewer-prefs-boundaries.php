<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Preface imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Chapter imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Appendix imported) Tj ET',
];

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R /PageLayout /TwoPageRight /PageMode /UseOutlines /ViewerPreferences 7 0 R /Names << /Dests 40 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Dur 6 /Trans 36 0 R /AA << /O 37 0 R >> /Contents 10 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Trans << /S /Split /D .5 /Dm /H /M /O /Di 90 /SS .75 /B true >> /AA << /C << /S /URI /URI (javascript:alert\\(1\\)) >> >> /Contents 11 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
    . "7 0 obj\n<< /DisplayDocTitle true /Direction 30 0 R /PrintScaling /None /Duplex 31 0 R /PrintPageRange 32 0 R /NumCopies 33 0 R /Enforce 34 0 R /PrintClip /Bogus >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

foreach ($contents as $objectNumber => $content) {
    $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
}

$pdf .= "20 0 obj\n<< /Kids 21 0 R >>\nendobj\n"
    . "21 0 obj\n[22 0 R 23 0 R]\nendobj\n"
    . "22 0 obj\n<< /Limits [0 0] /Nums [0 << /S /r /P 35 0 R /St 2 >> 2 << /S /D /P (stale-) /St 99 >>] >>\nendobj\n"
    . "23 0 obj\n<< /Limits [1 2] /Nums [1 << /S /D /P (Body ) /St 1 >> 2 << /S /A /P (App-) /St 27 >>] >>\nendobj\n"
    . "30 0 obj\n/R2L\nendobj\n"
    . "31 0 obj\n/DuplexFlipLongEdge\nendobj\n"
    . "32 0 obj\n[1 2]\nendobj\n"
    . "33 0 obj\n2\nendobj\n"
    . "34 0 obj\n[ /PrintScaling /PrintClip /Bogus ]\nendobj\n"
    . "35 0 obj\n(front-)\nendobj\n"
    . "36 0 obj\n<< /S /Dissolve /D 1.25 >>\nendobj\n"
    . "37 0 obj\n<< /S /GoTo /D /ChapterStart >>\nendobj\n"
    . "40 0 obj\n<< /Names [(ChapterStart) [4 0 R /FitH null]] >>\nendobj\n"
    . "%%EOF";

$textExtractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$viewerPreferences = $metadata['viewer_preferences'] ?? [];
$presentations = (new PdfOutlineExtractor())->getPageTransitionActionMetadata($pdf);
$pageActions = [];
foreach ($presentations as $page) {
    foreach ($page['actions'] as $action) {
        $pageActions[] = $action;
    }
}

echo '<!-- markerpdf-page-label-viewer-prefs-boundaries-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels number-tree /Limits, indirect /ViewerPreferences, and labeled page transition/action review metadata',
    'page_labels' => array_column($pages, 'page_label'),
    'viewer_preferences' => $viewerPreferences,
    'transition_page_labels' => array_column($presentations, 'page_label'),
    'transition_styles' => array_values(array_filter(array_map(static fn (array $page): ?string => $page['transition']['style'] ?? null, $presentations))),
    'ignored_stale_page_label_key' => !in_array('stale-99', array_column($pages, 'page_label'), true),
    'invalid_viewer_preference_filtered' => !array_key_exists('print_clip', $viewerPreferences),
    'all_page_actions_review_only' => count(array_filter(
        $pageActions,
        static fn (array $action): bool => ($action['executes_on_import'] ?? true) === false
    )) === count($pageActions),
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

echo '<!-- markerpdf:catalog-review ' . htmlspecialchars(json_encode([
    'page_layout' => $metadata['page_layout'] ?? null,
    'page_mode' => $metadata['page_mode'] ?? null,
    'display_doc_title' => $viewerPreferences['display_doc_title'] ?? null,
    'direction' => $viewerPreferences['direction'] ?? null,
    'print_scaling' => $viewerPreferences['print_scaling'] ?? null,
    'duplex' => $viewerPreferences['duplex'] ?? null,
    'print_page_range' => $viewerPreferences['print_page_range'] ?? [],
    'num_copies' => $viewerPreferences['num_copies'] ?? null,
    'enforce' => $viewerPreferences['enforce'] ?? [],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($presentations as $page) {
    $transition = $page['transition'];
    if ($transition !== null) {
        echo '<li data-marker-page-label="' . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '" data-marker-transition-style="' . htmlspecialchars((string) $transition['style'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">PDF page ' . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " transition review</li>\n";
    }

    foreach ($page['actions'] as $action) {
        echo '<li data-marker-page-label="' . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '" data-marker-page-action-event="' . htmlspecialchars($action['event'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '" data-marker-page-action-type="' . htmlspecialchars($action['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '" data-marker-executes-on-import="' . (($action['executes_on_import'] ?? true) ? 'true' : 'false')
            . '">PDF page ' . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' '
            . htmlspecialchars($action['event_label'] . ' ' . $action['action_type'] . ' ' . $action['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . "</li>\n";
    }
}
echo "</ul>\n<!-- /wp:list -->\n";
