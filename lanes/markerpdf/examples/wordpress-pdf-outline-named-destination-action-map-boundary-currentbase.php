<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$introContent = 'BT /F1 12 Tf 72 720 Td (Intro outline action map page remains visible) Tj ET';
$targetContent = 'BT /F1 12 Tf 72 720 Td (Target outline action map page remains visible) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Local review action destination) /Parent 5 0 R /Dest /ReviewAction /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Thread review action destination) /Parent 5 0 R /Dest /ThreadAction /Prev 6 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Names [(ReviewAction) 9 0 R (ReviewTarget) [4 0 R /FitH 640] (ThreadAction) 10 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /S /GoTo /D /ReviewTarget /Next 11 0 R >>\nendobj\n"
    . "10 0 obj\n<< /S /Thread /D (Boundary Thread) /B 22 0 R /Next 12 0 R >>\nendobj\n"
    . "11 0 obj\n<< /S /URI /URI (https://example.com/local-review-action) >>\nendobj\n"
    . "12 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden thread action followup'\\)) >>\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Boundary Thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [64 684 280 734] /N 22 0 R /V 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [292 684 548 734] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Target ) /St 5 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfOutlineExtractor();
$navigation = $extractor->getNavigationReviewMetadata($pdf);
$toc = $extractor->getPdfToc($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$actions = $navigation['outline_action_review_actions'];

$summary = [
    'support_component' => 'native-pdf-outline-named-destination-action-map',
    'native_boundary' => 'page destination map keeps named GoTo actions that resolve to local page views; action review map keeps named Thread or chained actions review-only',
    'toc_titles' => array_column($toc, 'title'),
    'toc_destinations' => array_column($toc, 'destination'),
    'outline_action_types' => array_column($actions, 'action_type'),
    'outline_action_safeties' => array_column($actions, 'safety'),
    'destination_action_names' => array_column($actions, 'destination_action_name'),
    'article_thread_titles' => array_column($navigation['article_threads'] ?? [], 'title'),
    'visible_text_excludes_outline_operands' => !str_contains($plainText, 'ReviewAction')
        && !str_contains($plainText, 'ThreadAction')
        && !str_contains($plainText, 'hidden thread action followup'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (($summary['toc_titles'] ?? []) !== ['Local review action destination']
    || ($summary['outline_action_types'] ?? []) !== ['GoTo', 'URI', 'Thread', 'JavaScript']
    || ($summary['destination_action_names'] ?? []) !== ['ReviewAction', 'ReviewAction', 'ThreadAction', 'ThreadAction']
    || !$summary['visible_text_excludes_outline_operands']
) {
    throw new RuntimeException('Expected named destination action maps to split local TOC rows from review-only action rows.');
}

echo '<!-- markerpdf-pdf-outline-named-destination-action-map-boundary-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($toc as $item) {
    echo '<li data-marker-outline-destination="'
        . htmlspecialchars((string) ($item['destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
