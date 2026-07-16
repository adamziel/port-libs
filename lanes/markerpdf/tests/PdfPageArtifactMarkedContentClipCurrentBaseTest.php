<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pageArtifactMarkedContentClipPdf = static function (): string {
    $content = 'q 72 650 240 50 re W n '
        . 'BT /F1 12 Tf '
        . '/Artifact << /Type /Pagination /ActualText (Clipped Header Replacement) >> BDC 1 0 0 1 72 724 Tm (Clipped Header Glyphs) Tj EMC '
        . '1 0 0 1 72 684 Tm (Body inside clipped region) Tj '
        . '/Artifact << /Type /Layout /Alt (Visible Artifact Caption) >> BDC 1 0 0 1 72 668 Tm (Caption Glyphs) Tj EMC '
        . '1 0 0 1 72 620 Tm (Clipped Footer Body) Tj '
        . 'ET Q '
        . 'BT /F1 12 Tf 1 0 0 1 72 600 Tm (Tail outside clip) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'clips page artifact marked content before WordPress text extraction' => static function (TestRunner $t) use ($pageArtifactMarkedContentClipPdf): void {
        $pdf = $pageArtifactMarkedContentClipPdf();
        $extractor = new PdfTextExtractor();
        $expected = [
            'Body inside clipped region',
            'Visible Artifact Caption',
            'Tail outside clip',
        ];
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $styledLines = array_map(
            static fn (array $block): string => implode('', array_column($block['lines'][0]['spans'], 'text')),
            $pages[0]['blocks'] ?? []
        );

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same($expected, $styledLines);
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'Visible Artifact Caption'));
        $t->true(!str_contains($plainText, 'Caption Glyphs'));
        $t->true(!str_contains($plainText, 'Clipped Header Replacement'));
        $t->true(!str_contains($plainText, 'Clipped Header Glyphs'));
        $t->true(!str_contains($plainText, 'Clipped Footer Body'));
    },
];
