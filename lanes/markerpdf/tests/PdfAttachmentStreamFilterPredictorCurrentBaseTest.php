<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$asciiHex = static function (string $bytes): string {
    return strtoupper(bin2hex($bytes)) . '>';
};

$pngSubPredictorEncode = static function (string $bytes, int $columns): string {
    if ($columns < 1 || strlen($bytes) % $columns !== 0) {
        throw new RuntimeException('PNG predictor fixture rows must be fixed width.');
    }

    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        $encoded .= "\x01";
        for ($index = 0; $index < $columns; $index++) {
            $left = $index > 0 ? ord($row[$index - 1]) : 0;
            $encoded .= chr((ord($row[$index]) - $left) & 0xff);
        }
    }

    return $encoded;
};

$tiffPredictorEncode = static function (string $bytes, int $columns): string {
    if ($columns < 1 || strlen($bytes) % $columns !== 0) {
        throw new RuntimeException('TIFF predictor fixture rows must be fixed width.');
    }

    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        for ($index = 0; $index < $columns; $index++) {
            $left = $index > 0 ? ord($row[$index - 1]) : 0;
            $encoded .= chr((ord($row[$index]) - $left) & 0xff);
        }
    }

    return $encoded;
};

$pdfPrefix = static function (string $nameTreeObject, string $embeddedObjects): string {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Review) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
        . "6 0 obj\n{$nameTreeObject}\nendobj\n"
        . $embeddedObjects
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";
};

