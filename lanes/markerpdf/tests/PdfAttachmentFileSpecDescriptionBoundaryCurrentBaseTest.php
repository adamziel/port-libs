<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$fileSpecDescriptionBoundaryPdf = static function (): array {
    $duplicatePayload = '<wp-export><post id="duplicate-desc-source"/></wp-export>';
    $tailedPayload = '<wp-export><post id="tailed-desc-source"/></wp-export>';
    $validPayload = '<wp-export><post id="valid-desc-source"/></wp-export>';
    $duplicateChecksum = md5($duplicatePayload);
    $tailedChecksum = md5($tailedPayload);
    $validChecksum = md5($validPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (FileSpec Description Boundary Body) Tj ET';

    $directTailedFileSpec = '<< /Type /Filespec /F (tailed-desc.xml) /Desc (Tailed description should be omitted) 44 0 R /AFRelationship /Supplement /EF << /F 21 0 R >> >>';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R {$directTailedFileSpec} 30 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(duplicate-desc.xml) 10 0 R (tailed-desc.xml) {$directTailedFileSpec} (valid-desc.xml) 30 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (duplicate-desc.xml) /Desc (Current duplicate description should be omitted) /Desc (Stale duplicate description leak) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicatePayload) . " /CheckSum <{$duplicateChecksum}> /ModDate (D:20260608205704Z) >> /Length " . strlen($duplicatePayload) . " >>\n"
        . "stream\n{$duplicatePayload}\nendstream\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($tailedPayload) . " /CheckSum <{$tailedChecksum}> /ModDate (D:20260608205705Z) >> /Length " . strlen($tailedPayload) . " >>\n"
        . "stream\n{$tailedPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (valid-desc.xml) /Desc (Valid description remains visible review metadata) /AFRelationship /Data /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608205706Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $duplicatePayload,
        $tailedPayload,
        $validPayload,
        $duplicateChecksum,
        $tailedChecksum,
        $validChecksum,
    ];
};

return [
    'omits malformed FileSpec descriptions while preserving embedded attachment review' => static function (
        TestRunner $t
    ) use ($fileSpecDescriptionBoundaryPdf): void {
        [
            $pdf,
            $duplicatePayload,
            $tailedPayload,
            $validPayload,
            $duplicateChecksum,
            $tailedChecksum,
            $validChecksum,
        ] = $fileSpecDescriptionBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(3, $summary['attachment_count']);
        $t->same(strlen($duplicatePayload) + strlen($tailedPayload) + strlen($validPayload), $summary['total_bytes']);
        $t->same(['duplicate-desc.xml', 'tailed-desc.xml', 'valid-desc.xml'], $summary['filenames']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $duplicate = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $duplicate['source']);
        $t->same('duplicate-desc.xml', $duplicate['filename']);
        $t->same(null, $duplicate['description']);
        $t->same('malformed_filespec_description_omitted', $duplicate['description_status']);
        $t->same('Source', $duplicate['relationship']);
        $t->same(10, $duplicate['file_spec_object_id']);
        $t->same(11, $duplicate['stream_object_id']);
        $t->same($duplicateChecksum, $duplicate['checksum_hex']);
        $t->same(true, $duplicate['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $duplicate));

        $tailed = $summary['attachments'][1];
        $t->same('embedded-files-name-tree', $tailed['source']);
        $t->same('tailed-desc.xml', $tailed['filename']);
        $t->same(null, $tailed['description']);
        $t->same('malformed_filespec_description_omitted', $tailed['description_status']);
        $t->same('Supplement', $tailed['relationship']);
        $t->same(null, $tailed['file_spec_object_id']);
        $t->same(21, $tailed['stream_object_id']);
        $t->same($tailedChecksum, $tailed['checksum_hex']);
        $t->same(true, $tailed['checksum_matches']);
        $t->same(true, $tailed['associated_file']);
        $t->same('catalog_af', $tailed['associated_file_source']);
        $t->same(false, array_key_exists('bytes', $tailed));

        $valid = $summary['attachments'][2];
        $t->same('embedded-files-name-tree', $valid['source']);
        $t->same('valid-desc.xml', $valid['filename']);
        $t->same('Valid description remains visible review metadata', $valid['description']);
        $t->same(false, array_key_exists('description_status', $valid));
        $t->same('Data', $valid['relationship']);
        $t->same(30, $valid['file_spec_object_id']);
        $t->same(31, $valid['stream_object_id']);
        $t->same($validChecksum, $valid['checksum_hex']);
        $t->same(true, $valid['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $valid));

        $t->same(3, count($files));
        $t->same(['duplicate-desc.xml', 'tailed-desc.xml', 'valid-desc.xml'], array_column($files, 'filename'));
        $t->same('malformed_filespec_description_omitted', $files[0]['description_status']);
        $t->same(false, array_key_exists('description', $files[0]));
        $t->same('malformed_filespec_description_omitted', $files[1]['description_status']);
        $t->same(false, array_key_exists('description', $files[1]));
        $t->same('Valid description remains visible review metadata', $files[2]['description']);
        $t->same($duplicatePayload, $files[0]['content']);
        $t->same($tailedPayload, $files[1]['content']);
        $t->same($validPayload, $files[2]['content']);
        $t->same($duplicateChecksum, $files[0]['checksum']);
        $t->same($tailedChecksum, $files[1]['checksum']);
        $t->same($validChecksum, $files[2]['checksum']);
        $t->same(true, $files[0]['associated_file']);
        $t->same(true, $files[1]['associated_file']);
        $t->same(true, $files[2]['associated_file']);

        $t->same('FileSpec Description Boundary Body', $plainText);
        foreach ([
            'Current duplicate description should be omitted',
            'Stale duplicate description leak',
            'Tailed description should be omitted',
            $duplicatePayload,
            $tailedPayload,
            $validPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden === $validPayload ? $duplicatePayload : $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
