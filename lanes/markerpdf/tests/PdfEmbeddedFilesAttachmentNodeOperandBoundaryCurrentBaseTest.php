<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$embeddedFilesAttachmentNodeOperandBoundaryPdf = static function (): array {
    $cleanPayload = '<wp-export><post id="clean-embeddedfiles-node"/></wp-export>';
    $malformedPayload = '<wp-export><post id="malformed-node-primary"/></wp-export>';
    $trailingDecoyPayload = '<wp-export><post id="malformed-node-trailing-decoy"/></wp-export>';
    $cleanChecksum = md5($cleanPayload);
    $malformedChecksum = md5($malformedPayload);
    $trailingDecoyChecksum = md5($trailingDecoyPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Visible EmbeddedFiles Node Operand Boundary) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Limits [(clean-source.xml) (malformed-source.xml)] /Kids [7 0 R 8 0 R] >>\nendobj\n"
        . "7 0 obj\n<< /Limits [(clean-source.xml) (clean-source.xml)] /Names [(clean-source.xml) 10 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(malformed-source.xml) (malformed-source.xml)] /Names 24 0 R 26 0 R >>\nendobj\n"
        . "24 0 obj\n[(malformed-source.xml) 20 0 R]\nendobj\n"
        . "26 0 obj\n[(trailing-decoy.xml) 30 0 R]\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (clean-source.xml) /Desc (Clean EmbeddedFiles sibling source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($cleanPayload) . " /CheckSum <{$cleanChecksum}> /ModDate (D:20260607232341Z) >> /Length " . strlen($cleanPayload) . " >>\n"
        . "stream\n{$cleanPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (malformed-source.xml) /Desc (Malformed node operand source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($malformedPayload) . " /CheckSum <{$malformedChecksum}> >> /Length " . strlen($malformedPayload) . " >>\n"
        . "stream\n{$malformedPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (trailing-decoy.xml) /Desc (Trailing operand decoy source) /AFRelationship /Alternative /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($trailingDecoyPayload) . " /CheckSum <{$trailingDecoyChecksum}> >> /Length " . strlen($trailingDecoyPayload) . " >>\n"
        . "stream\n{$trailingDecoyPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $cleanPayload,
        $malformedPayload,
        $trailingDecoyPayload,
        $cleanChecksum,
        $malformedChecksum,
        $trailingDecoyChecksum,
    ];
};

$embeddedFilesCatalogOperandBoundaryPdf = static function (): array {
    $catalogPayload = '<wp-export><post id="catalog-af-fallback"/></wp-export>';
    $nameTreePayload = '<wp-export><post id="malformed-catalog-nametree"/></wp-export>';
    $trailingDecoyPayload = '<wp-export><post id="catalog-trailing-decoy"/></wp-export>';
    $catalogChecksum = md5($catalogPayload);
    $nameTreeChecksum = md5($nameTreePayload);
    $trailingDecoyChecksum = md5($trailingDecoyPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Visible Catalog EmbeddedFiles Operand Boundary) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R 9 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(malformed-catalog.xml) 20 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /Names [(trailing-decoy.xml) 30 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (catalog-af-source.xml) /Desc (Catalog AF fallback after malformed EmbeddedFiles) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogPayload) . " /CheckSum <{$catalogChecksum}> /ModDate (D:20260607232919Z) >> /Length " . strlen($catalogPayload) . " >>\n"
        . "stream\n{$catalogPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (malformed-catalog.xml) /Desc (Malformed catalog EmbeddedFiles source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($nameTreePayload) . " /CheckSum <{$nameTreeChecksum}> >> /Length " . strlen($nameTreePayload) . " >>\n"
        . "stream\n{$nameTreePayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (trailing-decoy.xml) /Desc (Catalog trailing operand decoy) /AFRelationship /Alternative /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($trailingDecoyPayload) . " /CheckSum <{$trailingDecoyChecksum}> >> /Length " . strlen($trailingDecoyPayload) . " >>\n"
        . "stream\n{$trailingDecoyPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $catalogPayload,
        $nameTreePayload,
        $trailingDecoyPayload,
        $catalogChecksum,
        $nameTreeChecksum,
        $trailingDecoyChecksum,
    ];
};

