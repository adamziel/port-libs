<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$coverText = 'BT /F1 12 Tf 72 720 Td (Cover page remains visible) Tj ET';
$localText = 'BT /F1 12 Tf 72 720 Td (Local appendix page remains visible) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Remote Named Destination Action) /Parent 5 0 R /Dest /RemoteReview /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Remote Direct Destination Action) /Parent 5 0 R /Dest 10 0 R /Next 14 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Names [(RemoteReview) 9 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /S /GoToR /F << /F (fallback-guide.pdf) /UF <FEFF00650078007400650072006E0061006C002D00670075006900640065002E007000640066> >> /D [3 /FitH 720] /NewWindow true /Next [11 0 R 12 0 R 12 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /S /GoToR /F (appendix.pdf) /D /Chapter#202 /Next 13 0 R >>\nendobj\n"
    . "11 0 obj\n<< /S /URI /URI (https://example.com/remote-notes) >>\nendobj\n"
    . "12 0 obj\n<< /S /JavaScript /JS (app.alert\\('remote destination hidden script'\\)) >>\nendobj\n"
    . "13 0 obj\n<< /S /GoTo /D [4 0 R /Fit] >>\nendobj\n"
    . "14 0 obj\n<< /Title (Local Appendix) /Parent 5 0 R /Dest [4 0 R /Fit] >>\nendobj\n"
    . "20 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Appendix ) /St 2 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($localText) . " >>\nstream\n{$localText}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
$toc = $outlineExtractor->getPdfToc($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

if (count($remoteActions) !== 2) {
    throw new RuntimeException('Expected two remote destination action review rows.');
}
if (array_column($toc, 'title') !== ['Local Appendix']) {
    throw new RuntimeException('Expected remote destination actions to stay out of local TOC rows.');
}
if (str_contains($plainText, 'external-guide.pdf') || str_contains($plainText, 'remote-notes') || str_contains($plainText, 'remote destination hidden script')) {
    throw new RuntimeException('Expected remote destination action operands to stay out of visible WordPress text.');
}

$outlineActions = $navigation['outline_action_review_actions'];

echo '<!-- markerpdf-outline-remote-destination-action-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-remote-destination-action-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'outline /Dest values that resolve to /S /GoToR action dictionaries become remote review metadata, not local same-document TOC rows',
    'local_toc_titles' => array_column($toc, 'title'),
    'remote_action_titles' => array_column($remoteActions, 'title'),
    'remote_action_files' => array_column($remoteActions, 'file'),
    'remote_action_pages' => array_column($remoteActions, 'page'),
    'outline_action_count' => count($outlineActions),
    'outline_action_types' => array_column($outlineActions, 'action_type'),
    'outline_action_safeties' => array_column($outlineActions, 'safety'),
    'all_outline_actions_review_only' => array_reduce(
        $outlineActions,
        static fn (bool $carry, array $row): bool => $carry && ($row['executes_on_import'] ?? true) === false,
        true
    ),
    'visible_text_excludes_remote_action_operands' => !str_contains($plainText, 'external-guide.pdf')
        && !str_contains($plainText, 'appendix.pdf')
        && !str_contains($plainText, 'remote-notes')
        && !str_contains($plainText, 'remote destination hidden script'),
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
foreach ($toc as $outline) {
    echo '<li data-marker-outline-page="' . (int) $outline['page'] . '">Local TOC: '
        . htmlspecialchars($outline['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
foreach ($remoteActions as $row) {
    echo '<li data-marker-remote-destination-file="' . htmlspecialchars($row['file'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-remote-destination-page="' . htmlspecialchars((string) ($row['page'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-remote-destination-name="' . htmlspecialchars((string) ($row['destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">Remote destination action: ' . htmlspecialchars($row['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
