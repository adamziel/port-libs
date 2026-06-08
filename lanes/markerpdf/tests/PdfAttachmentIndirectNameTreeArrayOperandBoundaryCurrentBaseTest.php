<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$indirectNameTreeNamesArrayOperandBoundaryPdf = static function (): array {
    $validPayload = '<wp-export><post id="valid-indirect-names-array-boundary"/></wp-export>';
    $malformedPayload = '<wp-export><post id="malformed-indirect-names-array"/></wp-export>';
    $trailingDecoyPayload = '<wp-export><post id="trailing-indirect-names-array-decoy"/></wp-export>';
    $validChecksum = md5($validPayload);
    $malformedChecksum = md5($malformedPayload);
    $trailingDecoyChecksum = md5($trailingDecoyPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Indirect NameTree Names Array Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Limits [(malformed-indirect-names.xml) (valid-indirect-names.xml)] /Kids [7 0 R 8 0 R] >>\nendobj\n"
        . "7 0 obj\n<< /Limits [(valid-indirect-names.xml) (valid-indirect-names.xml)] /Names [(valid-indirect-names.xml) 10 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(malformed-indirect-names.xml) (malformed-indirect-names.xml)] /Names 50 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (valid-indirect-names.xml) /Desc (Valid sibling EmbeddedFiles source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608095832Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (malformed-indirect-names.xml) /Desc (Malformed indirect Names array source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($malformedPayload) . " /CheckSum <{$malformedChecksum}> >> /Length " . strlen($malformedPayload) . " >>\n"
        . "stream\n{$malformedPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (trailing-indirect-names-decoy.xml) /Desc (Trailing indirect Names array decoy) /AFRelationship /Alternative /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($trailingDecoyPayload) . " /CheckSum <{$trailingDecoyChecksum}> >> /Length " . strlen($trailingDecoyPayload) . " >>\n"
        . "stream\n{$trailingDecoyPayload}\nendstream\nendobj\n"
        . "50 0 obj\n[(malformed-indirect-names.xml) 20 0 R] 30 0 R\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $validPayload,
        $malformedPayload,
        $trailingDecoyPayload,
        $validChecksum,
        $malformedChecksum,
        $trailingDecoyChecksum,
    ];
};

$indirectNameTreeKidsArrayOperandBoundaryPdf = static function (): array {
    $catalogPayload = '<wp-export><post id="catalog-af-after-rejected-kids"/></wp-export>';
    $malformedPayload = '<wp-export><post id="malformed-indirect-kids-array"/></wp-export>';
    $trailingDecoyPayload = '<wp-export><post id="trailing-indirect-kids-array-decoy"/></wp-export>';
    $catalogChecksum = md5($catalogPayload);
    $malformedChecksum = md5($malformedPayload);
    $trailingDecoyChecksum = md5($trailingDecoyPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Indirect NameTree Kids Array Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Limits [(malformed-kids.xml) (malformed-kids.xml)] /Kids 50 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Limits [(malformed-kids.xml) (malformed-kids.xml)] /Names [(malformed-kids.xml) 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (catalog-af-after-rejected-kids.xml) /Desc (Catalog AF fallback after rejected indirect Kids array) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogPayload) . " /CheckSum <{$catalogChecksum}> /ModDate (D:20260608095833Z) >> /Length " . strlen($catalogPayload) . " >>\n"
        . "stream\n{$catalogPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (malformed-kids.xml) /Desc (Malformed indirect Kids array source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($malformedPayload) . " /CheckSum <{$malformedChecksum}> >> /Length " . strlen($malformedPayload) . " >>\n"
        . "stream\n{$malformedPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (trailing-kids-decoy.xml) /Desc (Trailing indirect Kids array decoy) /AFRelationship /Alternative /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($trailingDecoyPayload) . " /CheckSum <{$trailingDecoyChecksum}> >> /Length " . strlen($trailingDecoyPayload) . " >>\n"
        . "stream\n{$trailingDecoyPayload}\nendstream\nendobj\n"
        . "50 0 obj\n[7 0 R] 30 0 R\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $catalogPayload,
        $malformedPayload,
        $trailingDecoyPayload,
        $catalogChecksum,
        $malformedChecksum,
        $trailingDecoyChecksum,
    ];
};

return [
    'rejects indirect EmbeddedFiles Names array objects with trailing operands before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($indirectNameTreeNamesArrayOperandBoundaryPdf): void {
        [
            $pdf,
            $validPayload,
            $malformedPayload,
            $trailingDecoyPayload,
            $validChecksum,
            $malformedChecksum,
            $trailingDecoyChecksum,
        ] = $indirectNameTreeNamesArrayOperandBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-indirect-names.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('valid-indirect-names.xml', $attachment['name_key']);
        $t->same('valid-indirect-names.xml', $attachment['filename']);
        $t->same('Valid sibling EmbeddedFiles source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(10, $attachment['file_spec_object_id']);
        $t->same(11, $attachment['stream_object_id']);
        $t->same(strlen($validPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($validPayload), $attachment['byte_length']);
        $t->same($validChecksum, $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260608095832Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('valid-indirect-names.xml', $file['name']);
        $t->same('valid-indirect-names.xml', $file['filename']);
        $t->same($validPayload, $file['content']);
        $t->same($validChecksum, $file['checksum']);
        $t->same($validChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Indirect NameTree Names Array Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'malformed-indirect-names.xml',
            'trailing-indirect-names-decoy.xml',
            'Malformed indirect Names array source',
            'Trailing indirect Names array decoy',
            $malformedPayload,
            $trailingDecoyPayload,
            $malformedChecksum,
            $trailingDecoyChecksum,
            $validPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
    'rejects indirect EmbeddedFiles Kids array objects with trailing operands before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($indirectNameTreeKidsArrayOperandBoundaryPdf): void {
        [
            $pdf,
            $catalogPayload,
            $malformedPayload,
            $trailingDecoyPayload,
            $catalogChecksum,
            $malformedChecksum,
            $trailingDecoyChecksum,
        ] = $indirectNameTreeKidsArrayOperandBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($catalogPayload), $summary['total_bytes']);
        $t->same(['catalog-af-after-rejected-kids.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('catalog-associated-file', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same(0, $attachment['associated_file_index']);
        $t->same(1, $attachment['catalog_object_id']);
        $t->same('catalog-af-after-rejected-kids.xml', $attachment['filename']);
        $t->same('Catalog AF fallback after rejected indirect Kids array', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(10, $attachment['file_spec_object_id']);
        $t->same(11, $attachment['stream_object_id']);
        $t->same(strlen($catalogPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($catalogPayload), $attachment['byte_length']);
        $t->same($catalogChecksum, $attachment['checksum_hex']);
        $t->same($catalogChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260608095833Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_associated_files', $file['source']);
        $t->same(true, $file['associated_file']);
        $t->same(0, $file['associated_file_index']);
        $t->same('catalog-af-after-rejected-kids.xml', $file['name']);
        $t->same('catalog-af-after-rejected-kids.xml', $file['filename']);
        $t->same($catalogPayload, $file['content']);
        $t->same($catalogChecksum, $file['checksum']);
        $t->same($catalogChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Indirect NameTree Kids Array Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'malformed-kids.xml',
            'trailing-kids-decoy.xml',
            'Malformed indirect Kids array source',
            'Trailing indirect Kids array decoy',
            $malformedPayload,
            $trailingDecoyPayload,
            $malformedChecksum,
            $trailingDecoyChecksum,
            $catalogPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
