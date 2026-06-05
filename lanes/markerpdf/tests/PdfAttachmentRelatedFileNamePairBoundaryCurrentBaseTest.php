<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;

$relatedFilePairPdf = static function (): array {
    $sourcePayload = '<wp-export><post id="rf-pair"/></wp-export>';
    $stylePayload = "body{color:#111}\n";
    $manifestPayload = '{"asset":"print"}';
    $compressedManifest = gzcompress($manifestPayload);
    if (!is_string($compressedManifest)) {
        throw new RuntimeException('Unable to compress related manifest fixture.');
    }

    $styleChecksum = md5($stylePayload);
    $manifestChecksum = md5($manifestPayload);
    $unicodeRelatedName = iconv('UTF-8', 'UTF-16BE', 'print-manifest.json');
    if (!is_string($unicodeRelatedName)) {
        throw new RuntimeException('Unable to encode related filename fixture.');
    }
    $unicodeRelatedNameHex = strtoupper(bin2hex("\xFE\xFF" . $unicodeRelatedName));

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Names [(source.xml) 4 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Source with named related files) /AFRelationship /Source /EF << /F 5 0 R >> /RF << /F [(style.css) 6 0 R] /UF [<{$unicodeRelatedNameHex}> 7 0 R] >> >>\nendobj\n"
        . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Params << /Size " . strlen($stylePayload) . " /CheckSum <{$styleChecksum}> >> /Length " . strlen($stylePayload) . " >>\nstream\n{$stylePayload}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Filter /FlateDecode /Params << /Size " . strlen($manifestPayload) . " /CheckSum <{$manifestChecksum}> /ModDate (D:20260605044517Z) >> /Length " . strlen($compressedManifest) . " >>\nstream\n{$compressedManifest}\nendstream\nendobj\n"
        . "%%EOF\n";

    return [$pdf, $sourcePayload, $stylePayload, $manifestPayload, $styleChecksum, $manifestChecksum];
};

return [
    'summarizes FileSpec RF filename stream pairs in attachment preflight' => static function (TestRunner $t) use ($relatedFilePairPdf): void {
        [$pdf, $sourcePayload, $stylePayload, $manifestPayload, $styleChecksum, $manifestChecksum] = $relatedFilePairPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('source.xml', $attachment['filename']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same(2, $attachment['related_file_count']);

        $related = $attachment['related_files'];
        $t->same('F', $related[0]['rf_key']);
        $t->same(0, $related[0]['related_file_index']);
        $t->same('style.css', $related[0]['related_filename']);
        $t->same('rf_name_pair', $related[0]['related_filename_source']);
        $t->same(6, $related[0]['stream_object_id']);
        $t->same('text/css', $related[0]['content_type']);
        $t->same(strlen($stylePayload), $related[0]['byte_length']);
        $t->same($styleChecksum, $related[0]['checksum_hex']);
        $t->same($styleChecksum, $related[0]['computed_checksum_hex']);
        $t->same(true, $related[0]['checksum_matches']);

        $t->same('UF', $related[1]['rf_key']);
        $t->same(0, $related[1]['related_file_index']);
        $t->same('print-manifest.json', $related[1]['related_filename']);
        $t->same('rf_name_pair', $related[1]['related_filename_source']);
        $t->same(7, $related[1]['stream_object_id']);
        $t->same('application/json', $related[1]['content_type']);
        $t->same(['FlateDecode'], $related[1]['filters']);
        $t->same(strlen($manifestPayload), $related[1]['byte_length']);
        $t->same($manifestChecksum, $related[1]['checksum_hex']);
        $t->same($manifestChecksum, $related[1]['computed_checksum_hex']);
        $t->same(true, $related[1]['checksum_matches']);
        $t->same('D:20260605044517Z', $related[1]['modified_at']);

        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, array_key_exists('bytes', $related[0]));
        $t->same(false, array_key_exists('bytes', $related[1]));
        $t->true(is_string($encoded) && !str_contains($encoded, $sourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $stylePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $manifestPayload));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
    },
    'preserves FileSpec RF filename stream pairs in embedded-file review rows' => static function (TestRunner $t) use ($relatedFilePairPdf): void {
        [$pdf, $sourcePayload, $stylePayload, $manifestPayload, $styleChecksum, $manifestChecksum] = $relatedFilePairPdf();

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $encoded = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, count($files));

        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('source.xml', $file['filename']);
        $t->same($sourcePayload, $file['content']);
        $t->same(2, $file['related_file_count']);

        $related = $file['related_files'];
        $t->same('F', $related[0]['rf_key']);
        $t->same(0, $related[0]['related_file_index']);
        $t->same('style.css', $related[0]['related_filename']);
        $t->same('rf_name_pair', $related[0]['related_filename_source']);
        $t->same(6, $related[0]['embedded_file_object']);
        $t->same('text/css', $related[0]['mime_type']);
        $t->same(strlen($stylePayload), $related[0]['size']);
        $t->same(hash('sha256', $stylePayload), $related[0]['content_sha256']);
        $t->same($styleChecksum, $related[0]['checksum']);
        $t->same($styleChecksum, $related[0]['computed_checksum']);
        $t->same(true, $related[0]['checksum_matches']);

        $t->same('UF', $related[1]['rf_key']);
        $t->same(0, $related[1]['related_file_index']);
        $t->same('print-manifest.json', $related[1]['related_filename']);
        $t->same('rf_name_pair', $related[1]['related_filename_source']);
        $t->same(7, $related[1]['embedded_file_object']);
        $t->same('application/json', $related[1]['mime_type']);
        $t->same(['FlateDecode'], $related[1]['filters']);
        $t->same(strlen($manifestPayload), $related[1]['size']);
        $t->same(hash('sha256', $manifestPayload), $related[1]['content_sha256']);
        $t->same($manifestChecksum, $related[1]['checksum']);
        $t->same($manifestChecksum, $related[1]['computed_checksum']);
        $t->same(true, $related[1]['checksum_matches']);
        $t->same('D:20260605044517Z', $related[1]['modified_at']);

        $t->same(false, array_key_exists('content', $related[0]));
        $t->same(false, array_key_exists('content', $related[1]));
        $t->true(is_string($encoded) && !str_contains($encoded, $stylePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $manifestPayload));
    },
];
