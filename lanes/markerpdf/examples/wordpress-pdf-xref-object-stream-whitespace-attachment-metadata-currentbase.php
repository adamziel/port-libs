<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildAttachmentPdf = static function (): array {
    $currentPayload = "Title,Status\nCurrent Whitespace Attachment,Ready\n";
    $decoyPayload = "Title,Status\nWhitespace Decoy Attachment,Ignore\n";
    $currentChecksum = md5($currentPayload);
    $decoyChecksum = md5($decoyPayload);
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Current whitespace attachment object-stream page) Tj ET';
    $currentFileSpec = '<< /Type /Filespec /F (current-whitespace-offset.csv) /Desc (Current compressed FileSpec before whitespace offset) /AFRelationship /Source /EF << /F 9 0 R >> >>';
    $decoyFileSpec = '<< /Type /Filespec /F (decoy-whitespace-offset.csv) /Desc (Whitespace-owned decoy FileSpec) /AFRelationship /Alternative /EF << /F 11 0 R >> >>';
    $reviewMember = '<< /Type /Review /Note (valid member after whitespace decoy) >>';
    $badOffset = strlen($currentFileSpec);
    $reviewOffset = strlen($currentFileSpec . "\n" . $decoyFileSpec . "\n");
    $header = '4 0 12 ' . $badOffset . ' 16 ' . $reviewOffset;
    $objectStream = gzcompress($header . "\n" . $currentFileSpec . "\n" . $decoyFileSpec . "\n" . $reviewMember . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress whitespace attachment object stream smoke fixture.');
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, '<< /Type /Catalog /Pages 3 0 R /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R 12 0 R] >>');
    $addObject(2, '<< /Names [(current-whitespace-offset.csv) 4 0 R (decoy-whitespace-offset.csv) 12 0 R] >>');
    $addObject(3, '<< /Type /Pages /Kids [7 0 R] /Count 1 >>');
    $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, '<< /Type /ObjStm /N 3 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(7, '<< /Type /Page /Parent 3 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 8 0 R >>');
    $addObject(8, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
    $addObject(9, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260608115259Z) >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
    $addObject(11, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($decoyPayload) . " /CheckSum <{$decoyChecksum}> >> /Length " . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber <= 20; $objectNumber++) {
        if ($objectNumber === 0) {
            $rows .= $xrefRow(0, 0, 255);
            continue;
        }
        if ($objectNumber === 4) {
            $rows .= $xrefRow(2, 6, 0);
            continue;
        }
        if ($objectNumber === 12) {
            $rows .= $xrefRow(2, 6, 1);
            continue;
        }
        if ($objectNumber === 16) {
            $rows .= $xrefRow(2, 6, 2);
            continue;
        }
        if ($objectNumber === 20) {
            $rows .= $xrefRow(1, $xrefOffset, 0);
            continue;
        }
        $rows .= isset($offsets[$objectNumber]) ? $xrefRow(1, $offsets[$objectNumber], 0) : $xrefRow(0, 0, 0);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress whitespace attachment xref stream smoke fixture.');
    }

    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $currentPayload, $decoyPayload];
};

$buildMetadataPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Current whitespace metadata object-stream page) Tj ET';
    $prefixMember = '<< /Type /Review /Note (unowned prefix before whitespace viewer preferences) >>';
    $viewerPreferences = '<< /DisplayDocTitle true /Direction /R2L /PrintScaling /None /NumCopies 3 >>';
    $reviewMember = '<< /Type /Review /Note (valid member after whitespace viewer preferences) >>';
    $badOffset = strlen($prefixMember);
    $reviewOffset = strlen($prefixMember . "\n" . $viewerPreferences . "\n");
    $header = '12 ' . $badOffset . ' 16 ' . $reviewOffset;
    $objectStream = gzcompress($header . "\n" . $prefixMember . "\n" . $viewerPreferences . "\n" . $reviewMember . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress whitespace metadata object stream smoke fixture.');
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /PageLayout /SinglePage /PageMode /UseOutlines /ViewerPreferences 12 0 R >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
    $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber <= 20; $objectNumber++) {
        if ($objectNumber === 0) {
            $rows .= $xrefRow(0, 0, 255);
            continue;
        }
        if ($objectNumber === 12) {
            $rows .= $xrefRow(2, 6, 0);
            continue;
        }
        if ($objectNumber === 16) {
            $rows .= $xrefRow(2, 6, 1);
            continue;
        }
        if ($objectNumber === 20) {
            $rows .= $xrefRow(1, $xrefOffset, 0);
            continue;
        }
        $rows .= isset($offsets[$objectNumber]) ? $xrefRow(1, $offsets[$objectNumber], 0) : $xrefRow(0, 0, 0);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress whitespace metadata xref stream smoke fixture.');
    }

    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

