<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$previousIntro = 'BT /F1 12 Tf 72 720 Td (Previous generation outline intro) Tj ET';
$currentIntro = 'BT /F1 12 Tf 72 720 Td (Current generation outline intro) Tj ET';
$currentTarget = 'BT /F1 12 Tf 72 720 Td (Current generation outline target) Tj ET';

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$previousOffsets = [];
$previousOffsets[1] = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 9 0 R >> /PageMode /UseOutlines >>');
$previousOffsets[2] = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$previousOffsets[3] = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 20 0 R >>');
$previousOffsets[5] = $addObject(5, 0, '<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>');
$previousOffsets[6] = $addObject(6, 0, '<< /Title (Previous Generation Outline) /Parent 5 0 R /Dest /PreviousStart /A 12 0 R >>');
$previousOffsets[9] = $addObject(9, 0, '<< /Names [(PreviousStart) [3 0 R /Fit]] >>');
$previousOffsets[12] = $addObject(12, 0, "<< /S /JavaScript /JS (app.alert\\('previous generation outline action'\\)) >>");
$previousOffsets[20] = $addObject(20, 0, "<< /Length " . strlen($previousIntro) . " >>\nstream\n{$previousIntro}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n0 21\n"
    . $xrefTableRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 20; $objectNumber++) {
    $pdf .= isset($previousOffsets[$objectNumber])
        ? $xrefTableRow($previousOffsets[$objectNumber])
        : $xrefTableRow(0, 0, 'f');
}
$pdf .= "trailer\n<< /Size 21 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /Outlines 5 1 R /Names << /Dests 9 1 R >> /PageMode /UseOutlines >>');
$addObject(2, 1, '<< /Type /Pages /Kids [3 1 R 4 1 R] /Count 2 >>');
$addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Contents 20 1 R >>');
$addObject(4, 1, '<< /Type /Page /Parent 2 1 R /Contents 21 1 R >>');
$addObject(5, 1, '<< /Type /Outlines /First 6 1 R /Last 7 1 R /Count 2 >>');
$addObject(6, 1, '<< /Title (Current Generation Outline Start) /Parent 5 1 R /Dest /CurrentStart /Next 7 1 R /C [0 .2 .5] /F 2 >>');
$addObject(7, 1, '<< /Title (Current Generation Outline Review) /Parent 5 1 R /Prev 6 1 R /A 12 1 R >>');
$addObject(9, 1, '<< /Names [(CurrentStart) [3 1 R /FitH 700] (CurrentTarget) [4 1 R /XYZ 144 620 0]] >>');
$addObject(12, 1, '<< /S /GoTo /D /CurrentTarget /Next 13 1 R >>');
$addObject(13, 1, '<< /S /URI /URI (https://example.com/current-generation-outline-review) >>');
$addObject(20, 1, "<< /Length " . strlen($currentIntro) . " >>\nstream\n{$currentIntro}\nendstream");
$addObject(21, 1, "<< /Length " . strlen($currentTarget) . " >>\nstream\n{$currentTarget}\nendstream");

$rows = '';
foreach ([1, 2, 3, 4, 5, 6, 7, 9, 12, 13, 20, 21] as $objectNumber) {
    $rows .= $xrefStreamRow(1, 0, 1);
}
$compressedRows = gzcompress($rows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress outline generation xref stream.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 7 9 1 12 2 20 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$toc, $navigation, $plainText], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$titles = array_column($toc, 'title');
$actionTypes = array_column($navigation['outline_action_review_actions'] ?? [], 'action_type');

if ($titles !== ['Current Generation Outline Start', 'Current Generation Outline Review']) {
    throw new RuntimeException('Expected generation-one current outline xref rows to own TOC metadata.');
}
if ($actionTypes !== ['GoTo', 'URI']) {
    throw new RuntimeException('Expected current generation outline actions to be review metadata.');
}
if ($plainText !== "Current generation outline intro\nCurrent generation outline target") {
    throw new RuntimeException('Expected visible WordPress text to follow the current generation xref rows.');
}
if (str_contains($encodedReview, 'Previous Generation Outline') || str_contains($encodedReview, 'previous generation outline action')) {
    throw new RuntimeException('Expected previous-generation outline metadata and actions to stay excluded.');
}

echo '<!-- markerpdf-outline-xref-prev-chain-generation-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-outline-xref-prev-chain-generation-currentbase',
    'support_component' => 'native-pdf-outline-xref-prev-chain-generation-current-offset-repair',
    'toc_titles' => $titles,
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'action_types' => $actionTypes,
    'current_generation_outline_selected' => $titles === ['Current Generation Outline Start', 'Current Generation Outline Review'],
    'current_outline_action_reviewed' => $actionTypes === ['GoTo', 'URI'],
    'current_generation_page_text_selected' => $plainText === "Current generation outline intro\nCurrent generation outline target",
    'stale_prev_outline_excluded' => !str_contains($encodedReview, 'Previous Generation Outline'),
    'stale_prev_action_excluded' => !str_contains($encodedReview, 'previous generation outline action'),
    'stale_prev_text_excluded' => !str_contains($plainText, 'Previous generation outline intro'),
    'damaged_current_xref_offsets_repaired' => str_contains($pdf, '/Root 1 1 R') && str_contains($pdf, '/Prev '),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF current-generation outline review\"><ul>\n";
foreach ($toc as $item) {
    echo '<li data-marker-pdf-page="' . htmlspecialchars((string) (($item['page'] ?? 0) + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