return [
    'rejects EmbeddedFiles name-tree nodes with trailing /Names operands before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($embeddedFilesAttachmentNodeOperandBoundaryPdf): void {
        [
            $pdf,
            $cleanPayload,
            $malformedPayload,
            $trailingDecoyPayload,
            $cleanChecksum,
            $malformedChecksum,
            $trailingDecoyChecksum,
        ] = $embeddedFilesAttachmentNodeOperandBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($cleanPayload), $summary['total_bytes']);
        $t->same(['clean-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('clean-source.xml', $attachment['name_key']);
        $t->same('clean-source.xml', $attachment['filename']);
        $t->same('Clean EmbeddedFiles sibling source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(10, $attachment['file_spec_object_id']);
        $t->same(11, $attachment['stream_object_id']);
        $t->same(strlen($cleanPayload), $attachment['byte_length']);
        $t->same($cleanChecksum, $attachment['checksum_hex']);
        $t->same($cleanChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260607232341Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('clean-source.xml', $file['name']);
        $t->same('clean-source.xml', $file['filename']);
        $t->same('Clean EmbeddedFiles sibling source', $file['description']);
        $t->same('Source', $file['relationship']);
        $t->same(10, $file['file_spec_object']);
        $t->same(11, $file['embedded_file_object']);
        $t->same($cleanPayload, $file['content']);
        $t->same($cleanChecksum, $file['checksum']);
        $t->same($cleanChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Visible EmbeddedFiles Node Operand Boundary', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'malformed-source.xml',
            'trailing-decoy.xml',
            'Malformed node operand source',
            'Trailing operand decoy source',
            $malformedPayload,
            $trailingDecoyPayload,
            $malformedChecksum,
            $trailingDecoyChecksum,
            $cleanPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
    'rejects catalog Names EmbeddedFiles entries with trailing operands while preserving catalog AF attachments' => static function (
        TestRunner $t
    ) use ($embeddedFilesCatalogOperandBoundaryPdf): void {
        [
            $pdf,
            $catalogPayload,
            $nameTreePayload,
            $trailingDecoyPayload,
            $catalogChecksum,
            $nameTreeChecksum,
            $trailingDecoyChecksum,
        ] = $embeddedFilesCatalogOperandBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($catalogPayload), $summary['total_bytes']);
        $t->same(['catalog-af-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('catalog-associated-file', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same(0, $attachment['associated_file_index']);
        $t->same(1, $attachment['catalog_object_id']);
        $t->same('catalog-af-source.xml', $attachment['filename']);
        $t->same('Catalog AF fallback after malformed EmbeddedFiles', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(10, $attachment['file_spec_object_id']);
        $t->same(11, $attachment['stream_object_id']);
        $t->same(strlen($catalogPayload), $attachment['byte_length']);
        $t->same($catalogChecksum, $attachment['checksum_hex']);
        $t->same($catalogChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_associated_files', $file['source']);
        $t->same(true, $file['associated_file']);
        $t->same(0, $file['associated_file_index']);
        $t->same('catalog-af-source.xml', $file['name']);
        $t->same('catalog-af-source.xml', $file['filename']);
        $t->same('Catalog AF fallback after malformed EmbeddedFiles', $file['description']);
        $t->same('Source', $file['relationship']);
        $t->same(10, $file['file_spec_object']);
        $t->same(11, $file['embedded_file_object']);
        $t->same($catalogPayload, $file['content']);
        $t->same($catalogChecksum, $file['checksum']);
        $t->same($catalogChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Visible Catalog EmbeddedFiles Operand Boundary', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'malformed-catalog.xml',
            'trailing-decoy.xml',
            'Malformed catalog EmbeddedFiles source',
            'Catalog trailing operand decoy',
            $nameTreePayload,
            $trailingDecoyPayload,
            $nameTreeChecksum,
            $trailingDecoyChecksum,
            $catalogPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
