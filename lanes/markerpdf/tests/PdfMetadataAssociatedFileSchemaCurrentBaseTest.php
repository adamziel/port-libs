<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'propagates catalog Collection schema to EmbeddedFiles name-tree metadata rows' => static function (TestRunner $t): void {
        $sourcePayload = '<wp-export><post id="schema-current"/></wp-export>';
        $previewPayload = '{"preview":"name-tree-schema"}';
        $sourceChecksum = strtoupper(hash('md5', $sourcePayload));
        $previewChecksum = str_repeat('0b', 16);
        $content = 'BT /F1 12 Tf 72 720 Td (Associated File Schema Body) Tj ET';

        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /Collection /View /T /D (source-unicode.xml) /Schema << /NameField << /Subtype /F /N (Filename) /O 1 >> /DescriptionField << /Subtype /Desc /N (Description) /O 2 /V true /E false >> /ModifiedField << /Subtype /ModDate /N (Modified) /O 3 >> /BytesField << /Subtype /Size /N (Bytes) /O 4 >> /Subject << /Subtype /S /N (Subject) /O 5 >> /Priority << /Subtype /N /N (Priority) /O 6 >> /ReviewDate << /Subtype /D /N (Reviewed) /O 7 >> >> /Sort << /S [/Priority /ReviewDate] /A [true false] >> >>\nendobj\n"
            . "6 0 obj\n<< /Limits [(preview.json) (source-unicode.xml)] /Names [(source-unicode.xml) 10 0 R (preview.json) << /Type /Filespec /F (preview.json) /Desc (Rendered name-tree preview) /AFRelationship /Alternative /CI << /Subject (Preview JSON) /Priority << /Type /CollectionSubitem /D 1 /P (P) >> /ReviewDate (D:20260602200200Z) /Stale (ignored) >> /EF << /F 21 0 R >> >>] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (source-unicode.xml) /Desc (Original WordPress export) /AFRelationship /Source /CI 30 0 R /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602200100Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
            . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($previewPayload) . " /CheckSum <{$previewChecksum}> >> /Length " . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream\nendobj\n"
            . "30 0 obj\n<< /Type /CollectionItem /Subject (Migration Source) /Priority << /Type /CollectionSubitem /D 2 /P (P) >> /ReviewDate (D:20260602200300Z) /Stale (not in schema) >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $embeddedFiles = $metadata['embedded_files'] ?? [];
        $source = $embeddedFiles[0] ?? [];
        $preview = $embeddedFiles[1] ?? [];

        $t->same(['catalog'], $metadata['source']);
        $t->same('Associated File Schema Body', $plainText);
        $t->same('catalog_collection', $metadata['collection']['source'] ?? null);
        $t->same(['Priority', 'ReviewDate'], $metadata['collection']['sort']['keys'] ?? []);
        $t->same(['NameField', 'DescriptionField', 'ModifiedField', 'BytesField', 'Subject', 'Priority', 'ReviewDate'], array_keys($metadata['collection']['schema'] ?? []));
        $t->same(2, count($embeddedFiles));

        $t->same('catalog_names_embedded_files', $source['source'] ?? null);
        $t->same('source-unicode.xml', $source['name_tree_name'] ?? null);
        $t->same('source-unicode.xml', $source['filename'] ?? null);
        $t->same('Original WordPress export', $source['description'] ?? null);
        $t->same('Source', $source['relationship'] ?? null);
        $t->same('text/xml', $source['mime_type'] ?? null);
        $t->same(true, $source['checksum_matches'] ?? null);
        $t->same('Migration Source', $source['collection_item']['Subject'] ?? null);
        $t->same('source-unicode.xml', $source['collection_field_values']['NameField']['value'] ?? null);
        $t->same('file_spec', $source['collection_field_values']['NameField']['source'] ?? null);
        $t->same('Original WordPress export', $source['collection_field_values']['DescriptionField']['value'] ?? null);
        $t->same('file_spec', $source['collection_field_values']['DescriptionField']['source'] ?? null);
        $t->same('D:20260602200100Z', $source['collection_field_values']['ModifiedField']['value'] ?? null);
        $t->same('embedded_file_params', $source['collection_field_values']['ModifiedField']['source'] ?? null);
        $t->same(strlen($sourcePayload), $source['collection_field_values']['BytesField']['value'] ?? null);
        $t->same('embedded_file_params', $source['collection_field_values']['BytesField']['source'] ?? null);
        $t->same('P2', $source['collection_field_values']['Priority']['display_value'] ?? null);
        $t->same('number', $source['collection_field_values']['Priority']['value_type'] ?? null);
        $t->same('D:20260602200300Z', $source['collection_field_values']['ReviewDate']['value'] ?? null);
        $t->true(!array_key_exists('Stale', $source['collection_field_values'] ?? []));
        $t->true(!array_key_exists('content', $source));

        $t->same('preview.json', $preview['name_tree_name'] ?? null);
        $t->same('Alternative', $preview['relationship'] ?? null);
        $t->same('application/json', $preview['mime_type'] ?? null);
        $t->same(false, $preview['checksum_matches'] ?? null);
        $t->same('Preview JSON', $preview['collection_field_values']['Subject']['value'] ?? null);
        $t->same('P1', $preview['collection_field_values']['Priority']['display_value'] ?? null);
        $t->same('D:20260602200200Z', $preview['collection_field_values']['ReviewDate']['value'] ?? null);
        $t->true(!array_key_exists('content', $preview));

        $t->true(is_string($encoded) && !str_contains($encoded, $sourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $previewPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'name-tree-schema'));
    },
];
