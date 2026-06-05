<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = "Title,Status\nPlus Header Review,Ready\n";
$checksum = md5($payload);
$pageContent = 'BT /F1 12 Tf 72 720 Td (Plus header review page) Tj ET';
$catalog = '<< /Type /Catalog /Pages 2 0 R /Lang (en-GB) /ViewerPreferences << /DisplayDocTitle true /Direction /R2L >> /Names << /EmbeddedFiles 10 0 R >> /AF [4 0 R] >>';
$fileSpec = '<< /Type /Filespec /F (plus-header-review.csv) /Desc (Compressed plus-header review FileSpec) /AFRelationship /Source /EF << /F 9 0 R >> >>';
$decoy = '<< /Type /Filespec /F (plus-header-decoy.csv) /Desc (Decoy plus-header FileSpec) >>';
$header = '+1 +0 +4 +' . strlen($catalog . "\n") . ' +12 +' . strlen($catalog . "\n" . $fileSpec . "\n");
$objectStream = gzcompress($header . "\n" . $catalog . "\n" . $fileSpec . "\n" . $decoy . "\n");
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress plus-header review object stream fixture.');
}

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(2, '<< /Type /Pages /Kids [7 0 R] /Count 1 >>');
$addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(6, '<< /Type /ObjStm /N 3 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(7, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 8 0 R >>');
$addObject(8, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
$addObject(9, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605173613Z) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream");
$addObject(10, '<< /Names [(plus-header-review.csv) 4 0 R] >>');

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber <= 20; $objectNumber++) {
    if ($objectNumber === 0) {
        $rows .= $xrefRow(0, 0, 255);
        continue;
    }
    if ($objectNumber === 1) {
        $rows .= $xrefRow(2, 6, 0);
        continue;
    }
    if ($objectNumber === 4) {
        $rows .= $xrefRow(2, 6, 1);
        continue;
    }
    if ($objectNumber === 12) {
        $rows .= $xrefRow(2, 6, 2);
        continue;
    }
    if ($objectNumber === 20) {
        $rows .= $xrefRow(1, $xrefOffset, 0);
        continue;
    }

    $rows .= isset($offsets[$objectNumber])
        ? $xrefRow(1, $offsets[$objectNumber], 0)
        : $xrefRow(0, 0, 0);
}

$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress plus-header review xref stream fixture.');
}

$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$textExtractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$embedded = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$attachment = $summary['attachments'][0] ?? null;
$plainText = $textExtractor->extractPlainText($pdf);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$smoke = [
    'native_boundary' => 'plus-signed PDF object-stream header integers are parsed for text, metadata, and attachment review',
    'compressed_catalog_metadata_selected' => ($metadata['language'] ?? null) === 'en-GB'
        && (($metadata['viewer_preferences']['direction'] ?? null) === 'R2L'),
    'compressed_filespec_selected' => is_array($attachment)
        && ($attachment['filename'] ?? null) === 'plus-header-review.csv'
        && ($attachment['description'] ?? null) === 'Compressed plus-header review FileSpec',
    'embedded_payload_available_to_attachment_review' => ($embedded[0]['checksum'] ?? null) === $checksum,
    'payload_bytes_omitted_from_summary' => is_array($attachment)
        && !array_key_exists('bytes', $attachment)
        && !str_contains($summaryJson, $payload),
    'decoy_member_excluded' => !str_contains($summaryJson, 'plus-header-decoy.csv')
        && !str_contains($plainText, 'plus-header-decoy.csv'),
    'object_stream_member_count' => $entries[1]['object_stream_member_count'] ?? null,
    'catalog_selection_policy' => $entries[1]['selection_policy'] ?? null,
    'filespec_selection_policy' => $entries[4]['selection_policy'] ?? null,
    'page_count' => $textExtractor->extractOutlineMetadata($pdf)['pages'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'compressed_catalog_metadata_selected',
    'compressed_filespec_selected',
    'embedded_payload_available_to_attachment_review',
    'payload_bytes_omitted_from_summary',
    'decoy_member_excluded',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Plus-header object-stream review smoke failed: ' . $requiredFlag);
    }
}

echo '<!-- markerpdf-xref-object-stream-plus-header-review-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($textExtractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
echo '<li data-marker-attachment-checksum="'
    . htmlspecialchars((string) ($attachment['checksum_hex'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">'
    . htmlspecialchars(
        (string) ($attachment['filename'] ?? 'attachment')
        . ' - ' . (string) ($attachment['relationship'] ?? 'unassociated')
        . ', ' . (string) ($attachment['content_type'] ?? 'application/octet-stream')
        . ', ' . (string) ($attachment['byte_length'] ?? 0) . ' bytes',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    )
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
