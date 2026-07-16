<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationIndirectStringKeyTailBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Tailed key jump Valid jump Legacy jump Safe URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect key tail destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 168 718] /Dest (Tailed Key) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [178 700 260 718] /Dest (Valid Target) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 352 718] /Dest /LegacyOk >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [362 700 440 718] /A << /S /URI /URI (https://example.com/indirect-key-tail) >> >>\nendobj\n"
        . "12 0 obj\n(Tailed Key) /Extra\nendobj\n"
        . "20 0 obj\n<< /Limits [(Tailed Key) (Valid Target)] /Names [12 0 R [4 0 R /FitH 710] (Valid Target) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 53 0 R /Count 3 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Tailed Key Outline) /Parent 50 0 R /Dest (Tailed Key) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Valid Outline) /Parent 50 0 R /Dest (Valid Target) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Legacy Outline) /Parent 50 0 R /Dest /LegacyOk /Prev 52 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationIndirectStringKeyTailBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 440.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 440.0, 718.0],
                'spans' => [
                    ['text' => 'Tailed key jump', 'bbox' => [72.0, 700.0, 168.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Valid jump', 'bbox' => [178.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Legacy jump', 'bbox' => [270.0, 700.0, 352.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [362.0, 700.0, 440.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects tailed indirect string name-tree keys before WordPress destination metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationIndirectStringKeyTailBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationIndirectStringKeyTailBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Valid Target', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([1, 1], array_column($destinations, 'page'));
        $t->same([4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[0]['coordinates']);
        $t->same(['left' => 120.0], $destinations[1]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Valid Target', 'LegacyOk'], $documentDestinations['names'] ?? null);
        $t->same(2, $documentDestinations['count'] ?? null);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(['XYZ', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Valid Outline', 'Legacy Outline'], array_column($toc, 'title'));
        $t->same(['Valid Target', 'LegacyOk'], array_column($toc, 'destination'));
        $t->same(['XYZ', 'FitV'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Tailed Key', 'Tailed Key Outline', 'FitH', '710'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps tailed indirect string key rows out of link promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationIndirectStringKeyTailBoundaryCurrentBasePdf, $namedDestinationIndirectStringKeyTailBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationIndirectStringKeyTailBoundaryCurrentBasePdf();
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [[], ['local-destination'], ['local-destination'], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([8, 9, 10], array_column($links[0]['links'], 'annotation_object'));
        $t->same(['Valid Target', 'LegacyOk'], array_column(array_slice($links[0]['links'], 0, 2), 'destination'));
        $t->same([1, 1], array_column(array_slice($links[0]['links'], 0, 2), 'destination_page'));
        $t->same(['XYZ', 'FitV'], array_column(array_slice($links[0]['links'], 0, 2), 'view_mode'));
        $t->same('https://example.com/indirect-key-tail', $links[0]['links'][2]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationIndirectStringKeyTailBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->true(!isset($spans[0]['link_destination']));
        $t->same('Valid Target', $spans[1]['link_destination']);
        $t->same(1, $spans[1]['link_destination_page']);
        $t->same('XYZ', $spans[1]['link_view_mode']);
        $t->same('LegacyOk', $spans[2]['link_destination']);
        $t->same('https://example.com/indirect-key-tail', $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            'Tailed key jump Valid jump Legacy jump [Safe URI](https://example.com/indirect-key-tail)',
            $blocks[0]['text']
        );

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Tailed key jump Valid jump Legacy jump Safe URI', $plainText);
        $t->contains('Indirect key tail destination target body', $plainText);
        foreach (['Valid Target', 'LegacyOk', 'Tailed Key', 'Tailed Key Outline', 'FitH 710', 'indirect-key-tail'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
        foreach (['Tailed Key', 'Tailed Key Outline', 'FitH', '710'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
];
