<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationRootLimitsBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Current root-limit jump Decoy root-limit jump Safe URI) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Root-limit destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOnly [4 0 R /FitV 144] >> /Outlines 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 214 718] /Dest (Alpha Start) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [224 700 368 718] /Dest (Zulu Decoy) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [378 700 450 718] /A << /S /URI /URI (https://example.com/root-limit-safe-uri) >> >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Zulu Decoy) (Alpha Start)] /Names [(Alpha Start) [4 0 R /FitH 700] (Review Summary) [4 0 R /XYZ 72 640 0] (Zulu Decoy) [3 0 R /FitH 111]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Alpha Root Limit Outline) /Parent 50 0 R /Dest (Alpha Start) /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Zulu Root Limit Outline) /Parent 50 0 R /Dest (Zulu Decoy) /Prev 51 0 R >>\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationRootLimitsBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 450.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 450.0, 718.0],
                'spans' => [
                    ['text' => 'Current root-limit jump', 'bbox' => [72.0, 700.0, 214.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Decoy root-limit jump', 'bbox' => [224.0, 700.0, 368.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [378.0, 700.0, 450.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects reversed root destination Limits before WordPress destination metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationRootLimitsBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationRootLimitsBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['LegacyOnly'], array_column($destinations, 'name'));
        $t->same([1], array_column($destinations, 'page'));
        $t->same([4], array_column($destinations, 'page_object_id'));
        $t->same(['FitV'], array_column($destinations, 'fit'));
        $t->same(['legacy-dests'], array_column($destinations, 'source'));
        $t->same(['left' => 144.0], $destinations[0]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['LegacyOnly'], $documentDestinations['names'] ?? null);
        $t->same(['legacy_dests'], $documentDestinations['source'] ?? null);
        $t->same(1, $documentDestinations['count'] ?? null);
        $t->same(['FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $t->same([], $toc);

        $encoded = json_encode([$destinations, $documentDestinations, $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Alpha Start', 'Review Summary', 'Zulu Decoy', 'Alpha Root Limit Outline', 'FitH 111'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps reversed-root destination names out of link promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationRootLimitsBoundaryCurrentBasePdf, $namedDestinationRootLimitsBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationRootLimitsBoundaryCurrentBasePdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same([9], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/root-limit-safe-uri', $links[0]['links'][0]['uri']);

        $linkedPages = $extractor->applyLinksToPages($namedDestinationRootLimitsBoundaryCurrentBasePages(), $pdf);
        $spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->true(!isset($spans[0]['link_destination']));
        $t->true(!isset($spans[1]['link_destination']));
        $t->same('https://example.com/root-limit-safe-uri', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('Current root-limit jump Decoy root-limit jump [Safe URI](https://example.com/root-limit-safe-uri)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Current root-limit jump Decoy root-limit jump Safe URI', $plainText);
        $t->contains('Root-limit destination target body', $plainText);
        foreach (['Alpha Start', 'Review Summary', 'Zulu Decoy', 'Alpha Root Limit Outline', 'root-limit-safe-uri'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
        foreach (['Alpha Start', 'Review Summary', 'Zulu Decoy', 'FitH 111'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
];
