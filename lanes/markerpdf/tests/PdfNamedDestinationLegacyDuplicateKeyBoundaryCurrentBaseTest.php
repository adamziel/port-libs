<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationLegacyDuplicateKeyBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Legacy jump Unique jump Tree jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Legacy duplicate target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests << /Names [(Tree Target) [4 0 R /FitBH 600]] >> >> /Dests << /LegacyReview [3 0 R /FitH 700] /#4cegacyReview [4 0 R /XYZ 72 640 0] /UniqueLegacy [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 158 718] /Dest /LegacyReview >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [168 700 252 718] /Dest /UniqueLegacy >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [262 700 330 718] /Dest (Tree Target) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [340 700 410 718] /A << /S /URI /URI (https://example.com/legacy-duplicate-safe) >> >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 53 0 R /Count 3 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Legacy Duplicate Outline) /Parent 50 0 R /Dest /LegacyReview /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Unique Legacy Outline) /Parent 50 0 R /Dest /UniqueLegacy /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Tree Target Outline) /Parent 50 0 R /Dest (Tree Target) /Prev 52 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationLegacyDuplicateKeyBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 410.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 410.0, 718.0],
                'spans' => [
                    ['text' => 'Legacy jump', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Unique jump', 'bbox' => [168.0, 700.0, 252.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tree jump', 'bbox' => [262.0, 700.0, 330.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [340.0, 700.0, 410.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'skips duplicate decoded legacy Dests keys before WordPress destination review' => static function (
        TestRunner $t
    ) use ($namedDestinationLegacyDuplicateKeyBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationLegacyDuplicateKeyBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Tree Target', 'UniqueLegacy'], array_column($destinations, 'name'));
        $t->same([1, 1], array_column($destinations, 'page'));
        $t->same([4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitBH', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'legacy-dests'], array_column($destinations, 'source'));

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Tree Target', 'UniqueLegacy'], $documentDestinations['names'] ?? null);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(2, $documentDestinations['count'] ?? null);
        $t->same(['FitBH', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Unique Legacy Outline', 'Tree Target Outline'], array_column($toc, 'title'));
        $t->same(['UniqueLegacy', 'Tree Target'], array_column($toc, 'destination'));
        $t->same(['FitV', 'FitBH'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['LegacyReview', 'Legacy Duplicate Outline', 'FitH', 'XYZ', '700', '640'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps duplicate legacy destinations out of link promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationLegacyDuplicateKeyBoundaryCurrentBasePdf, $namedDestinationLegacyDuplicateKeyBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationLegacyDuplicateKeyBoundaryCurrentBasePdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same([8, 9, 10], array_column($links[0]['links'], 'annotation_object'));
        $t->same('UniqueLegacy', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('FitV', $links[0]['links'][0]['view_mode']);
        $t->same('Tree Target', $links[0]['links'][1]['destination']);
        $t->same('FitBH', $links[0]['links'][1]['view_mode']);
        $t->same('https://example.com/legacy-duplicate-safe', $links[0]['links'][2]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationLegacyDuplicateKeyBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->true(!isset($spans[0]['link_destination']));
        $t->same('UniqueLegacy', $spans[1]['link_destination']);
        $t->same('Tree Target', $spans[2]['link_destination']);
        $t->same('https://example.com/legacy-duplicate-safe', $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Legacy jump Unique jump Tree jump [Safe URI](https://example.com/legacy-duplicate-safe)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Legacy jump Unique jump Tree jump Safe URI', $plainText);
        $t->contains('Legacy duplicate target body', $plainText);
        foreach (['LegacyReview', 'UniqueLegacy', 'Tree Target', 'Legacy Duplicate Outline', 'FitH', 'XYZ'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
        foreach (['LegacyReview', 'FitH', 'XYZ'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
        $t->same(false, str_contains($plainText, 'legacy-duplicate-safe'));
    },
];
