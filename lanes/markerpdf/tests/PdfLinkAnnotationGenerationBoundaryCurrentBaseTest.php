<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$generationBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Current docs Exact jump Stale decoy) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Exact generation destination) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 13 1 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots 6 1 R /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 16 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 1 obj\n[7 1 R 8 1 R]\nendobj\n"
        . "7 1 obj\n<< /Type /Annot /Subtype /Link /Rect 40 1 R /F 41 1 R /A 30 1 R /AA << /E 31 1 R >> >>\nendobj\n"
        . "8 1 obj\n<< /Type /Annot /Subtype /Link /Rect [166 700 252 718] /Dest 44 1 R >>\nendobj\n"
        . "13 1 obj\n<< /Names [(exact-target) 17 1 R] >>\nendobj\n"
        . "16 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "17 1 obj\n[4 0 R /FitH 720]\nendobj\n"
        . "30 1 obj\n<< /S /URI /URI (https://example.com/current-generation-link) /Next 32 1 R >>\nendobj\n"
        . "31 1 obj\n<< /S /URI /URI (mailto:current-generation@example.test) >>\nendobj\n"
        . "32 1 obj\n<< /S /GoTo /D (exact-target) >>\nendobj\n"
        . "40 1 obj\n[72 700 158 718]\nendobj\n"
        . "41 1 obj\n4\nendobj\n"
        . "44 1 obj\n[4 0 R /XYZ 36 700 0]\nendobj\n"
        . "6 0 obj\n[9 0 R]\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 640 158 658] /F 4 /A 30 0 R /AA << /E 31 0 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [166 640 252 658] /Dest 44 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 700 342 718] /A << /S /URI /URI (https://example.com/stale-array-link) >> >>\nendobj\n"
        . "13 0 obj\n<< /Names [(exact-target) 18 0 R] >>\nendobj\n"
        . "18 0 obj\n[3 0 R /Fit]\nendobj\n"
        . "30 0 obj\n<< /S /URI /URI (https://example.com/stale-generation-link) /Next 32 0 R >>\nendobj\n"
        . "31 0 obj\n<< /S /JavaScript /JS (staleHoverLeak\\(\\)) >>\nendobj\n"
        . "32 0 obj\n<< /S /GoTo /D (stale-target) >>\nendobj\n"
        . "40 0 obj\n[72 640 158 658]\nendobj\n"
        . "41 0 obj\n2\nendobj\n"
        . "44 0 obj\n[3 0 R /Fit]\nendobj\n"
        . "%%EOF";
};

$mismatchedGenerationPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Generation mismatch page text) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 1 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 210 718] /A << /S /URI /URI (https://example.com/stale-mismatch-link) >> >>\nendobj\n"
        . "%%EOF";
};

$generationBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 342.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 342.0, 718.0],
                'spans' => [
                    ['text' => 'Current docs', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Exact jump', 'bbox' => [166.0, 700.0, 252.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale decoy', 'bbox' => [260.0, 700.0, 342.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'keeps link annotation object generations exact before WordPress span promotion' => static function (TestRunner $t) use (
        $generationBoundaryPdf,
        $mismatchedGenerationPdf,
        $generationBoundaryPages
    ): void {
        $pdf = $generationBoundaryPdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(2, count($links[0]['links']), 'Only generation-one annotations from the generation-one /Annots array are promoted.');

        $uriLink = $links[0]['links'][0];
        $t->same(7, $uriLink['annotation_object']);
        $t->same([72.0, 700.0, 158.0, 718.0], $uriLink['rect']);
        $t->same('https://example.com/current-generation-link', $uriLink['uri']);
        $t->same(['review-uri', 'local-destination'], array_column($uriLink['actions'], 'safety'));
        $t->same('exact-target', $uriLink['actions'][1]['destination']);
        $t->same(1, $uriLink['actions'][1]['destination_page']);
        $t->same(['E'], array_column($uriLink['additional_actions'], 'event'));
        $t->same('mailto:current-generation@example.test', $uriLink['additional_actions'][0]['uri']);

        $destinationLink = $links[0]['links'][1];
        $t->same(8, $destinationLink['annotation_object']);
        $t->same('local-destination', $destinationLink['safety']);
        $t->same(1, $destinationLink['destination_page']);
        $t->same('XYZ', $destinationLink['view_mode']);
        $t->same(['left' => 36.0, 'top' => 700.0, 'zoom' => null], $destinationLink['view_parameters']);

        $linkedPages = $extractor->applyLinksToPages($generationBoundaryPages(), $pdf);
        $spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/current-generation-link', $spans[0]['link_uri']);
        $t->same('exact-target', $spans[0]['link_actions_review'][1]['destination']);
        $t->same(1, $spans[1]['link_destination_page']);
        $t->same('XYZ', $spans[1]['link_view_mode']);
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['link_destination_page']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('[Current docs](https://example.com/current-generation-link) Exact jump Stale decoy', $blocks[0]['text']);

        $encodedLinks = json_encode($linkedPages, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encodedLinks, 'stale-array-link'));
        $t->true(!str_contains($encodedLinks, 'stale-generation-link'));
        $t->true(!str_contains($encodedLinks, 'staleHoverLeak'));
        $t->true(!str_contains($encodedLinks, 'stale-target'));

        $mismatchLinks = $extractor->extractPageLinks($mismatchedGenerationPdf());
        $t->same([], $mismatchLinks, 'A stale generation-zero annotation cannot satisfy a page /Annots 7 1 R reference.');

        $textExtractor = new PdfTextExtractor();
        $plainText = $textExtractor->extractPlainText($pdf);
        $mismatchPlainText = $textExtractor->extractPlainText($mismatchedGenerationPdf());
        $t->contains('Current docs Exact jump Stale decoy', $plainText);
        $t->contains('Generation mismatch page text', $mismatchPlainText);
        $t->true(!str_contains($plainText . $mismatchPlainText, 'stale-mismatch-link'));
        $t->true(!str_contains($plainText . $mismatchPlainText, 'current-generation-link'));
    },
];
