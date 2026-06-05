<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationInternalLeafBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Child destination jump Inline parent jump External URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Internal leaf destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOnly [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 194 718] /Dest (Child Target) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [204 700 326 718] /Dest (Inline Parent Target) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [336 700 424 718] /A << /S /URI /URI (https://example.com/leaf-boundary) >> >>\nendobj\n"
        . "10 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Limits [(Child Target) (Review Summary)] /Kids [21 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [(Child Target) (Review Summary)] /Names [(Inline Parent Target) [4 0 R /FitH 111]] /Kids [22 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [(Child Target) (Review Summary)] /Names [(Child Target) [4 0 R /FitH 700] (Review Summary) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Child Target Outline) /Parent 50 0 R /Dest (Child Target) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Inline Parent Outline) /Parent 50 0 R /Dest (Inline Parent Target) /Prev 51 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationInternalLeafBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 424.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 424.0, 718.0],
                'spans' => [
                    ['text' => 'Child destination jump', 'bbox' => [72.0, 700.0, 194.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Inline parent jump', 'bbox' => [204.0, 700.0, 326.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' External URI', 'bbox' => [336.0, 700.0, 424.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'treats destination name-tree nodes with Kids as internal nodes before WordPress review metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationInternalLeafBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationInternalLeafBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);

        $t->same(['Child Target', 'Review Summary', 'LegacyOnly'], array_column($destinations, 'name'));
        $t->same([1, 1, 1], array_column($destinations, 'page'));
        $t->same([4, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 120.0], $destinations[2]['coordinates']);

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Child Target', 'Review Summary', 'LegacyOnly'], $documentDestinations['names']);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source']);
        $t->same(3, $documentDestinations['count']);

        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
        $t->same(['Child Target Outline'], array_column($toc, 'title'));
        $t->same(['Child Target'], array_column($toc, 'destination'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
    },
    'keeps internal-node local destination names out of link promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationInternalLeafBoundaryCurrentBasePdf, $namedDestinationInternalLeafBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationInternalLeafBoundaryCurrentBasePdf();

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
        $t->same('Child Target', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('FitH', $links[0]['links'][0]['view_mode']);
        $t->same('https://example.com/leaf-boundary', $links[0]['links'][1]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationInternalLeafBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Child Target', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->true(!isset($spans[1]['link_destination_page']));
        $t->same('https://example.com/leaf-boundary', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Child destination jump Inline parent jump [External URI](https://example.com/leaf-boundary)', $blocks[0]['text']);

        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Child destination jump Inline parent jump External URI', $plainText);
        $t->contains('Internal leaf destination target body', $plainText);
        foreach (['Inline Parent Target', 'Inline Parent Outline', 'FitH 111'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'leaf-boundary'));
    },
];
