<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineLightweightNamedDestinationPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Lightweight named destination intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Lightweight named destination appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Lightweight Named Destination Chapter) /Parent 5 0 R /Dest /NamedIntro /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Lightweight Named Destination Appendix) /Parent 5 0 R /Prev 6 0 R /Dest (NamedAppendix) /Metadata 40 0 R >>\nendobj\n"
        . "20 0 obj\n<< /Names [(NamedAppendix) [4 0 R /XYZ 64 null 0] (NamedIntro) [3 0 R /FitH 720]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Metadata /Subtype /XML /Length 68 >>\nstream\n<x:xmpmeta>Named destination outline metadata stays review only</x:xmpmeta>\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'resolves named outline destinations in lightweight upstream pdf_toc metadata' => static function (
        TestRunner $t
    ) use ($outlineLightweightNamedDestinationPdf): void {
        $pdf = $outlineLightweightNamedDestinationPdf();
        $textExtractor = new PdfTextExtractor();
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $richToc = (new PdfOutlineExtractor())->getPdfToc($pdf);

        $expectedLightweightToc = [
            ['title' => 'Lightweight Named Destination Chapter', 'level' => 1, 'page' => 0],
            ['title' => 'Lightweight Named Destination Appendix', 'level' => 1, 'page' => 1],
        ];

        $t->same(2, $lightweight['pages']);
        $t->same($expectedLightweightToc, $lightweight['pdf_toc']);
        $t->same([
            ['title' => 'Lightweight Named Destination Chapter', 'level' => 1, 'page' => 0, 'destination' => 'NamedIntro'],
            ['title' => 'Lightweight Named Destination Appendix', 'level' => 1, 'page' => 1, 'destination' => 'NamedAppendix'],
        ], $richToc);
        $t->same(2, $metadata['document_outline']['item_count'] ?? null);
        $t->same(2, $metadata['document_outline']['resolved_destination_count'] ?? null);
        $t->same(['NamedIntro', 'NamedAppendix'], array_column($metadata['document_outline']['items'] ?? [], 'destination'));
    },
    'keeps named-destination outline metadata streams out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineLightweightNamedDestinationPdf): void {
        $pdf = $outlineLightweightNamedDestinationPdf();
        $textExtractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same("Lightweight named destination intro body\nLightweight named destination appendix body", $plainText);
        $t->same(['Lightweight Named Destination Chapter', 'Lightweight Named Destination Appendix'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([0, 1], array_column($navigation['outline'] ?? [], 'page'));
        $t->same(['FitH', 'XYZ'], array_column($navigation['outline'] ?? [], 'view_mode'));
        $t->same('reviewed_outline_item_metadata_stream', $metadata['document_outline']['items'][1]['metadata_stream_review']['status'] ?? null);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Named destination outline metadata stays review only'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'Named destination outline metadata stays review only'));
        $t->true(!str_contains($plainText, 'Lightweight Named Destination Chapter'));
        $t->true(!str_contains($plainText, 'Lightweight Named Destination Appendix'));
        $t->true(!str_contains($plainText, 'Named destination outline metadata stays review only'));
    },
];
