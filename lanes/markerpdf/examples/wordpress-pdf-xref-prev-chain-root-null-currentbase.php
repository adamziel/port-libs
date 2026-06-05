<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Root null smoke page) Tj ET';
$staleXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Stale Root Null Smoke XMP</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Root Null Smoke Info) /Author (Stale Root Null Smoke Author) /Producer (Stale Root Null Smoke Producer) >>');
$staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-root-null-smoke.xml) 10 0 R] >>');
$staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-root-null-smoke.xml) /Desc (Stale Root null smoke attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$stalePayload = '<wp-export><post id="stale-root-null-smoke"/></wp-export>';
$staleEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 12\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($staleCatalogOffset)
    . $xrefTableRow($stalePagesOffset)
    . $xrefTableRow($stalePageOffset)
    . $xrefTableRow($staleContentOffset)
    . $xrefTableRow($fontOffset)
    . $xrefTableRow($staleInfoOffset)
    . $xrefTableRow($staleMetadataOffset)
    . $xrefTableRow($staleNameTreeOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($staleFileSpecOffset)
    . $xrefTableRow($staleEmbeddedFileOffset)
    . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$currentInfoOffset = $addObject(6, 0, '<< /Title (Current Root Null Smoke Info) /Author (Current Root Null Smoke Author) /Producer (Current Root Null Smoke Producer) >>');
$currentRows = $xrefStreamRow(1, $currentInfoOffset, 0);
$compressedCurrentRows = gzcompress($currentRows);
if (!is_string($compressedCurrentRows)) {
    throw new RuntimeException('Unable to compress Root-null xref smoke rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root null /Info 6 0 R /Prev ' . $previousXrefOffset . ' /Index [6 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
    . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedEmbeddedFiles = json_encode($embeddedFiles, JSON_UNESCAPED_SLASHES);
$encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-xref-prev-chain-root-null-currentbase '
    . htmlspecialchars(json_encode([
        'root_null_latest_xref_stream_stops_prev_catalog' => $lines === []
            && !isset($metadata['catalog'])
            && !isset($metadata['language']),
        'current_info_selected' => ($metadata['title'] ?? null) === 'Current Root Null Smoke Info'
            && ($metadata['info']['Title'] ?? null) === 'Current Root Null Smoke Info',
        'stale_prev_text_excluded' => !in_array('Stale Root null smoke page', $lines, true),
        'stale_prev_metadata_excluded' => is_string($encodedMetadata)
            && !str_contains($encodedMetadata, 'Stale Root Null Smoke')
            && !str_contains($encodedMetadata, 'stale-root-null-smoke'),
        'stale_prev_embedded_files_excluded' => $embeddedFiles === []
            && is_string($encodedEmbeddedFiles)
            && !str_contains($encodedEmbeddedFiles, 'stale-root-null-smoke'),
        'attachment_summary_empty' => ($attachmentSummary['attachment_count'] ?? null) === 0
            && ($attachmentSummary['total_bytes'] ?? null) === 0
            && ($attachmentSummary['filenames'] ?? null) === []
            && ($attachmentSummary['attachments'] ?? null) === []
            && is_string($encodedAttachmentSummary)
            && !str_contains($encodedAttachmentSummary, 'stale-root-null-smoke'),
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
    ], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo '<h2>' . htmlspecialchars((string) ($metadata['title'] ?? 'PDF metadata review'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
echo "<!-- /wp:heading -->\n";
