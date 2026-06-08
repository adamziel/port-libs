<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationIndirectLimitsOperandBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Rejected named jump Legacy jump Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect limits destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacySafe [4 0 R /FitV 144] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 216 718] /Dest (Clean Target) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [226 700 300 718] /Dest /LegacySafe >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [310 700 378 718] /A << /S /URI /URI (https://example.com/named-destination-indirect-limits-tail) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [21 0 R (Stale Tail Target)] /Names [(Clean Target) [4 0 R /XYZ 72 640 0] (Stale Tail Target) [3 0 R /FitH 111]] >>\nendobj\n"
        . "21 0 obj\n(Clean Target) /PrivateLimitTail\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 53 0 R /Count 3 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Clean Target Outline) /Parent 50 0 R /Dest (Clean Target) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Legacy Target Outline) /Parent 50 0 R /Dest /LegacySafe /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
        . "53 0 obj\n<< /Title (Stale Tail Outline) /Parent 50 0 R /Dest (Stale Tail Target) /Prev 52 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationIndirectLimitsOperandBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 378.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 378.0, 718.0],
                'spans' => [
                    ['text' => 'Rejected named jump', 'bbox' => [72.0, 700.0, 216.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Legacy jump', 'bbox' => [226.0, 700.0, 300.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [310.0, 700.0, 378.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects tailed indirect destination Limits operands before WordPress metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationIndirectLimitsOperandBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationIndirectLimitsOperandBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['LegacySafe'], array_column($destinations, 'name'));
        $t->same([1], array_column($destinations, 'page'));
        $t->same([4], array_column($destinations, 'page_object_id'));
        $t->same(['FitV'], array_column($destinations, 'fit'));
        $t->same(['legacy-dests'], array_column($destinations, 'source'));
        $t->same(['left' => 144.0], $destinations[0]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['LegacySafe'], $documentDestinations['names'] ?? null);
        $t->same(1, $documentDestinations['count'] ?? null);
        $t->same(['legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(['FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Legacy Target Outline'], array_column($toc, 'title'));
        $t->same(['LegacySafe'], array_column($toc, 'destination'));
        $t->same([1], array_column($toc, 'page'));
        $t->same(['FitV'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Clean Target', 'Stale Tail Target', 'PrivateLimitTail', 'Clean Target Outline', 'Stale Tail Outline', 'FitH 111'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps tailed indirect destination Limits operands out of links and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationIndirectLimitsOperandBoundaryCurrentBasePdf, $namedDestinationIndirectLimitsOperandBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationIndirectLimitsOperandBoundaryCurrentBasePdf();
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [[], ['local-destination'], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([8, 9], array_column($links[0]['links'], 'annotation_object'));
        $t->same('LegacySafe', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('FitV', $links[0]['links'][0]['view_mode']);
        $t->same('https://example.com/named-destination-indirect-limits-tail', $links[0]['links'][1]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationIndirectLimitsOperandBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->true(!isset($spans[0]['link_destination']));
        $t->same('LegacySafe', $spans[1]['link_destination']);
        $t->same(1, $spans[1]['link_destination_page']);
        $t->same('FitV', $spans[1]['link_view_mode']);
        $t->same('https://example.com/named-destination-indirect-limits-tail', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Rejected named jump Legacy jump [Safe URI](https://example.com/named-destination-indirect-limits-tail)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Rejected named jump Legacy jump Safe URI', $plainText);
        $t->contains('Indirect limits destination target body', $plainText);
        foreach (['Clean Target', 'Stale Tail Target', 'PrivateLimitTail', 'Clean Target Outline', 'Stale Tail Outline'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'named-destination-indirect-limits-tail'));
    },
];
