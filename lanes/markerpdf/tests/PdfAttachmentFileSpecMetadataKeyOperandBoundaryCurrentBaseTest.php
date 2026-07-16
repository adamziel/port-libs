<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$fileSpecMetadataKeyOperandBoundaryPdf = static function (): array {
    $ambiguousPayload = '<wp-export><post id="ambiguous-filespec-metadata"/></wp-export>';
    $validPayload = '<wp-export><post id="valid-filespec-metadata"/></wp-export>';
    $ambiguousChecksum = md5($ambiguousPayload);
    $validChecksum = md5($validPayload);
    $validPermanentIdHex = '00112233445566778899aabbccddeeff';
    $validChangingIdHex = '776f726470726573732d6174746163686d656e742d7633';
    $decoyIdentifierHex = 'badf00d';
    $content = 'BT /F1 12 Tf 72 720 Td (FileSpec Metadata Key Operand Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(ambiguous-metadata.xml) 10 0 R (valid-metadata.xml) 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (ambiguous-metadata.xml) /Desc (Ambiguous metadata WordPress source) /AFRelationship /Supplement /FS /URL /FS /Launch /ID [<0011> <2233>] <{$decoyIdentifierHex}> /V true false /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($ambiguousPayload) . " /CheckSum <{$ambiguousChecksum}> /ModDate (D:20260608104807Z) >> /Length " . strlen($ambiguousPayload) . " >>\n"
        . "stream\n{$ambiguousPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /FS /URL /F (https://example.test/private/valid-metadata.xml) /UF (valid-metadata.xml) /Desc (Valid metadata WordPress source) /AFRelationship /Source /ID [<{$validPermanentIdHex}> <{$validChangingIdHex}>] /V false /EF << /UF 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608104808Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $ambiguousPayload,
        $validPayload,
        $ambiguousChecksum,
        $validChecksum,
        $validPermanentIdHex,
        $validChangingIdHex,
        $decoyIdentifierHex,
    ];
};

return [
    'omits malformed FileSpec local metadata while preserving valid embedded attachment rows' => static function (
        TestRunner $t
    ) use ($fileSpecMetadataKeyOperandBoundaryPdf): void {
        [
            $pdf,
            $ambiguousPayload,
            $validPayload,
            $ambiguousChecksum,
            $validChecksum,
            $validPermanentIdHex,
            $validChangingIdHex,
            $decoyIdentifierHex,
        ] = $fileSpecMetadataKeyOperandBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same(strlen($ambiguousPayload) + strlen($validPayload), $summary['total_bytes']);
        $t->same(['ambiguous-metadata.xml', 'valid-metadata.xml'], $summary['filenames']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $ambiguous = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $ambiguous['source']);
        $t->same('ambiguous-metadata.xml', $ambiguous['name_key']);
        $t->same('ambiguous-metadata.xml', $ambiguous['filename']);
        $t->same('Ambiguous metadata WordPress source', $ambiguous['description']);
        $t->same('Supplement', $ambiguous['relationship']);
        $t->same('supplemental_representation', $ambiguous['relationship_role']);
        $t->same(10, $ambiguous['file_spec_object_id']);
        $t->same(11, $ambiguous['stream_object_id']);
        $t->same('text/xml', $ambiguous['content_type']);
        $t->same(strlen($ambiguousPayload), $ambiguous['byte_length']);
        $t->same($ambiguousChecksum, $ambiguous['checksum_hex']);
        $t->same($ambiguousChecksum, $ambiguous['computed_checksum_hex']);
        $t->same(true, $ambiguous['checksum_matches']);
        $t->same('D:20260608104807Z', $ambiguous['modified_at']);
        $t->same(true, $ambiguous['associated_file']);
        $t->same('catalog_af', $ambiguous['associated_file_source']);
        $t->same(false, array_key_exists('file_system', $ambiguous));
        $t->same(false, array_key_exists('file_system_status', $ambiguous));
        $t->same(false, array_key_exists('file_identifier', $ambiguous));
        $t->same(false, array_key_exists('volatile', $ambiguous));
        $t->same(false, array_key_exists('volatile_status', $ambiguous));
        $t->same(false, array_key_exists('bytes', $ambiguous));

        $valid = $summary['attachments'][1];
        $t->same('embedded-files-name-tree', $valid['source']);
        $t->same('valid-metadata.xml', $valid['name_key']);
        $t->same('valid-metadata.xml', $valid['filename']);
        $t->same('UF', $valid['filename_source']);
        $t->same('Valid metadata WordPress source', $valid['description']);
        $t->same('Source', $valid['relationship']);
        $t->same('original_source', $valid['relationship_role']);
        $t->same('URL', $valid['file_system']);
        $t->same('external_url_file_system_review_only', $valid['file_system_status']);
        $t->same(false, $valid['volatile']);
        $t->same('stable_file_spec_review', $valid['volatile_status']);
        $t->same($validPermanentIdHex, $valid['file_identifier']['permanent_id_hex']);
        $t->same($validChangingIdHex, $valid['file_identifier']['changing_id_hex']);
        $t->same('complete_file_identifier_pair', $valid['file_identifier']['identifier_status']);
        $t->same(20, $valid['file_spec_object_id']);
        $t->same(21, $valid['stream_object_id']);
        $t->same('UF', $valid['ef_key']);
        $t->same($validChecksum, $valid['checksum_hex']);
        $t->same(true, $valid['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $valid));

        $t->same(2, count($files));
        $ambiguousFile = $files[0];
        $t->same('catalog_names_embedded_files', $ambiguousFile['source']);
        $t->same('ambiguous-metadata.xml', $ambiguousFile['name']);
        $t->same('ambiguous-metadata.xml', $ambiguousFile['filename']);
        $t->same($ambiguousPayload, $ambiguousFile['content']);
        $t->same($ambiguousChecksum, $ambiguousFile['checksum']);
        $t->same(true, $ambiguousFile['checksum_matches']);
        $t->same(true, $ambiguousFile['associated_file']);
        $t->same(false, array_key_exists('file_system', $ambiguousFile));
        $t->same(false, array_key_exists('file_identifier', $ambiguousFile));
        $t->same(false, array_key_exists('volatile', $ambiguousFile));

        $validFile = $files[1];
        $t->same('catalog_names_embedded_files', $validFile['source']);
        $t->same('valid-metadata.xml', $validFile['name']);
        $t->same('valid-metadata.xml', $validFile['filename']);
        $t->same('URL', $validFile['file_system']);
        $t->same(false, $validFile['volatile']);
        $t->same($validPermanentIdHex, $validFile['file_identifier']['permanent_id_hex']);
        $t->same($validChangingIdHex, $validFile['file_identifier']['changing_id_hex']);
        $t->same($validPayload, $validFile['content']);
        $t->same($validChecksum, $validFile['checksum']);
        $t->same(true, $validFile['checksum_matches']);

        $t->same('FileSpec Metadata Key Operand Boundary Body', $plainText);
        foreach ([
            'Launch',
            $decoyIdentifierHex,
            $ambiguousPayload,
            $validPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
        $t->true(is_string($filesJson) && !str_contains($filesJson, 'Launch'));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $decoyIdentifierHex));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
