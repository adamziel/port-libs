<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationMetadataKidGenerationBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Generation nested jump Safe URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Generation metadata target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyFallback [4 0 R /FitV 130] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 210 718] /Dest (Current Review) >>\nendobj\n"
        . "8 0 obj\n<< /Kids [9 0 R 10 0 R] /Limits [(Current Review) (Summary Review)] >>\nendobj\n"
        . "9 0 obj\n<< /Limits [(Current Review) (Current Review)] /Kids [9 1 R] >>\nendobj\n"
        . "9 1 obj\n<< /Limits [(Current Review) (Current Review)] /Names [(Current Review) [3 0 R /XYZ 72 700 1]] >>\nendobj\n"
        . "10 0 obj\n<< /Limits [(Summary Review) (Summary Review)] /Names [(Summary Review) 11 0 R] >>\nendobj\n"
        . "11 0 obj\n<< /D [4 0 R /FitBH 640] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Current Review Outline) /Parent 50 0 R /Dest (Current Review) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Summary Review Outline) /Parent 50 0 R /Dest (Summary Review) /Prev 51 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationMetadataKidGenerationBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 284.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 284.0, 718.0],
                'spans' => [
                    ['text' => 'Generation nested jump', 'bbox' => [72.0, 700.0, 210.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [220.0, 700.0, 284.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'mirrors generation-distinct name-tree kids into document destination metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationMetadataKidGenerationBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationMetadataKidGenerationBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $documentDestinations = $metadata['document_destinations'] ?? [];
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Current Review', 'Summary Review', 'LegacyFallback'], array_column($destinations, 'name'));
        $t->same(['Current Review', 'Summary Review', 'LegacyFallback'], $documentDestinations['names'] ?? null);
        $t->same(3, $documentDestinations['count'] ?? null);
        $t->same([0, 1, 1], array_column($documentDestinations['destinations'] ?? [], 'page'));
        $t->same([3, 4, 4], array_column($documentDestinations['destinations'] ?? [], 'page_object'));
        $t->same(['XYZ', 'FitBH', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));
        $t->same([[72.0, 700.0, 1.0], [640.0], [130.0]], array_column($documentDestinations['destinations'] ?? [], 'view_position'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same(['XYZ', 'FitBH', 'FitV'], array_column($destinations, 'fit'));

        $t->same(['Current Review Outline', 'Summary Review Outline'], array_column($toc, 'title'));
        $t->same(['Current Review', 'Summary Review'], array_column($toc, 'destination'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same(['XYZ', 'FitBH'], array_column($toc, 'view_mode'));
    },
    'keeps generation-distinct destination labels review-only for WordPress import' => static function (
        TestRunner $t
    ) use (
        $namedDestinationMetadataKidGenerationBoundaryCurrentBasePdf,
        $namedDestinationMetadataKidGenerationBoundaryCurrentBasePages
    ): void {
        $pdf = $namedDestinationMetadataKidGenerationBoundaryCurrentBasePdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Current Review', $links[0]['links'][0]['destination']);
        $t->same(0, $links[0]['links'][0]['destination_page']);
        $t->same('XYZ', $links[0]['links'][0]['view_mode']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationMetadataKidGenerationBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Current Review', $spans[0]['link_destination']);
        $t->same(0, $spans[0]['link_destination_page']);
        $t->true(!isset($spans[1]['link_uri']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Generation nested jump Safe URI', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Generation nested jump Safe URI', $plainText);
        $t->contains('Generation metadata target body', $plainText);
        foreach (['Current Review', 'Summary Review', 'LegacyFallback', 'Current Review Outline', 'Summary Review Outline'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
    },
];
