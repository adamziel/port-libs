<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceEntryCommentBoundaryCMap = static function (array $entries): string {
    $body = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . count($entries) . " beginbfchar\n";

    foreach ($entries as $sourceHex => $text) {
        $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', (string) $text);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode page-resource entry comment-boundary CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceEntryCommentBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceEntryCommentBoundaryPdf = static function () use ($pageResourceEntryCommentBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET '
        . 'q /CommentForm Do Q '
        . '/Span /CommentActual BDC BT /F1 12 Tf 72 680 Td (Glyph actual leak) Tj ET EMC '
        . 'q /StaleForm Do Q';
    $commentForm = 'BT /F1 12 Tf 12 24 Td (Comment entry inherited form text) Tj ET';
    $staleForm = 'BT /F1 12 Tf 12 24 Td (Comment entry stale form leak) Tj ET';
    $cmap = $pageResourceEntryCommentBoundaryCMap([
        '41' => 'Comment entry inherited font text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /PieceInfo << /WPReview << /Private << /Resources 30 0 R >> >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CommentEntryFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($commentForm) . " >>\nstream\n{$commentForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /ActualText (Comment entry inherited actual text) >>\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
        . "10 0 obj\n<< "
        . "/Font << /F1 5 % font resource object/generation split by PDF comment\n 0 % font generation/R split by PDF comment\n R >> "
        . "/XObject << /CommentForm 7 % form resource object/generation split by PDF comment\n 0 % form generation/R split by PDF comment\n R >> "
        . "/Properties << /CommentActual 8 % property resource object/generation split by PDF comment\n 0 % property generation/R split by PDF comment\n R >> "
        . ">>\nendobj\n"
        . "30 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /StaleForm 9 0 R >> /Properties << /CommentActual 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /ActualText (Comment entry stale ActualText leak) >>\nendobj\n"
        . "%%EOF";
};

return [
    'treats PDF comments as whitespace inside inherited resource entry references' => static function (
        TestRunner $t
    ) use ($pageResourceEntryCommentBoundaryPdf): void {
        $pdf = $pageResourceEntryCommentBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $styledPages = $extractor->extractStyledTextPages($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $styledLines = array_map(
            static fn (array $block): string => implode('', array_column($block['lines'][0]['spans'] ?? [], 'text')),
            $styledPages[0]['blocks'] ?? []
        );
        $expected = [
            'Comment entry inherited font text',
            'Comment entry inherited form text',
            'Comment entry inherited actual text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same($expected, $styledLines);
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(0, $resources['resource_generation'] ?? null);
        $t->same(['Font', 'XObject', 'Properties'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['CommentForm'], $resources['xobject_names'] ?? null);
        $t->same(['CommentActual'], $resources['properties_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Glyph actual leak'));
        $t->same(false, str_contains($plainText, 'Comment entry stale form leak'));
        $t->same(false, str_contains($plainText, 'Comment entry stale ActualText leak'));
        $t->same(false, str_contains($plainText, 'StaleForm'));
        $t->same(false, str_contains($plainText, 'CommentActual'));
    },
];