[$attachmentPdf, $currentPayload, $decoyPayload] = $buildAttachmentPdf();
$metadataPdf = $buildMetadataPdf();

$textExtractor = new PdfTextExtractor();
$summary = (new PdfAttachmentExtractor())->attachmentSummary($attachmentPdf);
$embedded = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($attachmentPdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($metadataPdf);
$attachmentReview = $textExtractor->extractXrefObjectStreamIndexReview($attachmentPdf);
$metadataReview = $textExtractor->extractXrefObjectStreamIndexReview($metadataPdf);
$attachmentSummaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$embeddedJson = json_encode($embedded, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? null;

$smoke = [
    'native_boundary' => 'xref-selected object-stream members must start on object tokens, not PDF whitespace before a token',
    'current_attachment_kept' => ($summary['attachment_count'] ?? null) === 1
        && is_array($attachment)
        && ($attachment['filename'] ?? null) === 'current-whitespace-offset.csv',
    'decoy_attachment_excluded' => !str_contains($attachmentSummaryJson, 'decoy-whitespace-offset.csv')
        && !str_contains($embeddedJson, 'decoy-whitespace-offset.csv')
        && !str_contains($attachmentSummaryJson, $decoyPayload),
    'current_payload_available_to_embedded_review' => ($embedded[0]['content'] ?? null) === $currentPayload,
    'payload_bytes_omitted_from_attachment_summary' => is_array($attachment)
        && !array_key_exists('bytes', $attachment)
        && !str_contains($attachmentSummaryJson, $currentPayload),
    'catalog_metadata_kept' => ($metadata['language'] ?? null) === 'en-US'
        && ($metadata['page_layout'] ?? null) === 'SinglePage',
    'whitespace_viewer_preferences_excluded' => !array_key_exists('viewer_preferences', $metadata)
        && !str_contains($metadataJson, 'display_doc_title')
        && !str_contains($metadataJson, 'R2L'),
    'attachment_invalid_member_offset_rejection_count' => $attachmentReview['invalid_member_offset_rejection_count'] ?? null,
    'metadata_invalid_member_offset_rejection_count' => $metadataReview['invalid_member_offset_rejection_count'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'current_attachment_kept',
    'decoy_attachment_excluded',
    'current_payload_available_to_embedded_review',
    'payload_bytes_omitted_from_attachment_summary',
    'catalog_metadata_kept',
    'whitespace_viewer_preferences_excluded',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Whitespace object-stream attachment/metadata smoke failed: ' . $requiredFlag);
    }
}

echo '<!-- markerpdf-xref-object-stream-whitespace-attachment-metadata-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($textExtractor->extractPlainText($attachmentPdf), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
echo "<!-- wp:list -->\n<ul>\n";
echo '<li data-marker-attachment-checksum="'
    . htmlspecialchars((string) ($attachment['checksum_hex'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">'
    . htmlspecialchars(
        (string) ($attachment['filename'] ?? 'attachment')
        . ' - ' . (string) ($attachment['relationship'] ?? 'unassociated')
        . ', ' . (string) ($attachment['byte_length'] ?? 0) . ' bytes',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    )
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars('Catalog metadata: en-US; whitespace-owned viewer preferences excluded', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
