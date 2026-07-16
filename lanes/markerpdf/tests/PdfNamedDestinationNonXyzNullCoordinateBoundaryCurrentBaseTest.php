<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationNonXyzNullCoordinateBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid jump Null H jump Null V jump Null R jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Non XYZ null coordinate target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] /LegacyNull [4 0 R /FitBH 22 0 R] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 142 718] /Dest (Valid XYZ Null) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [152 700 236 718] /Dest (Invalid FitH Null) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [246 700 330 718] /Dest (Invalid FitV Null) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [340 700 424 718] /Dest (Invalid FitR Null) >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [434 700 498 718] /A << /S /URI /URI (https://example.com/non-xyz-null-coordinate) >> >>\nendobj\n"
        . "20 0 obj\n<< /Names [(Valid XYZ Null) [4 0 R /XYZ null null null] (Valid FitH Numeric) [4 0 R /FitH 700] (Invalid FitH Null) [4 0 R /FitH null] (Invalid FitV Null) [4 0 R /FitV 21 0 R] (Invalid FitR Null) [4 0 R /FitR 1 2 null 4] (Invalid Action FitBH Null) << /S /GoTo /D [4 0 R /FitBH null] >>] >>\nendobj\n"
        . "21 0 obj\nnull\nendobj\n"
        . "22 0 obj\nnull\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 55 0 R /Count 5 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Valid XYZ Null Outline) /Parent 50 0 R /Dest (Valid XYZ Null) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Valid FitH Numeric Outline) /Parent 50 0 R /Dest (Valid FitH Numeric) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Invalid FitH Null Outline) /Parent 50 0 R /Dest (Invalid FitH Null) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
        . "54 0 obj\n<< /Title (Invalid FitV Null Outline) /Parent 50 0 R /Dest (Invalid FitV Null) /Prev 53 0 R /Next 55 0 R >>\nendobj\n"
        . "55 0 obj\n<< /Title (Invalid FitR Null Outline) /Parent 50 0 R /Dest (Invalid FitR Null) /Prev 54 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationNonXyzNullCoordinateBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 498.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 498.0, 718.0],
                'spans' => [
                    ['text' => 'Valid jump', 'bbox' => [72.0, 700.0, 142.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Null H jump', 'bbox' => [152.0, 700.0, 236.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Null V jump', 'bbox' => [246.0, 700.0, 330.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Null R jump', 'bbox' => [340.0, 700.0, 424.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [434.0, 700.0, 498.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects null required coordinates outside XYZ before WordPress named-destination metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationNonXyzNullCoordinateBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationNonXyzNullCoordinateBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Valid XYZ Null', 'Valid FitH Numeric', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([1, 1, 1], array_column($destinations, 'page'));
        $t->same([4, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['XYZ', 'FitH', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['left' => null, 'top' => null, 'zoom' => null], $destinations[0]['coordinates']);
        $t->same(['top' => 700.0], $destinations[1]['coordinates']);
        $t->same(['left' => 120.0], $destinations[2]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Valid XYZ Null', 'Valid FitH Numeric', 'LegacyOk'], $documentDestinations['names'] ?? null);
        $t->same(3, $documentDestinations['count'] ?? null);
        $t->same(['XYZ', 'FitH', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Valid XYZ Null Outline', 'Valid FitH Numeric Outline'], array_column($toc, 'title'));
        $t->same(['Valid XYZ Null', 'Valid FitH Numeric'], array_column($toc, 'destination'));
        $t->same(['XYZ', 'FitH'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'Invalid FitH Null',
            'Invalid FitV Null',
            'Invalid FitR Null',
            'Invalid Action FitBH Null',
            'LegacyNull',
            'Invalid FitH Null Outline',
            'Invalid FitV Null Outline',
            'Invalid FitR Null Outline',
        ] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps non-XYZ null coordinate destinations out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationNonXyzNullCoordinateBoundaryCurrentBasePdf, $namedDestinationNonXyzNullCoordinateBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationNonXyzNullCoordinateBoundaryCurrentBasePdf();
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10, 11], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], [], [], [], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 11], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Valid XYZ Null', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('XYZ', $links[0]['links'][0]['view_mode']);
        $t->same('https://example.com/non-xyz-null-coordinate', $links[0]['links'][1]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationNonXyzNullCoordinateBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Valid XYZ Null', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->true(!isset($spans[2]['link_destination']));
        $t->true(!isset($spans[3]['link_destination']));
        $t->same('https://example.com/non-xyz-null-coordinate', $spans[4]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Valid jump Null H jump Null V jump Null R jump [Safe URI](https://example.com/non-xyz-null-coordinate)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Valid jump Null H jump Null V jump Null R jump Safe URI', $plainText);
        $t->contains('Non XYZ null coordinate target body', $plainText);
        foreach ([
            'Invalid FitH Null',
            'Invalid FitV Null',
            'Invalid FitR Null',
            'Invalid Action FitBH Null',
            'Invalid FitH Null Outline',
            'Invalid FitV Null Outline',
            'Invalid FitR Null Outline',
        ] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'non-xyz-null-coordinate'));
    },
];
