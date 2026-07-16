<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationNodeDuplicateKeyBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Clean duplicate-node jump Malformed duplicate-node jump Legacy jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Duplicate node destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 220 718] /Dest (Clean Target) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [230 700 404 718] /Dest (Malformed Target) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [414 700 492 718] /Dest /LegacyOk >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [502 700 568 718] /A << /S /URI /URI (https://example.com/named-destination-node-duplicate) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Clean Target) (Malformed Target)] /Kids [21 0 R 22 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [(Clean Target) (Clean Target)] /Names [(Clean Target) [4 0 R /FitH 700]] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [(Malformed Target) (Malformed Target)] /Names [(Stale Target) [4 0 R /FitH 111]] /#4eames [(Malformed Target) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 53 0 R /Count 3 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Clean Node Outline) /Parent 50 0 R /Dest (Clean Target) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Malformed Node Outline) /Parent 50 0 R /Dest (Malformed Target) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Legacy Node Outline) /Parent 50 0 R /Dest /LegacyOk /Prev 52 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationNodeDuplicateKeyBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 568.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 568.0, 718.0],
                'spans' => [
                    ['text' => 'Clean duplicate-node jump', 'bbox' => [72.0, 700.0, 220.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Malformed duplicate-node jump', 'bbox' => [230.0, 700.0, 404.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Legacy jump', 'bbox' => [414.0, 700.0, 492.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [502.0, 700.0, 568.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'skips malformed destination name-tree nodes with duplicate traversal keys before WordPress metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationNodeDuplicateKeyBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationNodeDuplicateKeyBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Clean Target', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([1, 1], array_column($destinations, 'page'));
        $t->same([4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 120.0], $destinations[1]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Clean Target', 'LegacyOk'], $documentDestinations['names'] ?? null);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(2, $documentDestinations['count'] ?? null);
        $t->same(['FitH', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Clean Node Outline', 'Legacy Node Outline'], array_column($toc, 'title'));
        $t->same(['Clean Target', 'LegacyOk'], array_column($toc, 'destination'));
        $t->same(['FitH', 'FitV'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Malformed Target', 'Stale Target', 'Malformed Node Outline', 'FitH 111', 'XYZ', '640'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps duplicate-node named destinations out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationNodeDuplicateKeyBoundaryCurrentBasePdf, $namedDestinationNodeDuplicateKeyBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationNodeDuplicateKeyBoundaryCurrentBasePdf();
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], [], ['local-destination'], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 9, 10], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Clean Target', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('FitH', $links[0]['links'][0]['view_mode']);
        $t->same('LegacyOk', $links[0]['links'][1]['destination']);
        $t->same(1, $links[0]['links'][1]['destination_page']);
        $t->same('FitV', $links[0]['links'][1]['view_mode']);
        $t->same('https://example.com/named-destination-node-duplicate', $links[0]['links'][2]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationNodeDuplicateKeyBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Clean Target', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->same('LegacyOk', $spans[2]['link_destination']);
        $t->same('https://example.com/named-destination-node-duplicate', $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Clean duplicate-node jump Malformed duplicate-node jump Legacy jump [Safe URI](https://example.com/named-destination-node-duplicate)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Clean duplicate-node jump Malformed duplicate-node jump Legacy jump Safe URI', $plainText);
        $t->contains('Duplicate node destination target body', $plainText);
        foreach (['Clean Target', 'Malformed Target', 'Stale Target', 'Malformed Node Outline', 'FitH 111', 'XYZ'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
        foreach (['Malformed Target', 'Stale Target', 'FitH 111', 'XYZ'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
        $t->same(false, str_contains($plainText, 'named-destination-node-duplicate'));
    },
];
