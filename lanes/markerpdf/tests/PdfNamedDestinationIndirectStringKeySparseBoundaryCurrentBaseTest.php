<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationIndirectStringKeySparseBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Recovered key jump Missing key jump Alias jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Recovered indirect string key target page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyTail [4 0 R /FitV 144] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 190 718] /Dest (Recovered Indirect Key) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [200 700 312 718] /Dest (Missing Before Key) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [322 700 404 718] /Dest (Indirect Alias) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [414 700 486 718] /A << /S /URI /URI (https://example.com/indirect-string-key-sparse) >> >>\nendobj\n"
        . "12 0 obj\n(Actual Target)\nendobj\n"
        . "21 0 obj\n(Recovered Indirect Key)\nendobj\n"
        . "20 0 obj\n<< /Limits [(Actual Target) (Recovered Indirect Key)] /Names [(Actual Target) [4 0 R /XYZ 72 640 0] (Indirect Alias) 12 0 R (Missing Before Key) 21 0 R [4 0 R /FitH 620]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 54 0 R /Count 4 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Actual Target Outline) /Parent 50 0 R /Dest (Actual Target) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Recovered Key Outline) /Parent 50 0 R /Dest (Recovered Indirect Key) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Missing Key Outline) /Parent 50 0 R /Dest (Missing Before Key) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
        . "54 0 obj\n<< /Title (Alias Outline) /Parent 50 0 R /Dest (Indirect Alias) /Prev 53 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationIndirectStringKeySparseBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 486.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 486.0, 718.0],
                'spans' => [
                    ['text' => 'Recovered key jump', 'bbox' => [72.0, 700.0, 190.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Missing key jump', 'bbox' => [200.0, 700.0, 312.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Alias jump', 'bbox' => [322.0, 700.0, 404.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [414.0, 700.0, 486.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'recovers indirect string keys after sparse name-tree entries before WordPress destination metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationIndirectStringKeySparseBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationIndirectStringKeySparseBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $expectedNames = ['Actual Target', 'Indirect Alias', 'Recovered Indirect Key', 'LegacyTail'];
        $t->same($expectedNames, array_column($destinations, 'name'));
        $t->same([1, 1, 1, 1], array_column($destinations, 'page'));
        $t->same([4, 4, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['XYZ', 'XYZ', 'FitH', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['top' => 620.0], $destinations[2]['coordinates']);
        $t->same(['left' => 144.0], $destinations[3]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same($expectedNames, $documentDestinations['names'] ?? null);
        $t->same(4, $documentDestinations['count'] ?? null);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(['XYZ', 'XYZ', 'FitH', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Actual Target Outline', 'Recovered Key Outline', 'Alias Outline'], array_column($toc, 'title'));
        $t->same(['Actual Target', 'Recovered Indirect Key', 'Indirect Alias'], array_column($toc, 'destination'));
        $t->same(['XYZ', 'FitH', 'XYZ'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Missing Before Key', 'Missing Key Outline'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps sparse indirect-key decoys out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationIndirectStringKeySparseBoundaryCurrentBasePdf, $namedDestinationIndirectStringKeySparseBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationIndirectStringKeySparseBoundaryCurrentBasePdf();
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
        $t->same(['Recovered Indirect Key', 'Actual Target'], array_column(array_slice($links[0]['links'], 0, 2), 'destination'));
        $t->same([1, 1], array_column(array_slice($links[0]['links'], 0, 2), 'destination_page'));
        $t->same(['FitH', 'XYZ'], array_column(array_slice($links[0]['links'], 0, 2), 'view_mode'));
        $t->same('https://example.com/indirect-string-key-sparse', $links[0]['links'][2]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationIndirectStringKeySparseBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Recovered Indirect Key', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->same('FitH', $spans[0]['link_view_mode']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->same('Actual Target', $spans[2]['link_destination']);
        $t->same('https://example.com/indirect-string-key-sparse', $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            'Recovered key jump Missing key jump Alias jump [Safe URI](https://example.com/indirect-string-key-sparse)',
            $blocks[0]['text']
        );

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Recovered key jump Missing key jump Alias jump Safe URI', $plainText);
        $t->contains('Recovered indirect string key target page', $plainText);
        foreach (['Actual Target', 'Indirect Alias', 'Recovered Indirect Key', 'Missing Before Key', 'indirect-string-key-sparse'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
        foreach (['Missing Before Key', 'Missing Key Outline'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
];
