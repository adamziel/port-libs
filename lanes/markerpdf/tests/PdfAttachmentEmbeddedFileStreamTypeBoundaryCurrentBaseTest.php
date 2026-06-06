<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$embeddedFileStreamTypeBoundaryPdf = static function (): array {
    $metadataDecoyPayload = '<?xpacket begin="w"?><wp-export><post id="metadata-stream-decoy"/></wp-export><?xpacket end="w"?>';
    $xobjectDecoyPayload = '<wp-export><post id="xobject-stream-decoy"/></wp-export>';
    $legacyPayload = '<wp-export><post id="legacy-untyped-embedded-file"/></wp-export>';
    $typedPayload = '<wp-export><post id="typed-embedded-file"/></wp-export>';
    $metadataChecksum = md5($metadataDecoyPayload);
    $xobjectChecksum = md5($xobjectDecoyPayload);
    $legacyChecksum = md5($legacyPayload);
    $typedChecksum = md5($typedPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Embedded File Stream Type Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [30 0 R 40 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names ["
        . "(metadata-decoy.xml) 10 0 R "
        . "(xobject-decoy.xml) 20 0 R "
        . "(legacy-untyped.xml) 30 0 R "
        . "(typed-source.xml) 40 0 R"
        . "] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (metadata-decoy.xml) /Desc (Metadata stream decoy) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /Metadata /Subtype /XML /Params << /Size " . strlen($metadataDecoyPayload) . " /CheckSum <{$metadataChecksum}> >> /Length " . strlen($metadataDecoyPayload) . " >>\n"
        . "stream\n{$metadataDecoyPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (xobject-decoy.xml) /Desc (XObject stream decoy) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /XObject /Subtype /Image /Params << /Size " . strlen($xobjectDecoyPayload) . " /CheckSum <{$xobjectChecksum}> >> /Length " . strlen($xobjectDecoyPayload) . " >>\n"
        . "stream\n{$xobjectDecoyPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (legacy-untyped.xml) /Desc (Legacy untyped embedded source) /AFRelationship /Source /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Subtype /text#2Fxml /Params << /Size " . strlen($legacyPayload) . " /CheckSum <{$legacyChecksum}> /ModDate (D:20260606155704Z) >> /Length " . strlen($legacyPayload) . " >>\n"
        . "stream\n{$legacyPayload}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Filespec /F (typed-source.xml) /Desc (Typed embedded source) /AFRelationship /Source /EF << /F 41 0 R >> >>\nendobj\n"
        . "41 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($typedPayload) . " /CheckSum <{$typedChecksum}> /ModDate (D:20260606155705Z) >> /Length " . strlen($typedPayload) . " >>\n"
        . "stream\n{$typedPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $metadataDecoyPayload,
        $xobjectDecoyPayload,
        $legacyPayload,
        $typedPayload,
        $metadataChecksum,
        $xobjectChecksum,
        $legacyChecksum,
        $typedChecksum,
    ];
};

return [
    'excludes typed non-EmbeddedFile EF streams before WordPress attachment import' => static function (
        TestRunner $t
    ) use ($embeddedFileStreamTypeBoundaryPdf): void {
        [
            $pdf,
            $metadataDecoyPayload,
            $xobjectDecoyPayload,
            $legacyPayload,
            $typedPayload,
            $metadataChecksum,
            $xobjectChecksum,
            $legacyChecksum,
            $typedChecksum,
        ] = $embeddedFileStreamTypeBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same(strlen($legacyPayload) + strlen($typedPayload), $summary['total_bytes']);
        $t->same(['legacy-untyped.xml', 'typed-source.xml'], $summary['filenames']);

        $legacy = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $legacy['source']);
        $t->same('legacy-untyped.xml', $legacy['name_key']);
        $t->same('legacy-untyped.xml', $legacy['filename']);
        $t->same('Source', $legacy['relationship']);
        $t->same('original_source', $legacy['relationship_role']);
        $t->same(30, $legacy['file_spec_object_id']);
        $t->same(31, $legacy['stream_object_id']);
        $t->same('text/xml', $legacy['content_type']);
        $t->same($legacyChecksum, $legacy['checksum_hex']);
        $t->same($legacyChecksum, $legacy['computed_checksum_hex']);
        $t->same(true, $legacy['checksum_matches']);
        $t->same(true, $legacy['associated_file']);
        $t->same('catalog_af', $legacy['associated_file_source']);
        $t->same(false, array_key_exists('bytes', $legacy));

        $typed = $summary['attachments'][1];
        $t->same('embedded-files-name-tree', $typed['source']);
        $t->same('typed-source.xml', $typed['name_key']);
        $t->same('typed-source.xml', $typed['filename']);
        $t->same(40, $typed['file_spec_object_id']);
        $t->same(41, $typed['stream_object_id']);
        $t->same($typedChecksum, $typed['checksum_hex']);
        $t->same(true, $typed['checksum_matches']);
        $t->same(true, $typed['associated_file']);
        $t->same('catalog_af', $typed['associated_file_source']);

        $t->same(2, count($files));
        $t->same(['legacy-untyped.xml', 'typed-source.xml'], array_column($files, 'filename'));
        $t->same($legacyPayload, $files[0]['content']);
        $t->same($typedPayload, $files[1]['content']);

        $metadataFiles = $metadata['embedded_files'] ?? [];
        $t->true(is_array($metadataFiles));
        $t->same(2, count($metadataFiles));
        $t->same(['legacy-untyped.xml', 'typed-source.xml'], array_column($metadataFiles, 'filename'));

        $t->same('Embedded File Stream Type Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'metadata-decoy.xml',
            'xobject-decoy.xml',
            'Metadata stream decoy',
            'XObject stream decoy',
            $metadataDecoyPayload,
            $xobjectDecoyPayload,
            $metadataChecksum,
            $xobjectChecksum,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(is_string($metadataJson) && !str_contains($metadataJson, $hidden));
        }
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $legacyPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $typedPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'metadata-stream-decoy'));
        $t->true(!str_contains($plainText, 'xobject-stream-decoy'));
    },
];
