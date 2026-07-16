<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationLeafOrderBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Alpha duplicate jump Safe URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Alpha replacement and middle target page) Tj ET';
    $thirdPageContent = 'BT /F1 12 Tf 72 720 Td (Zulu destination target page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyTail [5 0 R /FitV 144] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 32 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 206 718] /Dest (Alpha Start) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [216 700 292 718] /A << /S /URI /URI (https://example.com/leaf-order-safe-uri) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Alpha Start) (Zulu Appendix)] /Names [(Zulu Appendix) [5 0 R /Fit] (Alpha Start) [3 0 R /FitH 710] (Middle Review) [4 0 R /XYZ 72 650 0] (Alpha Start) [4 0 R /FitBH 620]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($thirdPageContent) . " >>\nstream\n{$thirdPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 53 0 R /Count 3 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Alpha Outline) /Parent 50 0 R /Dest (Alpha Start) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Middle Outline) /Parent 50 0 R /Dest (Middle Review) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Zulu Outline) /Parent 50 0 R /Dest (Zulu Appendix) /Prev 52 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationLeafOrderBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 292.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 292.0, 718.0],
                'spans' => [
                    ['text' => 'Alpha duplicate jump', 'bbox' => [72.0, 700.0, 206.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [216.0, 700.0, 292.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'orders duplicate-key leaf destination name pairs by source bytes before WordPress review metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationLeafOrderBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationLeafOrderBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Alpha Start', 'Middle Review', 'Zulu Appendix', 'LegacyTail'], array_column($destinations, 'name'));
        $t->same([1, 1, 2, 2], array_column($destinations, 'page'));
        $t->same(['FitBH', 'XYZ', 'Fit', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 620.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 650.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same([], $destinations[2]['coordinates']);
        $t->same(['left' => 144.0], $destinations[3]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(array_column($destinations, 'name'), $documentDestinations['names'] ?? null);
        $t->same([1, 1, 2, 2], array_column($documentDestinations['destinations'] ?? [], 'page'));
        $t->same(['FitBH', 'XYZ', 'Fit', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));
        $t->same(['top' => 620.0], $documentDestinations['destinations'][0]['view_parameters'] ?? null);

        $t->same(['Alpha Outline', 'Middle Outline', 'Zulu Outline'], array_column($toc, 'title'));
        $t->same(['Alpha Start', 'Middle Review', 'Zulu Appendix'], array_column($toc, 'destination'));
        $t->same([1, 1, 2], array_column($toc, 'page'));
        $t->same(['FitBH', 'XYZ', 'Fit'], array_column($toc, 'view_mode'));
    },
    'preserves duplicate destination targets without leaking leaf names into WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationLeafOrderBoundaryCurrentBasePdf, $namedDestinationLeafOrderBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationLeafOrderBoundaryCurrentBasePdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same([7, 8], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Alpha Start', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('FitBH', $links[0]['links'][0]['view_mode']);
        $t->same(['top' => 620.0], $links[0]['links'][0]['view_parameters']);
        $t->same('https://example.com/leaf-order-safe-uri', $links[0]['links'][1]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationLeafOrderBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Alpha Start', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->same('FitBH', $spans[0]['link_view_mode']);
        $t->same('https://example.com/leaf-order-safe-uri', $spans[1]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Alpha duplicate jump [Safe URI](https://example.com/leaf-order-safe-uri)', $blocks[0]['text']);

        $encoded = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Alpha duplicate jump Safe URI', $plainText);
        $t->contains('Alpha replacement and middle target page', $plainText);
        $t->contains('Zulu destination target page', $plainText);
        foreach (['Alpha Start', 'Middle Review', 'Zulu Appendix', 'LegacyTail', 'Alpha Outline', 'leaf-order-safe-uri'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($encoded, 'FitH 710'));
    },
];
