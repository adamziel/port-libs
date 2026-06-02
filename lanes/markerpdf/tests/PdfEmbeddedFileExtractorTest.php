<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$embeddedFilesPdf = static function (): array {
    $manifest = '{"title":"WP Import","blocks":2}';
    $compressedManifest = gzcompress($manifest);
    if (!is_string($compressedManifest)) {
        throw new RuntimeException('Unable to compress embedded manifest fixture.');
    }

    $notes = 'Reviewer notes for attached import.';
    $asciiHexNotes = strtoupper(bin2hex($notes)) . '>';
    $checksum = '00112233445566778899AABBCCDDEEFF';
    $unicodeFilenameBytes = iconv('UTF-8', 'UTF-16BE', 'wp-import-manifest.json');
    if (!is_string($unicodeFilenameBytes)) {
        throw new RuntimeException('Unable to encode UTF-16BE filename fixture.');
    }
    $unicodeFilenameHex = strtoupper(bin2hex("\xFE\xFF" . $unicodeFilenameBytes));

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length 48 >>\nstream\nBT /F1 12 Tf 72 720 Td (Visible Page Text) Tj ET\nendstream\nendobj\n"
        . "6 0 obj\n<< /Kids [7 0 R 8 0 R] >>\nendobj\n"
        . "7 0 obj\n<< /Limits [(a) (m)] /Names [(wp-import-manifest.json) 10 0 R (stale) 99 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(n) (z)] /Names [(review-notes.txt) << /Type /Filespec /F (review-notes.txt) /Desc (Editorial attachment notes) /EF << /F 13 0 R >> >>] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (legacy-manifest.json) /UF <{$unicodeFilenameHex}> /Desc (WordPress import manifest) /AFRelationship /Data /EF << /F 11 0 R /UF 12 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Length 11 >>\nstream\nlegacy only\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Filter /FlateDecode /Params << /Size " . strlen($manifest) . " /CheckSum <{$checksum}> /CreationDate (D:20260602033725Z) /ModDate (D:20260602033800Z) >> /Length " . strlen($compressedManifest) . " >>\nstream\n{$compressedManifest}\nendstream\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Filter /ASCIIHexDecode /Params << /Size " . strlen($notes) . " >> /Length " . strlen($asciiHexNotes) . " >>\nstream\n{$asciiHexNotes}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $manifest, $notes, strtolower($checksum)];
};

return [
    'extracts catalog EmbeddedFiles name-tree attachments for review metadata' => static function (TestRunner $t) use ($embeddedFilesPdf): void {
        [$pdf, $manifest, $notes, $checksum] = $embeddedFilesPdf();
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);

        $t->same(2, count($files));

        $manifestFile = $files[0];
        $t->same('catalog_names_embedded_files', $manifestFile['source']);
        $t->same('wp-import-manifest.json', $manifestFile['name']);
        $t->same('wp-import-manifest.json', $manifestFile['filename']);
        $t->same('wp-import-manifest.json', $manifestFile['unicode_filename']);
        $t->same('WordPress import manifest', $manifestFile['description']);
        $t->same('Data', $manifestFile['relationship']);
        $t->same('application/json', $manifestFile['mime_type']);
        $t->same('UF', $manifestFile['ef_key']);
        $t->same(10, $manifestFile['file_spec_object']);
        $t->same(12, $manifestFile['embedded_file_object']);
        $t->same(['FlateDecode'], $manifestFile['filters']);
        $t->same(strlen($manifest), $manifestFile['declared_size']);
        $t->same(strlen($manifest), $manifestFile['size']);
        $t->same($checksum, $manifestFile['checksum']);
        $t->same('D:20260602033725Z', $manifestFile['created_at']);
        $t->same('D:20260602033800Z', $manifestFile['modified_at']);
        $t->same($manifest, $manifestFile['content']);
        $t->same(hash('sha256', $manifest), $manifestFile['content_sha256']);

        $notesFile = $files[1];
        $t->same('review-notes.txt', $notesFile['name']);
        $t->same('review-notes.txt', $notesFile['filename']);
        $t->same('Editorial attachment notes', $notesFile['description']);
        $t->same('text/plain', $notesFile['mime_type']);
        $t->same('F', $notesFile['ef_key']);
        $t->same(null, $notesFile['file_spec_object']);
        $t->same(13, $notesFile['embedded_file_object']);
        $t->same(['ASCIIHexDecode'], $notesFile['filters']);
        $t->same(strlen($notes), $notesFile['declared_size']);
        $t->same($notes, $notesFile['content']);
    },
    'skips unresolved name-tree file specs and dedupes repeated attachment entries' => static function (TestRunner $t): void {
        $payload = 'Only attached once';
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "6 0 obj\n<< /Names [(duplicate.txt) 10 0 R (duplicate.txt) 10 0 R (missing.txt) 99 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (duplicate.txt) /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);

        $t->same(1, count($files));
        $t->same('duplicate.txt', $files[0]['filename']);
        $t->same($payload, $files[0]['content']);
    },
    'keeps embedded-file payload streams out of fallback page text extraction' => static function (TestRunner $t): void {
        $payload = 'BT /F1 12 Tf 72 720 Td (Attachment Payload Leak) Tj ET';
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "6 0 obj\n<< /Names [(payload.txt) 10 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (payload.txt) /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $text = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(1, count($files));
        $t->same($payload, $files[0]['content']);
        $t->same('', $text);
    },
    'returns no attachment review rows when catalog EmbeddedFiles is absent' => static function (TestRunner $t): void {
        $t->same([], (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles('%PDF-1.4 no catalog'));
        $t->same([], (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles("%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n%%EOF"));
    },
];
