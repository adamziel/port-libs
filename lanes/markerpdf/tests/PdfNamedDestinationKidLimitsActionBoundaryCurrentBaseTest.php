<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationKidLimitsActionBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Duplicate limits action jump Safe URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Limits ordered destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 248 718] /Dest (DuplicateReview) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [258 700 326 718] /A << /S /URI /URI (https://example.com/kid-limits-action-safe-uri) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(A Broad) (DuplicateReview)] /Kids [21 0 R 22 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [(DuplicateReview) (DuplicateReview)] /Names [(DuplicateReview) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [(A Broad) (DuplicateReview)] /Names [(A Broad) [3 0 R /Fit] (DuplicateReview) [3 0 R /FitH 111]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationKidLimitsActionBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 326.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 326.0, 718.0],
                'spans' => [
                    ['text' => 'Duplicate limits action jump', 'bbox' => [72.0, 700.0, 248.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [258.0, 700.0, 326.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'orders destination name-tree kids by Limits before annotation action promotion' => static function (
        TestRunner $t
    ) use ($namedDestinationKidLimitsActionBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationKidLimitsActionBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $links = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);

        $t->same(['A Broad', 'DuplicateReview'], array_column($destinations, 'name'));
        $t->same([0, 1], array_column($destinations, 'page'));
        $t->same(['Fit', 'XYZ'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree'], array_column($destinations, 'source'));
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['A Broad', 'DuplicateReview'], $documentDestinations['names'] ?? null);
        $t->same([0, 1], array_column($documentDestinations['destinations'] ?? [], 'page'));
        $t->same(['Fit', 'XYZ'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(1, count($links));
        $t->same([7, 8], array_column($links[0]['links'], 'annotation_object'));
        $t->same('DuplicateReview', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('XYZ', $links[0]['links'][0]['view_mode']);
        $t->same([72.0, 640.0, null], $links[0]['links'][0]['view_position']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => null], $links[0]['links'][0]['view_parameters']);
        $t->same('https://example.com/kid-limits-action-safe-uri', $links[0]['links'][1]['uri']);
    },
    'keeps stale physically later destination children out of WordPress spans and text' => static function (
        TestRunner $t
    ) use ($namedDestinationKidLimitsActionBoundaryCurrentBasePdf, $namedDestinationKidLimitsActionBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationKidLimitsActionBoundaryCurrentBasePdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $pages = $linkExtractor->applyLinksToPages($namedDestinationKidLimitsActionBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];

        $t->same('DuplicateReview', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->same('XYZ', $spans[0]['link_view_mode']);
        $t->same('https://example.com/kid-limits-action-safe-uri', $spans[1]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Duplicate limits action jump [Safe URI](https://example.com/kid-limits-action-safe-uri)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Duplicate limits action jump Safe URI', $plainText);
        $t->contains('Limits ordered destination target body', $plainText);
        foreach (['DuplicateReview', 'A Broad', 'FitH 111', 'kid-limits-action-safe-uri'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($encoded, 'FitH 111'));
    },
];
