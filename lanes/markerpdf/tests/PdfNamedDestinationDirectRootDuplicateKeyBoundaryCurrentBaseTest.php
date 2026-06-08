<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationDirectRootDuplicateKeyBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Current root jump Stale root jump Legacy root jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Direct root duplicate-key target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests << /Limits [(Current Root) (Stale Root)] /Names [(Current Root) [4 0 R /FitH 700]] /#4eames [(Stale Root) [4 0 R /XYZ 72 640 0]] >> >> /Dests << /LegacyOk [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 170 718] /Dest (Current Root) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [180 700 268 718] /Dest (Stale Root) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [278 700 356 718] /Dest /LegacyOk >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [366 700 430 718] /A << /S /URI /URI (https://example.com/direct-root-duplicate-key) >> >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 53 0 R /Count 3 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Current Root Outline) /Parent 50 0 R /Dest (Current Root) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Stale Root Outline) /Parent 50 0 R /Dest (Stale Root) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Legacy Root Outline) /Parent 50 0 R /Dest /LegacyOk /Prev 52 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationDirectRootDuplicateKeyBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 430.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 430.0, 718.0],
                'spans' => [
                    ['text' => 'Current root jump', 'bbox' => [72.0, 700.0, 170.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale root jump', 'bbox' => [180.0, 700.0, 268.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Legacy root jump', 'bbox' => [278.0, 700.0, 356.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [366.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'fails closed on duplicate direct destination root traversal keys before standalone metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationDirectRootDuplicateKeyBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationDirectRootDuplicateKeyBoundaryCurrentBasePdf();
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

        $t->same(['Legacy Root Outline'], array_column($toc, 'title'));
        $t->same(['LegacyOk'], array_column($toc, 'destination'));
        $t->same(['FitV'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Current Root', 'Stale Root', 'Current Root Outline', 'Stale Root Outline', 'FitH', 'XYZ', '700', '640'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps duplicate direct destination root rows out of link promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationDirectRootDuplicateKeyBoundaryCurrentBasePdf, $namedDestinationDirectRootDuplicateKeyBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationDirectRootDuplicateKeyBoundaryCurrentBasePdf();
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [[], [], ['local-destination'], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([9, 10], array_column($links[0]['links'], 'annotation_object'));
        $t->same('LegacyOk', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('FitV', $links[0]['links'][0]['view_mode']);
        $t->same('https://example.com/direct-root-duplicate-key', $links[0]['links'][1]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationDirectRootDuplicateKeyBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->true(!isset($spans[0]['link_destination']));
        $t->true(!isset($spans[1]['link_destination']));
        $t->same('LegacyOk', $spans[2]['link_destination']);
        $t->same('https://example.com/direct-root-duplicate-key', $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Current root jump Stale root jump Legacy root jump [Safe URI](https://example.com/direct-root-duplicate-key)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Current root jump Stale root jump Legacy root jump Safe URI', $plainText);
        $t->contains('Direct root duplicate-key target body', $plainText);
        foreach (['Current Root', 'Stale Root', 'Current Root Outline', 'Stale Root Outline', 'FitH', 'XYZ'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'direct-root-duplicate-key'));
    },
];
