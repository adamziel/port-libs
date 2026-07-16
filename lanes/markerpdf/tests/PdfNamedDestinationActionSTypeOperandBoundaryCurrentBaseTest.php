<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationActionSTypeOperandBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid destination jump Direct action jump Indirect action jump Tailed action jump Plain destination jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Action subtype boundary target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk << /D [4 0 R /FitV 130] >> /LegacyBadIndirect << /S 22 0 R /D [4 0 R /FitH 333] >> >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /MediaBox [0 0 800 792] >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R 12 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 184 718] /Dest (Valid GoTo Dest) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [194 700 300 718] /Dest (Direct URI Dest) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [310 700 422 718] /Dest (Indirect Launch Dest) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [432 700 536 718] /Dest (Tailed GoTo Dest) >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [546 700 658 718] /Dest (Plain Dict Dest) >>\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [668 700 732 718] /A << /S /URI /URI (https://example.com/named-destination-s-boundary) >> >>\nendobj\n"
        . "20 0 obj\n<< /Names [(Valid GoTo Dest) << /S /GoTo /D [4 0 R /FitH 700] >> (Direct URI Dest) << /S /URI /URI (https://example.com/hidden-direct-destination-action) /D [4 0 R /FitH 111] >> (Indirect Launch Dest) << /S 21 0 R /D [4 0 R /FitV 222] >> (Tailed GoTo Dest) << /S 22 0 R /D [4 0 R /FitR 1 2 3 4] >> (Plain Dict Dest) << /D [4 0 R /XYZ 72 640 0] >>] >>\nendobj\n"
        . "21 0 obj\n/Launch\nendobj\n"
        . "22 0 obj\n/GoTo << /S /URI /URI (https://example.com/hidden-tailed-destination-action) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 55 0 R /Count 5 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Valid GoTo Outline) /Parent 50 0 R /Dest (Valid GoTo Dest) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Direct URI Outline) /Parent 50 0 R /Dest (Direct URI Dest) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Indirect Launch Outline) /Parent 50 0 R /Dest (Indirect Launch Dest) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
        . "54 0 obj\n<< /Title (Tailed GoTo Outline) /Parent 50 0 R /Dest (Tailed GoTo Dest) /Prev 53 0 R /Next 55 0 R >>\nendobj\n"
        . "55 0 obj\n<< /Title (Plain Dict Outline) /Parent 50 0 R /Dest (Plain Dict Dest) /Prev 54 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationActionSTypeOperandBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 732.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 732.0, 718.0],
                'spans' => [
                    ['text' => 'Valid destination jump', 'bbox' => [72.0, 700.0, 184.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Direct action jump', 'bbox' => [194.0, 700.0, 300.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Indirect action jump', 'bbox' => [310.0, 700.0, 422.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed action jump', 'bbox' => [432.0, 700.0, 536.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Plain destination jump', 'bbox' => [546.0, 700.0, 658.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [668.0, 700.0, 732.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects non-GoTo and tailed action subtype operands before destination metadata and outlines' => static function (
        TestRunner $t
    ) use ($namedDestinationActionSTypeOperandBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationActionSTypeOperandBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Valid GoTo Dest', 'Plain Dict Dest', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([1, 1, 1], array_column($destinations, 'page'));
        $t->same([4, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 130.0], $destinations[2]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Valid GoTo Dest', 'Plain Dict Dest', 'LegacyOk'], $documentDestinations['names'] ?? null);
        $t->same(3, $documentDestinations['count'] ?? null);
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Valid GoTo Outline', 'Plain Dict Outline'], array_column($toc, 'title'));
        $t->same(['Valid GoTo Dest', 'Plain Dict Dest'], array_column($toc, 'destination'));
        $t->same([1, 1], array_column($toc, 'page'));
        $t->same(['FitH', 'XYZ'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'Direct URI Dest',
            'Indirect Launch Dest',
            'Tailed GoTo Dest',
            'LegacyBadIndirect',
            'Direct URI Outline',
            'Indirect Launch Outline',
            'Tailed GoTo Outline',
            'hidden-direct-destination-action',
            'hidden-tailed-destination-action',
            '111',
            '222',
            '333',
        ] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps malformed action subtype destination rows out of link promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use (
        $namedDestinationActionSTypeOperandBoundaryCurrentBasePdf,
        $namedDestinationActionSTypeOperandBoundaryCurrentBasePages
    ): void {
        $pdf = $namedDestinationActionSTypeOperandBoundaryCurrentBasePdf();
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
        $t->same(['Valid GoTo Dest', 'Plain Dict Dest'], array_column(array_slice($links[0]['links'], 0, 2), 'destination'));
        $t->same([1, 1], array_column(array_slice($links[0]['links'], 0, 2), 'destination_page'));
        $t->same(['FitH', 'XYZ'], array_column(array_slice($links[0]['links'], 0, 2), 'view_mode'));
        $t->same('https://example.com/named-destination-s-boundary', $links[0]['links'][2]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationActionSTypeOperandBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Valid GoTo Dest', $spans[0]['link_destination']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->true(!isset($spans[2]['link_destination']));
        $t->true(!isset($spans[3]['link_destination']));
        $t->same('Plain Dict Dest', $spans[4]['link_destination']);
        $t->same('https://example.com/named-destination-s-boundary', $spans[5]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            'Valid destination jump Direct action jump Indirect action jump Tailed action jump Plain destination jump [Safe URI](https://example.com/named-destination-s-boundary)',
            $blocks[0]['text']
        );

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Valid destination jump Direct action jump Indirect action jump Tailed action jump Plain destination jump Safe URI', $plainText);
        $t->contains('Action subtype boundary target body', $plainText);
        foreach ([
            'Direct URI Dest',
            'Indirect Launch Dest',
            'Tailed GoTo Dest',
            'hidden-direct-destination-action',
            'hidden-tailed-destination-action',
            'FitH 111',
            'FitV 222',
            'FitH 333',
        ] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'named-destination-s-boundary'));
    },
];
