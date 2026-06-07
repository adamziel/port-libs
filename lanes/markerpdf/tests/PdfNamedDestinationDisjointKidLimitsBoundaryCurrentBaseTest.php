<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationDisjointKidLimitsBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Valid destination jump Disjoint decoy jump Direct URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Disjoint limits valid target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyTail [4 0 R /FitV 144] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 204 718] /Dest (Review Link) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [214 700 350 718] /Dest (Beta Decoy) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [360 700 430 718] /A << /S /URI /URI (https://example.com/disjoint-current) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Alpha Start) (Review Link)] /Kids [23 0 R 22 0 R 21 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [(Alpha Start) (Alpha Start)] /Names [(Alpha Start) [3 0 R /FitH 710]] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [(Review Link) (Review Link)] /Names [(Review Link) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "23 0 obj\n<< /Limits [(Zulu Decoy) (Zzz Decoy)] /Kids [24 0 R] >>\nendobj\n"
        . "24 0 obj\n<< /Limits [(Beta Decoy) (Review Link)] /Names [(Beta Decoy) [3 0 R /FitH 111] (Review Link) [3 0 R /FitH 222]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationDisjointKidLimitsBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 430.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 430.0, 718.0],
                'spans' => [
                    ['text' => 'Valid destination jump', 'bbox' => [72.0, 700.0, 204.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Disjoint decoy jump', 'bbox' => [214.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Direct URI', 'bbox' => [360.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'prunes disjoint destination kid Limits before WordPress destination metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationDisjointKidLimitsBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationDisjointKidLimitsBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $documentDestinations = $metadata['document_destinations'] ?? [];

        $t->same(['Alpha Start', 'Review Link', 'LegacyTail'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 710.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 144.0], $destinations[2]['coordinates']);

        $t->same(array_column($destinations, 'name'), $documentDestinations['names'] ?? null);
        $t->same(3, $documentDestinations['count'] ?? null);
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Beta Decoy', 'FitH 111', 'FitH 222'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps disjoint named destinations unresolved for WordPress link promotion and visible text' => static function (
        TestRunner $t
    ) use ($namedDestinationDisjointKidLimitsBoundaryCurrentBasePdf, $namedDestinationDisjointKidLimitsBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationDisjointKidLimitsBoundaryCurrentBasePdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same([7, 9], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Review Link', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('XYZ', $links[0]['links'][0]['view_mode']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => null], $links[0]['links'][0]['view_parameters']);
        $t->same('https://example.com/disjoint-current', $links[0]['links'][1]['uri']);

        $linkedPages = $extractor->applyLinksToPages($namedDestinationDisjointKidLimitsBoundaryCurrentBasePages(), $pdf);
        $spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same(1, $spans[0]['link_destination_page']);
        $t->same('Review Link', $spans[0]['link_destination']);
        $t->true(!isset($spans[1]['link_destination_page']));
        $t->true(!isset($spans[1]['link_uri']));
        $t->same('https://example.com/disjoint-current', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('Valid destination jump Disjoint decoy jump [Direct URI](https://example.com/disjoint-current)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Valid destination jump Disjoint decoy jump Direct URI', $plainText);
        $t->contains('Disjoint limits valid target body', $plainText);
        foreach (['Alpha Start', 'Review Link', 'Beta Decoy', 'disjoint-current'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
    },
];
