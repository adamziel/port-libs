<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationByteLimitsActionBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Valid destination jump Stale byte jump Safe URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Byte-limited destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 188 718] /Dest <41> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [198 700 304 718] /Dest <80> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [314 700 378 718] /A << /S /URI /URI (https://example.com/byte-limit-action) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [<18> <41>] /Names [<18> [3 0 R /FitH 700] <41> [4 0 R /XYZ 72 640 0] <80> [4 0 R /FitH 111]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Valid A Outline) /Parent 50 0 R /Dest <41> /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Stale Bullet Outline) /Parent 50 0 R /Dest <80> /Prev 51 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationByteLimitsActionBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 378.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 378.0, 718.0],
                'spans' => [
                    ['text' => 'Valid destination jump', 'bbox' => [72.0, 700.0, 188.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale byte jump', 'bbox' => [198.0, 700.0, 304.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [314.0, 700.0, 378.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'keeps byte-out-of-range named destinations out of outline and action metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationByteLimitsActionBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationByteLimitsActionBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(["\u{02d8}", 'A', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(["\u{02d8}", 'A', 'LegacyOk'], $metadata['document_destinations']['names'] ?? null);
        $t->same(3, $metadata['document_destinations']['count'] ?? null);

        $t->same(['Valid A Outline'], array_column($toc, 'title'));
        $t->same(['A'], array_column($toc, 'destination'));
        $t->same([1], array_column($toc, 'page'));
        $t->same(['XYZ'], array_column($toc, 'view_mode'));
        $t->same([['left' => 72.0, 'top' => 640.0, 'zoom' => null]], array_column($toc, 'view_parameters'));

        $encoded = json_encode([$destinations, $metadata['document_destinations'] ?? [], $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (["\u{2022}", 'Stale Bullet Outline', '111'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps byte-out-of-range named destinations out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationByteLimitsActionBoundaryCurrentBasePdf, $namedDestinationByteLimitsActionBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationByteLimitsActionBoundaryCurrentBasePdf();
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
        $t->same('A', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('XYZ', $links[0]['links'][0]['view_mode']);
        $t->same('https://example.com/byte-limit-action', $links[0]['links'][1]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationByteLimitsActionBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('A', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->same('https://example.com/byte-limit-action', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Valid destination jump Stale byte jump [Safe URI](https://example.com/byte-limit-action)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Valid destination jump Stale byte jump Safe URI', $plainText);
        $t->contains('Byte-limited destination target body', $plainText);
        foreach (["\u{2022}", 'Stale Bullet Outline', 'FitH 111'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'byte-limit-action'));
    },
];
