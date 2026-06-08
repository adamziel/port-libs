<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-08T04:44:55Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$staleText = 'BT /F1 12 Tf 72 720 Td (Stale sparse misnumbered smoke page) Tj ET';
$currentText = 'BT /F1 12 Tf 72 720 Td (Current sparse misnumbered smoke page) Tj T* (Classic offset owners selected) Tj ET';
$stalePayload = '<wp-export><post id="stale-sparse-misnumbered-smoke"/></wp-export>';
$currentPayload = '<wp-export><post id="current-sparse-misnumbered-smoke"/></wp-export>';
$staleXmp = gzcompress($xmpPacket(
    'Stale Sparse Misnumbered Smoke XMP Title',
    'Stale sparse classic xref rows must not win'
));
$currentXmp = gzcompress($xmpPacket(
    'Current Sparse Misnumbered Smoke XMP Title',
    'Current sparse classic xref rows selected by offset owner'
));
if (!is_string($staleXmp) || !is_string($currentXmp)) {
    throw new RuntimeException('Unable to compress sparse misnumbered xref smoke metadata streams.');
}

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset,
    $generation,
    $state
);

$staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleText) . " >>\nstream\n{$staleText}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Sparse Misnumbered Smoke Info) /Author (Stale Sparse Smoke Author) /Producer (Stale Sparse Smoke Producer) >>');
$staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-sparse-misnumbered-smoke.xml) 10 0 R] >>');
$staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-sparse-misnumbered-smoke.xml) /Desc (Stale sparse misnumbered smoke attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
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

$currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentText) . " >>\nstream\n{$currentText}\nendstream");
$currentInfoOffset = $addObject(6, 0, '<< /Title (Current Sparse Misnumbered Smoke Info) /Author (Current Sparse Smoke Author) /Producer (Current Sparse Smoke Producer) >>');
$currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$currentNameTreeOffset = $addObject(8, 0, '<< /Names [(current-sparse-misnumbered-smoke.xml) 10 0 R] >>');
$currentFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (current-sparse-misnumbered-smoke.xml) /Desc (Current sparse misnumbered smoke attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$currentEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "30 9\n"
    . $xrefTableRow($currentCatalogOffset)
    . $xrefTableRow($currentPagesOffset)
    . $xrefTableRow($currentPageOffset)
    . $xrefTableRow($currentContentOffset)
    . $xrefTableRow($currentInfoOffset)
    . $xrefTableRow($currentMetadataOffset)
    . $xrefTableRow($currentNameTreeOffset)
    . $xrefTableRow($currentFileSpecOffset)
    . $xrefTableRow($currentEmbeddedFileOffset)
    . "trailer\n<< /Size 40 /Prev {$previousXrefOffset} >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$encoded = json_encode([$metadata, $files, $summary, $lines], JSON_UNESCAPED_SLASHES);

$checks = [
    'classic_sparse_current_text_selected' => $lines === [
        'Current sparse misnumbered smoke page',
        'Classic offset owners selected',
    ],
    'classic_sparse_current_xmp_selected' => ($metadata['title'] ?? null) === 'Current Sparse Misnumbered Smoke XMP Title',
    'classic_sparse_current_info_selected' => ($metadata['info']['Title'] ?? null) === 'Current Sparse Misnumbered Smoke Info',
    'classic_sparse_current_language_selected' => ($metadata['language'] ?? null) === 'en-US',
    'classic_sparse_attachment_selected' => ($files[0]['filename'] ?? null) === 'current-sparse-misnumbered-smoke.xml'
        && ($files[0]['content'] ?? null) === $currentPayload,
    'classic_sparse_attachment_summary_selected' => ($summary['filenames'] ?? []) === ['current-sparse-misnumbered-smoke.xml']
        && ($summary['total_bytes'] ?? null) === strlen($currentPayload),
    'classic_sparse_trailer_inherits_prev_root_info' => str_contains($pdf, "trailer\n<< /Size 40 /Prev ")
        && str_contains($pdf, "xref\n30 9\n"),
    'classic_sparse_stale_prev_excluded' => is_string($encoded)
        && !str_contains($encoded, 'Stale Sparse Misnumbered')
        && !str_contains($encoded, 'stale-sparse-misnumbered-smoke'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ($checks as $name => $passed) {
    if ($passed !== true && $name !== 'executes_python_or_models' && $name !== 'executes_external_pdf_tools') {
        throw new RuntimeException('Smoke check failed: ' . $name);
    }
}

echo '<!-- wp:markerpdf/review ' . htmlspecialchars(json_encode($checks, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- /wp:markerpdf/review -->\n\n";
echo "<!-- wp:heading -->\n";
echo '<h2>' . htmlspecialchars((string) ($metadata['title'] ?? 'PDF metadata review'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
echo "<!-- /wp:heading -->\n\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
