<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefClassicRebuildActionReviewBoundaryCurrentBasePdf = static function (): array {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current action docs) Tj ET';

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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 218 718] /F 4 /Contents (Current action review) /A 8 0 R /AA << /E 9 0 R >> >>');
    $addObject(8, 0, '<< /S /URI /URI (https://example.com/current-classic-rebuild-action) >>');
    $addObject(9, 0, '<< /S /URI /URI (mailto:current-classic-rebuild@example.test) >>');

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 10\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0:0'])
        . $xrefRow($offsets['2:0:1'])
        . $xrefRow($offsets['3:0:2'])
        . $xrefRow($offsets['4:0:3'])
        . $xrefRow($offsets['5:0:4'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['7:0:5'])
        . $xrefRow($offsets['8:0:6'])
        . $xrefRow($offsets['9:0:7'])
        . "trailer\n<< /Size 10 /Root 1 0 R >>\n";

    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 218 718] /F 4 /Contents (Stale decoy action review) /A 8 0 R /AA << /E 9 0 R >> >>');
    $addObject(8, 0, '<< /S /URI /URI (https://stale.example.com/classic-rebuild-action-decoy) >>');
    $addObject(9, 0, '<< /S /JavaScript /JS (staleClassicRebuildAction\(\)) >>');
    $pdf .= "startxref\n999999\n%%EOF";

    return [$pdf, $currentXrefOffset];
};

$xrefClassicRebuildActionReviewBoundaryCurrentBasePages = static fn (): array => [[
    'page' => 1,
    'blocks' => [[
        'type' => 'text',
        'lines' => [[
            'spans' => [[
                'text' => 'Current action docs',
                'bbox' => [72.0, 700.0, 218.0, 718.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]];

return [
    'rebuilds damaged classic startxref before annotation action review and WordPress link promotion' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildActionReviewBoundaryCurrentBasePdf, $xrefClassicRebuildActionReviewBoundaryCurrentBasePages): void {
        [$pdf, $currentXrefOffset] = $xrefClassicRebuildActionReviewBoundaryCurrentBasePdf();

        $textExtractor = new PdfTextExtractor();
        $t->same('Current action docs', $textExtractor->extractPlainText($pdf));

        $annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotationPages));
        $t->same(1, count($annotationPages[0]['annotations']));
        $annotation = $annotationPages[0]['annotations'][0];
        $t->same(7, $annotation['annotation_object']);
        $t->same('https://example.com/current-classic-rebuild-action', $annotation['actions'][0]['uri']);
        $t->same('URI', $annotation['additional_actions'][0]['action_type']);
        $t->same('mailto:current-classic-rebuild@example.test', $annotation['additional_actions'][0]['uri']);

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same(1, count($links[0]['links']));
        $t->same(7, $links[0]['links'][0]['annotation_object']);
        $t->same('https://example.com/current-classic-rebuild-action', $links[0]['links'][0]['uri']);
        $t->same('mailto:current-classic-rebuild@example.test', $links[0]['links'][0]['additional_actions'][0]['uri']);

        $linkedPages = $linkExtractor->applyLinksToPages($xrefClassicRebuildActionReviewBoundaryCurrentBasePages(), $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $t->same('https://example.com/current-classic-rebuild-action', $span['link_uri']);
        $t->same('mailto:current-classic-rebuild@example.test', $span['link_additional_actions_review'][0]['uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('[Current action docs](https://example.com/current-classic-rebuild-action)', $blocks[0]['text']);

        $encoded = json_encode([$annotationPages, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(str_contains($pdf, 'https://stale.example.com/classic-rebuild-action-decoy'));
        $t->true(!str_contains($encoded, 'https://stale.example.com/classic-rebuild-action-decoy'));
        $t->true(!str_contains($encoded, 'staleClassicRebuildAction'));
        $t->true(str_contains($pdf, "startxref\n999999"));
        $t->true(str_contains($pdf, "xref\n0 10"));
        $t->true($currentXrefOffset > 0);
    },
];
