<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationActionPdfDocEncodingBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Breve jump Bullet jump Safe URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (PDFDoc encoded destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 148 718] /Dest <18> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [158 700 242 718] /A << /S /GoTo /D <80> >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [252 700 316 718] /A << /S /URI /URI (https://example.com/pdfdoc-destination) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [<18> <80>] /Names [<18> [4 0 R /FitH 700] <80> [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Breve Outline) /Parent 50 0 R /Dest <18> /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Bullet Outline) /Parent 50 0 R /Dest <80> /Prev 51 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationActionPdfDocEncodingBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 316.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 316.0, 718.0],
                'spans' => [
                    ['text' => 'Breve jump', 'bbox' => [72.0, 700.0, 148.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Bullet jump', 'bbox' => [158.0, 700.0, 242.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [252.0, 700.0, 316.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'decodes PDFDocEncoding byte-string destination names before action and outline review' => static function (
        TestRunner $t
    ) use ($namedDestinationActionPdfDocEncodingBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationActionPdfDocEncodingBoundaryCurrentBasePdf();
        $breve = "\u{02d8}";
        $bullet = "\u{2022}";

        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same([$breve, $bullet], array_column($destinations, 'name'));
        $t->same([$breve, $bullet], $metadata['document_destinations']['names'] ?? null);
        $t->same(['Breve Outline', 'Bullet Outline'], array_column($toc, 'title'));
        $t->same([$breve, $bullet], array_column($toc, 'destination'));
        $t->same(['FitH', 'XYZ'], array_column($toc, 'view_mode'));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'] ?? [], 'annotation_object'));
        $t->same(
            [$breve, $bullet, null],
            array_map(
                static fn (array $annotation): ?string => $annotation['actions'][0]['destination'] ?? null,
                $annotations[0]['annotations'] ?? []
            )
        );

        $encoded = json_encode([$destinations, $metadata['document_destinations'] ?? [], $toc, $annotations], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, "\x18"));
        $t->same(false, str_contains($encoded, "\x80"));
    },
    'promotes PDFDocEncoding named-destination links without leaking review names into WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationActionPdfDocEncodingBoundaryCurrentBasePdf, $namedDestinationActionPdfDocEncodingBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationActionPdfDocEncodingBoundaryCurrentBasePdf();
        $breve = "\u{02d8}";
        $bullet = "\u{2022}";

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8, 9], array_column($links[0]['links'], 'annotation_object'));
        $t->same([$breve, $bullet, null], array_map(static fn (array $link): ?string => $link['destination'] ?? null, $links[0]['links']));
        $t->same(['FitH', 'XYZ', null], array_map(static fn (array $link): ?string => $link['view_mode'] ?? null, $links[0]['links']));

        $pages = $linkExtractor->applyLinksToPages($namedDestinationActionPdfDocEncodingBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same($breve, $spans[0]['link_destination']);
        $t->same($bullet, $spans[1]['link_destination']);
        $t->same('https://example.com/pdfdoc-destination', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Breve jump Bullet jump [Safe URI](https://example.com/pdfdoc-destination)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Breve jump Bullet jump Safe URI', $plainText);
        $t->contains('PDFDoc encoded destination target body', $plainText);
        foreach ([$breve, $bullet, "\x18", "\x80", 'Breve Outline', 'Bullet Outline'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($encoded, "\x18"));
        $t->same(false, str_contains($encoded, "\x80"));
        $t->same(false, str_contains($plainText, 'pdfdoc-destination'));
    },
];
