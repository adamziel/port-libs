<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationNameTreeKeyActionBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Current jump Malformed name jump Legacy jump Safe URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Name-tree key destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyNameKey [4 0 R /FitV 130] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 158 718] /Dest (Current String Key) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [168 700 294 718] /Dest /NameObjectStale >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [304 700 394 718] /Dest /LegacyNameKey >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [404 700 468 718] /A << /S /URI /URI (https://example.com/name-tree-key-boundary) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Current String Key) (Review Summary)] /Names [(Current String Key) [4 0 R /FitH 700] /NameObjectStale [4 0 R /FitH 111] (Review Summary) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationNameTreeKeyActionBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 468.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 468.0, 718.0],
                'spans' => [
                    ['text' => 'Current jump', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Malformed name jump', 'bbox' => [168.0, 700.0, 294.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Legacy jump', 'bbox' => [304.0, 700.0, 394.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [404.0, 700.0, 468.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects PDF-name keys in destination name trees before WordPress document metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationNameTreeKeyActionBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationNameTreeKeyActionBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

        $t->same(['Current String Key', 'Review Summary', 'LegacyNameKey'], array_column($destinations, 'name'));
        $t->same([1, 1, 1], array_column($destinations, 'page'));
        $t->same([4, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 130.0], $destinations[2]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Current String Key', 'Review Summary', 'LegacyNameKey'], $documentDestinations['names'] ?? null);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(3, $documentDestinations['count'] ?? null);
        $t->same(2, $documentDestinations['page_count'] ?? null);

        $encoded = json_encode([$destinations, $documentDestinations], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, 'NameObjectStale'));
        $t->same(false, str_contains($encoded, '111'));
    },
    'keeps malformed name-tree name keys out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationNameTreeKeyActionBoundaryCurrentBasePdf, $namedDestinationNameTreeKeyActionBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationNameTreeKeyActionBoundaryCurrentBasePdf();
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
        $t->same('Current String Key', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('FitH', $links[0]['links'][0]['view_mode']);
        $t->same('LegacyNameKey', $links[0]['links'][1]['destination']);
        $t->same(1, $links[0]['links'][1]['destination_page']);
        $t->same('FitV', $links[0]['links'][1]['view_mode']);
        $t->same('https://example.com/name-tree-key-boundary', $links[0]['links'][2]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationNameTreeKeyActionBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Current String Key', $spans[0]['link_destination']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->same('LegacyNameKey', $spans[2]['link_destination']);
        $t->same('https://example.com/name-tree-key-boundary', $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Current jump Malformed name jump Legacy jump [Safe URI](https://example.com/name-tree-key-boundary)', $blocks[0]['text']);

        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current jump Malformed name jump Legacy jump Safe URI', $plainText);
        $t->contains('Name-tree key destination target body', $plainText);
        foreach (['NameObjectStale', 'FitH 111'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'name-tree-key-boundary'));
    },
];
