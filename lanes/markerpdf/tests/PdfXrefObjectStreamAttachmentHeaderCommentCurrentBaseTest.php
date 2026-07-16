<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamAttachmentHeaderCommentCurrentBasePdf = static function (): array {
    $currentPayload = '<wp-export><post id="comment-header-filespec"/></wp-export>';
    $stalePayload = '<wp-export><post id="stale-comment-header"/></wp-export>';
    $currentChecksum = md5($currentPayload);
    $staleChecksum = md5($stalePayload);

    $decoyMember = '<< /Type /Filespec /F (comment-header-decoy.xml) /Desc (Comment header decoy FileSpec) /AFRelationship /Alternative /EF << /F 6 0 R >> >>';
    $currentFileSpec = '<< /Type /Filespec /F (comment-header-current.xml) /Desc (Current comment-header FileSpec) /AFRelationship /Source /EF << /F 5 0 R >> >>';
    $currentOffset = strlen($decoyMember . "\n");
    $objectStreamHeader = '12 0 % 99 123 fake commented object-stream header row' . "\n" . '4 ' . $currentOffset . ' ';
    $objectStreamBytes = $objectStreamHeader . "\n" . $decoyMember . "\n" . $currentFileSpec . "\n";
    $compressedObjectStream = gzcompress($objectStreamBytes);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress comment-header attachment object stream fixture.');
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };

    $addObject(1, '<< /Type /Catalog /Pages 3 0 R /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>');
    $addObject(2, '<< /Names [(comment-header-current.xml) 4 0 R] >>');
    $addObject(3, '<< /Type /Pages /Kids [] /Count 0 >>');
    $addObject(4, '<< /Type /Filespec /F (stale-comment-header.xml) /Desc (Stale comment-header FileSpec) /AFRelationship /Alternative /EF << /F 6 0 R >> >>');
    $addObject(5, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605082954Z) >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
    $addObject(6, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");
    $addObject(20, '<< /Type /ObjStm /N 2 /First ' . strlen($objectStreamHeader . "\n") . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber < 22; $objectNumber++) {
        if ($objectNumber === 0) {
            $rows .= pack('CNn', 0, 0, 65535);
            continue;
        }
        if ($objectNumber === 4) {
            $rows .= pack('CNn', 2, 20, 1);
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
        throw new RuntimeException('Unable to compress comment-header attachment xref fixture.');
    }

    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF\n";

    return [$pdf, $currentPayload, $stalePayload, $currentChecksum];
};

return [
    'keeps explicit attachment object-stream indexes aligned across commented header rows' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamAttachmentHeaderCommentCurrentBasePdf): void {
        [$pdf, $currentPayload, $stalePayload, $currentChecksum] = $xrefObjectStreamAttachmentHeaderCommentCurrentBasePdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $review = (new PdfTextExtractor())->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($currentPayload), $summary['total_bytes']);
        $t->same(['comment-header-current.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('comment-header-current.xml', $attachment['name_key']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same(4, $attachment['file_spec_object_id']);
        $t->same(5, $attachment['stream_object_id']);
        $t->same('comment-header-current.xml', $attachment['filename']);
        $t->same('Current comment-header FileSpec', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($currentPayload), $attachment['byte_length']);
        $t->same($currentChecksum, $attachment['checksum_hex']);
        $t->same($currentChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605082954Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-comment-header.xml'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'comment-header-decoy.xml'));
        $t->true(is_string($encoded) && !str_contains($encoded, $stalePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $currentPayload));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $t->same(4, $entry['object_number'] ?? null);
        $t->same(20, $entry['object_stream'] ?? null);
        $t->same(1, $entry['xref_member_index'] ?? null);
        $t->same(1, $entry['actual_member_index'] ?? null);
        $t->same(2, $entry['object_stream_member_count'] ?? null);
        $t->same('explicit_member_index', $entry['selection_policy'] ?? null);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
