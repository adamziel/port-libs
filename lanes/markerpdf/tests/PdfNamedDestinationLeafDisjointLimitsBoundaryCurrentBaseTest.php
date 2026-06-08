<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationLeafDisjointLimitsBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Current leaf-limit jump Decoy leaf-limit jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Leaf-limit destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyTail [4 0 R /FitV 144] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 216 718] /Dest (Review Live) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [226 700 374 718] /Dest (Zulu Decoy) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [384 700 456 718] /A << /S /URI /URI (https://example.com/leaf-limit-safe-uri) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Alpha Live) (Review Live)] /Kids [21 0 R 23 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [(Alpha Live) (Alpha Live)] /Names [(Alpha Live) [3 0 R /FitH 710]] >>\nendobj\n"
        . "23 0 obj\n<< /Limits [(Zulu Decoy) (Zzz Decoy)] /Names [(Review Live) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Review Live Outline) /Parent 50 0 R /Dest (Review Live) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Zulu Decoy Outline) /Parent 50 0 R /Dest (Zulu Decoy) /Prev 51 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationLeafDisjointLimitsBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 456.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 456.0, 718.0],
                'spans' => [
                    ['text' => 'Current leaf-limit jump', 'bbox' => [72.0, 700.0, 216.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Decoy leaf-limit jump', 'bbox' => [226.0, 700.0, 374.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [384.0, 700.0, 456.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'prunes disjoint leaf destination Limits before WordPress destination metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationLeafDisjointLimitsBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationLeafDisjointLimitsBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Alpha Live', 'LegacyTail'], array_column($destinations, 'name'));
        $t->same([0, 1], array_column($destinations, 'page'));
        $t->same([3, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 710.0], $destinations[0]['coordinates']);
        $t->same(['left' => 144.0], $destinations[1]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Alpha Live', 'LegacyTail'], $documentDestinations['names'] ?? null);
        $t->same(2, $documentDestinations['count'] ?? null);
        $t->same(['FitH', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same([], $toc);

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Review Live', 'Zulu Decoy', 'XYZ', 'FitH 222'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps disjoint leaf destination rows out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationLeafDisjointLimitsBoundaryCurrentBasePdf, $namedDestinationLeafDisjointLimitsBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationLeafDisjointLimitsBoundaryCurrentBasePdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same([9], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/leaf-limit-safe-uri', $links[0]['links'][0]['uri']);

        $linkedPages = $linkExtractor->applyLinksToPages($namedDestinationLeafDisjointLimitsBoundaryCurrentBasePages(), $pdf);
        $spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->true(!isset($spans[0]['link_destination']));
        $t->true(!isset($spans[1]['link_destination']));
        $t->same('https://example.com/leaf-limit-safe-uri', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('Current leaf-limit jump Decoy leaf-limit jump [Safe URI](https://example.com/leaf-limit-safe-uri)', $blocks[0]['text']);

        $encoded = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current leaf-limit jump Decoy leaf-limit jump Safe URI', $plainText);
        $t->contains('Leaf-limit destination target body', $plainText);
        foreach (['Alpha Live', 'Review Live', 'Zulu Decoy', 'Review Live Outline', 'leaf-limit-safe-uri'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
        foreach (['Review Live', 'Zulu Decoy', 'XYZ', 'FitH 222'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
];
