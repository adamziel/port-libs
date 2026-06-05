<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationKidsReferenceBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Current child jump Direct child jump Safe URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Kids reference destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 174 718] /Dest (Current Child) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [184 700 286 718] /Dest (Direct Child Decoy) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [296 700 376 718] /A << /S /URI /URI (https://example.com/kids-reference-boundary) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Current Child) (Review Summary)] /Kids [21 0 R << /Limits [(Direct Child Decoy) (Direct Child Decoy)] /Names [(Direct Child Decoy) [4 0 R /FitH 111]] >> 22 1 R /ScalarKid] >>\nendobj\n"
        . "21 0 obj\n<< /Names [(Current Child) [4 0 R /FitH 700] (Review Summary) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "22 0 obj\n<< /Names [(Generation Child Decoy) [4 0 R /FitBH 222]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Current Child Outline) /Parent 50 0 R /Dest (Current Child) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Direct Child Outline) /Parent 50 0 R /Dest (Direct Child Decoy) /Prev 51 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationKidsReferenceBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 376.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 376.0, 718.0],
                'spans' => [
                    ['text' => 'Current child jump', 'bbox' => [72.0, 700.0, 174.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Direct child jump', 'bbox' => [184.0, 700.0, 286.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [296.0, 700.0, 376.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'requires destination name-tree Kids entries to be valid indirect node references before WordPress review' => static function (
        TestRunner $t
    ) use ($namedDestinationKidsReferenceBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationKidsReferenceBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);

        $t->same(['Current Child', 'Review Summary', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([1, 1, 1], array_column($destinations, 'page'));
        $t->same([4, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 120.0], $destinations[2]['coordinates']);

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Current Child', 'Review Summary', 'LegacyOk'], $documentDestinations['names']);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source']);
        $t->same(3, $documentDestinations['count']);

        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
        $t->same(['Current Child Outline'], array_column($toc, 'title'));
        $t->same(['Current Child'], array_column($toc, 'destination'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Direct Child Decoy', 'Generation Child Decoy', 'Direct Child Outline', '111', '222'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps malformed name-tree child dictionaries out of link promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationKidsReferenceBoundaryCurrentBasePdf, $namedDestinationKidsReferenceBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationKidsReferenceBoundaryCurrentBasePdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], [], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 9], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Current Child', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('FitH', $links[0]['links'][0]['view_mode']);
        $t->same('https://example.com/kids-reference-boundary', $links[0]['links'][1]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationKidsReferenceBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Current Child', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->true(!isset($spans[1]['link_destination_page']));
        $t->same('https://example.com/kids-reference-boundary', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Current child jump Direct child jump [Safe URI](https://example.com/kids-reference-boundary)', $blocks[0]['text']);

        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current child jump Direct child jump Safe URI', $plainText);
        $t->contains('Kids reference destination target body', $plainText);
        foreach (['Direct Child Decoy', 'Generation Child Decoy', 'Direct Child Outline', 'FitH 111'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'kids-reference-boundary'));
    },
];
