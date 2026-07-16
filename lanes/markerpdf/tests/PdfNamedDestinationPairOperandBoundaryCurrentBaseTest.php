<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationPairOperandBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Current jump Recovered jump Missing jump Alias jump Name alias jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Recovered name pair destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyTarget [4 0 R /FitV 130] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R 12 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 146 718] /Dest (Current Target) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [156 700 252 718] /Dest (Recovered Target) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [262 700 342 718] /Dest (Missing Value Target) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [352 700 428 718] /Dest (String Alias) >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [438 700 538 718] /Dest /Name#20Alias >>\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [548 700 622 718] /A << /S /URI /URI (https://example.com/name-pair-boundary) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Current Target) (String Alias)] /Names [(Current Target) [4 0 R /FitH 700] (Missing Value Target) (Recovered Target) [4 0 R /XYZ 72 640 0] (String Alias) (Current Target) (Name Alias) /Recovered#20Target] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 55 0 R /Count 5 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Current Target Outline) /Parent 50 0 R /Dest (Current Target) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Recovered Target Outline) /Parent 50 0 R /Dest (Recovered Target) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Missing Value Outline) /Parent 50 0 R /Dest (Missing Value Target) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
        . "54 0 obj\n<< /Title (String Alias Outline) /Parent 50 0 R /Dest (String Alias) /Prev 53 0 R /Next 55 0 R >>\nendobj\n"
        . "55 0 obj\n<< /Title (Name Alias Outline) /Parent 50 0 R /Dest /Name#20Alias /Prev 54 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationPairOperandBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 622.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 622.0, 718.0],
                'spans' => [
                    ['text' => 'Current jump', 'bbox' => [72.0, 700.0, 146.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Recovered jump', 'bbox' => [156.0, 700.0, 252.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Missing jump', 'bbox' => [262.0, 700.0, 342.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Alias jump', 'bbox' => [352.0, 700.0, 428.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Name alias jump', 'bbox' => [438.0, 700.0, 538.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [548.0, 700.0, 622.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'resynchronizes destination name-tree pairs after a string key with missing value' => static function (
        TestRunner $t
    ) use ($namedDestinationPairOperandBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationPairOperandBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $expectedNames = ['Current Target', 'Recovered Target', 'String Alias', 'Name Alias', 'LegacyTarget'];
        $t->same($expectedNames, array_column($destinations, 'name'));
        $t->same([1, 1, 1, 1, 1], array_column($destinations, 'page'));
        $t->same([4, 4, 4, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['top' => 700.0], $destinations[2]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[3]['coordinates']);
        $t->same(['left' => 130.0], $destinations[4]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same($expectedNames, $documentDestinations['names'] ?? null);
        $t->same(5, $documentDestinations['count'] ?? null);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(['FitH', 'XYZ', 'FitH', 'XYZ', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(
            ['Current Target Outline', 'Recovered Target Outline', 'String Alias Outline', 'Name Alias Outline'],
            array_column($toc, 'title')
        );
        $t->same(['Current Target', 'Recovered Target', 'String Alias', 'Name Alias'], array_column($toc, 'destination'));
        $t->same(['FitH', 'XYZ', 'FitH', 'XYZ'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Missing Value Target', 'Missing Value Outline'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps malformed missing-value destination keys out of link promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationPairOperandBoundaryCurrentBasePdf, $namedDestinationPairOperandBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationPairOperandBoundaryCurrentBasePdf();
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10, 11, 12], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], ['local-destination'], [], ['local-destination'], ['local-destination'], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8, 10, 11, 12], array_column($links[0]['links'], 'annotation_object'));
        $t->same(['Current Target', 'Recovered Target', 'Current Target', 'Recovered Target'], array_column(array_slice($links[0]['links'], 0, 4), 'destination'));
        $t->same(['FitH', 'XYZ', 'FitH', 'XYZ'], array_column(array_slice($links[0]['links'], 0, 4), 'view_mode'));
        $t->same('https://example.com/name-pair-boundary', $links[0]['links'][4]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationPairOperandBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Current Target', $spans[0]['link_destination']);
        $t->same('Recovered Target', $spans[1]['link_destination']);
        $t->true(!isset($spans[2]['link_destination']));
        $t->same('Current Target', $spans[3]['link_destination']);
        $t->same('Recovered Target', $spans[4]['link_destination']);
        $t->same('https://example.com/name-pair-boundary', $spans[5]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            'Current jump Recovered jump Missing jump Alias jump Name alias jump [Safe URI](https://example.com/name-pair-boundary)',
            $blocks[0]['text']
        );

        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current jump Recovered jump Missing jump Alias jump Name alias jump Safe URI', $plainText);
        $t->contains('Recovered name pair destination target body', $plainText);
        foreach (['Missing Value Target', 'Missing Value Outline'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        foreach (['Current Target', 'Recovered Target', 'String Alias', 'Name Alias'] as $reviewOnly) {
            $t->same(false, str_contains($plainText, $reviewOnly));
        }
        $t->same(false, str_contains($plainText, 'name-pair-boundary'));
    },
];
