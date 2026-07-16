<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationPageOnlyTailBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid jump Page tail jump Numeric tail jump Alias jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Page-only tail destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 130] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /MediaBox [0 0 800 792] >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Dest (Valid Target) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 284 718] /Dest (Page Tail Target) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [294 700 426 718] /Dest (Numeric Tail Target) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [436 700 520 718] /Dest (Alias Target) >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [530 700 610 718] /A << /S /URI /URI (https://example.com/named-destination-page-only-tail) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Alias Target) (Valid Target)] /Names [(Valid Target) [4 0 R /XYZ 72 640 0] (Page Tail Target) 4 0 R /FitH 610 (Numeric Tail Target) 1 /FitV 120 (Alias Target) /LegacyOk] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 55 0 R /Count 5 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Valid Outline) /Parent 50 0 R /Dest (Valid Target) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Page Tail Outline) /Parent 50 0 R /Dest (Page Tail Target) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Numeric Tail Outline) /Parent 50 0 R /Dest (Numeric Tail Target) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
        . "54 0 obj\n<< /Title (Alias Outline) /Parent 50 0 R /Dest (Alias Target) /Prev 53 0 R /Next 55 0 R >>\nendobj\n"
        . "55 0 obj\n<< /Title (Legacy Outline) /Parent 50 0 R /Dest /LegacyOk /Prev 54 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationPageOnlyTailBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 610.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 610.0, 718.0],
                'spans' => [
                    ['text' => 'Valid jump', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Page tail jump', 'bbox' => [170.0, 700.0, 284.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Numeric tail jump', 'bbox' => [294.0, 700.0, 426.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Alias jump', 'bbox' => [436.0, 700.0, 520.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [530.0, 700.0, 610.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects page-only name-tree values with unbracketed view operands before WordPress metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationPageOnlyTailBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationPageOnlyTailBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Valid Target', 'Alias Target', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([1, 1, 1], array_column($destinations, 'page'));
        $t->same([4, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['XYZ', 'FitV', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Valid Target', 'Alias Target', 'LegacyOk'], $documentDestinations['names'] ?? null);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(3, $documentDestinations['count'] ?? null);
        $t->same(['XYZ', 'FitV', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Valid Outline', 'Alias Outline', 'Legacy Outline'], array_column($toc, 'title'));
        $t->same(['Valid Target', 'Alias Target', 'LegacyOk'], array_column($toc, 'destination'));
        $t->same(['XYZ', 'FitV', 'FitV'], array_column($toc, 'view_mode'));
        $t->same([1, 1, 1], array_column($toc, 'page'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'Page Tail Target',
            'Numeric Tail Target',
            'Page Tail Outline',
            'Numeric Tail Outline',
            '610',
        ] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps page-only name-tree view tails out of links and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationPageOnlyTailBoundaryCurrentBasePdf, $namedDestinationPageOnlyTailBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationPageOnlyTailBoundaryCurrentBasePdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same([7, 10, 11], array_column($links[0]['links'], 'annotation_object'));
        $t->same(['Valid Target', 'LegacyOk'], array_column(array_slice($links[0]['links'], 0, 2), 'destination'));
        $t->same([1, 1], array_column(array_slice($links[0]['links'], 0, 2), 'destination_page'));
        $t->same(['XYZ', 'FitV'], array_column(array_slice($links[0]['links'], 0, 2), 'view_mode'));
        $t->same('https://example.com/named-destination-page-only-tail', $links[0]['links'][2]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationPageOnlyTailBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Valid Target', $spans[0]['link_destination']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->true(!isset($spans[2]['link_destination']));
        $t->same('LegacyOk', $spans[3]['link_destination']);
        $t->same('https://example.com/named-destination-page-only-tail', $spans[4]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            'Valid jump Page tail jump Numeric tail jump Alias jump [Safe URI](https://example.com/named-destination-page-only-tail)',
            $blocks[0]['text']
        );

        $encoded = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Valid jump Page tail jump Numeric tail jump Alias jump Safe URI', $plainText);
        $t->contains('Page-only tail destination target body', $plainText);
        foreach (['Page Tail Target', 'Numeric Tail Target', 'Page Tail Outline', 'Numeric Tail Outline'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'named-destination-page-only-tail'));
    },
];
