<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationAliasPageOperandBoundaryCurrentBasePdf = static function (): string {
    $sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Bad dest Bad action Real target Safe URI) Tj ET';
    $targetPageContent = 'BT /F1 12 Tf 72 720 Td (Alias page operand target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 11 0 R /Last 12 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 140 718] /Dest (Alias Page Operand) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [150 700 226 718] /A << /S /GoTo /D (Alias Page Operand) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [236 700 316 718] /Dest (Real Target) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [326 700 390 718] /A << /S /URI /URI (https://example.com/named-destination-alias-page-operand) >> >>\nendobj\n"
        . "11 0 obj\n<< /Title (Bad Alias Page Operand Chapter) /Parent 5 0 R /Dest (Alias Page Operand) /Next 12 0 R >>\nendobj\n"
        . "12 0 obj\n<< /Title (Real Target Chapter) /Parent 5 0 R /Prev 11 0 R /Dest (Real Target) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(Real Target) [4 0 R /XYZ 72 640 0] (Alias Page Operand) [/Real#20Target /FitH 111]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
        . "%%EOF\n";
};

$namedDestinationAliasPageOperandBoundaryCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 390.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 390.0, 718.0],
                'spans' => [
                    ['text' => 'Bad dest', 'bbox' => [72.0, 700.0, 140.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Bad action', 'bbox' => [150.0, 700.0, 226.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Real target', 'bbox' => [236.0, 700.0, 316.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [326.0, 700.0, 390.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects explicit named-destination arrays that use a destination alias as the page operand' => static function (
        TestRunner $t
    ) use ($namedDestinationAliasPageOperandBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationAliasPageOperandBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Real Target'], array_column($destinations, 'name'));
        $t->same([1], array_column($destinations, 'page'));
        $t->same([4], array_column($destinations, 'page_object_id'));
        $t->same(['XYZ'], array_column($destinations, 'fit'));
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[0]['coordinates']);

        $t->same(['Real Target'], $metadata['document_destinations']['names'] ?? null);
        $t->same(1, $metadata['document_destinations']['count'] ?? null);
        $t->same(1, $metadata['document_destinations']['unresolved_count'] ?? null);
        $t->same(['Real Target Chapter'], array_column($outline, 'title'));
        $t->same([1], array_column($outline, 'page'));
    },
    'keeps alias page operands out of annotation link promotion and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationAliasPageOperandBoundaryCurrentBasePdf, $namedDestinationAliasPageOperandBoundaryCurrentBasePages): void {
        $pdf = $namedDestinationAliasPageOperandBoundaryCurrentBasePdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same([9, 10], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Real Target', $links[0]['links'][0]['destination']);
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('XYZ', $links[0]['links'][0]['view_mode']);
        $t->same('https://example.com/named-destination-alias-page-operand', $links[0]['links'][1]['uri']);

        $pages = $linkExtractor->applyLinksToPages($namedDestinationAliasPageOperandBoundaryCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->true(!isset($spans[0]['link_destination']));
        $t->true(!isset($spans[1]['link_destination']));
        $t->same('Real Target', $spans[2]['link_destination']);
        $t->same('https://example.com/named-destination-alias-page-operand', $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Bad dest Bad action Real target [Safe URI](https://example.com/named-destination-alias-page-operand)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Bad dest Bad action Real target Safe URI', $plainText);
        $t->contains('Alias page operand target body', $plainText);
        foreach (['Alias Page Operand', 'Bad Alias Page Operand Chapter', 'FitH', '111'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($plainText, 'named-destination-alias-page-operand'));
    },
];
