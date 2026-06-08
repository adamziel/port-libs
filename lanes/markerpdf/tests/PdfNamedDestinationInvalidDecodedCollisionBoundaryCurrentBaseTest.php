<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationInvalidDecodedCollisionBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid alias jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Valid decoded destination page) Tj ET';
    $utf16Collision = '<FEFF0043006F006C006C006900730069006F006E>';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 210 718] /Dest (Alias Valid) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [220 700 288 718] /A << /S /URI /URI (https://example.com/valid-named-destination) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Alias Valid) {$utf16Collision}] /Names [(Collision) [99 0 R /FitH 111] {$utf16Collision} [4 0 R /XYZ 72 640 0] (Alias Valid) (Collision)] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 51 0 R /Count 1 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Valid Alias Outline) /Parent 50 0 R /Dest (Alias Valid) >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationInvalidDecodedCollisionBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 288.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 288.0, 718.0],
                'spans' => [
                    ['text' => 'Valid alias jump', 'bbox' => [72.0, 700.0, 210.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [220.0, 700.0, 288.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'ignores invalid raw-byte collisions before WordPress named-destination alias resolution' => static function (
        TestRunner $t
    ) use ($namedDestinationInvalidDecodedCollisionBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationInvalidDecodedCollisionBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $expectedNames = ['Collision', 'Alias Valid'];
        $t->same($expectedNames, array_column($destinations, 'name'));
        $t->same([1, 1], array_column($destinations, 'page'));
        $t->same([4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['XYZ', 'XYZ'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree'], array_column($destinations, 'source'));
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(false, isset($destinations[0]['name_bytes_hex']));
        $t->same(false, isset($destinations[1]['name_bytes_hex']));

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same($expectedNames, $documentDestinations['names'] ?? null);
        $t->same(2, $documentDestinations['count'] ?? null);
        $t->same([1, 1], array_column($documentDestinations['destinations'] ?? [], 'page'));
        $t->same(['XYZ', 'XYZ'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));
        $t->same(false, isset($documentDestinations['destinations'][0]['name_bytes_hex']));
        $t->same(false, isset($documentDestinations['destinations'][1]['name_bytes_hex']));
        $t->same(1, $documentDestinations['unresolved_count'] ?? 0);

        $t->same(['Valid Alias Outline'], array_column($toc, 'title'));
        $t->same(['Alias Valid'], array_column($toc, 'destination'));
        $t->same([1], array_column($toc, 'page'));
        $t->same(['XYZ'], array_column($toc, 'view_mode'));

        $t->same(1, count($annotations));
        $actions = array_map(
            static fn (array $annotation): array => $annotation['actions'] ?? [],
            $annotations[0]['annotations']
        );
        $t->same(1, $actions[0][0]['destination_page'] ?? null);
        $t->same('Collision', $actions[0][0]['destination'] ?? null);
        $t->same('XYZ', $actions[0][0]['view_mode'] ?? null);
        $t->same('https://example.com/valid-named-destination', $actions[1][0]['uri'] ?? null);
    },
    'keeps invalid collision operands out of linked WordPress text review output' => static function (
        TestRunner $t
    ) use ($namedDestinationInvalidDecodedCollisionBoundaryCurrentBasePdf, $namedDestinationInvalidDecodedCollisionBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationInvalidDecodedCollisionBoundaryCurrentBasePdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same([7, 8], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Collision', $links[0]['links'][0]['destination'] ?? null);
        $t->same(1, $links[0]['links'][0]['destination_page'] ?? null);
        $t->same('XYZ', $links[0]['links'][0]['view_mode'] ?? null);
        $t->same('https://example.com/valid-named-destination', $links[0]['links'][1]['uri'] ?? null);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationInvalidDecodedCollisionBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Collision', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->same('XYZ', $spans[0]['link_view_mode']);
        $t->same('https://example.com/valid-named-destination', $spans[1]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            'Valid alias jump [Safe URI](https://example.com/valid-named-destination)',
            $blocks[0]['text']
        );

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Valid alias jump Safe URI', $plainText);
        $t->contains('Valid decoded destination page', $plainText);
        $t->same(true, str_contains($encoded, '"destination_page":1'));
        foreach (['Collision', 'Alias Valid', 'Valid Alias Outline', 'FitH 111', '99 0 R', 'valid-named-destination'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
    },
];
