<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentPayload = "Title,Status\nGeneration Current,Ready\n";
$stalePayload = "Title,Status\nGeneration Stale,Ignore\n";
$currentChecksum = md5($currentPayload);
$staleChecksum = md5($stalePayload);

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber][$generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$addObject(1, 0, '<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> >>');
$addObject(2, 0, '<< /Names [(stale-generation.csv) 4 0 R] >>');
$addObject(4, 0, '<< /Type /Filespec /F (stale-generation.csv) /Desc (Stale generation attachment rows) /AFRelationship /Alternative /EF << /F 5 0 R >> >>');
$addObject(5, 0, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$previousXrefOffset = strlen($pdf);
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$pdf .= "xref\n0 6\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 5; $objectNumber++) {
    $pdf .= isset($offsets[$objectNumber][0])
        ? $xrefRow($offsets[$objectNumber][0])
        : $xrefRow(0, 0, 'f');
}
$pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";

$addObject(1, 1, '<< /Type /Catalog /Names << /EmbeddedFiles 2 1 R >> >>');
$addObject(2, 1, '<< /Names [(current-generation.csv) 4 1 R] >>');
$addObject(4, 1, '<< /Type /Filespec /F (current-generation.csv) /Desc (Current generation attachment rows) /AFRelationship /Data /EF << /F 5 1 R >> >>');
$addObject(5, 1, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605041718Z) >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$latestXrefOffset = strlen($pdf);
$pdf .= "xref\n1 1\n" . $xrefRow(0, 1, 'n')
    . "trailer\n<< /Size 6 /Root 1 1 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n{$latestXrefOffset}\n%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? null;
if (!is_array($attachment)
    || ($summary['attachment_count'] ?? null) !== 1
    || ($attachment['filename'] ?? null) !== 'current-generation.csv'
    || ($attachment['relationship'] ?? null) !== 'Data'
    || ($attachment['checksum_matches'] ?? null) !== true
    || str_contains($summaryJson, 'stale-generation.csv')
    || str_contains($summaryJson, $currentPayload)
    || str_contains($summaryJson, $stalePayload)
) {
    throw new RuntimeException('Expected generationed attachment preflight to select only the current nonzero-generation FileSpec without payload bytes.');
}

echo "<!-- markerpdf-pdf-attachments-generation-boundary " . htmlspecialchars(json_encode([
    'native_boundary' => 'generationed /Root, /Names, Filespec, and EmbeddedFile references in attachment preflight',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'generationed_root_repaired' => true,
    'generationed_filespec_selected' => ($attachment['file_spec_object_id'] ?? null) === 4,
    'generationed_stream_selected' => ($attachment['stream_object_id'] ?? null) === 5,
    'stale_prev_generation_attachment_excluded' => !str_contains($summaryJson, 'stale-generation.csv'),
    'payload_bytes_omitted' => !str_contains($summaryJson, $currentPayload) && !str_contains($summaryJson, $stalePayload),
    'relationship_role' => $attachment['relationship_role'] ?? null,
    'checksum_matches' => $attachment['checksum_matches'] ?? null,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li data-marker-attachment-sha256="' . htmlspecialchars((string) $attachment['sha256'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'] . ' (' . (string) $attachment['relationship'] . ', ' . (string) $attachment['content_type'] . ', ' . (string) $attachment['byte_length'] . ' bytes)', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
