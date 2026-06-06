<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentNameTreePdfDocEncodingByteLimitsCurrentBasePdf = static function (): array {
    $lowPayload = '<wp-export><post id="pdfdoc-low-byte-source"/></wp-export>';
    $upperPayload = '<wp-export><post id="pdfdoc-upper-bound-source"/></wp-export>';
    $stalePayload = '<wp-export><post id="pdfdoc-stale-out-of-range"/></wp-export>';
    $content = 'BT /F1 12 Tf 72 720 Td (Visible PDFDoc Byte Limits Body) Tj ET';

    $lowNameHex = strtoupper(bin2hex(chr(0x18)));
    $lowChecksum = md5($lowPayload);
    $upperChecksum = md5($upperPayload);
    $staleChecksum = md5($stalePayload);

    $fileSpec = static function (
        int $fileSpecObject,
        int $streamObject,
        string $filename,
        string $description,
        string $payload,
        string $checksum
    ): string {
        return "{$fileSpecObject} 0 obj\n"
            . "<< /Type /Filespec /F ({$filename}) /Desc ({$description}) /AFRelationship /Data /EF << /F {$streamObject} 0 R >> >>\n"
            . "endobj\n"
            . "{$streamObject} 0 obj\n"
            . "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($payload) . " >>\n"
            . "stream\n{$payload}\nendstream\nendobj\n";
    };

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Limits [<{$lowNameHex}> (z-report.xml)] /Names [<{$lowNameHex}> 10 0 R (z-report.xml) 12 0 R (zzzz-stale.xml) 14 0 R] >>\nendobj\n"
        . $fileSpec(10, 11, 'breve-source.xml', 'PDFDoc low raw-byte WordPress source', $lowPayload, $lowChecksum)
        . $fileSpec(12, 13, 'z-report.xml', 'Upper-bound WordPress source attachment', $upperPayload, $upperChecksum)
        . $fileSpec(14, 15, 'zzzz-stale.xml', 'Out-of-range stale attachment', $stalePayload, $staleChecksum)
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

    return [$pdf, $lowPayload, $upperPayload, $stalePayload, $lowChecksum, $upperChecksum];
};

return [
    'uses raw PDF string bytes for EmbeddedFiles name-tree Limits before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($attachmentNameTreePdfDocEncodingByteLimitsCurrentBasePdf): void {
        [$pdf, $lowPayload, $upperPayload, $stalePayload, $lowChecksum, $upperChecksum] = $attachmentNameTreePdfDocEncodingByteLimitsCurrentBasePdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same(['breve-source.xml', 'z-report.xml'], $summary['filenames']);
        $t->same(["\u{02D8}", 'z-report.xml'], array_column($summary['attachments'], 'name_key'));
        $t->same(['breve-source.xml', 'z-report.xml'], array_column($summary['attachments'], 'filename'));
        $t->same([10, 12], array_column($summary['attachments'], 'file_spec_object_id'));
        $t->same([11, 13], array_column($summary['attachments'], 'stream_object_id'));
        $t->same([$lowChecksum, $upperChecksum], array_column($summary['attachments'], 'checksum_hex'));
        $t->same([true, true], array_column($summary['attachments'], 'checksum_matches'));
        $t->same(false, array_key_exists('bytes', $summary['attachments'][0]));

        $t->same(2, count($files));
        $t->same(["\u{02D8}", 'z-report.xml'], array_column($files, 'name'));
        $t->same(['breve-source.xml', 'z-report.xml'], array_column($files, 'filename'));
        $t->same([$lowPayload, $upperPayload], array_column($files, 'content'));
        $t->same([$lowChecksum, $upperChecksum], array_column($files, 'checksum'));
        $t->same([true, true], array_column($files, 'checksum_matches'));

        $t->same('Visible PDFDoc Byte Limits Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $lowPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $upperPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $stalePayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, 'zzzz-stale.xml'));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $stalePayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'zzzz-stale'));
    },
];
