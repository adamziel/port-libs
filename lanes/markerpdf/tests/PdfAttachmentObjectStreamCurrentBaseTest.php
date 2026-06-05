<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

$attachmentObjectStreamCurrentBasePdf = static function (): array {
    $currentPayload = '<wp-export><post id="compressed-filespec"/></wp-export>';
    $stalePayload = '<wp-export><post id="stale-direct-filespec"/></wp-export>';
    $currentChecksum = md5($currentPayload);
    $staleChecksum = md5($stalePayload);

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };

    $addObject(1, '<< /Type /Catalog /Pages 3 0 R /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>');
    $addObject(2, '<< /Names [(compressed-source.xml) 4 0 R] >>');
    $addObject(3, '<< /Type /Pages /Kids [] /Count 0 >>');
    $addObject(4, '<< /Type /Filespec /F (stale-direct-source.xml) /Desc (Stale direct FileSpec) /AFRelationship /Alternative /EF << /F 6 0 R >> >>');
    $addObject(5, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605011736Z) >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
    $addObject(6, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $compressedFileSpec = '<< /Type /Filespec /F (compressed-source.xml) /Desc (Current compressed FileSpec) /AFRelationship /Source /EF << /F 5 0 R >> >>';
    $objectStreamHeader = '4 0 ';
    $objectStreamBytes = $objectStreamHeader . $compressedFileSpec;
    $compressedObjectStream = gzcompress($objectStreamBytes);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress attachment object stream fixture.');
    }
    $addObject(20, '<< /Type /ObjStm /N 1 /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber < 22; $objectNumber++) {
        if ($objectNumber === 0) {
            $rows .= pack('CNn', 0, 0, 65535);
            continue;
        }
        if ($objectNumber === 4) {
            $rows .= pack('CNn', 2, 20, 0);
            continue;
        }
        if ($objectNumber === 21) {
            $rows .= pack('CNn', 1, $xrefOffset, 0);
            continue;
        }
        if (isset($offsets[$objectNumber])) {
            $rows .= pack('CNn', 1, $offsets[$objectNumber], 0);
            continue;
        }

        $rows .= pack('CNn', 0, 0, 0);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress attachment object stream xref fixture.');
    }

    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF\n";

    return [$pdf, $currentPayload, $stalePayload, $currentChecksum];
};

return [
    'summarizes xref-stream object-stream FileSpec attachments before stale direct rows' => static function (
        TestRunner $t
    ) use ($attachmentObjectStreamCurrentBasePdf): void {
        [$pdf, $currentPayload, $stalePayload, $currentChecksum] = $attachmentObjectStreamCurrentBasePdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($currentPayload), $summary['total_bytes']);
        $t->same(['compressed-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('compressed-source.xml', $attachment['name_key']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same(4, $attachment['file_spec_object_id']);
        $t->same(5, $attachment['stream_object_id']);
        $t->same('compressed-source.xml', $attachment['filename']);
        $t->same('Current compressed FileSpec', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($currentPayload), $attachment['byte_length']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same($currentChecksum, $attachment['checksum_hex']);
        $t->same($currentChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605011736Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-direct-source.xml'));
        $t->true(is_string($encoded) && !str_contains($encoded, $stalePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $currentPayload));
    },
];
