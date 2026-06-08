<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationInvalidKidOrderBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Invalid kid stale jump Direct kid jump Safe URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Invalid kid current destination body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyTail [4 0 R /FitV 144] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 210 718] /Dest (DuplicateReview) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [220 700 320 718] /Dest (Direct Kid Decoy) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [330 700 402 718] /A << /S /URI /URI (https://example.com/invalid-kid-order) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(A Broad) (DuplicateReview)] /Kids [21 0 R null 22 0 R << /Limits [(Direct Kid Decoy) (Direct Kid Decoy)] /Names [(Direct Kid Decoy) [3 0 R /FitH 333]] >> /ScalarKid] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [(DuplicateReview) (DuplicateReview)] /Names [(DuplicateReview) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [(A Broad) (DuplicateReview)] /Names [(A Broad) [3 0 R /Fit] (DuplicateReview) [3 0 R /FitH 111]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Duplicate current outline) /Parent 50 0 R /Dest (DuplicateReview) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Direct kid decoy outline) /Parent 50 0 R /Dest (Direct Kid Decoy) /Prev 51 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationInvalidKidOrderBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 402.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 402.0, 718.0],
                'spans' => [
                    ['text' => 'Invalid kid stale jump', 'bbox' => [72.0, 700.0, 210.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Direct kid jump', 'bbox' => [220.0, 700.0, 320.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [330.0, 700.0, 402.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'orders valid bounded destination kids even when malformed Kids entries are present' => static function (
        TestRunner $t
    ) use ($namedDestinationInvalidKidOrderBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationInvalidKidOrderBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['A Broad', 'DuplicateReview', 'LegacyTail'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['Fit', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same([], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 144.0], $destinations[2]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['A Broad', 'DuplicateReview', 'LegacyTail'], $documentDestinations['names'] ?? null);
        $t->same([0, 1, 1], array_column($documentDestinations['destinations'] ?? [], 'page'));
        $t->same(['Fit', 'XYZ', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same(['Duplicate current outline'], array_column($toc, 'title'));
        $t->same(['DuplicateReview'], array_column($toc, 'destination'));
        $t->same([1], array_column($toc, 'page'));
        $t->same(['XYZ'], array_column($toc, 'view_mode'));

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Direct Kid Decoy', 'Direct kid decoy outline', 'FitH 111', 'FitH 333'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps invalid-Kids destination rows out of annotation promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationInvalidKidOrderBoundaryCurrentBasePdf, $namedDestinationInvalidKidOrderBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationInvalidKidOrderBoundaryCurrentBasePdf();
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], [], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            )
        );

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 9], array_column($links[0]['links'], 'annotation_object'));
        $t->same('DuplicateReview', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('XYZ', $links[0]['links'][0]['view_mode']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => null], $links[0]['links'][0]['view_parameters']);
        $t->same('https://example.com/invalid-kid-order', $links[0]['links'][1]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationInvalidKidOrderBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('DuplicateReview', $spans[0]['link_destination']);
        $t->same(1, $spans[0]['link_destination_page']);
        $t->same('XYZ', $spans[0]['link_view_mode']);
        $t->true(!isset($spans[1]['link_destination']));
        $t->same('https://example.com/invalid-kid-order', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Invalid kid stale jump Direct kid jump [Safe URI](https://example.com/invalid-kid-order)', $blocks[0]['text']);

        $encoded = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Invalid kid stale jump Direct kid jump Safe URI', $plainText);
        $t->contains('Invalid kid current destination body', $plainText);
        foreach ([
            'A Broad',
            'DuplicateReview',
            'Direct Kid Decoy',
            'Duplicate current outline',
            'invalid-kid-order',
        ] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
        foreach (['Direct Kid Decoy', 'FitH 111', 'FitH 333'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
];
