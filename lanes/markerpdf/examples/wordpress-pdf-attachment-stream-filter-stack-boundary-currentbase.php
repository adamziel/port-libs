<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

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
