<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationKeywordOperandBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid keyword jump Bare keyword jump Legacy keyword jump Legacy ok jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Keyword operand destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] /LegacyKeyword BareKeywordTarget >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 182 718] /Dest (Valid String Target) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [192 700 312 718] /Dest BareKeywordTarget >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [322 700 438 718] /Dest /LegacyKeyword >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [448 700 544 718] /Dest /LegacyOk >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [554 700 620 718] /A << /S /URI /URI (https://example.com/named-destination-keyword) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Alias From Keyword) (Valid String Target)] /Names [(Valid String Target) [4 0 R /FitH 700] BareKeywordTarget [4 0 R /FitH 111] (Alias From Keyword) BareKeywordTarget (Review Summary) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 55 0 R /Count 5 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Valid String Outline) /Parent 50 0 R /Dest (Valid String Target) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Bare Keyword Outline) /Parent 50 0 R /Dest BareKeywordTarget /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Alias Keyword Outline) /Parent 50 0 R /Dest (Alias From Keyword) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
        . "54 0 obj\n<< /Title (Legacy Keyword Outline) /Parent 50 0 R /Dest /LegacyKeyword /Prev 53 0 R /Next 55 0 R >>\nendobj\n"
        . "55 0 obj\n<< /Title (Legacy Ok Outline) /Parent 50 0 R /Dest /LegacyOk /Prev 54 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationKeywordOperandBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 620.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 620.0, 718.0],
                'spans' => [
                    ['text' => 'Valid keyword jump', 'bbox' => [72.0, 700.0, 182.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Bare keyword jump', 'bbox' => [192.0, 700.0, 312.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Legacy keyword jump', 'bbox' => [322.0, 700.0, 438.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Legacy ok jump', 'bbox' => [448.0, 700.0, 544.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [554.0, 700.0, 620.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects bare keyword operands before WordPress named-destination metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationKeywordOperandBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationKeywordOperandBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Valid String Target', 'Review Summary', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([1, 1, 1], array_column($destinations, 'page'));
        $t->same([4, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 120.0], $destinations[2]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Valid String Target', 'Review Summary', 'LegacyOk'], $documentDestinations['names'] ?? null);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(3, $documentDestinations['count'] ?? null);
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Valid String Outline', 'Legacy Ok Outline'], array_column($toc, 'title'));
        $t->same(['Valid String Target', 'LegacyOk'], array_column($toc, 'destination'));
        $t->same(['FitH', 'FitV'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['BareKeywordTarget', 'Alias From Keyword', 'LegacyKeyword', 'Bare Keyword Outline', 'Alias Keyword Outline', 'Legacy Keyword Outline', '111'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps bare keyword destination operands out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationKeywordOperandBoundaryCurrentBasePdf, $namedDestinationKeywordOperandBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationKeywordOperandBoundaryCurrentBasePdf();
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10, 11], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], [], [], ['local-destination'], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 10, 11], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Valid String Target', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('FitH', $links[0]['links'][0]['view_mode']);
        $t->same('LegacyOk', $links[0]['links'][1]['destination']);
        $t->same(1, $links[0]['links'][1]['destination_page']);
        $t->same('FitV', $links[0]['links'][1]['view_mode']);
        $t->same('https://example.com/named-destination-keyword', $links[0]['links'][2]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationKeywordOperandBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Valid String Target', $spans[0]['link_destination']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->true(!isset($spans[2]['link_destination']));
        $t->same('LegacyOk', $spans[3]['link_destination']);
        $t->same('https://example.com/named-destination-keyword', $spans[4]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            'Valid keyword jump Bare keyword jump Legacy keyword jump Legacy ok jump [Safe URI](https://example.com/named-destination-keyword)',
            $blocks[0]['text']
        );

        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Valid keyword jump Bare keyword jump Legacy keyword jump Legacy ok jump Safe URI', $plainText);
        $t->contains('Keyword operand destination target body', $plainText);
        foreach (['BareKeywordTarget', 'Alias From Keyword', 'LegacyKeyword', 'Bare Keyword Outline', 'Alias Keyword Outline', 'Legacy Keyword Outline', 'FitH 111'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'named-destination-keyword'));
    },
];
