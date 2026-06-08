<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationUtf8BomBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Resume jump Zurich jump Malformed jump Legacy jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (UTF8 BOM named destination target body) Tj ET';
    $resumeName = 'EFBBBF52C3A973756DC3A9205374617274';
    $zurichName = 'EFBBBF5AC3BC7269636820526576696577';
    $malformedName = 'EFBBBF53FF74616C65204B6579';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 130] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 152 718] /Dest <{$resumeName}> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [162 700 238 718] /Dest <{$zurichName}> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [248 700 348 718] /Dest <{$malformedName}> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [358 700 430 718] /Dest /LegacyOk >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [440 700 510 718] /A << /S /URI /URI (https://example.com/utf8-bom-destination) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [<{$resumeName}> <{$zurichName}>] /Names [<{$resumeName}> [4 0 R /FitH 700] <{$malformedName}> [4 0 R /FitH 111] <{$zurichName}> 21 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /D [4 0 R /XYZ 72 640 0] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 53 0 R /Count 3 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Resume Outline) /Parent 50 0 R /Dest <{$resumeName}> /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Zurich Outline) /Parent 50 0 R /Dest <{$zurichName}> /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Malformed Outline) /Parent 50 0 R /Dest <{$malformedName}> /Prev 52 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationUtf8BomBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 510.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 510.0, 718.0],
                'spans' => [
                    ['text' => 'Resume jump', 'bbox' => [72.0, 700.0, 152.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Zurich jump', 'bbox' => [162.0, 700.0, 238.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Malformed jump', 'bbox' => [248.0, 700.0, 348.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Legacy jump', 'bbox' => [358.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [440.0, 700.0, 510.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'decodes UTF-8 BOM destination names before WordPress metadata and outline review' => static function (
        TestRunner $t
    ) use ($namedDestinationUtf8BomBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationUtf8BomBoundaryCurrentBasePdf();
        $resume = "R\u{00e9}sum\u{00e9} Start";
        $zurich = "Z\u{00fc}rich Review";
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same([$resume, $zurich, 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([1, 1, 1], array_column($destinations, 'page'));
        $t->same([4, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 130.0], $destinations[2]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same([$resume, $zurich, 'LegacyOk'], $documentDestinations['names'] ?? null);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(3, $documentDestinations['count'] ?? null);

        $t->same(['Resume Outline', 'Zurich Outline'], array_column($toc, 'title'));
        $t->same([$resume, $zurich], array_column($toc, 'destination'));
        $t->same(['FitH', 'XYZ'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (["\u{00ef}\u{00bb}\u{00bf}", 'Stale Key', 'Malformed Outline', '111'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps malformed UTF-8 BOM destination keys out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationUtf8BomBoundaryCurrentBasePdf, $namedDestinationUtf8BomBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationUtf8BomBoundaryCurrentBasePdf();
        $resume = "R\u{00e9}sum\u{00e9} Start";
        $zurich = "Z\u{00fc}rich Review";

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10, 11], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], ['local-destination'], [], ['local-destination'], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8, 10, 11], array_column($links[0]['links'], 'annotation_object'));
        $t->same([$resume, $zurich, 'LegacyOk'], array_column(array_slice($links[0]['links'], 0, 3), 'destination'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column(array_slice($links[0]['links'], 0, 3), 'view_mode'));
        $t->same('https://example.com/utf8-bom-destination', $links[0]['links'][3]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationUtf8BomBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same($resume, $spans[0]['link_destination']);
        $t->same($zurich, $spans[1]['link_destination']);
        $t->true(!isset($spans[2]['link_destination']));
        $t->same('LegacyOk', $spans[3]['link_destination']);
        $t->same('https://example.com/utf8-bom-destination', $spans[4]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            'Resume jump Zurich jump Malformed jump Legacy jump [Safe URI](https://example.com/utf8-bom-destination)',
            $blocks[0]['text']
        );

        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Resume jump Zurich jump Malformed jump Legacy jump Safe URI', $plainText);
        $t->contains('UTF8 BOM named destination target body', $plainText);
        foreach (["\u{00ef}\u{00bb}\u{00bf}", 'Stale Key', 'Malformed Outline', 'FitH 111'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        foreach ([$resume, $zurich, 'LegacyOk'] as $reviewOnly) {
            $t->same(false, str_contains($plainText, $reviewOnly));
        }
        $t->same(false, str_contains($plainText, 'utf8-bom-destination'));
    },
];
