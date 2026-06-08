<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkStringOperandBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Clean state Tainted state Indirect clean Indirect tainted) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 158 718] /Contents (Clean state review) /T (Clean title) /Subj (Clean subject) /NM (clean-name) /M (D:20260608172104Z) /A << /S /URI /URI (https://example.com/clean-state) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [168 700 266 718] /Contents (Tainted direct review) 90 0 R /T (Tainted direct title) 90 0 R /Subj (Tainted direct subject) 90 0 R /NM (tainted-direct-name) 90 0 R /M (D:20260608172204Z) 90 0 R /A << /S /URI /URI (https://example.com/tainted-direct-state) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [276 700 378 718] /Contents 20 0 R /T 21 0 R /Subj 22 0 R /NM 23 0 R /M 24 0 R /A << /S /URI /URI (https://example.com/indirect-clean-state) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [388 700 510 718] /Contents 30 0 R /T 31 0 R /Subj 32 0 R /NM 33 0 R /M 34 0 R /A << /S /URI /URI (https://example.com/indirect-tainted-state) >> >>\nendobj\n"
        . "20 0 obj\n(Indirect clean review)\nendobj\n"
        . "21 0 obj\n<FEFF0049006E00640069007200650063007400200063006C00650061006E0020007400690074006C0065>\nendobj\n"
        . "22 0 obj\n(Indirect clean subject)\nendobj\n"
        . "23 0 obj\n(indirect-clean-name)\nendobj\n"
        . "24 0 obj\n(D:20260608172304Z)\nendobj\n"
        . "30 0 obj\n(Indirect tainted review) 90 0 R\nendobj\n"
        . "31 0 obj\n(Indirect tainted title) 90 0 R\nendobj\n"
        . "32 0 obj\n(Indirect tainted subject) 90 0 R\nendobj\n"
        . "33 0 obj\n(indirect-tainted-name) 90 0 R\nendobj\n"
        . "34 0 obj\n(D:20260608172404Z) 90 0 R\nendobj\n"
        . "90 0 obj\n<< /S /JavaScript /JS (staleStringOperandReview\\(\\)) >>\nendobj\n"
        . "%%EOF";
};

$linkStringOperandBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 510.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 510.0, 718.0],
                'spans' => [
                    ['text' => 'Clean state', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tainted state', 'bbox' => [168.0, 700.0, 266.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Indirect clean', 'bbox' => [276.0, 700.0, 378.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Indirect tainted', 'bbox' => [388.0, 700.0, 510.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects tailed Link annotation string state operands without dropping safe WordPress links' => static function (
        TestRunner $t
    ) use ($linkStringOperandBoundaryPdf, $linkStringOperandBoundaryPages): void {
        $pdf = $linkStringOperandBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $annotationRows = $annotations[0]['annotations'];
        $t->same([7, 8, 9, 10], array_column($annotationRows, 'annotation_object'));

        $annotationsByObject = [];
        foreach ($annotationRows as $row) {
            $annotationsByObject[$row['annotation_object']] = $row;
        }

        $t->same('Clean state review', $annotationsByObject[7]['contents']);
        $t->same('Clean title', $annotationsByObject[7]['title']);
        $t->same('clean-name', $annotationsByObject[7]['name']);
        $t->same('D:20260608172104Z', $annotationsByObject[7]['modified_at']);
        foreach (['contents', 'title', 'name', 'modified_at'] as $field) {
            $t->same(null, $annotationsByObject[8][$field], 'Direct tailed annotation string operands must stay review-malformed.');
            $t->same(null, $annotationsByObject[10][$field], 'Indirect tailed annotation string operands must stay review-malformed.');
        }
        $t->same('Indirect clean review', $annotationsByObject[9]['contents']);
        $t->same('Indirect clean title', $annotationsByObject[9]['title']);
        $t->same('indirect-clean-name', $annotationsByObject[9]['name']);
        $t->same('D:20260608172304Z', $annotationsByObject[9]['modified_at']);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $linkRows = $links[0]['links'];
        $t->same([7, 8, 9, 10], array_column($linkRows, 'annotation_object'));
        $t->same([
            'https://example.com/clean-state',
            'https://example.com/tainted-direct-state',
            'https://example.com/indirect-clean-state',
            'https://example.com/indirect-tainted-state',
        ], array_column($linkRows, 'uri'));

        $linksByObject = [];
        foreach ($linkRows as $row) {
            $linksByObject[$row['annotation_object']] = $row;
        }

        $t->same('Clean state review', $linksByObject[7]['contents']);
        $t->same('Clean subject', $linksByObject[7]['subject']);
        $t->same('Clean title', $linksByObject[7]['title']);
        $t->same('clean-name', $linksByObject[7]['name']);
        $t->same('D:20260608172104Z', $linksByObject[7]['modified_at']);
        $t->same('Indirect clean review', $linksByObject[9]['contents']);
        $t->same('Indirect clean subject', $linksByObject[9]['subject']);
        $t->same('Indirect clean title', $linksByObject[9]['title']);
        $t->same('indirect-clean-name', $linksByObject[9]['name']);
        $t->same('D:20260608172304Z', $linksByObject[9]['modified_at']);
        foreach ([8, 10] as $objectNumber) {
            foreach (['contents', 'subject', 'title', 'name', 'modified_at'] as $field) {
                $t->true(!array_key_exists($field, $linksByObject[$objectNumber]), 'Tailed string state must not be copied onto promoted link rows.');
            }
        }

        $pages = $extractor->applyLinksToPages($linkStringOperandBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/clean-state', $spans[0]['link_uri']);
        $t->same('Clean state review', $spans[0]['link_annotation_contents']);
        $t->same('Clean subject', $spans[0]['link_annotation_subject']);
        $t->same('clean-name', $spans[0]['link_annotation_name']);
        $t->same('https://example.com/tainted-direct-state', $spans[1]['link_uri']);
        $t->true(!isset($spans[1]['link_annotation_contents']));
        $t->true(!isset($spans[1]['link_annotation_subject']));
        $t->true(!isset($spans[1]['link_annotation_name']));
        $t->same('https://example.com/indirect-clean-state', $spans[2]['link_uri']);
        $t->same('Indirect clean review', $spans[2]['link_annotation_contents']);
        $t->same('Indirect clean subject', $spans[2]['link_annotation_subject']);
        $t->same('indirect-clean-name', $spans[2]['link_annotation_name']);
        $t->same('https://example.com/indirect-tainted-state', $spans[3]['link_uri']);
        $t->true(!isset($spans[3]['link_annotation_contents']));
        $t->true(!isset($spans[3]['link_annotation_subject']));
        $t->true(!isset($spans[3]['link_annotation_name']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            '[Clean state](https://example.com/clean-state) [Tainted state](https://example.com/tainted-direct-state) '
                . '[Indirect clean](https://example.com/indirect-clean-state) [Indirect tainted](https://example.com/indirect-tainted-state)',
            $blocks[0]['text']
        );

        $encodedReview = $encoded([$annotations, $links, $pages]);
        foreach ([
            'Tainted direct review',
            'Tainted direct title',
            'Tainted direct subject',
            'tainted-direct-name',
            'Indirect tainted review',
            'Indirect tainted title',
            'Indirect tainted subject',
            'indirect-tainted-name',
            'staleStringOperandReview',
        ] as $taintedReviewText) {
            $t->same(false, str_contains($encodedReview, $taintedReviewText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Clean state Tainted state Indirect clean Indirect tainted', $plainText);
        foreach ([
            'Clean state review',
            'Tainted direct review',
            'Indirect clean review',
            'Indirect tainted review',
            'clean-state',
            'tainted-direct-state',
            'indirect-clean-state',
            'indirect-tainted-state',
            'staleStringOperandReview',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
