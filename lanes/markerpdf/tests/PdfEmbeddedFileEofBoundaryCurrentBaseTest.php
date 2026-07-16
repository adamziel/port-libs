<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;

return [
    'bounds full embedded-file extraction at terminal EOF before stale appended FileSpecs' => static function (TestRunner $t): void {
        $currentPayload = "Title,Status\nCurrent Embedded File,Ready\n";
        $stalePayload = "Title,Status\nPost EOF Stale Embedded File,Ignore\n";
        $currentChecksum = md5($currentPayload);
        $staleChecksum = md5($stalePayload);

        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "6 0 obj\n<< /Names [(current-eof.csv) 10 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (current-eof.csv) /Desc (Current EOF-bounded attachment) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605115253Z) >> /Length " . strlen($currentPayload) . " >>\n"
            . "stream\n{$currentPayload}\nendstream\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF\n"
            . "10 0 obj\n<< /Type /Filespec /F (post-eof-stale.csv) /Desc (Post EOF stale attachment) /AFRelationship /Alternative /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
            . "stream\n{$stalePayload}\nendstream\nendobj\n";

        $embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $embeddedJson = json_encode($embeddedFiles, JSON_UNESCAPED_SLASHES);
        $summaryJson = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES);

        $t->same(1, count($embeddedFiles));
        $file = $embeddedFiles[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('current-eof.csv', $file['name']);
        $t->same('current-eof.csv', $file['filename']);
        $t->same('Current EOF-bounded attachment', $file['description']);
        $t->same('Data', $file['relationship']);
        $t->same('text/csv', $file['mime_type']);
        $t->same('F', $file['ef_key']);
        $t->same(10, $file['file_spec_object']);
        $t->same(11, $file['embedded_file_object']);
        $t->same(strlen($currentPayload), $file['declared_size']);
        $t->same(strlen($currentPayload), $file['size']);
        $t->same($currentPayload, $file['content']);
        $t->same(hash('sha256', $currentPayload), $file['content_sha256']);
        $t->same($currentChecksum, $file['checksum']);
        $t->same($currentChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);
        $t->same('D:20260605115253Z', $file['modified_at']);
        $t->true(is_string($embeddedJson) && !str_contains($embeddedJson, 'post-eof-stale.csv'));
        $t->true(is_string($embeddedJson) && !str_contains($embeddedJson, 'Post EOF Stale Embedded File'));
        $t->true(is_string($embeddedJson) && !str_contains($embeddedJson, $staleChecksum));

        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-eof.csv'], $attachmentSummary['filenames']);
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, 'post-eof-stale.csv'));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, 'Post EOF Stale Embedded File'));
    },
];