return [
    'decodes Flate DecodeParms PNG predictors in attachment filter stacks before checksum review' => static function (TestRunner $t) use ($asciiHex, $pngSubPredictorEncode, $pdfPrefix): void {
        $payload = "Title,Status\nPredictor Attachment,Ready\n";
        $columns = strlen($payload);
        $encodedPayload = $pngSubPredictorEncode($payload, $columns);
        $compressed = gzcompress($encodedPayload);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress PNG predictor attachment fixture.');
        }

        $invalidPayload = 'INVALID_PREDICTOR_ATTACHMENT_SHOULD_NOT_COUNT';
        $invalidCompressed = gzcompress($invalidPayload);
        if (!is_string($invalidCompressed)) {
            throw new RuntimeException('Unable to compress invalid predictor attachment fixture.');
        }

        $hex = $asciiHex($compressed);
        $invalidHex = $asciiHex($invalidCompressed);
        $checksum = md5($payload);
        $invalidChecksum = md5($invalidPayload);

        $pdf = $pdfPrefix(
            '<< /Names [(predictor.csv) 10 0 R (invalid-predictor.bin) 12 0 R] >>',
            "10 0 obj\n<< /Type /Filespec /F (predictor.csv) /Desc (PNG predictor attachment) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
                . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCIIHexDecode /FlateDecode ] /DecodeParms [ null << /Predictor 12 /Columns {$columns} /Colors 1 /BitsPerComponent 8 /EarlyChange 1 >> ] /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($hex) . " >>\nstream\n{$hex}\nendstream\nendobj\n"
                . "12 0 obj\n<< /Type /Filespec /F (invalid-predictor.bin) /Desc (Invalid predictor attachment) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
                . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Filter [ /ASCIIHexDecode /FlateDecode ] /DecodeParms [ null << /Predictor 99 /Columns 1 >> ] /Params << /Size " . strlen($invalidPayload) . " /CheckSum <{$invalidChecksum}> >> /Length " . strlen($invalidHex) . " >>\nstream\n{$invalidHex}\nendstream\nendobj\n"
        );

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(['predictor.csv'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('predictor.csv', $attachment['filename']);
        $t->same('PNG predictor attachment', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same('text/csv', $attachment['content_type']);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $attachment['filters']);
        $t->same(strlen($payload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same($checksum, $attachment['checksum_hex']);
        $t->same($checksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $payload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'invalid-predictor.bin'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $invalidPayload));

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, count($files));
        $t->same('predictor.csv', $files[0]['filename']);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $files[0]['filters']);
        $t->same(strlen($payload), $files[0]['declared_size']);
        $t->same(strlen($payload), $files[0]['size']);
        $t->same($payload, $files[0]['content']);
        $t->same($checksum, $files[0]['checksum']);
        $t->same($checksum, $files[0]['computed_checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'invalid-predictor.bin'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $invalidPayload));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->true(str_contains($plainText, 'Visible Attachment Review'));
        $t->true(!str_contains($plainText, 'Predictor Attachment'));
        $t->true(!str_contains($plainText, 'INVALID_PREDICTOR_ATTACHMENT'));
    },

    'resolves indirect DecodeParms arrays for predictor attachment streams' => static function (TestRunner $t) use ($asciiHex, $pngSubPredictorEncode, $pdfPrefix): void {
        $payload = "Title,Status\nIndirect Predictor,Ready\n";
        $columns = strlen($payload);
        $compressed = gzcompress($pngSubPredictorEncode($payload, $columns));
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress indirect predictor attachment fixture.');
        }

        $hex = $asciiHex($compressed);
        $checksum = md5($payload);
        $pdf = $pdfPrefix(
            '<< /Names [(indirect-predictor.csv) 10 0 R] >>',
            "10 0 obj\n<< /Type /Filespec /F (indirect-predictor.csv) /Desc (Indirect DecodeParms rows) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
                . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCIIHexDecode /FlateDecode ] /DecodeParms 20 0 R /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($hex) . " >>\nstream\n{$hex}\nendstream\nendobj\n"
                . "20 0 obj\n[ null 21 0 R ]\nendobj\n"
                . "21 0 obj\n<< /Predictor 12 /Columns {$columns} /Colors 1 /BitsPerComponent 8 /EarlyChange 1 >>\nendobj\n"
        );

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);

        $t->same(1, $summary['attachment_count']);
        $t->same(['indirect-predictor.csv'], $summary['filenames']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same('indirect-predictor.csv', $summary['attachments'][0]['filename']);
        $t->same('Source', $summary['attachments'][0]['relationship']);
        $t->same('original_source', $summary['attachments'][0]['relationship_role']);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $summary['attachments'][0]['filters']);
        $t->same(strlen($payload), $summary['attachments'][0]['byte_length']);
        $t->same($checksum, $summary['attachments'][0]['computed_checksum_hex']);
        $t->same(true, $summary['attachments'][0]['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $summary['attachments'][0]));

        $t->same(1, count($files));
        $t->same('indirect-predictor.csv', $files[0]['filename']);
        $t->same('Indirect DecodeParms rows', $files[0]['description']);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $files[0]['filters']);
        $t->same($payload, $files[0]['content']);
        $t->same($checksum, $files[0]['computed_checksum']);
        $t->same(true, $files[0]['checksum_matches']);
    },

    'ignores DecodeParms aligned to null attachment filters before embedded-file extraction' => static function (TestRunner $t) use ($asciiHex, $pngSubPredictorEncode, $pdfPrefix): void {
        $payload = "Title,Status\nNull Slot Attachment,Ready\n";
        $columns = strlen($payload);
        $compressed = gzcompress($pngSubPredictorEncode($payload, $columns));
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress null-slot DecodeParms attachment fixture.');
        }

        $leakingPayload = 'NULL_SLOT_ATTACHMENT_LEAK_SHOULD_NOT_COUNT';
        $leakingCompressed = gzcompress($leakingPayload);
        if (!is_string($leakingCompressed)) {
            throw new RuntimeException('Unable to compress leaking null-slot attachment fixture.');
        }

        $hex = $asciiHex($compressed);
        $leakingHex = $asciiHex($leakingCompressed);
        $checksum = md5($payload);
        $leakingChecksum = md5($leakingPayload);
        $pdf = $pdfPrefix(
            '<< /Names [(null-slot.csv) 10 0 R (leaking-null-slot.bin) 12 0 R] >>',
            "10 0 obj\n<< /Type /Filespec /F (null-slot.csv) /Desc (Null filter DecodeParms rows) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
                . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ null /ASCIIHexDecode /FlateDecode ] /DecodeParms [ 99 0 R null << /Predictor 12 /Columns {$columns} /Colors 1 /BitsPerComponent 8 >> ] /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($hex) . " >>\nstream\n{$hex}\nendstream\nendobj\n"
                . "12 0 obj\n<< /Type /Filespec /F (leaking-null-slot.bin) /Desc (Real filter unresolved DecodeParms) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
                . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Filter [ /ASCIIHexDecode /FlateDecode null ] /DecodeParms [ null 99 0 R null ] /Params << /Size " . strlen($leakingPayload) . " /CheckSum <{$leakingChecksum}> >> /Length " . strlen($leakingHex) . " >>\nstream\n{$leakingHex}\nendstream\nendobj\n"
        );

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['null-slot.csv'], $summary['filenames']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same('null-slot.csv', $summary['attachments'][0]['filename']);
        $t->same('Source', $summary['attachments'][0]['relationship']);
        $t->same('original_source', $summary['attachments'][0]['relationship_role']);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $summary['attachments'][0]['filters']);
        $t->same(strlen($payload), $summary['attachments'][0]['byte_length']);
        $t->same($checksum, $summary['attachments'][0]['computed_checksum_hex']);
        $t->same(true, $summary['attachments'][0]['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $summary['attachments'][0]));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'leaking-null-slot.bin'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $leakingPayload));

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, count($files));
        $t->same('null-slot.csv', $files[0]['filename']);
        $t->same('Null filter DecodeParms rows', $files[0]['description']);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $files[0]['filters']);
        $t->same(strlen($payload), $files[0]['size']);
        $t->same($payload, $files[0]['content']);
        $t->same($checksum, $files[0]['computed_checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'leaking-null-slot.bin'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $leakingPayload));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->true(str_contains($plainText, 'Visible Attachment Review'));
        $t->true(!str_contains($plainText, 'Null Slot Attachment'));
        $t->true(!str_contains($plainText, 'NULL_SLOT_ATTACHMENT_LEAK'));
    },

    'decodes singleton TIFF predictor DecodeParms for embedded attachment streams' => static function (TestRunner $t) use ($tiffPredictorEncode, $pdfPrefix): void {
        $payload = "ABCDEFGHabcdefgh";
        $columns = 8;
        $compressed = gzcompress($tiffPredictorEncode($payload, $columns));
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress TIFF predictor attachment fixture.');
        }

        $checksum = md5($payload);
        $pdf = $pdfPrefix(
            '<< /Names [(tiff-predictor.bin) 10 0 R] >>',
            "10 0 obj\n<< /Type /Filespec /F (tiff-predictor.bin) /Desc (TIFF predictor bytes) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
                . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Filter /FlateDecode /DecodeParms << /Predictor 2 /Columns {$columns} /Colors 1 /BitsPerComponent 8 >> /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
        );

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);

        $t->same(1, $summary['attachment_count']);
        $t->same(['tiff-predictor.bin'], $summary['filenames']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same('application/octet-stream', $summary['attachments'][0]['content_type']);
        $t->same(['FlateDecode'], $summary['attachments'][0]['filters']);
        $t->same(strlen($payload), $summary['attachments'][0]['byte_length']);
        $t->same($checksum, $summary['attachments'][0]['computed_checksum_hex']);
        $t->same(true, $summary['attachments'][0]['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $summary['attachments'][0]));

        $t->same(1, count($files));
        $t->same('tiff-predictor.bin', $files[0]['filename']);
        $t->same(['FlateDecode'], $files[0]['filters']);
        $t->same($payload, $files[0]['content']);
        $t->same($checksum, $files[0]['computed_checksum']);
        $t->same(true, $files[0]['checksum_matches']);
    },
];
