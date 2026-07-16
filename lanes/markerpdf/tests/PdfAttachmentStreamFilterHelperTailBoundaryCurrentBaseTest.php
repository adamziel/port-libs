<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentStreamFilterHelperTailBoundaryAscii85 = static function (string $bytes): string {
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

$attachmentStreamFilterHelperTailBoundaryPdf = static function () use (
    $attachmentStreamFilterHelperTailBoundaryAscii85
): array {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Helper Tail Review) Tj ET';
    $multiNamePayload = "Title,Status\nMulti Name Helper Attachment Leak,Blocked\n";
    $arrayTailPayload = "Title,Status\nArray Tail Helper Attachment Leak,Blocked\n";
    $validPayload = "Title,Status\nValid Exact Helper Attachment,Ready\n";

    $multiNameEncoded = $attachmentStreamFilterHelperTailBoundaryAscii85(gzcompress($multiNamePayload));
    $arrayTailEncoded = $attachmentStreamFilterHelperTailBoundaryAscii85(gzcompress($arrayTailPayload));
    $validEncoded = $attachmentStreamFilterHelperTailBoundaryAscii85(gzcompress($validPayload));

    return [
        'multi_name_payload' => $multiNamePayload,
        'array_tail_payload' => $arrayTailPayload,
        'valid_payload' => $validPayload,
        'pdf' => "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(multi-name-helper.csv) 10 0 R (array-tail-helper.csv) 12 0 R (valid-exact-helper.csv) 14 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (multi-name-helper.csv) /Desc (Malformed multi-name filter helper attachment) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter 20 0 R /Params << /Size " . strlen($multiNamePayload) . " /CheckSum <" . md5($multiNamePayload) . "> >> /Length " . strlen($multiNameEncoded) . " >>\nstream\n{$multiNameEncoded}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Filespec /F (array-tail-helper.csv) /Desc (Malformed array-tail filter helper attachment) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
            . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter 22 0 R /Params << /Size " . strlen($arrayTailPayload) . " /CheckSum <" . md5($arrayTailPayload) . "> >> /Length " . strlen($arrayTailEncoded) . " >>\nstream\n{$arrayTailEncoded}\nendstream\nendobj\n"
            . "14 0 obj\n<< /Type /Filespec /F (valid-exact-helper.csv) /Desc (Valid exact indirect filter helper attachment) /AFRelationship /Source /EF << /F 15 0 R >> >>\nendobj\n"
            . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter 24 0 R /Params << /Size " . strlen($validPayload) . " /CheckSum <" . md5($validPayload) . "> >> /Length " . strlen($validEncoded) . " >>\nstream\n{$validEncoded}\nendstream\nendobj\n"
            . "20 0 obj\n/ASCII85Decode /FlateDecode\nendobj\n"
            . "22 0 obj\n[ /ASCII85Decode /FlateDecode ] /RunLengthDecode\nendobj\n"
            . "24 0 obj\n26 0 R\nendobj\n"
            . "26 0 obj\n[ /ASCII85Decode /FlateDecode ] % exact helper comment\nendobj\n"
            . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF\n",
    ];
};

return [
    'rejects attachment filter helper objects with trailing operands before summary or payload extraction' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterHelperTailBoundaryPdf): void {
        $fixture = $attachmentStreamFilterHelperTailBoundaryPdf();
        $pdf = $fixture['pdf'];
        $validPayload = $fixture['valid_payload'];
        $validChecksum = md5($validPayload);

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['valid-exact-helper.csv'], $summary['filenames']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('valid-exact-helper.csv', $attachment['filename'] ?? null);
        $t->same('Valid exact indirect filter helper attachment', $attachment['description'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $attachment['filters'] ?? null);
        $t->same(strlen($validPayload), $attachment['byte_length'] ?? null);
        $t->same($validChecksum, $attachment['computed_checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $t->same('valid-exact-helper.csv', $files[0]['filename'] ?? null);
        $t->same($validPayload, $files[0]['content'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $files[0]['filters'] ?? null);
        $t->same($validChecksum, $files[0]['computed_checksum'] ?? null);
        $t->same(true, $files[0]['checksum_matches'] ?? null);

        $t->same('Visible Attachment Helper Tail Review', $plainText);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'multi-name-helper.csv'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'array-tail-helper.csv'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'Multi Name Helper Attachment Leak'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'Array Tail Helper Attachment Leak'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'multi-name-helper.csv'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'array-tail-helper.csv'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'Multi Name Helper Attachment Leak'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'Array Tail Helper Attachment Leak'));
        $t->true(!str_contains($plainText, 'Multi Name Helper Attachment Leak'));
        $t->true(!str_contains($plainText, 'Array Tail Helper Attachment Leak'));
        $t->true(!str_contains($plainText, 'Valid Exact Helper Attachment'));
        $t->true(!str_contains($plainText, 'ASCII85Decode'));
        $t->true(!str_contains($plainText, 'RunLengthDecode'));
    },
];
