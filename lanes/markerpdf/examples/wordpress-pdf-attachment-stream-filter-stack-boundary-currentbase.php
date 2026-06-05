<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ascii85Encode = static function (string $bytes): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($value === 0 && $chunkLength === 4) {
            $encoded .= 'z';
            continue;
        }

        $chars = '';
        for ($index = 0; $index < 5; $index++) {
            $chars = chr(($value % 85) + 33) . $chars;
            $value = intdiv($value, 85);
        }

        $encoded .= substr($chars, 0, $chunkLength + 1);
    }

    return $encoded . '~>';
};

$payload = "Title,Status\nIdentity Crypt Stacked Attachment,Ready\n";
$encodedPayload = $ascii85Encode(gzcompress($payload));
$checksum = md5($payload);
$privatePayload = "Title,Status\nPrivate Crypt Stacked Attachment,Blocked\n";
$privateEncodedPayload = $ascii85Encode(gzcompress($privatePayload));
$privateChecksum = md5($privatePayload);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Names [(stacked.csv) 4 0 R (private-stack.csv) 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Filespec /F (stacked.csv) /Desc (Identity Crypt stacked filter attachment) /AFRelationship /Data /EF << /F 5 0 R >> >>\nendobj\n"
    . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /Crypt /ASCII85Decode /FlateDecode ] /DecodeParms [ << /Name /Identity >> null null ] /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($encodedPayload) . " >>\n"
    . "stream\n{$encodedPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Filespec /F (private-stack.csv) /Desc (Private Crypt stacked filter attachment) /AFRelationship /Data /EF << /F 7 0 R >> >>\nendobj\n"
    . "7 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /Crypt /ASCII85Decode /FlateDecode ] /DecodeParms [ << /Name /PrivateCF >> null null ] /Params << /Size " . strlen($privatePayload) . " /CheckSum <{$privateChecksum}> >> /Length " . strlen($privateEncodedPayload) . " >>\n"
    . "stream\n{$privateEncodedPayload}\nendstream\nendobj\n"
    . "%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$attachment = $summary['attachments'][0] ?? [];
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
if (!is_array($attachment) || $summaryJson === false) {
    throw new RuntimeException('Expected stacked attachment summary row.');
}

$visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Malformed Filter Review) Tj ET';
$malformedPayload = "Title,Status\nDictionary Filter Attachment Leak,Blocked\n";
$malformedChecksum = md5($malformedPayload);
$malformedFilterPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(dict-filter.csv) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (dict-filter.csv) /Desc (Malformed dictionary filter attachment) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter << /Name /FlateDecode >> /Params << /Size " . strlen($malformedPayload) . " /CheckSum <{$malformedChecksum}> >> /Length " . strlen($malformedPayload) . " >>\nstream\n{$malformedPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$malformedSummary = (new PdfAttachmentExtractor())->attachmentSummary($malformedFilterPdf);
$malformedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($malformedFilterPdf);
$malformedText = (new PdfTextExtractor())->extractPlainText($malformedFilterPdf);
$malformedSummaryJson = json_encode($malformedSummary, JSON_UNESCAPED_SLASHES);
if ($malformedSummaryJson === false) {
    throw new RuntimeException('Expected malformed attachment summary JSON.');
}

$metadata = [
    'native_boundary' => 'WordPress attachment preflight stream-filter stack decoding',
    'attachment_count' => $summary['attachment_count'] ?? null,
    'total_bytes' => $summary['total_bytes'] ?? null,
    'filename' => $attachment['filename'] ?? null,
    'filters' => $attachment['filters'] ?? [],
    'identity_crypt_stage_applied' => in_array('Crypt', $attachment['filters'] ?? [], true)
        && ($attachment['checksum_matches'] ?? false) === true,
    'private_crypt_payload_suppressed' => !str_contains($summaryJson, 'private-stack.csv')
        && !str_contains($summaryJson, 'Private Crypt Stacked Attachment'),
    'declared_size_matches' => $attachment['declared_size_matches'] ?? false,
    'checksum_matches' => $attachment['checksum_matches'] ?? false,
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $attachment),
    'payload_content_exposed' => str_contains($summaryJson, 'Identity Crypt Stacked Attachment')
        || str_contains($summaryJson, 'Private Crypt Stacked Attachment'),
    'dictionary_filter_attachment_rejected' => ($malformedSummary['attachment_count'] ?? null) === 0
        && $malformedFiles === [],
    'dictionary_filter_filename_excluded' => !str_contains($malformedSummaryJson, 'dict-filter.csv'),
    'dictionary_filter_payload_excluded' => !str_contains($malformedSummaryJson, 'Dictionary Filter Attachment Leak')
        && !str_contains(json_encode($malformedFiles, JSON_UNESCAPED_SLASHES) ?: '', 'Dictionary Filter Attachment Leak'),
    'dictionary_filter_visible_text_preserved' => $malformedText === 'Visible Attachment Malformed Filter Review',
    'executes_python_or_models' => $summary['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'] ?? null,
];

echo '<!-- markerpdf:attachment-stream-filter-stack-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'stacked.csv attachment checksum verified without exposing payload bytes',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
