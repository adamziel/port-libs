<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Preface page stays visible) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf '
    . '/BodyAlias << /MCID 1 >> BDC 72 704 Td (Target body second) Tj EMC '
    . '/DeckTitle << /MCID 0 >> BDC 72 720 Td (Target heading first) Tj EMC '
    . '/Artifact << /MCID 2 >> BDC 72 680 Td (Target artifact noise) Tj EMC ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /OpenAction 40 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /MarkInfo << /Marked true >> /StructTreeRoot 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 7 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Accessible Chapter Target) /Parent 5 0 R /Dest /ChapterStart >>\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "8 0 obj\n<< /Names [(ChapterStart) [4 0 R /FitH 640] (StaleTarget) [99 0 R /Fit]] >>\nendobj\n"
    . "20 0 obj\n<< /Nums [0 << /S /r /P (front-) /St 2 >> 1 << /S /D /P (Body ) /St 1 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /S /GoTo /D /ChapterStart >>\nendobj\n"
    . "50 0 obj\n<< /Type /StructTreeRoot /RoleMap 60 0 R /ParentTree 55 0 R /K [52 0 R 53 0 R] >>\nendobj\n"
    . "52 0 obj\n<< /Type /StructElem /S /DeckTitle /P 50 0 R /K 0 >>\nendobj\n"
    . "53 0 obj\n<< /Type /StructElem /S /BodyAlias /P 50 0 R /K 1 >>\nendobj\n"
    . "55 0 obj\n<< /Nums [0 [52 0 R 53 0 R]] >>\nendobj\n"
    . "60 0 obj\n<< /DeckTitle /H2 /BodyAlias /P >>\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$metadata = $outlineExtractor->getNavigationReviewMetadata($pdf);
$outline = $metadata['outline'][0] ?? [];
$openAction = $metadata['open_action_review_actions'][0] ?? [];
$targetRows = is_array($outline['target_tagged_content'] ?? null) ? $outline['target_tagged_content'] : [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

echo '<!-- markerpdf-navigation-structelem-page-label-review ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-labels-named-destination-structelem-review',
    'native_boundary' => 'local outline/OpenAction named destinations carry target PageLabels and StructElem tagged-content rows',
    'navigation_sources' => $metadata['source'],
    'destination_name' => $outline['destination'] ?? null,
    'target_page_label' => $outline['page_label'] ?? null,
    'target_roles' => $outline['target_structure_roles'] ?? [],
    'outline_has_tagged_target' => $targetRows !== [],
    'open_action_has_tagged_target' => isset($openAction['target_tagged_content']) && is_array($openAction['target_tagged_content']),
    'artifact_excluded' => !str_contains($plainText, 'Target artifact noise'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
if ($outline !== []) {
    $title = htmlspecialchars((string) ($outline['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $destination = htmlspecialchars((string) ($outline['destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $pageLabel = htmlspecialchars((string) ($outline['page_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $roles = htmlspecialchars(implode(',', $outline['target_structure_roles'] ?? []), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "<li data-marker-destination-name=\"{$destination}\" data-marker-page-label=\"{$pageLabel}\" data-marker-structure-roles=\"{$roles}\">{$title}</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n\n";

foreach ($targetRows as $row) {
    $text = htmlspecialchars((string) ($row['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (($row['role'] ?? null) === 'H2') {
        echo "<!-- wp:heading {\"level\":2} -->\n";
        echo "<h2>{$text}</h2>\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo "<p>{$text}</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
