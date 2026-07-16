<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkAnnotationStateBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Named docs Indirect state Hidden state) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Named docs state review) /T (Import reviewer) /Subj (Migration link) /NM (named-link-1) /M (D:20260605213631Z) /CA 0.65 /A << /S /URI /URI (https://example.com/named-docs-state) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 252 718] /Contents (Indirect state review) /T 20 0 R /Subj 21 0 R /NM 22 0 R /M 23 0 R /CA 24 0 R /A << /S /URI /URI (https://example.com/indirect-state) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [262 700 352 718] /F 2 /Contents (Hidden state review) /T (Hidden reviewer) /Subj (Hidden subject) /NM (hidden-link-1) /M (D:20260605000000Z) /CA 0.25 /A << /S /URI /URI (https://example.com/hidden-state) >> >>\nendobj\n"
        . "20 0 obj\n<FEFF0049006E006400690072006500630074002000720065007600690065007700650072>\nendobj\n"
        . "21 0 obj\n(Indirect migration link)\nendobj\n"
        . "22 0 obj\n(indirect-link-2)\nendobj\n"
        . "23 0 obj\n(D:20260605213700-04'00')\nendobj\n"
        . "24 0 obj\n0.4\nendobj\n"
        . "%%EOF";
};

$linkAnnotationStateBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 352.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 352.0, 718.0],
                'spans' => [
                    ['text' => 'Named docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Indirect state', 'bbox' => [160.0, 700.0, 252.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Hidden state', 'bbox' => [262.0, 700.0, 352.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'carries visible Link annotation name subject modified date and opacity review state onto WordPress spans' => static function (
        TestRunner $t
    ) use ($linkAnnotationStateBoundaryPdf, $linkAnnotationStateBoundaryPages): void {
        $pdf = $linkAnnotationStateBoundaryPdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same(2, count($links[0]['links']), 'Hidden link annotation state remains review-only and is not promoted.');
        $t->same([7, 8], array_column($links[0]['links'], 'annotation_object'));

        $direct = $links[0]['links'][0];
        $t->same('Named docs state review', $direct['contents']);
        $t->same('Import reviewer', $direct['title']);
        $t->same('Migration link', $direct['subject']);
        $t->same('named-link-1', $direct['name']);
        $t->same('D:20260605213631Z', $direct['modified_at']);
        $t->same(0.65, $direct['opacity']);

        $indirect = $links[0]['links'][1];
        $t->same('Indirect reviewer', $indirect['title']);
        $t->same('Indirect migration link', $indirect['subject']);
        $t->same('indirect-link-2', $indirect['name']);
        $t->same("D:20260605213700-04'00'", $indirect['modified_at']);
        $t->same(0.4, $indirect['opacity']);

        $pages = $extractor->applyLinksToPages($linkAnnotationStateBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/named-docs-state', $spans[0]['link_uri']);
        $t->same('Migration link', $spans[0]['link_annotation_subject']);
        $t->same('named-link-1', $spans[0]['link_annotation_name']);
        $t->same('D:20260605213631Z', $spans[0]['link_annotation_modified_at']);
        $t->same(0.65, $spans[0]['link_annotation_opacity']);
        $t->same('https://example.com/indirect-state', $spans[1]['link_uri']);
        $t->same('Indirect migration link', $spans[1]['link_annotation_subject']);
        $t->same('indirect-link-2', $spans[1]['link_annotation_name']);
        $t->same("D:20260605213700-04'00'", $spans[1]['link_annotation_modified_at']);
        $t->same(0.4, $spans[1]['link_annotation_opacity']);
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['link_annotation_name']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Named docs](https://example.com/named-docs-state) [Indirect state](https://example.com/indirect-state) Hidden state', $blocks[0]['text']);

        $encoded = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, 'hidden-state'));
        $t->same(false, str_contains($encoded, 'Hidden state review'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Named docs Indirect state Hidden state', $plainText);
        foreach ([
            'named-docs-state',
            'indirect-state',
            'hidden-state',
            'Named docs state review',
            'Indirect state review',
            'Hidden state review',
            'Migration link',
            'named-link-1',
            'indirect-link-2',
            'Hidden subject',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
