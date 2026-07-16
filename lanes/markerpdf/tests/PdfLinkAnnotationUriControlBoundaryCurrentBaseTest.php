<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkUriControlBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Clean docs Control docs Mail control Relative control) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Clean docs link) /A << /S /URI /URI (https://example.com/clean-docs) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 250 718] /Contents (Control newline link) /A << /S /URI /URI (https://example.com/control\\njavascript:alert\\(1\\)) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 700 350 718] /Contents (Control tab mail link) /A << /S /URI /URI (mailto:import@example.com\\tjavascript:alert\\(2\\)) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [360 700 470 718] /Contents (Control relative link) /A << /S /URI /URI (/wp-content/uploads/file.pdf\\rjavascript:alert\\(3\\)) >> >>\nendobj\n"
        . "%%EOF";
};

$linkUriControlBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 470.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 470.0, 718.0],
                'spans' => [
                    ['text' => 'Clean docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Control docs', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Mail control', 'bbox' => [260.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Relative control', 'bbox' => [360.0, 700.0, 470.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'keeps Link annotation URIs with control bytes review-only before WordPress span promotion' => static function (TestRunner $t) use (
        $linkUriControlBoundaryPdf,
        $linkUriControlBoundaryPages
    ): void {
        $pdf = $linkUriControlBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['review-uri', 'blocked-unsafe-uri', 'blocked-unsafe-uri', 'blocked-unsafe-uri'], array_map(
            static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
            $annotations[0]['annotations']
        ));
        $t->same(false, $annotations[0]['annotations'][1]['actions'][0]['is_safe_uri']);
        $t->same(false, $annotations[0]['annotations'][2]['actions'][0]['is_safe_uri']);
        $t->same(false, $annotations[0]['annotations'][3]['actions'][0]['is_safe_uri']);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'), 'Only the clean URI is promoted as a WordPress link.');
        $t->same('https://example.com/clean-docs', $links[0]['links'][0]['uri']);

        $pages = $extractor->applyLinksToPages($linkUriControlBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/clean-docs', $spans[0]['link_uri']);
        foreach ([1, 2, 3] as $spanIndex) {
            $t->true(!isset($spans[$spanIndex]['link_uri']));
            $t->true(!isset($spans[$spanIndex]['link_actions_review']));
        }

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Clean docs](https://example.com/clean-docs) Control docs Mail control Relative control', $blocks[0]['text']);
        $t->same(false, str_contains($blocks[0]['text'], "javascript:alert"));
        $t->same(false, str_contains($blocks[0]['text'], "\n"));
        $t->same(false, str_contains($blocks[0]['text'], "\r"));
        $t->same(false, str_contains($blocks[0]['text'], "\t"));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Clean docs Control docs Mail control Relative control', $plainText);
        foreach (['clean-docs', 'javascript:alert', 'Control newline link', 'Control tab mail link', 'Control relative link'] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
