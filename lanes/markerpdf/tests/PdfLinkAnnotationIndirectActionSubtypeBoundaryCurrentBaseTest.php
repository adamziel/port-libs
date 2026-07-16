<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkIndirectActionSubtypeBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Indirect action Hover review Unsupported launch) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 178 718] /Contents (Indirect subtype link review) /A << /S 20 0 R /URI (https://example.com/indirect-action-subtype) /Next << /S 21 0 R /JS (indirectSubtypeScriptReview\\(\\)) >> >> /AA << /E << /S 20 0 R /URI (mailto:indirect-subtype@example.test) >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [294 700 418 718] /Contents (Indirect launch subtype review) /A << /S 22 0 R /F (review-helper.exe) >> >>\nendobj\n"
        . "20 0 obj\n/URI\nendobj\n"
        . "21 0 obj\n/JavaScript\nendobj\n"
        . "22 0 obj\n/Launch\nendobj\n"
        . "%%EOF";
};

$linkIndirectActionSubtypeBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 418.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 418.0, 718.0],
                'spans' => [
                    ['text' => 'Indirect action', 'bbox' => [72.0, 700.0, 178.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Hover review', 'bbox' => [188.0, 700.0, 286.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Unsupported launch', 'bbox' => [294.0, 700.0, 418.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'resolves indirect Link action subtype names before WordPress span promotion' => static function (TestRunner $t) use (
        $linkIndirectActionSubtypeBoundaryPdf,
        $linkIndirectActionSubtypeBoundaryPages
    ): void {
        $pdf = $linkIndirectActionSubtypeBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['URI', 'JavaScript'], array_column($annotations[0]['annotations'][0]['actions'], 'action_type'));
        $t->same(['review-uri', 'blocked-javascript'], array_column($annotations[0]['annotations'][0]['actions'], 'safety'));
        $t->same(['URI'], array_column($annotations[0]['annotations'][0]['additional_actions'], 'action_type'));
        $t->same('mailto:indirect-subtype@example.test', $annotations[0]['annotations'][0]['additional_actions'][0]['uri']);
        $t->same(['Launch'], array_column($annotations[0]['annotations'][1]['actions'], 'action_type'));
        $t->same(['blocked-launch'], array_column($annotations[0]['annotations'][1]['actions'], 'safety'));

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'), 'The indirect Launch subtype stays review-only and is not promoted.');
        $t->same('https://example.com/indirect-action-subtype', $links[0]['links'][0]['uri']);
        $t->same(['URI', 'JavaScript'], array_column($links[0]['links'][0]['actions'], 'action_type'));
        $t->same(['review-uri', 'blocked-javascript'], array_column($links[0]['links'][0]['actions'], 'safety'));
        $t->same(['URI'], array_column($links[0]['links'][0]['additional_actions'], 'action_type'));
        $t->same(false, str_contains($encoded($links), 'review-helper.exe'));

        $pages = $extractor->applyLinksToPages($linkIndirectActionSubtypeBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/indirect-action-subtype', $spans[0]['link_uri']);
        $t->same(['URI', 'JavaScript'], array_column($spans[0]['link_actions_review'], 'action_type'));
        $t->same('mailto:indirect-subtype@example.test', $spans[0]['link_additional_actions_review'][0]['uri']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['link_actions_review']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Indirect action](https://example.com/indirect-action-subtype) Hover review Unsupported launch', $blocks[0]['text']);
        $t->same(false, str_contains($blocks[0]['text'], 'review-helper.exe'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Indirect action Hover review Unsupported launch', $plainText);
        foreach ([
            'indirect-action-subtype',
            'indirectSubtypeScriptReview',
            'indirect-subtype@example.test',
            'review-helper.exe',
            'Indirect subtype link review',
            'Indirect launch subtype review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
