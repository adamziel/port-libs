<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkActionOperandBoundaryPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Clean docs Tailed URI Tailed remote Tailed launch Tailed submit) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 20 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Clean URI review) /A << /S /URI /URI (https://example.com/clean-docs) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 250 718] /Contents (Tailed URI review) /A << /S /URI /URI (https://example.com/tailed-uri-leak) 30 0 R >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 700 360 718] /Contents (Tailed remote review) /A << /S /GoToR /F (tailed-remote.pdf) 30 0 R /D [2 /FitH 720] /NewWindow true >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [370 700 470 718] /Contents (Tailed launch review) /A << /S /Launch /F (tailed-launch.exe) 30 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [480 700 585 718] /Contents (Tailed submit review) /A << /S /SubmitForm /F (https://submit.example.test/import) 30 0 R /Flags 4 >> >>\nendobj\n"
        . "20 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "30 0 obj\n<< /S /JavaScript /JS (tailedOperandReview\\(\\)) >>\nendobj\n"
        . "%%EOF";
};

$linkActionOperandBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 585.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 585.0, 718.0],
                'spans' => [
                    ['text' => 'Clean docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed URI', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed remote', 'bbox' => [260.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed launch', 'bbox' => [370.0, 700.0, 470.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed submit', 'bbox' => [480.0, 700.0, 585.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects malformed nested Link action operands before WordPress link promotion and review leakage' => static function (
        TestRunner $t
    ) use ($linkActionOperandBoundaryPdf, $linkActionOperandBoundaryPages): void {
        $pdf = $linkActionOperandBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10, 11], array_column($annotations[0]['annotations'], 'annotation_object'));

        $actionsByObject = [];
        foreach ($annotations[0]['annotations'] as $annotation) {
            $actionsByObject[$annotation['annotation_object']] = $annotation['actions'][0] ?? null;
        }

        $t->same('review-uri', $actionsByObject[7]['safety'] ?? null);
        $t->same('https://example.com/clean-docs', $actionsByObject[7]['uri'] ?? null);
        $t->same('malformed-action-dictionary', $actionsByObject[8]['safety'] ?? null);
        $t->same(['URI'], $actionsByObject[8]['malformed_action_operand_keys'] ?? null);
        $t->same(null, $actionsByObject[8]['uri'] ?? null);
        $t->same('malformed-action-dictionary', $actionsByObject[9]['safety'] ?? null);
        $t->same(['F'], $actionsByObject[9]['malformed_action_operand_keys'] ?? null);
        $t->same(null, $actionsByObject[9]['file'] ?? null);
        $t->same('malformed-action-dictionary', $actionsByObject[10]['safety'] ?? null);
        $t->same(['F'], $actionsByObject[10]['malformed_action_operand_keys'] ?? null);
        $t->same(null, $actionsByObject[10]['file'] ?? null);
        $t->same('malformed-action-dictionary', $actionsByObject[11]['safety'] ?? null);
        $t->same(['F'], $actionsByObject[11]['malformed_action_operand_keys'] ?? null);
        $t->same(null, $actionsByObject[11]['target'] ?? null);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/clean-docs', $links[0]['links'][0]['uri']);

        $pages = $extractor->applyLinksToPages($linkActionOperandBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/clean-docs', $spans[0]['link_uri']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->true(!isset($spans[2]['link_remote_file']));
        $t->true(!isset($spans[2]['link_actions_review']));
        $t->true(!isset($spans[3]['link_actions_review']));
        $t->true(!isset($spans[4]['link_actions_review']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Clean docs](https://example.com/clean-docs) Tailed URI Tailed remote Tailed launch Tailed submit', $blocks[0]['text']);

        $encodedReview = $encoded([$annotations, $links, $pages]);
        foreach ([
            'tailed-uri-leak',
            'tailed-remote.pdf',
            'tailed-launch.exe',
            'submit.example.test',
            'tailedOperandReview',
        ] as $tailedReviewText) {
            $t->same(false, str_contains($encodedReview, $tailedReviewText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->same('Clean docs Tailed URI Tailed remote Tailed launch Tailed submit', $plainText);
        foreach ([
            'clean-docs',
            'tailed-uri-leak',
            'tailed-remote.pdf',
            'tailed-launch.exe',
            'submit.example.test',
            'Clean URI review',
            'Tailed URI review',
            'Tailed remote review',
            'Tailed launch review',
            'Tailed submit review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
