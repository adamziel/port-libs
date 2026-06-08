<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationIndirectOperandTailBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid coordinate jump Tailed coordinate jump Tailed view jump Legacy jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect operand tail destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 188 718] /Dest (Valid Target) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [198 700 344 718] /Dest (Tailed Coordinate Target) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [354 700 462 718] /Dest (Tailed View Target) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [472 700 550 718] /Dest /LegacyOk >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [560 700 632 718] /A << /S /URI /URI (https://example.com/named-destination-indirect-operand-tail) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Tailed Coordinate Target) (Valid Target)] /Names [(Valid Target) [4 0 R /XYZ 21 0 R 22 0 R 23 0 R] (Tailed Coordinate Target) [4 0 R /FitH 24 0 R] (Tailed View Target) [4 0 R 25 0 R 500]] >>\nendobj\n"
        . "21 0 obj\n72\nendobj\n"
        . "22 0 obj\n640\nendobj\n"
        . "23 0 obj\n0\nendobj\n"
        . "24 0 obj\n610 /PrivateCoordinateTail\nendobj\n"
        . "25 0 obj\n/FitH /PrivateViewTail\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 54 0 R /Count 4 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Valid Target Outline) /Parent 50 0 R /Dest (Valid Target) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Tailed Coordinate Outline) /Parent 50 0 R /Dest (Tailed Coordinate Target) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Tailed View Outline) /Parent 50 0 R /Dest (Tailed View Target) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
        . "54 0 obj\n<< /Title (Legacy Outline) /Parent 50 0 R /Dest /LegacyOk /Prev 53 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationIndirectOperandTailBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 632.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 632.0, 718.0],
                'spans' => [
                    ['text' => 'Valid coordinate jump', 'bbox' => [72.0, 700.0, 188.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed coordinate jump', 'bbox' => [198.0, 700.0, 344.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed view jump', 'bbox' => [354.0, 700.0, 462.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Legacy jump', 'bbox' => [472.0, 700.0, 550.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [560.0, 700.0, 632.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects tailed indirect named-destination view and coordinate operands before WordPress metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationIndirectOperandTailBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationIndirectOperandTailBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Valid Target', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([1, 1], array_column($destinations, 'page'));
        $t->same([4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[0]['coordinates']);
        $t->same(['left' => 120.0], $destinations[1]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Valid Target', 'LegacyOk'], $documentDestinations['names'] ?? null);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(2, $documentDestinations['count'] ?? null);
        $t->same(['XYZ', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Valid Target Outline', 'Legacy Outline'], array_column($toc, 'title'));
        $t->same(['Valid Target', 'LegacyOk'], array_column($toc, 'destination'));
        $t->same(['XYZ', 'FitV'], array_column($toc, 'view_mode'));
        $t->same([1, 1], array_column($toc, 'page'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'Tailed Coordinate Target',
            'Tailed View Target',
            'Tailed Coordinate Outline',
            'Tailed View Outline',
            'PrivateCoordinateTail',
            'PrivateViewTail',
            '610',
            '500',
        ] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps tailed indirect named-destination operands out of links and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationIndirectOperandTailBoundaryCurrentBasePdf, $namedDestinationIndirectOperandTailBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationIndirectOperandTailBoundaryCurrentBasePdf();
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10, 11], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], [], [], ['local-destination'], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 10, 11], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Valid Target', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('XYZ', $links[0]['links'][0]['view_mode']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => null], $links[0]['links'][0]['view_parameters']);
        $t->same('LegacyOk', $links[0]['links'][1]['destination']);
        $t->same('https://example.com/named-destination-indirect-operand-tail', $links[0]['links'][2]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationIndirectOperandTailBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Valid Target', $spans[0]['link_destination']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->true(!isset($spans[2]['link_destination']));
        $t->same('LegacyOk', $spans[3]['link_destination']);
        $t->same('https://example.com/named-destination-indirect-operand-tail', $spans[4]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            'Valid coordinate jump Tailed coordinate jump Tailed view jump Legacy jump [Safe URI](https://example.com/named-destination-indirect-operand-tail)',
            $blocks[0]['text']
        );

        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Valid coordinate jump Tailed coordinate jump Tailed view jump Legacy jump Safe URI', $plainText);
        $t->contains('Indirect operand tail destination target body', $plainText);
        foreach ([
            'Tailed Coordinate Target',
            'Tailed View Target',
            'Tailed Coordinate Outline',
            'Tailed View Outline',
            'PrivateCoordinateTail',
            'PrivateViewTail',
        ] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'named-destination-indirect-operand-tail'));
    },
];
