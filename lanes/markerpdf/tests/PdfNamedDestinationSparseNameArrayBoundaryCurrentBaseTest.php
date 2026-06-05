<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationSparseNameArrayBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Current jump Recovered jump Stray jump Safe URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Recovered named destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyTarget [4 0 R /FitV 130] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 158 718] /Dest (Current Start) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [168 700 272 718] /Dest (Recovered Target) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [282 700 348 718] /Dest /StrayName >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [358 700 430 718] /A << /S /URI /URI (https://example.com/recovered-named-destination) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Current Start) (Recovered Target)] /Names [(Current Start) [4 0 R /FitH 700] /StrayName (Recovered Target) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 53 0 R /Count 3 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Current Start Outline) /Parent 50 0 R /Dest (Current Start) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Recovered Target Outline) /Parent 50 0 R /Dest (Recovered Target) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Stray Name Outline) /Parent 50 0 R /Dest /StrayName /Prev 52 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationSparseNameArrayBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 430.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 430.0, 718.0],
                'spans' => [
                    ['text' => 'Current jump', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Recovered jump', 'bbox' => [168.0, 700.0, 272.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stray jump', 'bbox' => [282.0, 700.0, 348.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [358.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'recovers string-key destination pairs after a stray name-tree token before WordPress metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationSparseNameArrayBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationSparseNameArrayBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Current Start', 'Recovered Target', 'LegacyTarget'], array_column($destinations, 'name'));
        $t->same([1, 1, 1], array_column($destinations, 'page'));
        $t->same([4, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 130.0], $destinations[2]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Current Start', 'Recovered Target', 'LegacyTarget'], $documentDestinations['names'] ?? null);
        $t->same(3, $documentDestinations['count'] ?? null);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source'] ?? null);

        $t->same(['Current Start Outline', 'Recovered Target Outline'], array_column($toc, 'title'));
        $t->same(['Current Start', 'Recovered Target'], array_column($toc, 'destination'));
        $t->same(['FitH', 'XYZ'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, 'StrayName'));
        $t->same(false, str_contains($encoded, 'Stray Name Outline'));
    },
    'keeps stray name-tree tokens out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationSparseNameArrayBoundaryCurrentBasePdf, $namedDestinationSparseNameArrayBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationSparseNameArrayBoundaryCurrentBasePdf();
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], ['local-destination'], [], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8, 10], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Current Start', $links[0]['links'][0]['destination']);
        $t->same('Recovered Target', $links[0]['links'][1]['destination']);
        $t->same('XYZ', $links[0]['links'][1]['view_mode']);
        $t->same('https://example.com/recovered-named-destination', $links[0]['links'][2]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationSparseNameArrayBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Current Start', $spans[0]['link_destination']);
        $t->same('Recovered Target', $spans[1]['link_destination']);
        $t->same('XYZ', $spans[1]['link_view_mode']);
        $t->true(!isset($spans[2]['link_destination']));
        $t->same('https://example.com/recovered-named-destination', $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Current jump Recovered jump Stray jump [Safe URI](https://example.com/recovered-named-destination)', $blocks[0]['text']);

        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current jump Recovered jump Stray jump Safe URI', $plainText);
        $t->contains('Recovered named destination target body', $plainText);
        foreach (['StrayName', 'Stray Name Outline'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        foreach (['Current Start Outline', 'Recovered Target Outline'] as $reviewOnly) {
            $t->same(false, str_contains($plainText, $reviewOnly));
        }
        $t->same(false, str_contains($plainText, 'recovered-named-destination'));
    },
];
