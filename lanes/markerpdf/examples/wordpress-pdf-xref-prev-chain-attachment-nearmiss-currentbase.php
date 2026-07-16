<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$nameTreePayload = "Title,Status\nRepaired Prev Attachment,Ready\n";
$pagePayload = '<wp-page><attachment role="repaired-prev-page-af"/></wp-page>';
$decoyPayload = '<wp-export><post id="unindexed-generation-decoy"/></wp-export>';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . pack('n', $fieldThree);

$addObject(6, 0, '<< /Names [(repaired-prev-chain.csv) 7 0 R] >>');
$addObject(7, 0, '<< /Type /Filespec /F (repaired-prev-chain.csv) /Desc (Repaired near-miss Prev name tree rows) /AFRelationship /Data /EF << /F 8 0 R >> >>');
$addObject(8, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size ' . strlen($nameTreePayload) . ' /CheckSum <' . md5($nameTreePayload) . '> >> /Length ' . strlen($nameTreePayload) . " >>\nstream\n{$nameTreePayload}\nendstream");
$addObject(9, 0, '<< /Type /Filespec /F (repaired-prev-page.xml) /Desc (Repaired near-miss Prev page AF) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($pagePayload) . ' /CheckSum <' . md5($pagePayload) . '> >> /Length ' . strlen($pagePayload) . " >>\nstream\n{$pagePayload}\nendstream");

$previousCommentOffset = strlen($pdf);
$pdf .= "% producer padding before previous xref\n";
$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n0 11\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets['6:0'])
    . $xrefRow($offsets['7:0'])
    . $xrefRow($offsets['8:0'])
    . $xrefRow($offsets['9:0'])
    . $xrefRow($offsets['10:0'])
    . "trailer\n<< /Size 11 >>\nstartxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(6, 1, '<< /Names [(unindexed-decoy.csv) 7 1 R] >>');
$addObject(7, 1, '<< /Type /Filespec /F (unindexed-decoy.csv) /Desc (Unindexed generation decoy) /AFRelationship /Alternative /EF << /F 8 1 R >> >>');
$addObject(8, 1, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");
$addObject(9, 1, '<< /Type /Filespec /F (unindexed-page-decoy.xml) /Desc (Unindexed page decoy) /AFRelationship /Alternative /EF << /F 10 1 R >> >>');
$addObject(10, 1, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");
$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /Names << /EmbeddedFiles 6 0 R >> /AF [7 0 R] >>');
$addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
$addObject(3, 1, '<< /Type /Page /Parent 2 1 R /MediaBox [0 0 612 792] /AF [9 0 R] >>');

$currentRows = $xrefStreamRow(1, $offsets['1:1'], 1)
    . $xrefStreamRow(1, $offsets['2:1'], 1)
    . $xrefStreamRow(1, $offsets['3:1'], 1)
    . $xrefStreamRow(1, 0, 0);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress near-miss Prev xref-stream smoke fixture.');
}

$currentXrefOffset = strlen($pdf);
$nearMissPrevOffset = $previousCommentOffset + 2;
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 1 R /Prev ' . $nearMissPrevOffset
    . ' /Index [1 3 20 1] /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\nstartxref\n{$currentXrefOffset}\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (
    $summary['filenames'] !== ['repaired-prev-chain.csv', 'repaired-prev-page.xml']
    || str_contains($summaryJson, 'unindexed-decoy')
    || str_contains($summaryJson, $nameTreePayload)
    || str_contains($summaryJson, $pagePayload)
    || str_contains($summaryJson, $decoyPayload)
) {
    throw new RuntimeException('Expected repaired /Prev chain attachment review without decoy or payload leakage.');
}

echo '<!-- markerpdf-xref-prev-chain-attachment-nearmiss-currentbase-smoke ' . htmlspecialchars(json_encode([
    'native_boundary' => 'xref-stream /Prev near-miss offset repaired to the prior top-level xref table for generation-exact attachment review',
    'prev_offset_repaired_backward' => $nearMissPrevOffset < $previousXrefOffset,
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'catalog_af_mirror_marked' => ($summary['attachments'][0]['associated_file_source'] ?? null) === 'catalog_af',
    'page_af_preserved' => ($summary['attachments'][1]['page_associated_file'] ?? null) === true,
    'unindexed_generation_decoy_excluded' => !str_contains($summaryJson, 'unindexed-decoy'),
    'raw_payload_omitted' => !str_contains($summaryJson, $nameTreePayload)
        && !str_contains($summaryJson, $pagePayload)
        && !str_contains($summaryJson, $decoyPayload),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($summary['attachments'] as $attachment) {
    echo '<li data-marker-attachment-sha256="'
        . htmlspecialchars((string) $attachment['sha256'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . ' - '
        . htmlspecialchars((string) ($attachment['relationship'] ?? 'unassociated'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
