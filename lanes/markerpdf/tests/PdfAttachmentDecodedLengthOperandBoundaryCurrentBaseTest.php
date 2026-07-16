<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentDecodedLengthOperandBoundaryPdf = static function (): array {
    $badTailPayload = '<wp-export><post id="bad-dl-tail"/></wp-export>';
    $badDuplicatePayload = '<wp-export><post id="bad-dl-duplicate"/></wp-export>';
    $validPayload = '<wp-export><post id="valid-dl-boundary"/></wp-export>';
    $relatedPrimaryPayload = '<wp-export><post id="related-primary-dl-boundary"/></wp-export>';
    $relatedTailPayload = 'RELATED_DL_TAIL_SHOULD_NOT_BE_REVIEWED';
    $relatedDuplicatePayload = 'RELATED_DL_DUPLICATE_SHOULD_NOT_BE_REVIEWED';
    $relatedValidPayload = '{"related":"valid-dl-boundary"}';

    $badTailChecksum = md5($badTailPayload);
    $badDuplicateChecksum = md5($badDuplicatePayload);
    $validChecksum = md5($validPayload);
    $relatedPrimaryChecksum = md5($relatedPrimaryPayload);
    $relatedTailChecksum = md5($relatedTailPayload);
    $relatedDuplicateChecksum = md5($relatedDuplicatePayload);
    $relatedValidChecksum = md5($relatedValidPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Attachment DL Operand Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R 30 0 R 40 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(bad-dl-tail.xml) 10 0 R (bad-dl-duplicate.xml) 20 0 R (valid-dl.xml) 30 0 R (related-primary.xml) 40 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (bad-dl-tail.xml) /Desc (Bad DL tail source) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /DL " . strlen($badTailPayload) . " 999 /Params << /Size " . strlen($badTailPayload) . " /CheckSum <{$badTailChecksum}> >> /Length " . strlen($badTailPayload) . " >>\n"
        . "stream\n{$badTailPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (bad-dl-duplicate.xml) /Desc (Bad duplicate DL source) /AFRelationship /Supplement /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /DL " . strlen($badDuplicatePayload) . " /DL 7 /Params << /Size " . strlen($badDuplicatePayload) . " /CheckSum <{$badDuplicateChecksum}> >> /Length " . strlen($badDuplicatePayload) . " >>\n"
        . "stream\n{$badDuplicatePayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (valid-dl.xml) /Desc (Valid decoded-length source) /AFRelationship /Source /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /DL " . strlen($validPayload) . " /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608180349Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Filespec /F (related-primary.xml) /Desc (Primary with DL related files) /AFRelationship /Alternative /EF << /F 41 0 R >> /RF << /F [(related-tail.css) 42 0 R (related-duplicate.css) 43 0 R] /UF [(related-valid.json) 44 0 R] >> >>\nendobj\n"
        . "41 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /DL " . strlen($relatedPrimaryPayload) . " /Params << /Size " . strlen($relatedPrimaryPayload) . " /CheckSum <{$relatedPrimaryChecksum}> >> /Length " . strlen($relatedPrimaryPayload) . " >>\n"
        . "stream\n{$relatedPrimaryPayload}\nendstream\nendobj\n"
        . "42 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /DL " . strlen($relatedTailPayload) . " 17 /Params << /Size " . strlen($relatedTailPayload) . " /CheckSum <{$relatedTailChecksum}> >> /Length " . strlen($relatedTailPayload) . " >>\n"
        . "stream\n{$relatedTailPayload}\nendstream\nendobj\n"
        . "43 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /DL " . strlen($relatedDuplicatePayload) . " /DL 1 /Params << /Size " . strlen($relatedDuplicatePayload) . " /CheckSum <{$relatedDuplicateChecksum}> >> /Length " . strlen($relatedDuplicatePayload) . " >>\n"
        . "stream\n{$relatedDuplicatePayload}\nendstream\nendobj\n"
        . "44 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /DL " . strlen($relatedValidPayload) . " /Params << /Size " . strlen($relatedValidPayload) . " /CheckSum <{$relatedValidChecksum}> >> /Length " . strlen($relatedValidPayload) . " >>\n"
        . "stream\n{$relatedValidPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $badTailPayload,
        $badDuplicatePayload,
        $validPayload,
        $relatedPrimaryPayload,
        $relatedTailPayload,
        $relatedDuplicatePayload,
        $relatedValidPayload,
        $badTailChecksum,
        $badDuplicateChecksum,
        $validChecksum,
        $relatedPrimaryChecksum,
        $relatedTailChecksum,
        $relatedDuplicateChecksum,
        $relatedValidChecksum,
    ];
};

