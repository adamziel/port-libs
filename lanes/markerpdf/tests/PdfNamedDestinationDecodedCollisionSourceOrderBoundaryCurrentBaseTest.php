<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationDecodedCollisionSourceOrderBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (ASCII collision jump UTF16 collision jump Safe URI) Tj ET';
    $asciiPageContent = 'BT /F1 12 Tf 72 720 Td (ASCII source-byte collision destination page) Tj ET';
    $utf16PageContent = 'BT /F1 12 Tf 72 720 Td (UTF16 source-byte collision destination page) Tj ET';
    $utf16Collision = '<FEFF0043006F006C006C006900730069006F006E>';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 32 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 206 718] /Dest (Alias ASCII) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [216 700 326 718] /Dest (Alias UTF16) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [336 700 410 718] /A << /S /URI /URI (https://example.com/named-destination-decoded-source-order) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Alias ASCII) {$utf16Collision}] /Names [{$utf16Collision} [5 0 R /XYZ 72 640 0] (Collision) [4 0 R /FitH 710] (Alias ASCII) (Collision) (Alias UTF16) {$utf16Collision}] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($asciiPageContent) . " >>\nstream\n{$asciiPageContent}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($utf16PageContent) . " >>\nstream\n{$utf16PageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
        . "51 0 obj\n<< /Title (ASCII Collision Alias Outline) /Parent 50 0 R /Dest (Alias ASCII) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (UTF16 Collision Alias Outline) /Parent 50 0 R /Dest (Alias UTF16) /Prev 51 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationDecodedCollisionSourceOrderBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 410.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 410.0, 718.0],
                'spans' => [
                    ['text' => 'ASCII collision jump', 'bbox' => [72.0, 700.0, 206.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' UTF16 collision jump', 'bbox' => [216.0, 700.0, 326.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [336.0, 700.0, 410.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'orders decoded-collision destination rows by source bytes before WordPress metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationDecodedCollisionSourceOrderBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationDecodedCollisionSourceOrderBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $expectedNames = ['Collision', 'Collision', 'Alias ASCII', 'Alias UTF16'];
        $t->same($expectedNames, array_column($destinations, 'name'));
        $t->same([1, 2, 1, 2], array_column($destinations, 'page'));
        $t->same([4, 5, 4, 5], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitH', 'XYZ'], array_column($destinations, 'fit'));
        $t->same('436f6c6c6973696f6e', $destinations[0]['name_bytes_hex'] ?? null);
        $t->same('feff0043006f006c006c006900730069006f006e', $destinations[1]['name_bytes_hex'] ?? null);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same($expectedNames, $documentDestinations['names'] ?? null);
        $t->same([1, 2, 1, 2], array_column($documentDestinations['destinations'] ?? [], 'page'));
        $t->same(['FitH', 'XYZ', 'FitH', 'XYZ'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));
        $t->same('436f6c6c6973696f6e', $documentDestinations['destinations'][0]['name_bytes_hex'] ?? null);
        $t->same('feff0043006f006c006c006900730069006f006e', $documentDestinations['destinations'][1]['name_bytes_hex'] ?? null);

        $t->same(['ASCII Collision Alias Outline', 'UTF16 Collision Alias Outline'], array_column($toc, 'title'));
        $t->same(['Alias ASCII', 'Alias UTF16'], array_column($toc, 'destination'));
        $t->same([1, 2], array_column($toc, 'page'));
        $t->same(['FitH', 'XYZ'], array_column($toc, 'view_mode'));

        $t->same(1, count($annotations));
        $actions = array_map(
            static fn (array $annotation): array => $annotation['actions'] ?? [],
            $annotations[0]['annotations']
        );
        $t->same([1, 2], [$actions[0][0]['destination_page'] ?? null, $actions[1][0]['destination_page'] ?? null]);
        $t->same(['FitH', 'XYZ'], [$actions[0][0]['view_mode'] ?? null, $actions[1][0]['view_mode'] ?? null]);
        $t->same('https://example.com/named-destination-decoded-source-order', $actions[2][0]['uri'] ?? null);
    },
    'applies decoded-collision aliases to WordPress spans without leaking destination labels' => static function (
        TestRunner $t
    ) use ($namedDestinationDecodedCollisionSourceOrderBoundaryCurrentBasePdf, $namedDestinationDecodedCollisionSourceOrderBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationDecodedCollisionSourceOrderBoundaryCurrentBasePdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same([7, 8, 9], array_column($links[0]['links'], 'annotation_object'));
        $t->same(['Collision', 'Collision'], array_column(array_slice($links[0]['links'], 0, 2), 'destination'));
        $t->same([1, 2], array_column(array_slice($links[0]['links'], 0, 2), 'destination_page'));
        $t->same(['FitH', 'XYZ'], array_column(array_slice($links[0]['links'], 0, 2), 'view_mode'));
        $t->same('https://example.com/named-destination-decoded-source-order', $links[0]['links'][2]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationDecodedCollisionSourceOrderBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Collision', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->same('FitH', $spans[0]['link_view_mode']);
        $t->same('Collision', $spans[1]['link_destination']);
        $t->same(2, $spans[1]['link_destination_page']);
        $t->same('XYZ', $spans[1]['link_view_mode']);
        $t->same('https://example.com/named-destination-decoded-source-order', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            'ASCII collision jump UTF16 collision jump [Safe URI](https://example.com/named-destination-decoded-source-order)',
            $blocks[0]['text']
        );

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('ASCII collision jump UTF16 collision jump Safe URI', $plainText);
        $t->contains('ASCII source-byte collision destination page', $plainText);
        $t->contains('UTF16 source-byte collision destination page', $plainText);
        $t->same(true, str_contains($encoded, '"destination_page":1'));
        $t->same(true, str_contains($encoded, '"destination_page":2'));
        foreach (['Collision', 'Alias ASCII', 'Alias UTF16', 'Collision Alias Outline', 'named-destination-decoded-source-order'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
    },
];
