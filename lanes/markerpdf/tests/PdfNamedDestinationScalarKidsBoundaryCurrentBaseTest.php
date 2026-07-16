<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationScalarKidsBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Scalar kids jump Legacy jump Safe URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Scalar Kids destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 176 718] /Dest (Scalar Kids Target) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [186 700 268 718] /Dest /LegacyOk >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [278 700 350 718] /A << /S /URI /URI (https://example.com/scalar-kids-boundary) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Review Summary) (Scalar Kids Target)] /Kids /ScalarKid /Names [(Scalar Kids Target) [4 0 R /FitH 700] (Review Summary) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Scalar Kids Outline) /Parent 50 0 R /Dest (Scalar Kids Target) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Legacy Outline) /Parent 50 0 R /Dest /LegacyOk /Prev 51 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationScalarKidsBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 350.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 350.0, 718.0],
                'spans' => [
                    ['text' => 'Scalar kids jump', 'bbox' => [72.0, 700.0, 176.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Legacy jump', 'bbox' => [186.0, 700.0, 268.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [278.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects scalar Kids operands before named-destination metadata and outline review' => static function (
        TestRunner $t
    ) use ($namedDestinationScalarKidsBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationScalarKidsBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['LegacyOk'], array_column($destinations, 'name'));
        $t->same([1], array_column($destinations, 'page'));
        $t->same([4], array_column($destinations, 'page_object_id'));
        $t->same(['FitV'], array_column($destinations, 'fit'));
        $t->same(['legacy-dests'], array_column($destinations, 'source'));
        $t->same(['left' => 120.0], $destinations[0]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['LegacyOk'], $documentDestinations['names'] ?? null);
        $t->same(['legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(1, $documentDestinations['count'] ?? null);
        $t->same(['FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Legacy Outline'], array_column($toc, 'title'));
        $t->same(['LegacyOk'], array_column($toc, 'destination'));
        $t->same(['FitV'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Scalar Kids Target', 'Review Summary', 'Scalar Kids Outline', 'FitH', 'XYZ', '700', '640'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps scalar Kids name-tree rows out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationScalarKidsBoundaryCurrentBasePdf, $namedDestinationScalarKidsBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationScalarKidsBoundaryCurrentBasePdf();
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
        $t->same('LegacyOk', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('FitV', $links[0]['links'][0]['view_mode']);
        $t->same('https://example.com/scalar-kids-boundary', $links[0]['links'][1]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationScalarKidsBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->true(!isset($spans[0]['link_destination']));
        $t->same('LegacyOk', $spans[1]['link_destination']);
        $t->same(1, $spans[1]['link_destination_page']);
        $t->same('https://example.com/scalar-kids-boundary', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Scalar kids jump Legacy jump [Safe URI](https://example.com/scalar-kids-boundary)', $blocks[0]['text']);

        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Scalar kids jump Legacy jump Safe URI', $plainText);
        $t->contains('Scalar Kids destination target body', $plainText);
        foreach (['Scalar Kids Target', 'Review Summary', 'Scalar Kids Outline', 'FitH 700'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'scalar-kids-boundary'));
    },
];
