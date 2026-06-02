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
    $checksum = strtoupper(hash('md5', $manifest));
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
        $t->same('md5', $manifestFile['checksum_algorithm']);
        $t->same($checksum, $manifestFile['computed_checksum']);
        $t->same(true, $manifestFile['checksum_matches']);
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
    'normalizes embedded-file Params checksums and reports current content match state' => static function (TestRunner $t): void {
        $literalChecksum = static function (string $bytes): string {
            $escaped = '';
            foreach (unpack('C*', $bytes) ?: [] as $byte) {
                $escaped .= sprintf('\\%03o', $byte);
            }

            return '(' . $escaped . ')';
        };

        $verifiedPayload = 'Verified WordPress attachment bytes';
        $stalePayload = 'Edited payload after checksum';
        $legacyPayload = 'Legacy producer literal checksum';
        $staleChecksum = str_repeat('00', 16);
        $verifiedChecksum = hash('md5', $verifiedPayload);
        $legacyChecksum = hash('md5', $legacyPayload);

        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "6 0 obj\n<< /Names [(verified.txt) 10 0 R (stale.txt) 20 0 R (legacy.txt) 30 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (verified.txt) /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size " . strlen($verifiedPayload) . " /CheckSum " . $literalChecksum(hash('md5', $verifiedPayload, true)) . " /ModDate (D:20260602054400Z) >> /Length " . strlen($verifiedPayload) . " >>\nstream\n{$verifiedPayload}\nendstream\nendobj\n"
            . "20 0 obj\n<< /Type /Filespec /F (stale.txt) /EF << /F 21 0 R >> >>\nendobj\n"
            . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream\nendobj\n"
            . "30 0 obj\n<< /Type /Filespec /F (legacy.txt) /EF << /F 31 0 R >> >>\nendobj\n"
            . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size " . strlen($legacyPayload) . " /CheckSum ({$legacyChecksum}) >> /Length " . strlen($legacyPayload) . " >>\nstream\n{$legacyPayload}\nendstream\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);

        $t->same(3, count($files));

        $verified = $files[0];
        $t->same('verified.txt', $verified['filename']);
        $t->same($verifiedPayload, $verified['content']);
        $t->same($verifiedChecksum, $verified['checksum']);
        $t->same('md5', $verified['checksum_algorithm']);
        $t->same($verifiedChecksum, $verified['computed_checksum']);
        $t->same(true, $verified['checksum_matches']);
        $t->same('D:20260602054400Z', $verified['modified_at']);

        $stale = $files[1];
        $t->same('stale.txt', $stale['filename']);
        $t->same($stalePayload, $stale['content']);
        $t->same($staleChecksum, $stale['checksum']);
        $t->same(hash('md5', $stalePayload), $stale['computed_checksum']);
        $t->same(false, $stale['checksum_matches']);

        $legacy = $files[2];
        $t->same('legacy.txt', $legacy['filename']);
        $t->same($legacyPayload, $legacy['content']);
        $t->same($legacyChecksum, $legacy['checksum']);
        $t->same($legacyChecksum, $legacy['computed_checksum']);
        $t->same(true, $legacy['checksum_matches']);
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
    'keeps Filespec EF payload streams without EmbeddedFile type out of fallback text extraction' => static function (TestRunner $t): void {
        $payload = 'BT /F1 12 Tf 72 720 Td (Filespec Payload Leak) Tj ET';
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "6 0 obj\n<< /Names [(payload.txt) 10 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (payload.txt) /Desc (Review-only payload) /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Subtype /text#2Fplain /Note (fake endobj before stream) /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $text = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(1, count($files));
        $t->same('payload.txt', $files[0]['filename']);
        $t->same('Review-only payload', $files[0]['description']);
        $t->same('text/plain', $files[0]['mime_type']);
        $t->same($payload, $files[0]['content']);
        $t->same('', $text);
    },
    'extracts catalog associated Filespec entries with AFRelationship review metadata' => static function (TestRunner $t): void {
        $sourceXml = '<wp-export><post id="7"/></wp-export>';
        $previewText = 'Rendered preview notes';
        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AF [10 0 R << /Type /Filespec /UF (preview.pdf) /Desc (Rendered preview) /AFRelationship /Alternative /EF << /UF 15 0 R >> >> 99 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourceXml) . " >> /Length " . strlen($sourceXml) . " >>\nstream\n{$sourceXml}\nendstream\nendobj\n"
            . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length " . strlen($previewText) . " >>\nstream\n{$previewText}\nendstream\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);

        $t->same(2, count($files));

        $sourceFile = $files[0];
        $t->same('catalog_associated_files', $sourceFile['source']);
        $t->same(true, $sourceFile['associated_file']);
        $t->same(0, $sourceFile['associated_file_index']);
        $t->same('source.xml', $sourceFile['name']);
        $t->same('source.xml', $sourceFile['filename']);
        $t->same('Source', $sourceFile['relationship']);
        $t->same('Original WordPress export', $sourceFile['description']);
        $t->same('text/xml', $sourceFile['mime_type']);
        $t->same(10, $sourceFile['file_spec_object']);
        $t->same(11, $sourceFile['embedded_file_object']);
        $t->same(strlen($sourceXml), $sourceFile['declared_size']);
        $t->same($sourceXml, $sourceFile['content']);

        $previewFile = $files[1];
        $t->same('catalog_associated_files', $previewFile['source']);
        $t->same(true, $previewFile['associated_file']);
        $t->same(1, $previewFile['associated_file_index']);
        $t->same('preview.pdf', $previewFile['name']);
        $t->same('preview.pdf', $previewFile['filename']);
        $t->same('Alternative', $previewFile['relationship']);
        $t->same('Rendered preview', $previewFile['description']);
        $t->same('application/pdf', $previewFile['mime_type']);
        $t->same(null, $previewFile['file_spec_object']);
        $t->same(15, $previewFile['embedded_file_object']);
        $t->same($previewText, $previewFile['content']);
    },
    'extracts PDF portfolio collection item and PieceInfo metadata from EmbeddedFiles name trees' => static function (TestRunner $t): void {
        $exportXml = '<wp-export><post id="42"/></wp-export>';
        $notes = 'Portfolio review notes';
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /PieceInfo << /WPPortfolio << /LastModified (D:20260602043000Z) /Private << /Workflow (WordPress migration review) /Batch 7 /Kind /Portfolio >> >> >> /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "5 0 obj\n<< /Type /Collection /View /D /D (wp-export.xml) /Schema << /Subject << /Subtype /S /N (Subject) /O 1 /V true /E false >> /Modified << /Subtype /D /N (Modified) /O 2 >> /Size << /Subtype /Size /N (Size) /O 3 >> >> /Sort << /S [/Subject /Modified] /A [false true] >> >>\nendobj\n"
            . "6 0 obj\n<< /Kids [7 0 R] >>\nendobj\n"
            . "7 0 obj\n<< /Limits [(review-notes.txt) (wp-export.xml)] /Names [(wp-export.xml) 10 0 R (review-notes.txt) 20 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (wp-export.xml) /Desc (Original WordPress export) /AFRelationship /Source /CI 30 0 R /PieceInfo 31 0 R /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($exportXml) . " >> /Length " . strlen($exportXml) . " >>\nstream\n{$exportXml}\nendstream\nendobj\n"
            . "20 0 obj\n<< /Type /Filespec /F (review-notes.txt) /Desc (Portfolio review notes) /CI << /Subject (Editorial Notes) /Size " . strlen($notes) . " >> /EF << /F 21 0 R >> >>\nendobj\n"
            . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Length " . strlen($notes) . " >>\nstream\n{$notes}\nendstream\nendobj\n"
            . "30 0 obj\n<< /Subject (WordPress Export) /Modified (D:20260602043100Z) /Size " . strlen($exportXml) . " /Review << /Type /CollectionSubitem /D (Approved) /P (Status: ) >> >>\nendobj\n"
            . "31 0 obj\n<< /WPImporter << /LastModified (D:20260602043200Z) /Private << /ManifestId (wp-42) /Preserve true /Priority 2 >> >> >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);

        $t->same(2, count($files));

        $export = $files[0];
        $t->same('wp-export.xml', $export['filename']);
        $t->same('Original WordPress export', $export['description']);
        $t->same('Source', $export['relationship']);
        $t->same('text/xml', $export['mime_type']);
        $t->same($exportXml, $export['content']);
        $t->same('catalog_collection', $export['portfolio']['source']);
        $t->same('Collection', $export['portfolio']['type']);
        $t->same('D', $export['portfolio']['view']);
        $t->same('wp-export.xml', $export['portfolio']['default_document']);
        $t->same('Subject', $export['portfolio']['schema']['Subject']['label']);
        $t->same('S', $export['portfolio']['schema']['Subject']['subtype']);
        $t->same(1, $export['portfolio']['schema']['Subject']['order']);
        $t->same(true, $export['portfolio']['schema']['Subject']['visible']);
        $t->same(false, $export['portfolio']['schema']['Subject']['editable']);
        $t->same(['Subject', 'Modified'], $export['portfolio']['sort']['keys']);
        $t->same([false, true], $export['portfolio']['sort']['ascending']);
        $t->same('WordPress Export', $export['portfolio_item']['Subject']);
        $t->same('D:20260602043100Z', $export['portfolio_item']['Modified']);
        $t->same(strlen($exportXml), $export['portfolio_item']['Size']);
        $t->same('Approved', $export['portfolio_item']['Review']['value']);
        $t->same('Status: ', $export['portfolio_item']['Review']['prefix']);
        $t->same('D:20260602043200Z', $export['piece_info']['WPImporter']['last_modified']);
        $t->same('wp-42', $export['piece_info']['WPImporter']['private']['ManifestId']);
        $t->same(true, $export['piece_info']['WPImporter']['private']['Preserve']);
        $t->same(2, $export['piece_info']['WPImporter']['private']['Priority']);
        $t->same('D:20260602043000Z', $export['catalog_piece_info']['WPPortfolio']['last_modified']);
        $t->same('WordPress migration review', $export['catalog_piece_info']['WPPortfolio']['private']['Workflow']);
        $t->same(7, $export['catalog_piece_info']['WPPortfolio']['private']['Batch']);
        $t->same('Portfolio', $export['catalog_piece_info']['WPPortfolio']['private']['Kind']);

        $review = $files[1];
        $t->same('review-notes.txt', $review['filename']);
        $t->same('Portfolio review notes', $review['description']);
        $t->same('text/plain', $review['mime_type']);
        $t->same('Editorial Notes', $review['portfolio_item']['Subject']);
        $t->same(strlen($notes), $review['portfolio_item']['Size']);
        $t->same($export['portfolio'], $review['portfolio']);
        $t->same($export['catalog_piece_info'], $review['catalog_piece_info']);
    },
    'keeps Filespec PieceInfo private streams review-only and out of fallback text extraction' => static function (TestRunner $t): void {
        $attachmentPayload = '<wp-export><post id="84"/></wp-export>';
        $privatePayload = 'BT /F1 12 Tf 72 720 Td (PieceInfo Private Leak) Tj ET';
        $compressedPrivatePayload = gzcompress($privatePayload);
        if (!is_string($compressedPrivatePayload)) {
            throw new RuntimeException('Unable to compress PieceInfo private stream fixture.');
        }

        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Collection 5 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "5 0 obj\n<< /Type /Collection /View /D /D (wp-export.xml) >>\nendobj\n"
            . "6 0 obj\n<< /Names [(wp-export.xml) 10 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (wp-export.xml) /Desc (Original WordPress export) /AFRelationship /Source /PieceInfo 30 0 R /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($attachmentPayload) . " >> /Length " . strlen($attachmentPayload) . " >>\nstream\n{$attachmentPayload}\nendstream\nendobj\n"
            . "30 0 obj\n<< /WPImporter << /LastModified (D:20260602084600Z) /Private 31 0 R >> >>\nendobj\n"
            . "31 0 obj\n<< /Type /Metadata /Subtype /application#2Fjson /Filter /FlateDecode /Length " . strlen($compressedPrivatePayload) . " >>\nstream\n{$compressedPrivatePayload}\nendstream\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $text = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('wp-export.xml', $file['filename']);
        $t->same('Source', $file['relationship']);
        $t->same($attachmentPayload, $file['content']);
        $t->same('D:20260602084600Z', $file['piece_info']['WPImporter']['last_modified']);

        $privateStream = $file['piece_info']['WPImporter']['private_stream'];
        $t->same(31, $privateStream['object']);
        $t->same(strlen($compressedPrivatePayload), $privateStream['declared_length']);
        $t->same(strlen($privatePayload), $privateStream['size']);
        $t->same('application/json', $privateStream['mime_type']);
        $t->same(['FlateDecode'], $privateStream['filters']);
        $t->same(hash('sha256', $privatePayload), $privateStream['content_sha256']);
        $t->same(false, array_key_exists('content', $privateStream));
        $t->same('', $text);
    },
    'returns no attachment review rows when catalog EmbeddedFiles is absent' => static function (TestRunner $t): void {
        $t->same([], (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles('%PDF-1.4 no catalog'));
        $t->same([], (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles("%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n%%EOF"));
    },
];
