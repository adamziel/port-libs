<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationIndirectStringAliasBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Indirect alias jump Direct alias jump Stray jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect string alias target page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyTail [4 0 R /FitV 144] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 198 718] /Dest (Indirect Alias) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [208 700 318 718] /Dest (Direct Alias) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [328 700 396 718] /Dest /StrayOperand >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [406 700 478 718] /A << /S /URI /URI (https://example.com/indirect-string-alias) >> >>\nendobj\n"
        . "12 0 obj\n(Actual Target)\nendobj\n"
        . "20 0 obj\n<< /Limits [(Actual Target) (Indirect Alias)] /Names [(Actual Target) [4 0 R /XYZ 72 640 0] (Indirect Alias) 12 0 R /StrayOperand (Direct Alias) (Actual Target)] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 54 0 R /Count 4 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Actual Target Outline) /Parent 50 0 R /Dest (Actual Target) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Indirect Alias Outline) /Parent 50 0 R /Dest (Indirect Alias) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Direct Alias Outline) /Parent 50 0 R /Dest (Direct Alias) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
        . "54 0 obj\n<< /Title (Stray Operand Outline) /Parent 50 0 R /Dest /StrayOperand /Prev 53 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationIndirectStringAliasBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 478.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 478.0, 718.0],
                'spans' => [
                    ['text' => 'Indirect alias jump', 'bbox' => [72.0, 700.0, 198.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Direct alias jump', 'bbox' => [208.0, 700.0, 318.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stray jump', 'bbox' => [328.0, 700.0, 396.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [406.0, 700.0, 478.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'preserves indirect string alias values before malformed name-tree operands in WordPress destination metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationIndirectStringAliasBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationIndirectStringAliasBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $expectedNames = ['Actual Target', 'Indirect Alias', 'Direct Alias', 'LegacyTail'];
        $t->same($expectedNames, array_column($destinations, 'name'));
        $t->same([1, 1, 1, 1], array_column($destinations, 'page'));
        $t->same([4, 4, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['XYZ', 'XYZ', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(
            ['names-tree', 'names-tree', 'names-tree', 'legacy-dests'],
            array_column($destinations, 'source')
        );
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[2]['coordinates']);
        $t->same(['left' => 144.0], $destinations[3]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same($expectedNames, $documentDestinations['names'] ?? null);
        $t->same(4, $documentDestinations['count'] ?? null);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(['XYZ', 'XYZ', 'XYZ', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Actual Target Outline', 'Indirect Alias Outline', 'Direct Alias Outline'], array_column($toc, 'title'));
        $t->same(['Actual Target', 'Indirect Alias', 'Direct Alias'], array_column($toc, 'destination'));
        $t->same([1, 1, 1], array_column($toc, 'page'));
        $t->same(['XYZ', 'XYZ', 'XYZ'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['StrayOperand', 'Stray Operand Outline'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps malformed stray operands out of link promotion and visible WordPress text after indirect aliases' => static function (
        TestRunner $t
    ) use ($namedDestinationIndirectStringAliasBoundaryCurrentBasePdf, $namedDestinationIndirectStringAliasBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationIndirectStringAliasBoundaryCurrentBasePdf();
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], ['local-destination'], [], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8, 10], array_column($links[0]['links'], 'annotation_object'));
        $t->same(['Actual Target', 'Actual Target'], array_column(array_slice($links[0]['links'], 0, 2), 'destination'));
        $t->same([1, 1], array_column(array_slice($links[0]['links'], 0, 2), 'destination_page'));
        $t->same(['XYZ', 'XYZ'], array_column(array_slice($links[0]['links'], 0, 2), 'view_mode'));
        $t->same('https://example.com/indirect-string-alias', $links[0]['links'][2]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationIndirectStringAliasBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Actual Target', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->same('XYZ', $spans[0]['link_view_mode']);
        $t->same('Actual Target', $spans[1]['link_destination']);
        $t->same(1, $spans[1]['link_destination_page']);
        $t->true(!isset($spans[2]['link_destination']));
        $t->same('https://example.com/indirect-string-alias', $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            'Indirect alias jump Direct alias jump Stray jump [Safe URI](https://example.com/indirect-string-alias)',
            $blocks[0]['text']
        );

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Indirect alias jump Direct alias jump Stray jump Safe URI', $plainText);
        $t->contains('Indirect string alias target page', $plainText);
        foreach (['Actual Target', 'Indirect Alias', 'Direct Alias', 'StrayOperand', 'indirect-string-alias'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
        foreach (['StrayOperand', 'Stray Operand Outline'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
];
