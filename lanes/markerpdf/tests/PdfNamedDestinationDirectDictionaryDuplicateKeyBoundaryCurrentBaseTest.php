<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationDirectDictionaryDuplicateKeyBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Clean direct jump Inline duplicate jump Direct S jump Legacy duplicate jump Legacy jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Direct dictionary duplicate target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] /LegacyInline << /D [4 0 R /FitR 1 2 3 4] /#44 [3 0 R /FitH 555] >> >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R 12 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 170 718] /Dest (Clean Direct Target) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [180 700 296 718] /Dest (Inline Duplicate D) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [306 700 398 718] /Dest (Inline Duplicate S) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [400 700 488 718] /Dest /LegacyInline >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [498 700 548 718] /Dest /LegacyOk >>\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [558 700 606 718] /A << /S /URI /URI (https://example.com/direct-dictionary-duplicate-boundary) >> >>\nendobj\n"
        . "20 0 obj\n<< /Names [(Clean Direct Target) << /D [4 0 R /FitH 700] >> (Inline Duplicate D) << /D [4 0 R /FitH 111] /#44 [3 0 R /XYZ 72 640 0] >> (Inline Duplicate S) << /S /URI /#53 /GoTo /URI (https://example.com/hidden-inline-action) /D [4 0 R /FitV 222] >>] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 55 0 R /Count 5 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Clean Direct Outline) /Parent 50 0 R /Dest (Clean Direct Target) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Inline Duplicate D Outline) /Parent 50 0 R /Dest (Inline Duplicate D) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Inline Duplicate S Outline) /Parent 50 0 R /Dest (Inline Duplicate S) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
        . "54 0 obj\n<< /Title (Legacy Inline Outline) /Parent 50 0 R /Dest /LegacyInline /Prev 53 0 R /Next 55 0 R >>\nendobj\n"
        . "55 0 obj\n<< /Title (Legacy Direct Outline) /Parent 50 0 R /Dest /LegacyOk /Prev 54 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationDirectDictionaryDuplicateKeyBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 606.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 606.0, 718.0],
                'spans' => [
                    ['text' => 'Clean direct jump', 'bbox' => [72.0, 700.0, 170.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Inline duplicate jump', 'bbox' => [180.0, 700.0, 296.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Direct S jump', 'bbox' => [306.0, 700.0, 398.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Legacy duplicate jump', 'bbox' => [400.0, 700.0, 488.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Legacy jump', 'bbox' => [498.0, 700.0, 548.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [558.0, 700.0, 606.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects direct duplicate destination dictionary keys before WordPress destination metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationDirectDictionaryDuplicateKeyBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationDirectDictionaryDuplicateKeyBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Clean Direct Target', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([1, 1], array_column($destinations, 'page'));
        $t->same([4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 120.0], $destinations[1]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Clean Direct Target', 'LegacyOk'], $documentDestinations['names'] ?? null);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(2, $documentDestinations['count'] ?? null);
        $t->same(['FitH', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Clean Direct Outline', 'Legacy Direct Outline'], array_column($toc, 'title'));
        $t->same(['Clean Direct Target', 'LegacyOk'], array_column($toc, 'destination'));
        $t->same(['FitH', 'FitV'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'Inline Duplicate D',
            'Inline Duplicate S',
            'LegacyInline',
            'Inline Duplicate D Outline',
            'Inline Duplicate S Outline',
            'Legacy Inline Outline',
            'hidden-inline-action',
            'XYZ',
            'FitR',
            '640',
            '555',
        ] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps direct duplicate destination dictionaries out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationDirectDictionaryDuplicateKeyBoundaryCurrentBasePdf, $namedDestinationDirectDictionaryDuplicateKeyBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationDirectDictionaryDuplicateKeyBoundaryCurrentBasePdf();
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
        $t->same('Clean Direct Target', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('FitH', $links[0]['links'][0]['view_mode']);
        $t->same('LegacyOk', $links[0]['links'][1]['destination']);
        $t->same(1, $links[0]['links'][1]['destination_page']);
        $t->same('FitV', $links[0]['links'][1]['view_mode']);
        $t->same('https://example.com/direct-dictionary-duplicate-boundary', $links[0]['links'][2]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationDirectDictionaryDuplicateKeyBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Clean Direct Target', $spans[0]['link_destination']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->true(!isset($spans[2]['link_destination']));
        $t->true(!isset($spans[3]['link_destination']));
        $t->same('LegacyOk', $spans[4]['link_destination']);
        $t->same('https://example.com/direct-dictionary-duplicate-boundary', $spans[5]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            'Clean direct jump Inline duplicate jump Direct S jump Legacy duplicate jump Legacy jump [Safe URI](https://example.com/direct-dictionary-duplicate-boundary)',
            $blocks[0]['text']
        );

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Clean direct jump Inline duplicate jump Direct S jump Legacy duplicate jump Legacy jump Safe URI', $plainText);
        $t->contains('Direct dictionary duplicate target body', $plainText);
        foreach (['Inline Duplicate D', 'Inline Duplicate S', 'LegacyInline', 'hidden-inline-action', 'XYZ', 'FitR'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'direct-dictionary-duplicate-boundary'));
    },
];
