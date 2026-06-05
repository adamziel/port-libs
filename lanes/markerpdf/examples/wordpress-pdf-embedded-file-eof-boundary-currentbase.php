<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentPayload = "Title,Status\nCurrent Embedded File,Ready\n";
$stalePayload = "Title,Status\nPost EOF Stale Embedded File,Ignore\n";
$currentChecksum = md5($currentPayload);
$staleChecksum = md5($stalePayload);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
    . "6 0 obj\n<< /Names [(current-eof.csv) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (current-eof.csv) /Desc (Current EOF-bounded attachment) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605115253Z) >> /Length " . strlen($currentPayload) . " >>\n"
    . "stream\n{$currentPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n"
    . "10 0 obj\n<< /Type /Filespec /F (post-eof-stale.csv) /Desc (Post EOF stale attachment) /AFRelationship /Alternative /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
    . "stream\n{$stalePayload}\nendstream\nendobj\n";

$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$embeddedJson = json_encode($embeddedFiles, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$file = $embeddedFiles[0] ?? null;
if (!is_array($file)) {
    throw new RuntimeException('Expected one EOF-bounded embedded-file row.');
}
if (($file['filename'] ?? null) !== 'current-eof.csv' || ($file['checksum_matches'] ?? null) !== true) {
    throw new RuntimeException('Expected current EOF-bounded embedded file to win.');
}
if (str_contains($embeddedJson, 'post-eof-stale.csv') || str_contains($summaryJson, 'post-eof-stale.csv')) {
    throw new RuntimeException('Expected post-EOF stale FileSpec rows to stay excluded.');
}

echo '<!-- markerpdf-pdf-embedded-file-eof-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-embedded-files-name-tree-parser',
    'native_boundary' => 'terminal %%EOF bounds full EmbeddedFiles extraction and lightweight attachment preflight',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'embedded_file_count' => count($embeddedFiles),
    'attachment_count' => $summary['attachment_count'],
    'filename' => $file['filename'],
    'relationship' => $file['relationship'] ?? null,
    'mime_type' => $file['mime_type'] ?? null,
    'declared_size_matches' => ($file['declared_size'] ?? null) === strlen($currentPayload),
    'checksum_matches' => $file['checksum_matches'] ?? null,
    'terminal_eof_bounds_full_embedded_file_scan' => !str_contains($embeddedJson, 'Post EOF Stale Embedded File'),
    'terminal_eof_bounds_attachment_summary_scan' => !str_contains($summaryJson, 'Post EOF Stale Embedded File'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars($file['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($file['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars($file['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
