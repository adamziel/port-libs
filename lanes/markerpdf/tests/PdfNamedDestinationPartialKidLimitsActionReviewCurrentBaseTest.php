<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationPartialKidLimitsActionReviewCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Partial limits stale page link) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Partial limits current destination page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Annots [7 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Partial bounded kid outline) /Parent 5 0 R /Dest (DuplicateReview) >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 250 718] /Dest (DuplicateReview) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(A Broad) (DuplicateReview)] /Kids [14 0 R 21 0 R 22 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Names [(Zulu Ignored) [3 0 R /Fit] (zz-stale-ignored) [3 0 R /Fit]] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [(DuplicateReview) (DuplicateReview)] /Names [(DuplicateReview) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [(A Broad) (DuplicateReview)] /Names [(A Broad) [3 0 R /Fit] (DuplicateReview) [3 0 R /FitH 111]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationPartialKidLimitsActionReviewCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 250.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 250.0, 718.0],
                'spans' => [[
                    'text' => 'Partial limits stale page link',
                    'bbox' => [72.0, 700.0, 250.0, 718.0],
                    'font' => 'Helvetica',
                ]],
            ]],
        ]],
    ]];
};

return [
    'keeps partially bounded destination kids ordered for link and outline action review' => static function (
        TestRunner $t
    ) use ($namedDestinationPartialKidLimitsActionReviewCurrentBasePdf, $namedDestinationPartialKidLimitsActionReviewCurrentBasePages): void {
        $pdf = $namedDestinationPartialKidLimitsActionReviewCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $outline = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);

        $t->same(['A Broad', 'DuplicateReview'], array_column($destinations, 'name'));
        $t->same([0, 1], array_column($destinations, 'page'));
        $t->same(['Fit', 'XYZ'], array_column($destinations, 'fit'));
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);

        $t->same(['Partial bounded kid outline'], array_column($outline, 'title'));
        $t->same([1], array_column($outline, 'page'));
        $t->same(['DuplicateReview'], array_column($outline, 'destination'));
        $t->same(['XYZ'], array_column($outline, 'view_mode'));
        $t->same([[72.0, 640.0, null]], array_column($outline, 'view_position'));
        $t->same([['left' => 72.0, 'top' => 640.0, 'zoom' => null]], array_column($outline, 'view_parameters'));

        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('DuplicateReview', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('XYZ', $links[0]['links'][0]['view_mode']);
        $t->same([72.0, 640.0, null], $links[0]['links'][0]['view_position']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => null], $links[0]['links'][0]['view_parameters']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationPartialKidLimitsActionReviewCurrentBasePages(), $pdf);
        $span = $pages[0]['blocks'][0]['lines'][0]['spans'][0];
        $t->same('DuplicateReview', $span['link_destination']);
        $t->same(1, $span['link_destination_page']);
        $t->same('XYZ', $span['link_view_mode']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Partial limits stale page link', $blocks[0]['text']);
    },
    'keeps malformed partial-kid destination metadata out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationPartialKidLimitsActionReviewCurrentBasePdf): void {
        $pdf = $namedDestinationPartialKidLimitsActionReviewCurrentBasePdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $reviewJson = json_encode([
            (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf),
            (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf),
        ], JSON_UNESCAPED_SLASHES) ?: '';

        $t->contains('Partial limits stale page link', $plainText);
        $t->contains('Partial limits current destination page', $plainText);
        foreach ([
            'Partial bounded kid outline',
            'DuplicateReview',
            'Zulu Ignored',
            'zz-stale-ignored',
        ] as $hidden) {
            $t->true(!str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($reviewJson, 'FitH'));
        $t->same(false, str_contains($reviewJson, '111'));
    },
];
