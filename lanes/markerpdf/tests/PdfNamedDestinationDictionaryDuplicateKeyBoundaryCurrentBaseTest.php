<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationDictionaryDuplicateKeyBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Clean destination jump Duplicate D jump Duplicate S jump Legacy duplicate jump Legacy jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Dictionary duplicate destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] /LegacyDuplicateD 24 0 R >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R 12 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Dest (Clean Target) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 250 718] /Dest (Duplicate D Target) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 700 340 718] /Dest (Duplicate S Target) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [350 700 455 718] /Dest /LegacyDuplicateD >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [465 700 525 718] /Dest /LegacyOk >>\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [535 700 600 718] /A << /S /URI /URI (https://example.com/named-destination-duplicate-key-boundary) >> >>\nendobj\n"
        . "20 0 obj\n<< /Names [(Clean Target) 21 0 R (Duplicate D Target) 22 0 R (Duplicate S Target) 23 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /D [4 0 R /FitH 700] >>\nendobj\n"
        . "22 0 obj\n<< /D [4 0 R /FitH 111] /#44 [4 0 R /XYZ 72 640 0] >>\nendobj\n"
        . "23 0 obj\n<< /S /URI /#53 /GoTo /URI (https://example.com/hidden-duplicate-s-destination) /D [4 0 R /FitV 222] >>\nendobj\n"
        . "24 0 obj\n<< /D [4 0 R /FitR 1 2 3 4] /#44 [3 0 R /FitH 555] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 54 0 R /Count 4 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Clean Destination Outline) /Parent 50 0 R /Dest (Clean Target) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Duplicate D Outline) /Parent 50 0 R /Dest (Duplicate D Target) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Duplicate S Outline) /Parent 50 0 R /Dest (Duplicate S Target) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
        . "54 0 obj\n<< /Title (Legacy Destination Outline) /Parent 50 0 R /Dest /LegacyOk /Prev 53 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationDictionaryDuplicateKeyBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 600.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 600.0, 718.0],
                'spans' => [
                    ['text' => 'Clean destination jump', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Duplicate D jump', 'bbox' => [170.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Duplicate S jump', 'bbox' => [260.0, 700.0, 340.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Legacy duplicate jump', 'bbox' => [350.0, 700.0, 455.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Legacy jump', 'bbox' => [465.0, 700.0, 525.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [535.0, 700.0, 600.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects duplicate destination dictionary keys before WordPress destination metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationDictionaryDuplicateKeyBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationDictionaryDuplicateKeyBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Clean Target', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([1, 1], array_column($destinations, 'page'));
        $t->same([4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 120.0], $destinations[1]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Clean Target', 'LegacyOk'], $documentDestinations['names'] ?? null);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(2, $documentDestinations['count'] ?? null);
        $t->same(['FitH', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Clean Destination Outline', 'Legacy Destination Outline'], array_column($toc, 'title'));
        $t->same(['Clean Target', 'LegacyOk'], array_column($toc, 'destination'));
        $t->same(['FitH', 'FitV'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'Duplicate D Target',
            'Duplicate S Target',
            'LegacyDuplicateD',
            'Duplicate D Outline',
            'Duplicate S Outline',
            'hidden-duplicate-s-destination',
            'XYZ',
            'FitR',
            '640',
            '555',
        ] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps duplicate destination dictionaries out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationDictionaryDuplicateKeyBoundaryCurrentBasePdf, $namedDestinationDictionaryDuplicateKeyBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationDictionaryDuplicateKeyBoundaryCurrentBasePdf();
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10, 11, 12], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], [], [], [], ['local-destination'], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 11, 12], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Clean Target', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('FitH', $links[0]['links'][0]['view_mode']);
        $t->same('LegacyOk', $links[0]['links'][1]['destination']);
        $t->same(1, $links[0]['links'][1]['destination_page']);
        $t->same('FitV', $links[0]['links'][1]['view_mode']);
        $t->same('https://example.com/named-destination-duplicate-key-boundary', $links[0]['links'][2]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationDictionaryDuplicateKeyBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Clean Target', $spans[0]['link_destination']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->true(!isset($spans[2]['link_destination']));
        $t->true(!isset($spans[3]['link_destination']));
        $t->same('LegacyOk', $spans[4]['link_destination']);
        $t->same('https://example.com/named-destination-duplicate-key-boundary', $spans[5]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            'Clean destination jump Duplicate D jump Duplicate S jump Legacy duplicate jump Legacy jump [Safe URI](https://example.com/named-destination-duplicate-key-boundary)',
            $blocks[0]['text']
        );

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Clean destination jump Duplicate D jump Duplicate S jump Legacy duplicate jump Legacy jump Safe URI', $plainText);
        $t->contains('Dictionary duplicate destination target body', $plainText);
        foreach (['Duplicate D Target', 'Duplicate S Target', 'LegacyDuplicateD', 'hidden-duplicate-s-destination', 'XYZ', 'FitR'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'named-destination-duplicate-key-boundary'));
    },
];
