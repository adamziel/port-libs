<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationSurplusOperandBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid jump Numeric jump String jump Action jump Name jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Surplus operand destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitR 1 2 3 4] /LegacyBad [4 0 R /Fit (legacy hidden surplus)] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R 12 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 142 718] /Dest (Valid Target) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [152 700 238 718] /Dest (Numeric Slop Target) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [248 700 322 718] /Dest (String Payload Target) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [332 700 410 718] /Dest (Action Payload Target) >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [420 700 494 718] /Dest (Name Payload Target) >>\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [504 700 568 718] /A << /S /URI /URI (https://example.com/surplus-operand-boundary) >> >>\nendobj\n"
        . "20 0 obj\n<< /Names [(Valid Target) [4 0 R /FitH 700] (Numeric Slop Target) [4 0 R /FitB 111 222] (String Payload Target) [4 0 R /Fit (hidden surplus string)] (Action Payload Target) [4 0 R /XYZ 72 640 0 << /S /URI /URI (https://example.com/hidden-surplus-action) >>] (Name Payload Target) [4 0 R /FitV 120 /URI] (Array Payload Target) [4 0 R /FitR 1 2 3 4 [(hidden surplus array)]]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 55 0 R /Count 5 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Valid Surplus Boundary Outline) /Parent 50 0 R /Dest (Valid Target) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Numeric Slop Boundary Outline) /Parent 50 0 R /Dest (Numeric Slop Target) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (String Payload Outline) /Parent 50 0 R /Dest (String Payload Target) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
        . "54 0 obj\n<< /Title (Action Payload Outline) /Parent 50 0 R /Dest (Action Payload Target) /Prev 53 0 R /Next 55 0 R >>\nendobj\n"
        . "55 0 obj\n<< /Title (Name Payload Outline) /Parent 50 0 R /Dest (Name Payload Target) /Prev 54 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationSurplusOperandBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 568.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 568.0, 718.0],
                'spans' => [
                    ['text' => 'Valid jump', 'bbox' => [72.0, 700.0, 142.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Numeric jump', 'bbox' => [152.0, 700.0, 238.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' String jump', 'bbox' => [248.0, 700.0, 322.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Action jump', 'bbox' => [332.0, 700.0, 410.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Name jump', 'bbox' => [420.0, 700.0, 494.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [504.0, 700.0, 568.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects nonnumeric surplus destination operands before WordPress destination metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationSurplusOperandBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationSurplusOperandBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Valid Target', 'Numeric Slop Target', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([1, 1, 1], array_column($destinations, 'page'));
        $t->same(['FitH', 'FitB', 'FitR'], array_column($destinations, 'fit'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same([], $destinations[1]['coordinates']);
        $t->same(['left' => 1.0, 'bottom' => 2.0, 'right' => 3.0, 'top' => 4.0], $destinations[2]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Valid Target', 'Numeric Slop Target', 'LegacyOk'], $documentDestinations['names'] ?? null);
        $t->same(3, $documentDestinations['count'] ?? null);
        $t->same(['FitH', 'FitB', 'FitR'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Valid Surplus Boundary Outline', 'Numeric Slop Boundary Outline'], array_column($toc, 'title'));
        $t->same(['Valid Target', 'Numeric Slop Target'], array_column($toc, 'destination'));
        $t->same(['FitH', 'FitB'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'String Payload Target',
            'Action Payload Target',
            'Name Payload Target',
            'Array Payload Target',
            'LegacyBad',
            'hidden surplus string',
            'hidden-surplus-action',
            'hidden surplus array',
            'String Payload Outline',
            'Action Payload Outline',
            'Name Payload Outline',
        ] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps surplus payload destinations out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationSurplusOperandBoundaryCurrentBasePdf, $namedDestinationSurplusOperandBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationSurplusOperandBoundaryCurrentBasePdf();
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10, 11, 12], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], ['local-destination'], [], [], [], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8, 12], array_column($links[0]['links'], 'annotation_object'));
        $t->same(['Valid Target', 'Numeric Slop Target'], array_column(array_slice($links[0]['links'], 0, 2), 'destination'));
        $t->same(['FitH', 'FitB'], array_column(array_slice($links[0]['links'], 0, 2), 'view_mode'));
        $t->same('https://example.com/surplus-operand-boundary', $links[0]['links'][2]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationSurplusOperandBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('Valid Target', $spans[0]['link_destination']);
        $t->same('Numeric Slop Target', $spans[1]['link_destination']);
        $t->true(!isset($spans[2]['link_destination']));
        $t->true(!isset($spans[3]['link_destination']));
        $t->true(!isset($spans[4]['link_destination']));
        $t->same('https://example.com/surplus-operand-boundary', $spans[5]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Valid jump Numeric jump String jump Action jump Name jump [Safe URI](https://example.com/surplus-operand-boundary)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Valid jump Numeric jump String jump Action jump Name jump Safe URI', $plainText);
        $t->contains('Surplus operand destination target body', $plainText);
        foreach ([
            'String Payload Target',
            'Action Payload Target',
            'Name Payload Target',
            'Array Payload Target',
            'hidden surplus string',
            'hidden-surplus-action',
            'hidden surplus array',
        ] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'surplus-operand-boundary'));
    },
];
