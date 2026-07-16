<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentPayload = "Title,Status\nCurrent XRef Attachment,Ready\n";
$stalePayload = "Title,Status\nStale XRef Attachment,Ignore\n";
$currentChecksum = md5($currentPayload);
$staleChecksum = md5($stalePayload);

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>');
$addObject(2, '<< /Names [(current-xref.csv) 4 0 R] >>');
$addObject(4, '<< /Type /Filespec /F (current-xref.csv) /Desc (Current xref-selected attachment rows) /AFRelationship /Data /EF << /F 5 0 R >> >>');
$addObject(5, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605001158Z) >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$pdf .= "4 0 obj\n<< /Type /Filespec /F (stale-xref.csv) /Desc (Stale unindexed attachment rows) /AFRelationship /Alternative /EF << /F 5 0 R >> >>\nendobj\n"
    . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
    . "stream\n{$stalePayload}\nendstream\nendobj\n";

$xrefOffset = strlen($pdf);
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$pdf .= "xref\n0 6\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1])
    . $xrefRow($offsets[2])
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets[4])
    . $xrefRow($offsets[5])
    . "trailer\n<< /Size 6 /Root 1 0 R >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$attachment = $summary['attachments'][0] ?? null;
if (!is_array($attachment)
    || ($attachment['filename'] ?? null) !== 'current-xref.csv'
    || ($attachment['relationship'] ?? null) !== 'Data'
    || ($attachment['checksum_matches'] ?? null) !== true
    || str_contains($summaryJson, 'stale-xref.csv')
    || str_contains($summaryJson, 'Stale XRef Attachment')
) {
    throw new RuntimeException('Expected xref-selected attachment preflight to exclude stale same-object FileSpecs.');
}

echo "<!-- markerpdf-pdf-attachments-xref-smoke " . htmlspecialchars(json_encode([
    'native_boundary' => 'lightweight attachment preflight selects FileSpec rows through current xref offsets',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'xref_selected_current_attachment' => ($attachment['filename'] ?? null) === 'current-xref.csv',
    'stale_same_object_attachment_excluded' => !str_contains($summaryJson, 'stale-xref.csv'),
    'raw_payload_omitted' => !str_contains($summaryJson, $currentPayload) && !str_contains($summaryJson, $stalePayload),
    'associated_file_mirror_marked' => ($attachment['associated_file_source'] ?? null) === 'catalog_af',
    'checksum_matches' => $attachment['checksum_matches'] ?? null,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($summary['attachments'] as $row) {
    echo '<li data-marker-attachment-sha256="'
        . htmlspecialchars((string) $row['sha256'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars((string) $row['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . ' - '
        . htmlspecialchars((string) ($row['relationship'] ?? 'unassociated'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
