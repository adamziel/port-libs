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

$commentPayload = "Title,Status\nEOD Comment Attachment,Ready\n";
$commentCompressed = gzcompress($commentPayload);
if (!is_string($commentCompressed)) {
    throw new RuntimeException('Unable to compress EOD-comment attachment smoke payload.');
}
$commentEncodedPayload = $ascii85Encode($commentCompressed)
    . '% attachment filter comment reaches the stream boundary';
$commentVisible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment EOD Comment Review) Tj ET';
$commentPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($commentVisible) . " >>\nstream\n{$commentVisible}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(eod-comment-stack.csv) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (eod-comment-stack.csv) /Desc (EOD comment attachment stack) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($commentPayload) . " /CheckSum <" . md5($commentPayload) . "> >> /Length " . strlen($commentEncodedPayload) . " >>\nstream\n{$commentEncodedPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$commentSummary = (new PdfAttachmentExtractor())->attachmentSummary($commentPdf);
$commentFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($commentPdf);
$commentText = (new PdfTextExtractor())->extractPlainText($commentPdf);
$commentSummaryJson = json_encode($commentSummary, JSON_UNESCAPED_SLASHES);
$commentFilesJson = json_encode($commentFiles, JSON_UNESCAPED_SLASHES);
if ($commentSummaryJson === false || $commentFilesJson === false) {
    throw new RuntimeException('Expected EOD-comment attachment summary JSON.');
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

$duplicateFilterPayload = "Title,Status\nDuplicate Filter Attachment Smoke Leak,Blocked\n";
$duplicateDecodeParmsPayload = "Title,Status\nDuplicate DecodeParms Attachment Smoke Leak,Blocked\n";
$validAfterDuplicateKeysPayload = "Title,Status\nValid Attachment After Duplicate Keys,Ready\n";
$duplicateFilterEncoded = $ascii85Encode(gzcompress($duplicateFilterPayload));
$duplicateDecodeParmsEncoded = $ascii85Encode(gzcompress($duplicateDecodeParmsPayload));
$validAfterDuplicateKeysEncoded = $ascii85Encode(gzcompress($validAfterDuplicateKeysPayload));
$duplicateStreamKeyVisible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Duplicate Stream Key Review) Tj ET';
$duplicateStreamKeyPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($duplicateStreamKeyVisible) . " >>\nstream\n{$duplicateStreamKeyVisible}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(duplicate-filter.csv) 10 0 R (duplicate-decodeparms.csv) 12 0 R (valid-after-duplicates.csv) 14 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (duplicate-filter.csv) /Desc (Duplicate Filter attachment stream) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter /FlateDecode /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($duplicateFilterPayload) . " /CheckSum <" . md5($duplicateFilterPayload) . "> >> /Length " . strlen($duplicateFilterEncoded) . " >>\nstream\n{$duplicateFilterEncoded}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (duplicate-decodeparms.csv) /Desc (Duplicate DecodeParms attachment stream) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null << /Predictor 99 /Columns 1 >> ] /DecodeParms [ null null ] /Params << /Size " . strlen($duplicateDecodeParmsPayload) . " /CheckSum <" . md5($duplicateDecodeParmsPayload) . "> >> /Length " . strlen($duplicateDecodeParmsEncoded) . " >>\nstream\n{$duplicateDecodeParmsEncoded}\nendstream\nendobj\n"
    . "14 0 obj\n<< /Type /Filespec /F (valid-after-duplicates.csv) /Desc (Valid attachment after duplicate stream keys) /AFRelationship /Source /EF << /F 15 0 R >> >>\nendobj\n"
    . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($validAfterDuplicateKeysPayload) . " /CheckSum <" . md5($validAfterDuplicateKeysPayload) . "> >> /Length " . strlen($validAfterDuplicateKeysEncoded) . " >>\nstream\n{$validAfterDuplicateKeysEncoded}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$duplicateStreamKeySummary = (new PdfAttachmentExtractor())->attachmentSummary($duplicateStreamKeyPdf);
$duplicateStreamKeyFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($duplicateStreamKeyPdf);
$duplicateStreamKeyText = (new PdfTextExtractor())->extractPlainText($duplicateStreamKeyPdf);
$duplicateStreamKeySummaryJson = json_encode($duplicateStreamKeySummary, JSON_UNESCAPED_SLASHES);
$duplicateStreamKeyFilesJson = json_encode($duplicateStreamKeyFiles, JSON_UNESCAPED_SLASHES);
if ($duplicateStreamKeySummaryJson === false || $duplicateStreamKeyFilesJson === false) {
    throw new RuntimeException('Expected duplicate stream-key attachment summary JSON.');
}

$allNullPayload = "Title,Status\nAll Null Attachment Smoke,Ready\n";
$allNullChecksum = md5($allNullPayload);
$allNullVisible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment All Null Stack Review) Tj ET';
$allNullPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($allNullVisible) . " >>\nstream\n{$allNullVisible}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(all-null-attachment.csv) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (all-null-attachment.csv) /Desc (All-null attachment filter stack) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ null ] /DecodeParms [ 99 0 R 100 0 R ] /Params << /Size " . strlen($allNullPayload) . " /CheckSum <{$allNullChecksum}> >> /Length " . strlen($allNullPayload) . " >>\nstream\n{$allNullPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "99 0 obj\n<< /Predictor 12 /Columns 5 >>\nendobj\n"
    . "100 0 obj\n(All Null Attachment DecodeParms Smoke Leak)\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$allNullSummary = (new PdfAttachmentExtractor())->attachmentSummary($allNullPdf);
$allNullFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($allNullPdf);
$allNullText = (new PdfTextExtractor())->extractPlainText($allNullPdf);
$allNullSummaryJson = json_encode($allNullSummary, JSON_UNESCAPED_SLASHES);
$allNullFilesJson = json_encode($allNullFiles, JSON_UNESCAPED_SLASHES);
if ($allNullSummaryJson === false || $allNullFilesJson === false) {
    throw new RuntimeException('Expected all-null attachment summary JSON.');
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

$duplicatePredictorParameterPayload = "Title,Status\nDuplicate Predictor Parameter Attachment Smoke Leak,Blocked\n";
$duplicateCryptNamePayload = "Title,Status\nDuplicate Crypt Name Attachment Smoke Leak,Blocked\n";
$validAfterParameterDuplicatesPayload = "Title,Status\nValid Attachment After Parameter Duplicates,Ready\n";
$duplicatePredictorParameterEncoded = $ascii85Encode(gzcompress($duplicatePredictorParameterPayload));
$duplicateCryptNameEncoded = $ascii85Encode(gzcompress($duplicateCryptNamePayload));
$validAfterParameterDuplicatesEncoded = $ascii85Encode(gzcompress($validAfterParameterDuplicatesPayload));
$duplicateParameterVisible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment DecodeParms Parameter Review) Tj ET';
$duplicateParameterPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($duplicateParameterVisible) . " >>\nstream\n{$duplicateParameterVisible}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(duplicate-predictor-parameter.csv) 10 0 R (duplicate-crypt-name.csv) 12 0 R (valid-after-parameter-duplicates.csv) 14 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (duplicate-predictor-parameter.csv) /Desc (Duplicate predictor parameter attachment) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null << /Predictor 99 /Predictor 1 /Columns 1 >> ] /Params << /Size " . strlen($duplicatePredictorParameterPayload) . " /CheckSum <" . md5($duplicatePredictorParameterPayload) . "> >> /Length " . strlen($duplicatePredictorParameterEncoded) . " >>\nstream\n{$duplicatePredictorParameterEncoded}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (duplicate-crypt-name.csv) /Desc (Duplicate Crypt Name attachment) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /Crypt /ASCII85Decode /FlateDecode ] /DecodeParms [ << /Name /PrivateCF /Name /Identity >> null null ] /Params << /Size " . strlen($duplicateCryptNamePayload) . " /CheckSum <" . md5($duplicateCryptNamePayload) . "> >> /Length " . strlen($duplicateCryptNameEncoded) . " >>\nstream\n{$duplicateCryptNameEncoded}\nendstream\nendobj\n"
    . "14 0 obj\n<< /Type /Filespec /F (valid-after-parameter-duplicates.csv) /Desc (Valid attachment after duplicate DecodeParms parameters) /AFRelationship /Source /EF << /F 15 0 R >> >>\nendobj\n"
    . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($validAfterParameterDuplicatesPayload) . " /CheckSum <" . md5($validAfterParameterDuplicatesPayload) . "> >> /Length " . strlen($validAfterParameterDuplicatesEncoded) . " >>\nstream\n{$validAfterParameterDuplicatesEncoded}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$duplicateParameterSummary = (new PdfAttachmentExtractor())->attachmentSummary($duplicateParameterPdf);
$duplicateParameterFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($duplicateParameterPdf);
$duplicateParameterText = (new PdfTextExtractor())->extractPlainText($duplicateParameterPdf);
$duplicateParameterSummaryJson = json_encode($duplicateParameterSummary, JSON_UNESCAPED_SLASHES);
$duplicateParameterFilesJson = json_encode($duplicateParameterFiles, JSON_UNESCAPED_SLASHES);
if ($duplicateParameterSummaryJson === false || $duplicateParameterFilesJson === false) {
    throw new RuntimeException('Expected duplicate DecodeParms parameter attachment summary JSON.');
}

$indirectOperandVisible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Indirect Operand Review) Tj ET';
$indirectOperandPayload = "Title,Status\nIndirect Filter Operand Attachment,Ready\n";
$indirectOperandPredicted = "\0" . $indirectOperandPayload;
$indirectOperandCompressed = gzcompress($indirectOperandPredicted);
if (!is_string($indirectOperandCompressed)) {
    throw new RuntimeException('Unable to compress indirect filter operand attachment payload.');
}
$indirectOperandEncoded = $ascii85Encode($indirectOperandCompressed);
$cyclicOperandPayload = "Title,Status\nCyclic Filter Operand Attachment Leak,Blocked\n";
$indirectOperandPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 50 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($indirectOperandVisible) . " >>\nstream\n{$indirectOperandVisible}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(indirect-filter-stack.csv) 10 0 R (cycle-filter-stack.csv) 12 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (indirect-filter-stack.csv) /Desc (Indirect filter operand attachment stack) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ 20 0 R 21 0 R ] /DecodeParms 30 0 R /Params << /Size " . strlen($indirectOperandPayload) . " /CheckSum <" . md5($indirectOperandPayload) . "> >> /Length " . strlen($indirectOperandEncoded) . " >>\nstream\n{$indirectOperandEncoded}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (cycle-filter-stack.csv) /Desc (Cyclic filter operand attachment stack) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter 40 0 R /Params << /Size " . strlen($cyclicOperandPayload) . " /CheckSum <" . md5($cyclicOperandPayload) . "> >> /Length " . strlen($cyclicOperandPayload) . " >>\nstream\n{$cyclicOperandPayload}\nendstream\nendobj\n"
    . "20 0 obj\n22 0 R\nendobj\n"
    . "21 0 obj\n23 0 R\nendobj\n"
    . "22 0 obj\n/ASCII85Decode\nendobj\n"
    . "23 0 obj\n/FlateDecode\nendobj\n"
    . "30 0 obj\n31 0 R\nendobj\n"
    . "31 0 obj\n[ null 32 0 R ]\nendobj\n"
    . "32 0 obj\n33 0 R\nendobj\n"
    . "33 0 obj\n<< /Predictor 12 /Columns " . strlen($indirectOperandPayload) . " >>\nendobj\n"
    . "40 0 obj\n41 0 R\nendobj\n"
    . "41 0 obj\n40 0 R\nendobj\n"
    . "50 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$indirectOperandSummary = (new PdfAttachmentExtractor())->attachmentSummary($indirectOperandPdf);
$indirectOperandFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($indirectOperandPdf);
$indirectOperandText = (new PdfTextExtractor())->extractPlainText($indirectOperandPdf);
$indirectOperandSummaryJson = json_encode($indirectOperandSummary, JSON_UNESCAPED_SLASHES);
$indirectOperandFilesJson = json_encode($indirectOperandFiles, JSON_UNESCAPED_SLASHES);
if ($indirectOperandSummaryJson === false || $indirectOperandFilesJson === false) {
    throw new RuntimeException('Expected indirect filter operand attachment summary JSON.');
}

$shortLengthVisible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Short Length Stack Review) Tj ET';
$shortLengthPayload = "Title,Status\nShort Length Stacked Attachment,Ready\n";
$shortLengthCompressed = gzcompress($shortLengthPayload);
$shortLengthSurplusPayload = "Title,Status\nShort Length Surplus Stacked Attachment,Blocked\n";
$shortLengthSurplusCompressed = gzcompress($shortLengthSurplusPayload);
if (!is_string($shortLengthCompressed) || !is_string($shortLengthSurplusCompressed)) {
    throw new RuntimeException('Unable to compress short-length attachment smoke payloads.');
}
$shortLengthEncoded = $ascii85Encode($shortLengthCompressed);
$shortLengthSurplusCleanEncoded = $ascii85Encode($shortLengthSurplusCompressed);
$shortLengthSurplusEncoded = $shortLengthSurplusCleanEncoded
    . 'BT /F1 12 Tf 72 680 Td (short length attachment surplus smoke bytes) Tj ET';
$shortLengthPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($shortLengthVisible) . " >>\nstream\n{$shortLengthVisible}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(short-length-stack.csv) 10 0 R (short-length-surplus.csv) 12 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (short-length-stack.csv) /Desc (Short declared attachment stream length) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($shortLengthPayload) . " /CheckSum <" . md5($shortLengthPayload) . "> >> /Length " . max(0, strlen($shortLengthEncoded) - 7) . " >>\nstream\n{$shortLengthEncoded}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (short-length-surplus.csv) /Desc (Short declared surplus attachment stream length) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($shortLengthSurplusPayload) . " /CheckSum <" . md5($shortLengthSurplusPayload) . "> >> /Length " . max(0, strlen($shortLengthSurplusCleanEncoded) - 7) . " >>\nstream\n{$shortLengthSurplusEncoded}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$shortLengthSummary = (new PdfAttachmentExtractor())->attachmentSummary($shortLengthPdf);
$shortLengthFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($shortLengthPdf);
$shortLengthText = (new PdfTextExtractor())->extractPlainText($shortLengthPdf);
$shortLengthSummaryJson = json_encode($shortLengthSummary, JSON_UNESCAPED_SLASHES);
$shortLengthFilesJson = json_encode($shortLengthFiles, JSON_UNESCAPED_SLASHES);
if ($shortLengthSummaryJson === false || $shortLengthFilesJson === false) {
    throw new RuntimeException('Expected short-length attachment summary JSON.');
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
    'eod_comment_attachment_decoded' => ($commentSummary['attachment_count'] ?? null) === 1
        && ($commentSummary['attachments'][0]['filename'] ?? null) === 'eod-comment-stack.csv'
        && ($commentSummary['attachments'][0]['checksum_matches'] ?? false) === true
        && (($commentFiles[0]['content'] ?? null) === $commentPayload),
    'eod_comment_filters' => $commentSummary['attachments'][0]['filters'] ?? [],
    'eod_comment_payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $commentSummary['attachments'][0] ?? []),
    'eod_comment_payload_excluded' => !str_contains($commentSummaryJson, 'EOD Comment Attachment')
        && !str_contains($commentSummaryJson, 'attachment filter comment')
        && !str_contains($commentFilesJson, 'attachment filter comment')
        && !str_contains($commentText, 'EOD Comment Attachment')
        && !str_contains($commentText, 'attachment filter comment'),
    'eod_comment_visible_text_preserved' => $commentText === 'Visible Attachment EOD Comment Review',
    'dictionary_filter_attachment_rejected' => ($malformedSummary['attachment_count'] ?? null) === 0
        && $malformedFiles === [],
    'dictionary_filter_filename_excluded' => !str_contains($malformedSummaryJson, 'dict-filter.csv'),
    'dictionary_filter_payload_excluded' => !str_contains($malformedSummaryJson, 'Dictionary Filter Attachment Leak')
        && !str_contains(json_encode($malformedFiles, JSON_UNESCAPED_SLASHES) ?: '', 'Dictionary Filter Attachment Leak'),
    'dictionary_filter_visible_text_preserved' => $malformedText === 'Visible Attachment Malformed Filter Review',
    'duplicate_stream_key_attachments_rejected' => ($duplicateStreamKeySummary['attachment_count'] ?? null) === 1
        && ($duplicateStreamKeySummary['attachments'][0]['filename'] ?? null) === 'valid-after-duplicates.csv'
        && count($duplicateStreamKeyFiles) === 1
        && (($duplicateStreamKeyFiles[0]['content'] ?? null) === $validAfterDuplicateKeysPayload),
    'duplicate_filter_stream_rejected' => !str_contains($duplicateStreamKeySummaryJson, 'duplicate-filter.csv')
        && !str_contains($duplicateStreamKeyFilesJson, 'duplicate-filter.csv'),
    'duplicate_decodeparms_stream_rejected' => !str_contains($duplicateStreamKeySummaryJson, 'duplicate-decodeparms.csv')
        && !str_contains($duplicateStreamKeyFilesJson, 'duplicate-decodeparms.csv'),
    'duplicate_stream_key_payload_excluded' => !str_contains($duplicateStreamKeySummaryJson, 'Duplicate Filter Attachment Smoke Leak')
        && !str_contains($duplicateStreamKeySummaryJson, 'Duplicate DecodeParms Attachment Smoke Leak')
        && !str_contains($duplicateStreamKeyFilesJson, 'Duplicate Filter Attachment Smoke Leak')
        && !str_contains($duplicateStreamKeyFilesJson, 'Duplicate DecodeParms Attachment Smoke Leak')
        && !str_contains($duplicateStreamKeyText, 'Duplicate Filter Attachment Smoke Leak')
        && !str_contains($duplicateStreamKeyText, 'Duplicate DecodeParms Attachment Smoke Leak'),
    'duplicate_stream_key_visible_text_preserved' => $duplicateStreamKeyText === 'Visible Attachment Duplicate Stream Key Review',
    'all_null_attachment_decoded' => ($allNullSummary['attachment_count'] ?? null) === 1
        && ($allNullSummary['attachments'][0]['filename'] ?? null) === 'all-null-attachment.csv'
        && (($allNullSummary['attachments'][0]['filters'] ?? []) === [])
        && ($allNullSummary['attachments'][0]['checksum_matches'] ?? false) === true
        && (($allNullFiles[0]['content'] ?? null) === $allNullPayload),
    'all_null_decodeparms_ignored' => !str_contains($allNullSummaryJson, 'All Null Attachment DecodeParms Smoke Leak')
        && !str_contains($allNullFilesJson, 'All Null Attachment DecodeParms Smoke Leak'),
    'all_null_payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $allNullSummary['attachments'][0] ?? []),
    'all_null_visible_text_preserved' => $allNullText === 'Visible Attachment All Null Stack Review',
    'all_null_payload_excluded_from_visible_text' => !str_contains($allNullText, 'All Null Attachment Smoke')
        && !str_contains($allNullText, 'DecodeParms'),
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
    'duplicate_decodeparms_parameter_attachments_rejected' => ($duplicateParameterSummary['attachment_count'] ?? null) === 1
        && ($duplicateParameterSummary['attachments'][0]['filename'] ?? null) === 'valid-after-parameter-duplicates.csv'
        && count($duplicateParameterFiles) === 1
        && (($duplicateParameterFiles[0]['content'] ?? null) === $validAfterParameterDuplicatesPayload),
    'duplicate_predictor_parameter_rejected' => !str_contains($duplicateParameterSummaryJson, 'duplicate-predictor-parameter.csv')
        && !str_contains($duplicateParameterFilesJson, 'duplicate-predictor-parameter.csv'),
    'duplicate_crypt_name_parameter_rejected' => !str_contains($duplicateParameterSummaryJson, 'duplicate-crypt-name.csv')
        && !str_contains($duplicateParameterFilesJson, 'duplicate-crypt-name.csv'),
    'duplicate_decodeparms_parameter_payload_excluded' => !str_contains($duplicateParameterSummaryJson, 'Duplicate Predictor Parameter Attachment Smoke Leak')
        && !str_contains($duplicateParameterSummaryJson, 'Duplicate Crypt Name Attachment Smoke Leak')
        && !str_contains($duplicateParameterFilesJson, 'Duplicate Predictor Parameter Attachment Smoke Leak')
        && !str_contains($duplicateParameterFilesJson, 'Duplicate Crypt Name Attachment Smoke Leak')
        && !str_contains($duplicateParameterText, 'Duplicate Predictor Parameter Attachment Smoke Leak')
        && !str_contains($duplicateParameterText, 'Duplicate Crypt Name Attachment Smoke Leak'),
    'duplicate_decodeparms_parameter_visible_text_preserved' => $duplicateParameterText === 'Visible Attachment DecodeParms Parameter Review',
    'indirect_operand_attachment_decoded' => ($indirectOperandSummary['attachment_count'] ?? null) === 1
        && ($indirectOperandSummary['attachments'][0]['filename'] ?? null) === 'indirect-filter-stack.csv'
        && ($indirectOperandSummary['attachments'][0]['checksum_matches'] ?? false) === true
        && (($indirectOperandFiles[0]['content'] ?? null) === $indirectOperandPayload),
    'indirect_operand_filters' => $indirectOperandSummary['attachments'][0]['filters'] ?? [],
    'indirect_operand_payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $indirectOperandSummary['attachments'][0] ?? []),
    'cyclic_filter_operand_rejected' => !str_contains($indirectOperandSummaryJson, 'cycle-filter-stack.csv')
        && !str_contains($indirectOperandFilesJson, 'cycle-filter-stack.csv'),
    'cyclic_filter_operand_payload_excluded' => !str_contains($indirectOperandSummaryJson, 'Cyclic Filter Operand Attachment Leak')
        && !str_contains($indirectOperandFilesJson, 'Cyclic Filter Operand Attachment Leak')
        && !str_contains($indirectOperandText, 'Cyclic Filter Operand Attachment Leak'),
    'indirect_operand_visible_text_preserved' => $indirectOperandText === 'Visible Attachment Indirect Operand Review',
    'short_length_attachment_recovered' => ($shortLengthSummary['attachment_count'] ?? null) === 1
        && ($shortLengthSummary['attachments'][0]['filename'] ?? null) === 'short-length-stack.csv'
        && ($shortLengthSummary['attachments'][0]['checksum_matches'] ?? false) === true
        && (($shortLengthFiles[0]['content'] ?? null) === $shortLengthPayload),
    'short_length_filters' => $shortLengthSummary['attachments'][0]['filters'] ?? [],
    'short_length_payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $shortLengthSummary['attachments'][0] ?? []),
    'short_length_surplus_attachment_rejected' => !str_contains($shortLengthSummaryJson, 'short-length-surplus.csv')
        && !str_contains($shortLengthFilesJson, 'short-length-surplus.csv'),
    'short_length_surplus_payload_excluded' => !str_contains($shortLengthSummaryJson, 'Short Length Surplus Stacked Attachment')
        && !str_contains($shortLengthFilesJson, 'Short Length Surplus Stacked Attachment')
        && !str_contains($shortLengthText, 'Short Length Surplus Stacked Attachment')
        && !str_contains($shortLengthText, 'short length attachment surplus smoke bytes'),
    'short_length_visible_text_preserved' => $shortLengthText === 'Visible Attachment Short Length Stack Review',
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
