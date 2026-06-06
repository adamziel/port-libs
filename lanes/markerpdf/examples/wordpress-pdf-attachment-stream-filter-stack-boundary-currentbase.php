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

$lzwLiteralEncode = static function (string $bytes, bool $includeEndCode = true, string $suffix = ''): string {
    if (strlen($bytes) > 240) {
        throw new RuntimeException('Focused attachment LZW smoke fixture must keep 9-bit literal codes.');
    }

    $codes = array_merge([256], array_map('ord', str_split($bytes)));
    if ($includeEndCode) {
        $codes[] = 257;
    }

    $bits = '';
    foreach ($codes as $code) {
        for ($shift = 8; $shift >= 0; $shift--) {
            $bits .= (($code >> $shift) & 1) === 1 ? '1' : '0';
        }
    }

    $encoded = '';
    for ($offset = 0, $length = strlen($bits); $offset < $length; $offset += 8) {
        $byte = substr($bits, $offset, 8);
        if (strlen($byte) < 8) {
            $byte = str_pad($byte, 8, '0');
        }
        $encoded .= chr(bindec($byte));
    }

    return $encoded . $suffix;
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

$lzwPayload = "Title,Status\nLZW Flate Stacked Attachment,Ready\n";
$lzwCompressed = gzcompress($lzwPayload);
$lzwSurplusPayload = "Title,Status\nLZW Surplus Stacked Attachment,Blocked\n";
$lzwSurplusCompressed = gzcompress($lzwSurplusPayload);
if (!is_string($lzwCompressed) || !is_string($lzwSurplusCompressed)) {
    throw new RuntimeException('Unable to compress LZW attachment smoke payloads.');
}
$lzwEncodedPayload = $lzwLiteralEncode($lzwCompressed);
$lzwSurplusEncodedPayload = $lzwLiteralEncode(
    $lzwSurplusCompressed,
    true,
    'BT /F1 12 Tf 72 680 Td (LZW attachment surplus smoke bytes) Tj ET'
);
$lzwChecksum = md5($lzwPayload);
$lzwSurplusChecksum = md5($lzwSurplusPayload);
$lzwPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(lzw-stack.csv) 10 0 R (lzw-surplus.csv) 12 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (lzw-stack.csv) /Desc (LZW Flate stacked attachment) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /LZWDecode /FlateDecode ] /DecodeParms [ << /EarlyChange 0 >> null ] /Params << /Size " . strlen($lzwPayload) . " /CheckSum <{$lzwChecksum}> >> /Length " . strlen($lzwEncodedPayload) . " >>\nstream\n{$lzwEncodedPayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (lzw-surplus.csv) /Desc (LZW surplus stacked attachment) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /LZWDecode /FlateDecode ] /DecodeParms [ << /EarlyChange 0 >> null ] /Params << /Size " . strlen($lzwSurplusPayload) . " /CheckSum <{$lzwSurplusChecksum}> >> /Length " . strlen($lzwSurplusEncodedPayload) . " >>\nstream\n{$lzwSurplusEncodedPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$lzwSummary = (new PdfAttachmentExtractor())->attachmentSummary($lzwPdf);
$lzwFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($lzwPdf);
$lzwText = (new PdfTextExtractor())->extractPlainText($lzwPdf);
$lzwSummaryJson = json_encode($lzwSummary, JSON_UNESCAPED_SLASHES);
$lzwFilesJson = json_encode($lzwFiles, JSON_UNESCAPED_SLASHES);
if ($lzwSummaryJson === false || $lzwFilesJson === false) {
    throw new RuntimeException('Expected LZW attachment summary JSON.');
}

$extraDecodeParmsVisible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Extra Params Review) Tj ET';
$extraDecodeParmsPayload = "Title,Status\nExtra DecodeParms Attachment Leak,Blocked\n";
$validAfterDecodeParmsPayload = "Title,Status\nValid Attachment After DecodeParms,Ready\n";
$extraDecodeParmsEncoded = $ascii85Encode(gzcompress($extraDecodeParmsPayload));
$validAfterDecodeParmsEncoded = $ascii85Encode(gzcompress($validAfterDecodeParmsPayload));
$extraDecodeParmsPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($extraDecodeParmsVisible) . " >>\nstream\n{$extraDecodeParmsVisible}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(extra-decodeparms.csv) 10 0 R (valid-after-decodeparms.csv) 12 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (extra-decodeparms.csv) /Desc (Extra DecodeParms attachment leak) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null << /Predictor 1 >> ] /Params << /Size " . strlen($extraDecodeParmsPayload) . " /CheckSum <" . md5($extraDecodeParmsPayload) . "> >> /Length " . strlen($extraDecodeParmsEncoded) . " >>\nstream\n{$extraDecodeParmsEncoded}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (valid-after-decodeparms.csv) /Desc (Valid attachment after extra DecodeParms) /AFRelationship /Source /EF << /F 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($validAfterDecodeParmsPayload) . " /CheckSum <" . md5($validAfterDecodeParmsPayload) . "> >> /Length " . strlen($validAfterDecodeParmsEncoded) . " >>\nstream\n{$validAfterDecodeParmsEncoded}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$extraDecodeParmsSummary = (new PdfAttachmentExtractor())->attachmentSummary($extraDecodeParmsPdf);
$extraDecodeParmsFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($extraDecodeParmsPdf);
$extraDecodeParmsText = (new PdfTextExtractor())->extractPlainText($extraDecodeParmsPdf);
$extraDecodeParmsSummaryJson = json_encode($extraDecodeParmsSummary, JSON_UNESCAPED_SLASHES);
$extraDecodeParmsFilesJson = json_encode($extraDecodeParmsFiles, JSON_UNESCAPED_SLASHES);
if ($extraDecodeParmsSummaryJson === false || $extraDecodeParmsFilesJson === false) {
    throw new RuntimeException('Expected extra DecodeParms attachment summary JSON.');
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
    'lzw_attachment_count' => $lzwSummary['attachment_count'] ?? null,
    'lzw_filter_stack_decoded' => ($lzwSummary['attachment_count'] ?? null) === 1
        && ($lzwSummary['attachments'][0]['filename'] ?? null) === 'lzw-stack.csv'
        && ($lzwSummary['attachments'][0]['checksum_matches'] ?? false) === true
        && (($lzwFiles[0]['content'] ?? null) === $lzwPayload),
    'lzw_filters' => $lzwSummary['attachments'][0]['filters'] ?? [],
    'lzw_payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $lzwSummary['attachments'][0] ?? []),
    'lzw_surplus_attachment_rejected' => !str_contains($lzwSummaryJson, 'lzw-surplus.csv')
        && !str_contains($lzwFilesJson, 'lzw-surplus.csv'),
    'lzw_surplus_payload_excluded' => !str_contains($lzwSummaryJson, 'LZW Surplus Stacked Attachment')
        && !str_contains($lzwFilesJson, 'LZW Surplus Stacked Attachment')
        && !str_contains($lzwText, 'LZW Surplus Stacked Attachment')
        && !str_contains($lzwText, 'LZW attachment surplus smoke bytes'),
    'extra_decodeparms_attachment_rejected' => ($extraDecodeParmsSummary['attachment_count'] ?? null) === 1
        && ($extraDecodeParmsSummary['attachments'][0]['filename'] ?? null) === 'valid-after-decodeparms.csv'
        && count($extraDecodeParmsFiles) === 1
        && (($extraDecodeParmsFiles[0]['content'] ?? null) === $validAfterDecodeParmsPayload),
    'extra_decodeparms_payload_excluded' => !str_contains($extraDecodeParmsSummaryJson, 'extra-decodeparms.csv')
        && !str_contains($extraDecodeParmsSummaryJson, 'Extra DecodeParms Attachment Leak')
        && !str_contains($extraDecodeParmsFilesJson, 'extra-decodeparms.csv')
        && !str_contains($extraDecodeParmsFilesJson, 'Extra DecodeParms Attachment Leak')
        && !str_contains($extraDecodeParmsText, 'Extra DecodeParms Attachment Leak'),
    'valid_attachment_after_extra_decodeparms_preserved' => ($extraDecodeParmsSummary['attachments'][0]['checksum_matches'] ?? false) === true
        && (($extraDecodeParmsFiles[0]['computed_checksum'] ?? null) === md5($validAfterDecodeParmsPayload)),
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
