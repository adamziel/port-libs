<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainIncrementalActionInheritedTrailerPdf = static function (): array {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale inherited action link) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current inherited action docs) Tj ET';

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleAnnotationOffset = $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 304 718] /F 4 /Contents (Inherited trailer action link) /A 8 0 R /AA << /E 9 0 R >> >>');
    $staleActionOffset = $addObject(8, 0, '<< /S /URI /URI (https://example.com/stale-inherited-action) >>');
    $staleAdditionalActionOffset = $addObject(9, 0, '<< /S /JavaScript /JS (staleInheritedTrailerAction()) >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 10\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($staleCatalogOffset)
        . $xrefTableRow($stalePagesOffset)
        . $xrefTableRow($stalePageOffset)
        . $xrefTableRow($staleContentOffset)
        . $xrefTableRow($fontOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($staleAnnotationOffset)
        . $xrefTableRow($staleActionOffset)
        . $xrefTableRow($staleAdditionalActionOffset)
        . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $currentAnnotationOffset = $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 304 718] /F 4 /Contents (Inherited trailer action link) /A 8 0 R /AA << /E 9 0 R >> >>');
    $currentActionOffset = $addObject(8, 0, '<< /S /URI /URI (https://example.com/current-inherited-action) >>');
    $currentAdditionalActionOffset = $addObject(9, 0, '<< /S /URI /URI (mailto:current-inherited-action@example.test) >>');

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "5 1\n"
        . $xrefTableRow($fontOffset)
        . "trailer\n<< /Size 21 /Prev {$previousXrefOffset} >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return [
        'pdf' => $pdf,
        'previousXrefOffset' => $previousXrefOffset,
        'currentXrefOffset' => $currentXrefOffset,
        'currentOffsets' => [
            'catalog' => $currentCatalogOffset,
            'pages' => $currentPagesOffset,
            'page' => $currentPageOffset,
            'content' => $currentContentOffset,
            'annotation' => $currentAnnotationOffset,
            'action' => $currentActionOffset,
            'additional_action' => $currentAdditionalActionOffset,
        ],
    ];
};

$xrefPrevChainIncrementalActionInheritedTrailerPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 304.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 304.0, 718.0],
                'spans' => [[
                    'text' => 'Current inherited action docs',
                    'bbox' => [72.0, 700.0, 304.0, 718.0],
                    'font' => 'Helvetica',
                ]],
            ]],
        ]],
    ]];
};

return [
    'repairs action review graph through inherited Prev trailer references in sparse classic updates' => static function (
        TestRunner $t
    ) use ($xrefPrevChainIncrementalActionInheritedTrailerPdf, $xrefPrevChainIncrementalActionInheritedTrailerPages): void {
        $fixture = $xrefPrevChainIncrementalActionInheritedTrailerPdf();
        $pdf = $fixture['pdf'];
        $latestTrailer = substr($pdf, $fixture['currentXrefOffset']);

        $t->same(['Current inherited action docs'], (new PdfTextExtractor())->extractTextLines($pdf));
        $t->true($fixture['previousXrefOffset'] < $fixture['currentXrefOffset']);
        $t->true(str_contains($latestTrailer, '/Prev '));
        $t->true(!str_contains($latestTrailer, '/Root '));
        $t->same(1, substr_count($latestTrailer, "\n5 1\n"));
        $t->true($fixture['currentOffsets']['action'] > $fixture['previousXrefOffset']);
        $t->true($fixture['currentOffsets']['additional_action'] > $fixture['previousXrefOffset']);

        $annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotationPages));
        $annotation = $annotationPages[0]['annotations'][0] ?? [];
        $t->same(7, $annotation['annotation_object'] ?? null);
        $t->same('https://example.com/current-inherited-action', $annotation['actions'][0]['uri'] ?? null);
        $t->same('mailto:current-inherited-action@example.test', $annotation['additional_actions'][0]['uri'] ?? null);

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same(1, count($links[0]['links'] ?? []));
        $t->same('https://example.com/current-inherited-action', $links[0]['links'][0]['uri'] ?? null);
        $t->same('mailto:current-inherited-action@example.test', $links[0]['links'][0]['additional_actions'][0]['uri'] ?? null);

        $linkedPages = $linkExtractor->applyLinksToPages($xrefPrevChainIncrementalActionInheritedTrailerPages(), $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $t->same('https://example.com/current-inherited-action', $span['link_uri']);
        $t->same('mailto:current-inherited-action@example.test', $span['link_additional_actions_review'][0]['uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('[Current inherited action docs](https://example.com/current-inherited-action)', $blocks[0]['text']);

        $encoded = json_encode([$annotationPages, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'stale-inherited-action'));
        $t->true(!str_contains($encoded, 'staleInheritedTrailerAction'));
        $t->true(!str_contains($encoded, 'Stale inherited action link'));
    },
];
