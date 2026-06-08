<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationStreamNodeBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Carrier jump Legacy jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Stream-node boundary target body) Tj ET';
    $carrierPayload = 'BT /F1 12 Tf 72 720 Td (hidden carrier stream destination payload) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 162 718] /Dest (Carrier Decoy) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [172 700 252 718] /Dest /LegacyOk >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [262 700 330 718] /A << /S /URI /URI (https://example.com/stream-node-boundary) >> >>\nendobj\n"
        . "20 0 obj\n<< /Type /ObjStm /N 1 /First 0 /Names [(Carrier Decoy) [4 0 R /XYZ 72 640 0]] /Length " . strlen($carrierPayload) . " >>\nstream\n{$carrierPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Carrier Decoy Outline) /Parent 50 0 R /Dest (Carrier Decoy) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Legacy Destination Outline) /Parent 50 0 R /Dest /LegacyOk /Prev 51 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationStreamNodeBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 330.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 330.0, 718.0],
                'spans' => [
                    ['text' => 'Carrier jump', 'bbox' => [72.0, 700.0, 162.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Legacy jump', 'bbox' => [172.0, 700.0, 252.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [262.0, 700.0, 330.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects stream-object destination name-tree nodes before WordPress metadata and outline review' => static function (
        TestRunner $t
    ) use ($namedDestinationStreamNodeBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationStreamNodeBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['LegacyOk'], array_column($destinations, 'name'));
        $t->same([1], array_column($destinations, 'page'));
        $t->same([4], array_column($destinations, 'page_object_id'));
        $t->same(['FitV'], array_column($destinations, 'fit'));
        $t->same(['legacy-dests'], array_column($destinations, 'source'));
        $t->same(['left' => 120.0], $destinations[0]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['LegacyOk'], $documentDestinations['names'] ?? null);
        $t->same(['legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(1, $documentDestinations['count'] ?? null);
        $t->same(['FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Legacy Destination Outline'], array_column($toc, 'title'));
        $t->same(['LegacyOk'], array_column($toc, 'destination'));
        $t->same(['FitV'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Carrier Decoy', 'Carrier Decoy Outline', 'hidden carrier stream destination payload', 'XYZ', '640'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps stream-object name-tree decoys out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationStreamNodeBoundaryCurrentBasePdf, $namedDestinationStreamNodeBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationStreamNodeBoundaryCurrentBasePdf();
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [[], ['local-destination'], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([8, 9], array_column($links[0]['links'], 'annotation_object'));
        $t->same('LegacyOk', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('FitV', $links[0]['links'][0]['view_mode']);
        $t->same('https://example.com/stream-node-boundary', $links[0]['links'][1]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationStreamNodeBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->true(!isset($spans[0]['link_destination']));
        $t->same('LegacyOk', $spans[1]['link_destination']);
        $t->same('https://example.com/stream-node-boundary', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Carrier jump Legacy jump [Safe URI](https://example.com/stream-node-boundary)', $blocks[0]['text']);

        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Carrier jump Legacy jump Safe URI', $plainText);
        $t->contains('Stream-node boundary target body', $plainText);
        foreach (['Carrier Decoy', 'Carrier Decoy Outline', 'hidden carrier stream destination payload', 'XYZ'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'stream-node-boundary'));
    },
];
