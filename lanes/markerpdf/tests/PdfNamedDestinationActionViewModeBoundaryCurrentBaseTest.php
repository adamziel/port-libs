<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationActionViewModeBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Valid jump Invalid view jump Action invalid jump Safe URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Named destination view target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] /LegacyBad [4 0 R /Launch 88] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 142 718] /Dest (Valid Target) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [152 700 264 718] /Dest (Invalid View Target) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [274 700 396 718] /A << /S /GoTo /D (Action Invalid View) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [406 700 470 718] /A << /S /URI /URI (https://example.com/named-destination-action-view) >> >>\nendobj\n"
        . "20 0 obj\n<< /Names [(Valid Target) [4 0 R /XYZ 72 640 0] (Invalid View Target) [4 0 R /Launch 77] (Indirect Invalid View) [4 0 R 21 0 R 88] (Action Invalid View) << /S /GoTo /D [4 0 R /Movie 99] >>] >>\nendobj\n"
        . "21 0 obj\n/RichMedia\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationActionViewModeBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 470.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 470.0, 718.0],
                'spans' => [
                    ['text' => 'Valid jump', 'bbox' => [72.0, 700.0, 142.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Invalid view jump', 'bbox' => [152.0, 700.0, 264.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Action invalid jump', 'bbox' => [274.0, 700.0, 396.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [406.0, 700.0, 470.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects invalid named-destination view modes before annotation action review' => static function (
        TestRunner $t
    ) use ($namedDestinationActionViewModeBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationActionViewModeBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(['Valid Target', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([1, 1], array_column($destinations, 'page'));
        $t->same(['XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['Valid Target', 'LegacyOk'], $metadata['document_destinations']['names'] ?? null);

        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], [], ['unsupported-action-review'], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );
    },
    'keeps invalid named-destination view modes out of link promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationActionViewModeBoundaryCurrentBasePdf, $namedDestinationActionViewModeBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationActionViewModeBoundaryCurrentBasePdf();

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 10], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Valid Target', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('XYZ', $links[0]['links'][0]['view_mode']);
        $t->same('https://example.com/named-destination-action-view', $links[0]['links'][1]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationActionViewModeBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Valid Target', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->same('XYZ', $spans[0]['link_view_mode']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->true(!isset($spans[2]['link_destination']));
        $t->same('https://example.com/named-destination-action-view', $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Valid jump Invalid view jump Action invalid jump [Safe URI](https://example.com/named-destination-action-view)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Valid jump Invalid view jump Action invalid jump Safe URI', $plainText);
        $t->contains('Named destination view target body', $plainText);
        foreach ([
            'Invalid View Target',
            'Indirect Invalid View',
            'Action Invalid View',
            'LegacyBad',
            'Launch',
            'RichMedia',
            'Movie',
            '77',
            '88',
            '99',
        ] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'named-destination-action-view'));
    },
];
