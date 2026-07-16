<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$previousIntro = 'BT /F1 12 Tf 72 720 Td (Previous outline Prev chain intro) Tj ET';
$currentIntro = 'BT /F1 12 Tf 72 720 Td (Current outline Prev chain intro) Tj ET';
$currentTarget = 'BT /F1 12 Tf 72 720 Td (Current outline Prev chain target) Tj ET';
$decoyIntro = 'BT /F1 12 Tf 72 720 Td (Post xref outline decoy intro) Tj ET';

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
$previousOffsets[6] = $addObject(6, 0, '<< /Title (Previous XRef Prev Chain Outline) /Parent 5 0 R /Dest /PreviousStart /A 12 0 R >>');
$previousOffsets[9] = $addObject(9, 0, '<< /Names [(PreviousStart) [3 0 R /Fit]] >>');
$previousOffsets[12] = $addObject(12, 0, "<< /S /JavaScript /JS (app.alert\\('previous xref prev chain outline action'\\)) >>");
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

$currentOffsets = [];
$currentOffsets[1] = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 9 0 R >> /PageMode /UseOutlines >>');
$currentOffsets[2] = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
$currentOffsets[3] = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 20 0 R >>');
$currentOffsets[4] = $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Contents 21 0 R >>');
$currentOffsets[5] = $addObject(5, 0, '<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>');
$currentOffsets[6] = $addObject(6, 0, '<< /Title (Current XRef Prev Chain Start) /Parent 5 0 R /Dest /CurrentStart /Next 7 0 R /C [0 .2 .5] /F 2 >>');
$currentOffsets[7] = $addObject(7, 0, '<< /Title (Current XRef Prev Chain Review) /Parent 5 0 R /Prev 6 0 R /A 12 0 R >>');
$currentOffsets[9] = $addObject(9, 0, '<< /Names [(CurrentStart) [3 0 R /FitH 700] (CurrentTarget) [4 0 R /XYZ 144 620 0]] >>');
$currentOffsets[12] = $addObject(12, 0, '<< /S /GoTo /D /CurrentTarget /Next 13 0 R >>');
$currentOffsets[13] = $addObject(13, 0, '<< /S /URI /URI (https://example.com/current-xref-prev-chain-outline-review) >>');
$currentOffsets[20] = $addObject(20, 0, "<< /Length " . strlen($currentIntro) . " >>\nstream\n{$currentIntro}\nendstream");
$currentOffsets[21] = $addObject(21, 0, "<< /Length " . strlen($currentTarget) . " >>\nstream\n{$currentTarget}\nendstream");

$rows = '';
for ($objectNumber = 1; $objectNumber <= 21; $objectNumber++) {
    $rows .= isset($currentOffsets[$objectNumber])
        ? $xrefStreamRow(1, $currentOffsets[$objectNumber], 0)
        : $xrefStreamRow(0, 0, 0);
}
$compressedRows = gzcompress($rows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress outline xref-stream Prev chain smoke rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [1 21] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n";

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 9 0 R >> /PageMode /UseOutlines >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 20 0 R >>');
$addObject(5, 0, '<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>');
$addObject(6, 0, '<< /Title (Post XRef Outline Decoy) /Parent 5 0 R /Dest /DecoyStart /A 12 0 R >>');
$addObject(9, 0, '<< /Names [(DecoyStart) [3 0 R /Fit]] >>');
$addObject(12, 0, "<< /S /JavaScript /JS (app.alert\\('post xref outline decoy action'\\)) >>");
$addObject(20, 0, "<< /Length " . strlen($decoyIntro) . " >>\nstream\n{$decoyIntro}\nendstream");

$pdf .= "startxref\n{$currentXrefOffset}\n%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$toc, $navigation, $plainText], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

if (array_column($toc, 'title') !== ['Current XRef Prev Chain Start', 'Current XRef Prev Chain Review']) {
    throw new RuntimeException('Expected current xref-stream Prev chain outline rows to own TOC metadata.');
}
if ($plainText !== "Current outline Prev chain intro\nCurrent outline Prev chain target") {
    throw new RuntimeException('Expected visible WordPress text to follow the current xref-stream rows.');
}
if (str_contains($encodedReview, 'Previous XRef Prev Chain Outline') || str_contains($encodedReview, 'Post XRef Outline Decoy')) {
    throw new RuntimeException('Expected previous-section and post-xref outline decoys to stay excluded.');
}

echo '<!-- markerpdf-outline-xref-stream-prev-chain-owner-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-outline-xref-stream-prev-chain-owner-currentbase',
    'support_component' => 'native-pdf-outline-xref-stream-prev-chain-owner',
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'action_types' => array_column($navigation['outline_action_review_actions'] ?? [], 'action_type'),
    'previous_prev_outline_excluded' => !str_contains($encodedReview, 'Previous XRef Prev Chain Outline'),
    'post_xref_outline_decoy_excluded' => !str_contains($encodedReview, 'Post XRef Outline Decoy'),
    'post_xref_action_decoy_excluded' => !str_contains($encodedReview, 'post xref outline decoy action'),
    'visible_text' => $plainText,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($toc as $item) {
    echo '<li data-marker-pdf-page="' . htmlspecialchars((string) (($item['page'] ?? 0) + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
