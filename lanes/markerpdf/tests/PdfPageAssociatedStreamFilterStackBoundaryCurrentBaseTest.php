<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'rejects page associated embedded-file stream stacks with trailing bytes after ASCIIHex EOD' => static function (TestRunner $t): void {
        $safePayload = '<wp-export><post id="safe-page-associated-stream"/></wp-export>';
        $unsafePayload = '<wp-export><post id="unsafe-page-associated-stack-leak"/></wp-export>';
        $pageText = 'BT /F1 12 Tf 72 720 Td (Visible Page Associated Stack Boundary Review) Tj ET';

        $safeCompressed = gzcompress($safePayload);
        $unsafeCompressed = gzcompress($unsafePayload);
        if (!is_string($safeCompressed) || !is_string($unsafeCompressed)) {
            throw new RuntimeException('Unable to compress page-associated stream stack fixtures.');
        }

        $safeEncoded = strtoupper(bin2hex($safeCompressed)) . ">\n% safe page-associated EOD comment\r\n";
        $unsafeEncoded = strtoupper(bin2hex($unsafeCompressed))
            . '>BT /F1 12 Tf 72 680 Td (Unsafe Page Associated Stack Leak) Tj ET';

        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true /UserProperties true /Suspects false >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /PieceInfo << /WPImport << /LastModified (D:20260607180500Z) /Private << /BatchId (page-associated-stack-boundary) /NeedsReview true >> >> >> /AF [10 0 R 12 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (safe-page-associated.xml) /Desc (Safe page associated source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Filter [ /ASCIIHexDecode /FlateDecode ] /Params << /Size " . strlen($safePayload) . " /CheckSum <" . md5($safePayload) . "> >> /Length " . strlen($safeEncoded) . " >>\nstream\n{$safeEncoded}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Filespec /F (unsafe-page-associated.xml) /Desc (Unsafe page associated stacked source) /AFRelationship /Source /EF << /F 13 0 R >> >>\nendobj\n"
            . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Filter [ /ASCIIHexDecode /FlateDecode ] /Params << /Size " . strlen($unsafePayload) . " /CheckSum <" . md5($unsafePayload) . "> >> /Length " . strlen($unsafeEncoded) . " >>\nstream\n{$unsafeEncoded}\nendstream\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $pages = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(1, count($pages));
        $page = $pages[0];
        $t->same('page-associated-stack-boundary', $page['piece_info']['WPImport']['private']['BatchId'] ?? null);
        $t->same(true, $page['piece_info']['WPImport']['private']['NeedsReview'] ?? null);

        $files = $page['page_associated_files'] ?? [];
        $t->same(2, count($files));
        $t->same(['safe-page-associated.xml', 'unsafe-page-associated.xml'], array_column($files, 'filename'));

        $safe = $files[0];
        $t->same(11, $safe['embedded_file_object'] ?? null);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $safe['filters'] ?? null);
        $t->same(strlen($safePayload), $safe['declared_size'] ?? null);
        $t->same(strlen($safePayload), $safe['size'] ?? null);
        $t->same(hash('sha256', $safePayload), $safe['content_sha256'] ?? null);
        $t->same(md5($safePayload), $safe['checksum'] ?? null);
        $t->same(md5($safePayload), $safe['computed_checksum'] ?? null);
        $t->same(true, $safe['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('content', $safe));

        $unsafe = $files[1];
        $t->same('Unsafe page associated stacked source', $unsafe['description'] ?? null);
        $t->same(false, array_key_exists('embedded_file_object', $unsafe));
        $t->same(false, array_key_exists('filters', $unsafe));
        $t->same(false, array_key_exists('declared_size', $unsafe));
        $t->same(false, array_key_exists('size', $unsafe));
        $t->same(false, array_key_exists('content_sha256', $unsafe));
        $t->same(false, array_key_exists('checksum', $unsafe));
        $t->same(false, array_key_exists('computed_checksum', $unsafe));
        $t->same(false, array_key_exists('checksum_matches', $unsafe));
        $t->same(false, array_key_exists('content', $unsafe));

        $t->contains('Visible Page Associated Stack Boundary Review', $plainText);
        $t->same(false, str_contains($plainText, 'safe-page-associated-stream'));
        $t->same(false, str_contains($plainText, 'unsafe-page-associated-stack-leak'));
        $t->same(false, str_contains($plainText, 'Unsafe Page Associated Stack Leak'));
    },
];
