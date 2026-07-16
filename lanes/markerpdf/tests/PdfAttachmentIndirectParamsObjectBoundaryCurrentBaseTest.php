<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$indirectParamsObjectBoundaryPdf = static function (): array {
    $badPrimaryPayload = '<wp-export><post id="bad-indirect-params-object"/></wp-export>';
    $validPayload = '<wp-export><post id="valid-indirect-params-boundary"/></wp-export>';
    $relatedPrimaryPayload = '<wp-export><post id="related-primary-indirect-params"/></wp-export>';
    $badRelatedPayload = 'BAD_RELATED_INDIRECT_PARAMS_OBJECT_SHOULD_NOT_COUNT';
    $validRelatedPayload = '{"manifest":"valid-indirect-params-related"}';
    $badPrimaryChecksum = md5($badPrimaryPayload);
    $validChecksum = md5($validPayload);
    $relatedPrimaryChecksum = md5($relatedPrimaryPayload);
    $badRelatedChecksum = md5($badRelatedPayload);
    $validRelatedChecksum = md5($validRelatedPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Indirect Params Object Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R 30 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(bad-indirect-params.xml) 10 0 R (valid-indirect-params.xml) 20 0 R (related-primary-params.xml) 30 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (bad-indirect-params.xml) /Desc (Bad indirect Params source) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params 50 0 R /Length " . strlen($badPrimaryPayload) . " >>\nstream\n{$badPrimaryPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (valid-indirect-params.xml) /Desc (Valid indirect Params source) /AFRelationship /Source /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params 60 0 R /Length " . strlen($validPayload) . " >>\nstream\n{$validPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (related-primary-params.xml) /Desc (Primary with indirect Params related files) /AFRelationship /Supplement /EF << /F 31 0 R >> /RF << /F [(bad-related-params.bin) 32 0 R] /UF [(valid-related-params.json) 33 0 R] >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params 70 0 R /Length " . strlen($relatedPrimaryPayload) . " >>\nstream\n{$relatedPrimaryPayload}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Params 80 0 R /Length " . strlen($badRelatedPayload) . " >>\nstream\n{$badRelatedPayload}\nendstream\nendobj\n"
        . "33 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params 90 0 R /Length " . strlen($validRelatedPayload) . " >>\nstream\n{$validRelatedPayload}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Size " . strlen($badPrimaryPayload) . " /CheckSum <{$badPrimaryChecksum}> /ModDate (D:20260608191400Z) >> 99 0 R\nendobj\n"
        . "60 0 obj\n<< /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608191401Z) >>\nendobj\n"
        . "70 0 obj\n<< /Size " . strlen($relatedPrimaryPayload) . " /CheckSum <{$relatedPrimaryChecksum}> /CreationDate (D:20260608191402Z) >>\nendobj\n"
        . "80 0 obj\n<< /Size " . strlen($badRelatedPayload) . " /CheckSum <{$badRelatedChecksum}> /CreationDate (D:20260608191403Z) >> 98 0 R\nendobj\n"
        . "90 0 obj\n<< /Size " . strlen($validRelatedPayload) . " /CheckSum <{$validRelatedChecksum}> /CreationDate (D:20260608191404Z) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $badPrimaryPayload,
        $validPayload,
        $relatedPrimaryPayload,
        $badRelatedPayload,
        $validRelatedPayload,
        $badPrimaryChecksum,
        $validChecksum,
        $relatedPrimaryChecksum,
        $badRelatedChecksum,
        $validRelatedChecksum,
    ];
};

return [
    'rejects indirect EmbeddedFile Params helper objects with trailing operands before attachment review' => static function (
        TestRunner $t
    ) use ($indirectParamsObjectBoundaryPdf): void {
        [
            $pdf,
            $badPrimaryPayload,
            $validPayload,
            $relatedPrimaryPayload,
            $badRelatedPayload,
            $validRelatedPayload,
            $badPrimaryChecksum,
            $validChecksum,
            $relatedPrimaryChecksum,
            $badRelatedChecksum,
            $validRelatedChecksum,
        ] = $indirectParamsObjectBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same(strlen($validPayload) + strlen($relatedPrimaryPayload), $summary['total_bytes']);
        $t->same(['valid-indirect-params.xml', 'related-primary-params.xml'], $summary['filenames']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $valid = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $valid['source']);
        $t->same(true, $valid['associated_file']);
        $t->same('catalog_af', $valid['associated_file_source']);
        $t->same('valid-indirect-params.xml', $valid['name_key']);
        $t->same('valid-indirect-params.xml', $valid['filename']);
        $t->same('Valid indirect Params source', $valid['description']);
        $t->same('Source', $valid['relationship']);
        $t->same('original_source', $valid['relationship_role']);
        $t->same(20, $valid['file_spec_object_id']);
        $t->same(21, $valid['stream_object_id']);
        $t->same(strlen($validPayload), $valid['declared_size']);
        $t->same(true, $valid['declared_size_matches']);
        $t->same(strlen($validPayload), $valid['byte_length']);
        $t->same($validChecksum, $valid['checksum_hex']);
        $t->same($validChecksum, $valid['computed_checksum_hex']);
        $t->same(true, $valid['checksum_matches']);
        $t->same('D:20260608191401Z', $valid['modified_at']);
        $t->same(false, array_key_exists('bytes', $valid));

        $primary = $summary['attachments'][1];
        $t->same('embedded-files-name-tree', $primary['source']);
        $t->same('related-primary-params.xml', $primary['filename']);
        $t->same('Supplement', $primary['relationship']);
        $t->same('supplemental_representation', $primary['relationship_role']);
        $t->same(30, $primary['file_spec_object_id']);
        $t->same(31, $primary['stream_object_id']);
        $t->same(strlen($relatedPrimaryPayload), $primary['declared_size']);
        $t->same(true, $primary['declared_size_matches']);
        $t->same($relatedPrimaryChecksum, $primary['checksum_hex']);
        $t->same(true, $primary['checksum_matches']);
        $t->same(1, $primary['related_file_count']);
        $related = $primary['related_files'][0] ?? null;
        $t->true(is_array($related));
        $t->same('UF', $related['rf_key'] ?? null);
        $t->same('valid-related-params.json', $related['related_filename'] ?? null);
        $t->same(33, $related['stream_object_id'] ?? null);
        $t->same(strlen($validRelatedPayload), $related['declared_size'] ?? null);
        $t->same($validRelatedChecksum, $related['checksum_hex'] ?? null);
        $t->same(true, $related['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $related));

        $t->same(2, count($files));
        $t->same(['valid-indirect-params.xml', 'related-primary-params.xml'], array_column($files, 'filename'));
        $t->same($validPayload, $files[0]['content']);
        $t->same($validChecksum, $files[0]['computed_checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same($relatedPrimaryPayload, $files[1]['content']);
        $t->same($relatedPrimaryChecksum, $files[1]['computed_checksum']);
        $t->same(true, $files[1]['checksum_matches']);
        $t->same(1, $files[1]['related_file_count']);
        $t->same('valid-related-params.json', $files[1]['related_files'][0]['related_filename']);
        $t->same(strlen($validRelatedPayload), $files[1]['related_files'][0]['declared_size']);
        $t->same(true, $files[1]['related_files'][0]['checksum_matches']);
        $t->same(false, array_key_exists('content', $files[1]['related_files'][0]));

        $t->same('Indirect Params Object Boundary Body', $plainText);
        foreach ([
            'bad-indirect-params.xml',
            'Bad indirect Params source',
            'bad-related-params.bin',
            $badPrimaryPayload,
            $badRelatedPayload,
            $badPrimaryChecksum,
            $badRelatedChecksum,
            $validPayload,
            $relatedPrimaryPayload,
            $validRelatedPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
