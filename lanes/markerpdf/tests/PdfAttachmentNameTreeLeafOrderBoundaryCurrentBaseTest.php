<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentNameTreeLeafOrderBoundaryCurrentBasePdf = static function (): array {
    $payloads = [
        'alpha-source.xml' => '<wp-export><post id="alpha-leaf-order"/></wp-export>',
        'review-summary.xml' => '<wp-export><post id="review-leaf-order"/></wp-export>',
        'zulu-appendix.xml' => '<wp-export><post id="zulu-leaf-order"/></wp-export>',
    ];
    $content = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Leaf Order Body) Tj ET';

    $fileSpec = static function (int $fileSpecObject, int $streamObject, string $filename, string $description) use ($payloads): string {
        $payload = $payloads[$filename];
        $checksum = md5($payload);

        return "{$fileSpecObject} 0 obj\n"
            . "<< /Type /Filespec /F ({$filename}) /Desc ({$description}) /AFRelationship /Data /EF << /F {$streamObject} 0 R >> >>\n"
            . "endobj\n"
            . "{$streamObject} 0 obj\n"
            . "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260607101800Z) >> /Length " . strlen($payload) . " >>\n"
            . "stream\n{$payload}\nendstream\nendobj\n";
    };

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 8 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Limits [(alpha-source.xml) (zulu-appendix.xml)] /Names [(zulu-appendix.xml) 30 0 R (alpha-source.xml) 20 0 R (review-summary.xml) 24 0 R] >>\nendobj\n"
        . $fileSpec(20, 21, 'alpha-source.xml', 'Alpha WordPress source attachment')
        . $fileSpec(24, 25, 'review-summary.xml', 'Review summary WordPress source attachment')
        . $fileSpec(30, 31, 'zulu-appendix.xml', 'Zulu appendix WordPress source attachment')
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

    return [$pdf, $payloads];
};

return [
    'orders EmbeddedFiles leaf Names pairs by byte key before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($attachmentNameTreeLeafOrderBoundaryCurrentBasePdf): void {
        [$pdf, $payloads] = $attachmentNameTreeLeafOrderBoundaryCurrentBasePdf();
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $expectedOrder = [
            'alpha-source.xml',
            'review-summary.xml',
            'zulu-appendix.xml',
        ];

        $t->same(3, $summary['attachment_count']);
        $t->same($expectedOrder, $summary['filenames']);
        $t->same($expectedOrder, array_column($summary['attachments'], 'name_key'));
        $t->same($expectedOrder, array_column($summary['attachments'], 'filename'));
        $t->same([20, 24, 30], array_column($summary['attachments'], 'file_spec_object_id'));
        $t->same([21, 25, 31], array_column($summary['attachments'], 'stream_object_id'));
        $t->same(['Data', 'Data', 'Data'], array_column($summary['attachments'], 'relationship'));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $t->same(3, count($files));
        $t->same($expectedOrder, array_column($files, 'name'));
        $t->same($expectedOrder, array_column($files, 'filename'));
        $t->same([20, 24, 30], array_column($files, 'file_spec_object'));
        $t->same([21, 25, 31], array_column($files, 'embedded_file_object'));
        $t->same(array_values($payloads), array_column($files, 'content'));

        $t->same('Visible Attachment Leaf Order Body', $plainText);
        foreach ($payloads as $filename => $payload) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $payload));
            $t->true(!str_contains($plainText, $filename));
            $t->true(!str_contains($plainText, $payload));
        }
    },
];
