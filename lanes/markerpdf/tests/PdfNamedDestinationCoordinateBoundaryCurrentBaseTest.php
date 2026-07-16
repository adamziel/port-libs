<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationCoordinateBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid coordinate jump Missing coordinate jump Bad coordinate jump Bad rectangle jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Coordinate destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] /LegacyBadMissing [4 0 R /FitV] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 178 718] /Dest (Valid FitH Target) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [188 700 316 718] /Dest (Missing FitH Target) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [326 700 438 718] /Dest (String FitV Target) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [448 700 570 718] /Dest (Bad Rect Target) >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [580 700 644 718] /A << /S /URI /URI (https://example.com/coordinate-boundary) >> >>\nendobj\n"
        . "20 0 obj\n<< /Names [(Valid FitH Target) [4 0 R /FitH 700] (Valid XYZ Null Target) [4 0 R /XYZ null null null] (Missing FitH Target) [4 0 R /FitH] (String FitV Target) [4 0 R /FitV (left)] (Bad Rect Target) [4 0 R /FitR 1 2 (right) 4]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 54 0 R /Count 4 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Valid Coordinate Outline) /Parent 50 0 R /Dest (Valid FitH Target) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Missing Coordinate Outline) /Parent 50 0 R /Dest (Missing FitH Target) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Bad Coordinate Outline) /Parent 50 0 R /Dest (String FitV Target) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
        . "54 0 obj\n<< /Title (Bad Rectangle Outline) /Parent 50 0 R /Dest (Bad Rect Target) /Prev 53 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationCoordinateBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 644.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 644.0, 718.0],
                'spans' => [
                    ['text' => 'Valid coordinate jump', 'bbox' => [72.0, 700.0, 178.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Missing coordinate jump', 'bbox' => [188.0, 700.0, 316.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Bad coordinate jump', 'bbox' => [326.0, 700.0, 438.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Bad rectangle jump', 'bbox' => [448.0, 700.0, 570.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [580.0, 700.0, 644.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects named destinations with missing or nonnumeric required view coordinates before document metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationCoordinateBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationCoordinateBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Valid FitH Target', 'Valid XYZ Null Target', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([1, 1, 1], array_column($destinations, 'page'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => null, 'top' => null, 'zoom' => null], $destinations[1]['coordinates']);
        $t->same(['left' => 120.0], $destinations[2]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Valid FitH Target', 'Valid XYZ Null Target', 'LegacyOk'], $documentDestinations['names'] ?? null);
        $t->same(3, $documentDestinations['count'] ?? null);
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Valid Coordinate Outline'], array_column($toc, 'title'));
        $t->same(['Valid FitH Target'], array_column($toc, 'destination'));
        $t->same([1], array_column($toc, 'page'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
        $t->same([['top' => 700.0]], array_column($toc, 'view_parameters'));
    },
    'keeps malformed coordinate named destinations out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationCoordinateBoundaryCurrentBasePdf, $namedDestinationCoordinateBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationCoordinateBoundaryCurrentBasePdf();
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
        $t->same('Valid FitH Target', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('FitH', $links[0]['links'][0]['view_mode']);
        $t->same('https://example.com/coordinate-boundary', $links[0]['links'][1]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationCoordinateBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Valid FitH Target', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->true(!isset($spans[2]['link_destination']));
        $t->true(!isset($spans[3]['link_destination']));
        $t->same('https://example.com/coordinate-boundary', $spans[4]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Valid coordinate jump Missing coordinate jump Bad coordinate jump Bad rectangle jump [Safe URI](https://example.com/coordinate-boundary)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Valid coordinate jump Missing coordinate jump Bad coordinate jump Bad rectangle jump Safe URI', $plainText);
        $t->contains('Coordinate destination target body', $plainText);
        foreach ([
            'Missing FitH Target',
            'String FitV Target',
            'Bad Rect Target',
            'Missing Coordinate Outline',
            'Bad Coordinate Outline',
            'Bad Rectangle Outline',
            'right',
        ] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'coordinate-boundary'));
    },
];
