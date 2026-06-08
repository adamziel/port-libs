<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentRelatedFilePairOperandBoundaryPdf = static function (): array {
    $sourcePayload = '<wp-export><post id="rf-pair-boundary-source"/></wp-export>';
    $stylePayload = "body{color:#123}\n";
    $unpairedDecoyPayload = 'UNPAIRED_RELATED_FILE_STREAM_SHOULD_NOT_BE_REVIEWED';
    $manifestPayload = '{"manifest":"paired"}';
    $legacyJsonPayload = '{"legacy":"stream-only-one"}';
    $legacyTextPayload = 'BT /F1 12 Tf 72 720 Td (Legacy stream-only related file) Tj ET';
    $content = 'BT /F1 12 Tf 72 720 Td (Related File Pair Operand Boundary Body) Tj ET';

    $sourceChecksum = md5($sourcePayload);
    $styleChecksum = md5($stylePayload);
    $unpairedDecoyChecksum = md5($unpairedDecoyPayload);
    $manifestChecksum = md5($manifestPayload);
    $legacyJsonChecksum = md5($legacyJsonPayload);
    $legacyTextChecksum = md5($legacyTextPayload);

    $compressedManifest = gzcompress($manifestPayload);
    if (!is_string($compressedManifest)) {
        throw new RuntimeException('Unable to compress related manifest fixture.');
    }

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(source.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Source with bounded related file pairs) /AFRelationship /Source /EF << /F 11 0 R >>"
        . " /RF << /F [(style.css) 12 0 R 13 0 R (manifest.json) 14 0 R] /UF [15 0 R 16 0 R] >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> >> /Length " . strlen($sourcePayload) . " >>\n"
        . "stream\n{$sourcePayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Params << /Size " . strlen($stylePayload) . " /CheckSum <{$styleChecksum}> >> /Length " . strlen($stylePayload) . " >>\n"
        . "stream\n{$stylePayload}\nendstream\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size " . strlen($unpairedDecoyPayload) . " /CheckSum <{$unpairedDecoyChecksum}> >> /Length " . strlen($unpairedDecoyPayload) . " >>\n"
        . "stream\n{$unpairedDecoyPayload}\nendstream\nendobj\n"
        . "14 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Filter /FlateDecode /Params << /Size " . strlen($manifestPayload) . " /CheckSum <{$manifestChecksum}> /ModDate (D:20260608020019Z) >> /Length " . strlen($compressedManifest) . " >>\n"
        . "stream\n{$compressedManifest}\nendstream\nendobj\n"
        . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($legacyJsonPayload) . " /CheckSum <{$legacyJsonChecksum}> >> /Length " . strlen($legacyJsonPayload) . " >>\n"
        . "stream\n{$legacyJsonPayload}\nendstream\nendobj\n"
        . "16 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size " . strlen($legacyTextPayload) . " /CheckSum <{$legacyTextChecksum}> >> /Length " . strlen($legacyTextPayload) . " >>\n"
        . "stream\n{$legacyTextPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $sourcePayload,
        $stylePayload,
        $unpairedDecoyPayload,
        $manifestPayload,
        $legacyJsonPayload,
        $legacyTextPayload,
        $sourceChecksum,
        $styleChecksum,
        $unpairedDecoyChecksum,
        $manifestChecksum,
        $legacyJsonChecksum,
        $legacyTextChecksum,
    ];
};

return [
    'skips unpaired RF stream operands in mixed filename-pair arrays while preserving stream-only related arrays' => static function (
        TestRunner $t
    ) use ($attachmentRelatedFilePairOperandBoundaryPdf): void {
        [
            $pdf,
            $sourcePayload,
            $stylePayload,
            $unpairedDecoyPayload,
            $manifestPayload,
            $legacyJsonPayload,
            $legacyTextPayload,
            $sourceChecksum,
            $styleChecksum,
            $unpairedDecoyChecksum,
            $manifestChecksum,
            $legacyJsonChecksum,
            $legacyTextChecksum,
        ] = $attachmentRelatedFilePairOperandBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['source.xml'], $summary['filenames']);
        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('Source', $attachment['relationship']);
        $t->same($sourceChecksum, $attachment['checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(4, $attachment['related_file_count']);

        $related = $attachment['related_files'];
        $t->same(['F', 'F', 'UF', 'UF'], array_column($related, 'rf_key'));
        $t->same([0, 1, 0, 1], array_column($related, 'related_file_index'));
        $t->same([12, 14, 15, 16], array_column($related, 'stream_object_id'));
        $t->same('style.css', $related[0]['related_filename']);
        $t->same('rf_name_pair', $related[0]['related_filename_source']);
        $t->same($styleChecksum, $related[0]['checksum_hex']);
        $t->same(true, $related[0]['checksum_matches']);
        $t->same('manifest.json', $related[1]['related_filename']);
        $t->same(['FlateDecode'], $related[1]['filters']);
        $t->same($manifestChecksum, $related[1]['checksum_hex']);
        $t->same(true, $related[1]['checksum_matches']);
        $t->same($legacyJsonChecksum, $related[2]['checksum_hex']);
        $t->same($legacyTextChecksum, $related[3]['checksum_hex']);
        $t->same(false, array_key_exists('related_filename', $related[2]));
        $t->same(false, array_key_exists('related_filename', $related[3]));
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, array_key_exists('bytes', $related[0]));
        $t->same(false, array_key_exists('bytes', $related[1]));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('source.xml', $file['filename']);
        $t->same($sourcePayload, $file['content']);
        $t->same(4, $file['related_file_count']);
        $t->same([12, 14, 15, 16], array_column($file['related_files'], 'embedded_file_object'));
        $t->same('style.css', $file['related_files'][0]['related_filename']);
        $t->same('manifest.json', $file['related_files'][1]['related_filename']);
        $t->same(false, array_key_exists('related_filename', $file['related_files'][2]));
        $t->same(false, array_key_exists('related_filename', $file['related_files'][3]));

        $associated = $metadata['associated_files'][0] ?? [];
        $t->same(4, $associated['related_file_count'] ?? null);
        $t->same([12, 14, 15, 16], array_column($associated['related_files'] ?? [], 'embedded_file_object'));
        $t->same('style.css', $associated['related_files'][0]['related_filename'] ?? null);
        $t->same('manifest.json', $associated['related_files'][1]['related_filename'] ?? null);
        $t->same(false, array_key_exists('related_filename', $associated['related_files'][2] ?? []));
        $t->same(false, array_key_exists('related_filename', $associated['related_files'][3] ?? []));
        $t->same([12, 14, 15, 16], $associated['provenance_review']['related_files']['embedded_file_objects'] ?? []);

        $t->same('Related File Pair Operand Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            $stylePayload,
            $unpairedDecoyPayload,
            $manifestPayload,
            $legacyJsonPayload,
            $legacyTextPayload,
            $unpairedDecoyChecksum,
            'UNPAIRED_RELATED_FILE_STREAM_SHOULD_NOT_BE_REVIEWED',
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(is_string($metadataJson) && !str_contains($metadataJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
