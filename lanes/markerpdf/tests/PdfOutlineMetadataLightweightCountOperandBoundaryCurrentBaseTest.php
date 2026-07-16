<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineLightweightMalformedRootCountBoundaryPdf = static function (): string {
    $chapterContent = 'BT /F1 12 Tf 72 720 Td (Malformed root count chapter body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Malformed root count appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 0.5 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Malformed Root Count Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Malformed Root Count Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /Fit] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Title (Malformed Root Count Info Title) /Author (Current Outline Count Team) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 40 0 R >>\n"
        . "%%EOF";
};

$outlineLightweightMalformedItemCountBoundaryPdf = static function (): string {
    $chapterContent = 'BT /F1 12 Tf 72 720 Td (Malformed item count chapter body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Malformed item count appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Malformed Item Count Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R /First 8 0 R /Last 8 0 R /Count 0.5 >>\nendobj\n"
        . "7 0 obj\n<< /Title (Malformed Item Count Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /Fit] >>\nendobj\n"
        . "8 0 obj\n<< /Title (Malformed Item Count Child) /Parent 6 0 R /Dest [3 0 R /XYZ 72 680 0] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'treats malformed root Count decimals as absent in lightweight outline metadata' => static function (
        TestRunner $t
    ) use ($outlineLightweightMalformedRootCountBoundaryPdf): void {
        $pdf = $outlineLightweightMalformedRootCountBoundaryPdf();
        $textExtractor = new PdfTextExtractor();
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);

        $expectedTitles = ['Malformed Root Count Chapter', 'Malformed Root Count Appendix'];

        $t->same(2, $lightweight['pages']);
        $t->same('Malformed Root Count Info Title', $lightweight['document_info']['title'] ?? null);
        $t->same($expectedTitles, array_column($lightweight['pdf_toc'], 'title'));
        $t->same([0, 1], array_column($lightweight['pdf_toc'], 'page'));
        $t->same($expectedTitles, $metadata['document_outline']['titles'] ?? null);
        $t->true(array_key_exists('outline_count', $metadata['document_outline'] ?? []));
        $t->same(null, $metadata['document_outline']['outline_count']);
        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same("Malformed root count chapter body\nMalformed root count appendix body", $plainText);
        $t->true(!str_contains($plainText, 'Malformed Root Count Chapter'));
        $t->true(!str_contains($plainText, 'Malformed Root Count Appendix'));
    },
    'treats malformed item Count decimals as absent before lightweight child traversal' => static function (
        TestRunner $t
    ) use ($outlineLightweightMalformedItemCountBoundaryPdf): void {
        $pdf = $outlineLightweightMalformedItemCountBoundaryPdf();
        $textExtractor = new PdfTextExtractor();
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);

        $expectedTitles = [
            'Malformed Item Count Chapter',
            'Malformed Item Count Child',
            'Malformed Item Count Appendix',
        ];

        $t->same($expectedTitles, array_column($lightweight['pdf_toc'], 'title'));
        $t->same([1, 2, 1], array_column($lightweight['pdf_toc'], 'level'));
        $t->same([0, 0, 1], array_column($lightweight['pdf_toc'], 'page'));
        $t->same($expectedTitles, $metadata['document_outline']['titles'] ?? null);
        $firstItem = ($metadata['document_outline']['items'] ?? [])[0] ?? [];
        $t->true(array_key_exists('outline_count', $firstItem));
        $t->same(null, $firstItem['outline_count']);
        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same([1, 2, 1], array_column($toc, 'level'));
        $t->same("Malformed item count chapter body\nMalformed item count appendix body", $plainText);
        $t->true(!str_contains($plainText, 'Malformed Item Count Child'));
    },
];
