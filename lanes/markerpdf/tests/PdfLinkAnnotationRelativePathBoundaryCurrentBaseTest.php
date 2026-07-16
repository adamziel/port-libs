<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkRelativePathBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Guide link Query link Parent file Network path Backslash path) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 148 718] /Contents (Guide path review) /A << /S /URI /URI (guide.html#setup) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [158 700 238 718] /Contents (Query path review) /A << /S /URI /URI (?download=1) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [248 700 332 718] /Contents (Parent path review) /A << /S /URI /URI (../media/spec.pdf#attachment) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [342 700 432 718] /Contents (Network path review) /A << /S /URI /URI (//evil.example/import.pdf) >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [442 700 532 718] /Contents (Backslash path review) /A << /S /URI /URI (..\\\\evil\\\\import.pdf) >> >>\nendobj\n"
        . "%%EOF";
};

$linkRelativePathBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 532.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 532.0, 718.0],
                'spans' => [
                    ['text' => 'Guide link', 'bbox' => [72.0, 700.0, 148.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Query link', 'bbox' => [158.0, 700.0, 238.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Parent file', 'bbox' => [248.0, 700.0, 332.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Network path', 'bbox' => [342.0, 700.0, 432.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Backslash path', 'bbox' => [442.0, 700.0, 532.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'promotes safe path-relative Link annotation URIs without a catalog base' => static function (TestRunner $t) use (
        $linkRelativePathBoundaryPdf,
        $linkRelativePathBoundaryPages
    ): void {
        $pdf = $linkRelativePathBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10, 11], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same([
            'review-uri',
            'review-uri',
            'review-uri',
            'blocked-unsafe-uri',
            'blocked-unsafe-uri',
        ], array_map(
            static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
            $annotations[0]['annotations']
        ));
        $t->same([true, true, true, false, true], array_map(
            static fn (array $annotation): bool => (bool) ($annotation['actions'][0]['uri_relative'] ?? false),
            $annotations[0]['annotations']
        ));
        $t->same(false, str_contains($encoded($annotations), 'uri_base'));

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8, 9], array_column($links[0]['links'], 'annotation_object'), 'Network-path and backslash URI references stay review-only.');
        $t->same([
            'guide.html#setup',
            '?download=1',
            '../media/spec.pdf#attachment',
        ], array_column($links[0]['links'], 'uri'));
        $t->same([true, true, true], array_column($links[0]['links'], 'uri_relative'));
        $t->same([false, false, false], array_column($links[0]['links'], 'uri_resolved_from_base'));
        $t->same(false, str_contains($encoded($links), 'evil.example'));
        $t->same(false, str_contains($encoded($links), '..\\\\evil'));

        $pages = $extractor->applyLinksToPages($linkRelativePathBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('guide.html#setup', $spans[0]['link_uri']);
        $t->same(true, $spans[0]['link_uri_relative']);
        $t->same(false, $spans[0]['link_uri_resolved_from_base']);
        $t->same('?download=1', $spans[1]['link_uri']);
        $t->same('../media/spec.pdf#attachment', $spans[2]['link_uri']);
        $t->true(!isset($spans[3]['link_uri']));
        $t->true(!isset($spans[3]['link_actions_review']));
        $t->true(!isset($spans[4]['link_uri']));
        $t->true(!isset($spans[4]['link_actions_review']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            '[Guide link](guide.html\\#setup) [Query link](?download=1) [Parent file](../media/spec.pdf\\#attachment) Network path Backslash path',
            $blocks[0]['text']
        );

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Guide link Query link Parent file Network path Backslash path', $plainText);
        foreach ([
            'guide.html',
            'download=1',
            'media/spec.pdf',
            'evil.example',
            'Guide path review',
            'Query path review',
            'Parent path review',
            'Network path review',
            'Backslash path review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
