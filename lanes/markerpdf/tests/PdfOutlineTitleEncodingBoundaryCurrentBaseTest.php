<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineTitleEncodingBoundaryPdf = static function (): array {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Outline encoding page one body) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Outline encoding page two body) Tj ET';
    $pageThreeContent = 'BT /F1 12 Tf 72 720 Td (Outline encoding page three body) Tj ET';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 11 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 9 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Import \\223nance \\200 Summary) /Parent 5 0 R /Dest [3 0 R /XYZ 72 720 0] /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title <52657669657720852044617368> /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /FitH 700] /Next 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Title 32 0 R /Parent 5 0 R /Prev 7 0 R /A << /S /GoTo /D [11 0 R /Fit] >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 33 0 R >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "32 0 obj\n(Checklist \\212 Sign)\nendobj\n"
        . "33 0 obj\n<< /Length " . strlen($pageThreeContent) . " >>\nstream\n{$pageThreeContent}\nendstream\nendobj\n"
        . "%%EOF";

    $expectedTitles = [
        'Import ' . mb_chr(0xfb01, 'UTF-8') . 'nance ' . mb_chr(0x2022, 'UTF-8') . ' Summary',
        'Review ' . mb_chr(0x2013, 'UTF-8') . ' Dash',
        'Checklist ' . mb_chr(0x2212, 'UTF-8') . ' Sign',
    ];

    return [$pdf, $expectedTitles];
};

return [
    'decodes PDFDocEncoding outline titles for TOC navigation and document metadata' => static function (
        TestRunner $t
    ) use ($outlineTitleEncodingBoundaryPdf): void {
        [$pdf, $expectedTitles] = $outlineTitleEncodingBoundaryPdf();
        $textExtractor = new PdfTextExtractor();
        $outlineExtractor = new PdfOutlineExtractor();
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];

        $t->same($expectedTitles, array_column($lightweight['pdf_toc'], 'title'));
        $t->same([0, 1, 2], array_column($lightweight['pdf_toc'], 'page'));
        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same([0, 1, 2], array_column($toc, 'page'));
        $t->same(['XYZ', 'FitH', 'Fit'], array_column($toc, 'view_mode'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same($expectedTitles, $outline['titles'] ?? []);
        $t->same(3, $outline['item_count'] ?? null);
        $t->same(3, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
    },
    'keeps decoded outline title metadata out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineTitleEncodingBoundaryPdf): void {
        [$pdf, $expectedTitles] = $outlineTitleEncodingBoundaryPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $t->same("Outline encoding page one body\nOutline encoding page two body\nOutline encoding page three body", $plainText);
        foreach ($expectedTitles as $title) {
            $t->true(is_string($encodedMetadata) && str_contains($encodedMetadata, $title));
            $t->true(is_string($encodedNavigation) && str_contains($encodedNavigation, $title));
            $t->true(!str_contains($plainText, $title));
        }
        $t->true(!str_contains($plainText, 'Import'));
        $t->true(!str_contains($plainText, 'Review'));
        $t->true(!str_contains($plainText, 'Checklist'));
    },
];
