<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\NativePdfFactsProvider;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PdfReader;

/**
 * @param list<array{content:string,rotation?:int}> $pages
 */
$orientationRegionPdf = static function (array $pages): string {
    $pageObjects = [];
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
    ];
    $nextObject = 4;
    foreach ($pages as $page) {
        $pageObject = $nextObject++;
        $contentObject = $nextObject++;
        $pageObjects[] = $pageObject . ' 0 R';
        $rotation = isset($page['rotation']) ? ' /Rotate ' . (int) $page['rotation'] : '';
        $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 430 572]'
            . $rotation
            . ' /Resources << /Font << /F1 3 0 R >> >> /Contents '
            . $contentObject . ' 0 R >>';
        $content = $page['content'];
        $objects[$contentObject] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageObjects) . '] /Count ' . count($pages) . ' >>';
    ksort($objects, SORT_NUMERIC);

    $pdf = "%PDF-1.4\n";
    foreach ($objects as $number => $body) {
        $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
    }

    return $pdf . "%%EOF";
};

$horizontalBody = static function (string $first, string $second): string {
    return 'BT /F1 11 Tf '
        . '1 0 0 1 98 500 Tm (' . $first . ') Tj '
        . '1 0 0 1 98 478 Tm (' . $second . ') Tj ET';
};

$splitVerticalLabel = static function (
    int $x,
    array $fragments,
    int $startY = 430,
    int $minimumStep = 14
): string {
    $operators = ['BT /F1 8 Tf'];
    $y = $startY;
    foreach ($fragments as $fragment) {
        $operators[] = '0 1 -1 0 ' . $x . ' ' . $y . ' Tm (' . $fragment . ') Tj';
        $y += max($minimumStep, strlen($fragment) * 5);
    }
    $operators[] = 'ET';

    return implode(' ', $operators);
};

$sequentialVerticalLabel = static function (int $x, array $fragments, int $startY = 40): string {
    $operators = ['BT /F1 8 Tf 0 1 -1 0 ' . $x . ' ' . $startY . ' Tm'];
    foreach ($fragments as $fragment) {
        $operators[] = '(' . $fragment . ') Tj';
    }
    $operators[] = 'ET';

    return implode(' ', $operators);
};

return [
    'preserves native text progression orientation in durable positioned facts' => static function (
        TestRunner $t
    ) use ($orientationRegionPdf): void {
        $pdf = $orientationRegionPdf([[
            'content' => 'BT /F1 12 Tf 1 0 0 1 98 500 Tm (Horizontal) Tj '
                . '0 1 -1 0 24 430 Tm (Vertical) Tj ET',
        ]]);
        $spans = (new NativePdfFactsProvider())->extract($pdf)->page(1)?->text()['spans'] ?? [];

        $t->same(2, count($spans));
        $t->same(0.0, $spans[0]['rotation'] ?? null);
        $t->same(1.0, $spans[0]['axisX'] ?? null);
        $t->same(0.0, $spans[0]['axisY'] ?? null);
        $t->same(90.0, $spans[1]['rotation'] ?? null);
        $t->same(0.0, $spans[1]['axisX'] ?? null);
        $t->same(1.0, $spans[1]['axisY'] ?? null);
    },
    'removes repeated split vertical edge furniture without injecting it into body lines' => static function (
        TestRunner $t
    ) use ($orientationRegionPdf, $horizontalBody, $splitVerticalLabel): void {
        $pages = [];
        foreach ([1, 2] as $pageNumber) {
            $pages[] = [
                'content' => $splitVerticalLabel(24, ['RUN', 'NING', ' TITLE', ' ' . $pageNumber])
                    . ' ' . $horizontalBody(
                        'The first ordinary sentence remains intact.',
                        'The second ordinary sentence remains intact.'
                    ),
            ];
        }
        $document = (new PdfReader(['pdfRepairProseText' => true]))->read($orientationRegionPdf($pages));
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta', []);

        $t->same(2, substr_count($blocks, 'The first ordinary sentence remains intact.'));
        $t->same(2, substr_count($blocks, 'The second ordinary sentence remains intact.'));
        $t->true(!str_contains($blocks, 'RUN'));
        $t->true(!str_contains($blocks, 'NING'));
        $t->same(2, $meta['pdfRunningFurnitureRegionsRemoved'] ?? null);
    },
    'preserves one-off rotated edge content once and after the horizontal body' => static function (
        TestRunner $t
    ) use ($orientationRegionPdf, $horizontalBody, $sequentialVerticalLabel): void {
        $rotatedMessage = 'CAUTION READ THIS UNIQUE ROTATED MESSAGE FIRST';
        $rotatedFragments = [
            'C', 'A', 'U', 'T', 'I', 'O', 'N', ' R', 'E', 'A', 'D', ' T', 'H', 'I', 'S',
            ' U', 'N', 'I', 'Q', 'U', 'E', ' R', 'O', 'T', 'A', 'T', 'E', 'D', ' M', 'E',
            'S', 'S', 'A', 'G', 'E', ' F', 'I', 'R', 'S', 'T',
        ];
        $pdf = $orientationRegionPdf([[
            'content' => $sequentialVerticalLabel(24, $rotatedFragments)
                . ' ' . $horizontalBody(
                    'The body starts with a complete sentence.',
                    'The body ends with another complete sentence.'
                ),
        ]]);
        $document = (new PdfReader(['pdfRepairProseText' => true]))->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta', []);

        $t->same(1, substr_count($blocks, $rotatedMessage));
        $t->true(strpos($blocks, 'The body ends with another complete sentence.') < strpos($blocks, $rotatedMessage));
        $t->same(1, $meta['pdfRotatedRegionsPreserved'] ?? null);
        $t->same(0, $meta['pdfRunningFurnitureRegionsRemoved'] ?? null);
        $t->true(!str_contains($blocks, '<pre'));
    },
    'keeps a fully rotated document flow as content rather than edge furniture' => static function (
        TestRunner $t
    ) use ($orientationRegionPdf): void {
        $pdf = $orientationRegionPdf([[
            'content' => 'BT /F1 11 Tf '
                . '0 1 -1 0 320 120 Tm (Rotated opening sentence.) Tj '
                . '0 1 -1 0 298 120 Tm (Rotated closing sentence.) Tj ET',
        ]]);
        $document = (new PdfReader(['pdfRepairProseText' => true]))->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('Rotated opening sentence.', $blocks);
        $t->contains('Rotated closing sentence.', $blocks);
        $t->true(strpos($blocks, 'Rotated opening sentence.') < strpos($blocks, 'Rotated closing sentence.'));
    },
    'normalizes text orientation through an inherited page rotation' => static function (
        TestRunner $t
    ) use ($orientationRegionPdf): void {
        $pdf = $orientationRegionPdf([[
            'rotation' => 90,
            'content' => 'BT /F1 11 Tf 0 -1 1 0 120 500 Tm (Displayed horizontal body.) Tj ET',
        ]]);
        $spans = (new NativePdfFactsProvider())->extract($pdf)->page(1)?->text()['spans'] ?? [];
        $document = (new PdfReader(['pdfRepairProseText' => true]))->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');

        $t->same(0.0, $spans[0]['rotation'] ?? null);
        $t->contains('Displayed horizontal body.', $blocks);
    },
];
