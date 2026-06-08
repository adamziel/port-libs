<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataUtf8BomInfoBoundaryPdf = static function (): string {
    $body = 'BT /F1 12 Tf 72 720 Td (UTF8 BOM outline metadata body) Tj ET';
    $hexText = static fn (string $text): string => '<' . strtoupper(bin2hex("\xEF\xBB\xBF" . $text)) . '>';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title " . $hexText('UTF-8 BOM Outline Start') . " /Parent 5 0 R /Dest [3 0 R /FitH 720] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($body) . " >>\nstream\n{$body}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Title " . $hexText('UTF-8 BOM Info Title') . " /Author " . $hexText('UTF-8 BOM Metadata Team') . " /Keywords " . $hexText('UTF-8 BOM outline metadata') . " >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 40 0 R >>\n%%EOF";
};

return [
    'decodes UTF-8 BOM outline metadata strings across TOC and trailer Info review' => static function (
        TestRunner $t
    ) use ($outlineMetadataUtf8BomInfoBoundaryPdf): void {
        $pdf = $outlineMetadataUtf8BomInfoBoundaryPdf();
        $textExtractor = new PdfTextExtractor();
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
        $encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $t->same(1, $lightweight['pages']);
        $t->same([
            [
                'title' => 'UTF-8 BOM Outline Start',
                'level' => 1,
                'page' => 0,
            ],
        ], $lightweight['pdf_toc']);
        $t->same('UTF-8 BOM Info Title', $lightweight['document_info']['title'] ?? null);
        $t->same('UTF-8 BOM Metadata Team', $lightweight['document_info']['author'] ?? null);
        $t->same('UTF-8 BOM outline metadata', $lightweight['document_info']['keywords'] ?? null);

        $t->same(['UTF-8 BOM Outline Start'], array_column($toc, 'title'));
        $t->same('UTF-8 BOM Info Title', $metadata['title'] ?? null);
        $t->same('UTF-8 BOM Metadata Team', $metadata['authors'][0] ?? null);
        $t->same(['UTF-8 BOM Outline Start'], $metadata['document_outline']['titles'] ?? null);
        $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, "\xEF\xBB\xBF"));
        $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, 'ï»¿'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'ï»¿'));
    },
    'keeps UTF-8 BOM outline and Info metadata out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineMetadataUtf8BomInfoBoundaryPdf): void {
        $pdf = $outlineMetadataUtf8BomInfoBoundaryPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('UTF8 BOM outline metadata body', $plainText);
        $t->true(!str_contains($plainText, 'UTF-8 BOM Outline Start'));
        $t->true(!str_contains($plainText, 'UTF-8 BOM Info Title'));
        $t->true(!str_contains($plainText, 'UTF-8 BOM Metadata Team'));
        $t->true(!str_contains($plainText, 'ï»¿'));
    },
];
