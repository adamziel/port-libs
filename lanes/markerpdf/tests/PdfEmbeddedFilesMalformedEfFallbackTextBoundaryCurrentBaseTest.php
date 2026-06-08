<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$malformedEfFallbackTextBoundaryPdf = static function (): array {
    $malformedPayload = 'BT /F1 12 Tf 72 720 Td (Malformed EF Payload Leak) Tj ET';
    $trailingOperandPayload = 'BT /F1 12 Tf 72 700 Td (Trailing EF Operand Leak) Tj ET';
    $validPayload = '<wp-export><post id="valid-malformed-ef-sibling"/></wp-export>';
    $validChecksum = md5($validPayload);

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
        . "6 0 obj\n<< /Names [(malformed-ef.txt) 10 0 R (valid-source.xml) 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (malformed-ef.txt) /Desc (Malformed direct EF stream operand) /AFRelationship /Data /EF 11 0 R 12 0 R >>\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($malformedPayload) . " >>\nstream\n{$malformedPayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Length " . strlen($trailingOperandPayload) . " >>\nstream\n{$trailingOperandPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (valid-source.xml) /Desc (Valid sibling source export) /AFRelationship /Source /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608200330Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $malformedPayload, $trailingOperandPayload, $validPayload, $validChecksum];
};

return [
    'excludes malformed direct EF stream operands from fallback text while preserving valid sibling attachments' => static function (
        TestRunner $t
    ) use ($malformedEfFallbackTextBoundaryPdf): void {
        [$pdf, $malformedPayload, $trailingOperandPayload, $validPayload, $validChecksum] = $malformedEfFallbackTextBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['valid-source.xml'], $summary['filenames']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('embedded-files-name-tree', $attachment['source'] ?? null);
        $t->same('valid-source.xml', $attachment['filename'] ?? null);
        $t->same('Valid sibling source export', $attachment['description'] ?? null);
        $t->same('Source', $attachment['relationship'] ?? null);
        $t->same('original_source', $attachment['relationship_role'] ?? null);
        $t->same(true, $attachment['associated_file'] ?? null);
        $t->same('catalog_af', $attachment['associated_file_source'] ?? null);
        $t->same(strlen($validPayload), $attachment['byte_length'] ?? null);
        $t->same($validChecksum, $attachment['checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0] ?? [];
        $t->same('catalog_names_embedded_files', $file['source'] ?? null);
        $t->same('valid-source.xml', $file['name'] ?? null);
        $t->same('valid-source.xml', $file['filename'] ?? null);
        $t->same('Valid sibling source export', $file['description'] ?? null);
        $t->same('Source', $file['relationship'] ?? null);
        $t->same('text/xml', $file['mime_type'] ?? null);
        $t->same(21, $file['embedded_file_object'] ?? null);
        $t->same(strlen($validPayload), $file['declared_size'] ?? null);
        $t->same(strlen($validPayload), $file['size'] ?? null);
        $t->same($validPayload, $file['content'] ?? null);
        $t->same($validChecksum, $file['checksum'] ?? null);
        $t->same(true, $file['checksum_matches'] ?? null);
        $t->same(hash('sha256', $validPayload), $file['content_sha256'] ?? null);

        $t->same('', $plainText);
        $t->true(is_string($summaryJson));
        $t->true(is_string($filesJson));
        foreach ([
            'malformed-ef.txt',
            'Malformed direct EF stream operand',
            $malformedPayload,
            $trailingOperandPayload,
            'Malformed EF Payload Leak',
            'Trailing EF Operand Leak',
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
