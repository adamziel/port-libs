<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationOverlongLimitsBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid overlong jump Direct URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Overlong named destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyTail [4 0 R /FitV 144] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 224 718] /Dest (Review Link) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [234 700 304 718] /A << /S /URI /URI (https://example.com/overlong-limits-boundary) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Alpha Start) (Review Link)] /Kids [21 0 R 22 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [(Alpha Start) (Alpha Start)] /Names [(Alpha Start) [3 0 R /FitH 710]] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [(Zulu Decoy) (Zzz Decoy) (Review Link)] /Kids [23 0 R] >>\nendobj\n"
        . "23 0 obj\n<< /Limits [(Review Link) (Review Link)] /Names [(Review Link) [4 0 R /XYZ 72 640 0] (Zulu Decoy) [3 0 R /FitH 111]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 51 0 R /Count 1 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Overlong Limits Outline) /Parent 50 0 R /Dest (Review Link) >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationOverlongLimitsBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 304.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 304.0, 718.0],
                'spans' => [
                    ['text' => 'Valid overlong jump', 'bbox' => [72.0, 700.0, 224.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Direct URI', 'bbox' => [234.0, 700.0, 304.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'treats overlong destination name-tree Limits as malformed before WordPress metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationOverlongLimitsBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationOverlongLimitsBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Alpha Start', 'Review Link', 'LegacyTail'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['top' => 710.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 144.0], $destinations[2]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Alpha Start', 'Review Link', 'LegacyTail'], $documentDestinations['names'] ?? null);
        $t->same(3, $documentDestinations['count'] ?? null);
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Overlong Limits Outline'], array_column($toc, 'title'));
        $t->same(['Review Link'], array_column($toc, 'destination'));
        $t->same([1], array_column($toc, 'page'));
        $t->same(['XYZ'], array_column($toc, 'view_mode'));
        $t->same([['left' => 72.0, 'top' => 640.0, 'zoom' => null]], array_column($toc, 'view_parameters'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Zulu Decoy', 'FitH 111'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps overlong Limits decoys out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationOverlongLimitsBoundaryCurrentBasePdf, $namedDestinationOverlongLimitsBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationOverlongLimitsBoundaryCurrentBasePdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same([7, 8], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Review Link', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('XYZ', $links[0]['links'][0]['view_mode']);
        $t->same('https://example.com/overlong-limits-boundary', $links[0]['links'][1]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationOverlongLimitsBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Review Link', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->same('XYZ', $spans[0]['link_view_mode']);
        $t->same('https://example.com/overlong-limits-boundary', $spans[1]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Valid overlong jump [Direct URI](https://example.com/overlong-limits-boundary)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Valid overlong jump Direct URI', $plainText);
        $t->contains('Overlong named destination target body', $plainText);
        foreach (['Alpha Start', 'Review Link', 'Zulu Decoy', 'Overlong Limits Outline', 'overlong-limits-boundary'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
        foreach (['Zulu Decoy', 'FitH 111'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
];
