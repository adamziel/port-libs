<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfActionReviewExtractor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefClassicRebuildActionTrailerCommentBoundaryCurrentBasePdf = static function (): array {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current comment trailer action docs) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset,
        $generation,
        $state
    );

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /OpenAction 8 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 276 718] /F 4 /Contents (Current comment trailer action review) /A 8 0 R /AA << /E 9 0 R >> >>');
    $addObject(8, 0, '<< /S /URI /URI (https://example.com/current-comment-trailer-action) >>');
    $addObject(9, 0, '<< /S /URI /URI (mailto:current-comment-trailer@example.test) >>');

    $currentXrefOffset = strlen($pdf);
    $commentTrailerOffset = $currentXrefOffset
        + strlen("xref\n")
        + strlen("0 6\n")
        + strlen($xrefRow(0, 65535, 'f'))
        + strlen($xrefRow($offsets['1:0:0']))
        + strlen($xrefRow($offsets['2:0:1']))
        + strlen($xrefRow($offsets['3:0:2']))
        + strlen($xrefRow($offsets['4:0:3']))
        + strlen($xrefRow($offsets['5:0:4']))
        + strlen('% ');
    $pdf .= "xref\n"
        . "0 6\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0:0'])
        . $xrefRow($offsets['2:0:1'])
        . $xrefRow($offsets['3:0:2'])
        . $xrefRow($offsets['4:0:3'])
        . $xrefRow($offsets['5:0:4'])
        . "% trailer << /Size 40 /Root 20 0 R /Prev 0 >>\n"
        . "7 3\n"
        . $xrefRow($offsets['7:0:5'])
        . $xrefRow($offsets['8:0:6'])
        . $xrefRow($offsets['9:0:7'])
        . "trailer\n<< /Size 40 /Root 1 0 R >>\n"
        . "startxref\n999999\n%%EOF\n";

    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 276 718] /F 4 /Contents (Stale comment trailer action review) /A 8 0 R /AA << /E 9 0 R >> >>');
    $addObject(8, 0, '<< /S /URI /URI (https://stale.example.com/comment-trailer-action-decoy) >>');
    $addObject(9, 0, '<< /S /JavaScript /JS (staleCommentTrailerAction\(\)) >>');

    return [$pdf, $currentXrefOffset, $commentTrailerOffset];
};

$xrefClassicRebuildActionTrailerCommentBoundaryCurrentBasePages = static fn (): array => [[
    'page' => 1,
    'blocks' => [[
        'type' => 'text',
        'lines' => [[
            'spans' => [[
                'text' => 'Current comment trailer action docs',
                'bbox' => [72.0, 700.0, 276.0, 718.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]];

return [
    'skips comment-contained trailer tokens while rebuilding classic xref rows before action review' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildActionTrailerCommentBoundaryCurrentBasePdf, $xrefClassicRebuildActionTrailerCommentBoundaryCurrentBasePages): void {
        [$pdf, $currentXrefOffset, $commentTrailerOffset] = $xrefClassicRebuildActionTrailerCommentBoundaryCurrentBasePdf();

        $textExtractor = new PdfTextExtractor();
        $t->same('Current comment trailer action docs', $textExtractor->extractPlainText($pdf));

        $actionReview = (new PdfActionReviewExtractor($pdf))->reviewAnnotationActions(
            '<< /A 8 0 R /AA << /E 9 0 R >> >>'
        );
        $encodedActionReview = json_encode($actionReview, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(str_contains($encodedActionReview, 'https://example.com/current-comment-trailer-action'));
        $t->true(str_contains($encodedActionReview, 'mailto:current-comment-trailer@example.test'));
        $t->true(!str_contains($encodedActionReview, 'https://stale.example.com/comment-trailer-action-decoy'));
        $t->true(!str_contains($encodedActionReview, 'staleCommentTrailerAction'));

        $annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $annotation = $annotationPages[0]['annotations'][0] ?? [];
        $t->same('https://example.com/current-comment-trailer-action', $annotation['actions'][0]['uri'] ?? null);
        $t->same('mailto:current-comment-trailer@example.test', $annotation['additional_actions'][0]['uri'] ?? null);

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same('https://example.com/current-comment-trailer-action', $links[0]['links'][0]['uri'] ?? null);
        $t->same('mailto:current-comment-trailer@example.test', $links[0]['links'][0]['additional_actions'][0]['uri'] ?? null);

        $linkedPages = $linkExtractor->applyLinksToPages($xrefClassicRebuildActionTrailerCommentBoundaryCurrentBasePages(), $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $t->same('https://example.com/current-comment-trailer-action', $span['link_uri']);
        $t->same('mailto:current-comment-trailer@example.test', $span['link_additional_actions_review'][0]['uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('[Current comment trailer action docs](https://example.com/current-comment-trailer-action)', $blocks[0]['text']);

        $encoded = json_encode([$annotationPages, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->true($currentXrefOffset > 0);
        $t->true($commentTrailerOffset > $currentXrefOffset);
        $t->true(str_contains($pdf, '% trailer << /Size 40 /Root 20 0 R /Prev 0 >>'));
        $t->true(str_contains($pdf, 'https://stale.example.com/comment-trailer-action-decoy'));
        $t->true(!str_contains($encoded, 'https://stale.example.com/comment-trailer-action-decoy'));
        $t->true(!str_contains($encoded, 'staleCommentTrailerAction'));
    },
];
