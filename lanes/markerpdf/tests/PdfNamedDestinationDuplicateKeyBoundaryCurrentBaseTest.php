<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationDuplicateKeyBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Current duplicate destination jump Safe URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Duplicate destination current target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /DuplicateReview [3 0 R /Fit] /LegacyOnly [3 0 R /FitV 90] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 244 718] /Dest (DuplicateReview) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [254 700 324 718] /A << /S /URI /URI (https://example.com/duplicate-destination-safe-uri) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(DuplicateReview) (SummaryReview)] /Kids [21 0 R 22 0 R 23 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [(DuplicateReview) (DuplicateReview)] /Names [(DuplicateReview) [3 0 R /FitH 111]] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [(DuplicateReview) (SummaryReview)] /Names [(DuplicateReview) [4 0 R /XYZ 72 640 0] (SummaryReview) [4 0 R /FitBH 600]] >>\nendobj\n"
        . "23 0 obj\n<< /Limits [(DuplicateReview) (DuplicateReview)] /Names [(DuplicateReview) [99 /FitH 222]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Count 1 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Duplicate Destination Outline) /Parent 50 0 R /Dest (DuplicateReview) >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationDuplicateKeyBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 324.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 324.0, 718.0],
                'spans' => [
                    ['text' => 'Current duplicate destination jump', 'bbox' => [72.0, 700.0, 244.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [254.0, 700.0, 324.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'uses the later valid duplicate name-tree destination before WordPress review metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationDuplicateKeyBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationDuplicateKeyBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);

        $t->same(['DuplicateReview', 'SummaryReview', 'LegacyOnly'], array_column($destinations, 'name'));
        $t->same([1, 1, 0], array_column($destinations, 'page'));
        $t->same([4, 4, 3], array_column($destinations, 'page_object_id'));
        $t->same(['XYZ', 'FitBH', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[0]['coordinates']);
        $t->same(['top' => 600.0], $destinations[1]['coordinates']);
        $t->same(['left' => 90.0], $destinations[2]['coordinates']);

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['DuplicateReview', 'SummaryReview', 'LegacyOnly'], $documentDestinations['names']);
        $t->same([1, 1, 0], array_column($documentDestinations['destinations'], 'page'));
        $t->same(['XYZ', 'FitBH', 'FitV'], array_column($documentDestinations['destinations'], 'view_mode'));
        $t->same([[72.0, 640.0, null], [600.0], [90.0]], array_column($documentDestinations['destinations'], 'view_position'));

        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
        $t->same(['Duplicate Destination Outline'], array_column($toc, 'title'));
        $t->same([1], array_column($toc, 'page'));
        $t->same(['XYZ'], array_column($toc, 'view_mode'));
        $t->same([[72.0, 640.0, null]], array_column($toc, 'view_position'));
        $t->same([['left' => 72.0, 'top' => 640.0, 'zoom' => null]], array_column($toc, 'view_parameters'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, 'FitH 111'));
        $t->same(false, str_contains($encoded, '222'));
    },
    'keeps stale duplicate named-destination rows out of link promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationDuplicateKeyBoundaryCurrentBasePdf, $namedDestinationDuplicateKeyBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationDuplicateKeyBoundaryCurrentBasePdf();

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8], array_column($links[0]['links'], 'annotation_object'));
        $t->same('DuplicateReview', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('XYZ', $links[0]['links'][0]['view_mode']);
        $t->same([72.0, 640.0, null], $links[0]['links'][0]['view_position']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => null], $links[0]['links'][0]['view_parameters']);
        $t->same('https://example.com/duplicate-destination-safe-uri', $links[0]['links'][1]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationDuplicateKeyBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('DuplicateReview', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->same('https://example.com/duplicate-destination-safe-uri', $spans[1]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Current duplicate destination jump [Safe URI](https://example.com/duplicate-destination-safe-uri)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current duplicate destination jump Safe URI', $plainText);
        $t->contains('Duplicate destination current target body', $plainText);
        foreach (['Duplicate Destination Outline', 'DuplicateReview', 'SummaryReview', 'FitH 111', '222', 'duplicate-destination-safe-uri'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
    },
];