return [
    'rejects duplicate and tailed EmbeddedFile DL operands before attachment review' => static function (
        TestRunner $t
    ) use ($attachmentDecodedLengthOperandBoundaryPdf): void {
        [
            $pdf,
            $badTailPayload,
            $badDuplicatePayload,
            $validPayload,
            $relatedPrimaryPayload,
            $relatedTailPayload,
            $relatedDuplicatePayload,
            $relatedValidPayload,
            $badTailChecksum,
            $badDuplicateChecksum,
            $validChecksum,
            $relatedPrimaryChecksum,
            $relatedTailChecksum,
            $relatedDuplicateChecksum,
            $relatedValidChecksum,
        ] = $attachmentDecodedLengthOperandBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same(strlen($validPayload) + strlen($relatedPrimaryPayload), $summary['total_bytes']);
        $t->same(['valid-dl.xml', 'related-primary.xml'], $summary['filenames']);

        $valid = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $valid['source']);
        $t->same(true, $valid['associated_file']);
        $t->same('catalog_af', $valid['associated_file_source']);
        $t->same('valid-dl.xml', $valid['name_key']);
        $t->same('valid-dl.xml', $valid['filename']);
        $t->same('Valid decoded-length source', $valid['description']);
        $t->same('Source', $valid['relationship']);
        $t->same('original_source', $valid['relationship_role']);
        $t->same(30, $valid['file_spec_object_id']);
        $t->same(31, $valid['stream_object_id']);
        $t->same(strlen($validPayload), $valid['decoded_length']);
        $t->same(true, $valid['decoded_length_matches']);
        $t->same(strlen($validPayload), $valid['declared_size']);
        $t->same(true, $valid['declared_size_matches']);
        $t->same($validChecksum, $valid['checksum_hex']);
        $t->same($validChecksum, $valid['computed_checksum_hex']);
        $t->same(true, $valid['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $valid));

        $relatedPrimary = $summary['attachments'][1];
        $t->same('embedded-files-name-tree', $relatedPrimary['source']);
        $t->same('related-primary.xml', $relatedPrimary['filename']);
        $t->same('Alternative', $relatedPrimary['relationship']);
        $t->same('alternative_representation', $relatedPrimary['relationship_role']);
        $t->same(40, $relatedPrimary['file_spec_object_id']);
        $t->same(41, $relatedPrimary['stream_object_id']);
        $t->same(strlen($relatedPrimaryPayload), $relatedPrimary['decoded_length']);
        $t->same(true, $relatedPrimary['decoded_length_matches']);
        $t->same($relatedPrimaryChecksum, $relatedPrimary['checksum_hex']);
        $t->same(true, $relatedPrimary['checksum_matches']);
        $t->same(1, $relatedPrimary['related_file_count'] ?? null);
        $related = $relatedPrimary['related_files'][0] ?? null;
        $t->true(is_array($related));
        $t->same('filespec_related_files', $related['source'] ?? null);
        $t->same('UF', $related['rf_key'] ?? null);
        $t->same('related-valid.json', $related['related_filename'] ?? null);
        $t->same(44, $related['stream_object_id'] ?? null);
        $t->same(strlen($relatedValidPayload), $related['decoded_length'] ?? null);
        $t->same(true, $related['decoded_length_matches'] ?? null);
        $t->same($relatedValidChecksum, $related['checksum_hex'] ?? null);
        $t->same(true, $related['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $related));

        $t->same(2, count($files));
        $t->same(['valid-dl.xml', 'related-primary.xml'], array_column($files, 'filename'));
        $t->same($validPayload, $files[0]['content']);
        $t->same(strlen($validPayload), $files[0]['decoded_length']);
        $t->same(true, $files[0]['decoded_length_matches']);
        $t->same($validChecksum, $files[0]['checksum']);
        $t->same($validChecksum, $files[0]['computed_checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same($relatedPrimaryPayload, $files[1]['content']);
        $t->same(1, $files[1]['related_file_count'] ?? null);
        $t->same('related-valid.json', $files[1]['related_files'][0]['related_filename'] ?? null);
        $t->same(strlen($relatedValidPayload), $files[1]['related_files'][0]['decoded_length'] ?? null);
        $t->same(true, $files[1]['related_files'][0]['decoded_length_matches'] ?? null);

        $t->same('Attachment DL Operand Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'bad-dl-tail.xml',
            'bad-dl-duplicate.xml',
            'Bad DL tail source',
            'Bad duplicate DL source',
            $badTailPayload,
            $badDuplicatePayload,
            $relatedTailPayload,
            $relatedDuplicatePayload,
            $badTailChecksum,
            $badDuplicateChecksum,
            $relatedTailChecksum,
            $relatedDuplicateChecksum,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $validPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $relatedPrimaryPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $relatedValidPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
