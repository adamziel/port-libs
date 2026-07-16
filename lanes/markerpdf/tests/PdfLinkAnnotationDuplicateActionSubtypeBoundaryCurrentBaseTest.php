<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkDuplicateActionSubtypeBoundaryPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Safe docs Duplicate subtype Safe tail) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Safe primary docs review) /A << /S /URI /URI (https://example.com/safe-docs) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 282 718] /Contents (Duplicate subtype action review) /A << /S /JavaScript /JS (staleDuplicateSubtypeReview\\(\\)) /S /URI /URI (https://example.com/duplicate-subtype-should-not-promote) /Next << /S /URI /URI (https://example.com/duplicate-subtype-followup-review) >> >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [292 700 360 718] /Contents (Safe tail review) /A << /S /URI /URI (https://example.com/safe-tail) >> >>\nendobj\n"
        . "%%EOF";
};

$linkDuplicateActionSubtypeBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 360.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 360.0, 718.0],
                'spans' => [
                    ['text' => 'Safe docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Duplicate subtype', 'bbox' => [160.0, 700.0, 282.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe tail', 'bbox' => [292.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'keeps duplicate Link action subtype dictionaries review-only before WordPress span promotion' => static function (
        TestRunner $t
    ) use ($linkDuplicateActionSubtypeBoundaryPdf, $linkDuplicateActionSubtypeBoundaryPages): void {
        $pdf = $linkDuplicateActionSubtypeBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['review-uri'], array_column($annotations[0]['annotations'][0]['actions'], 'safety'));
        $t->same(['malformed-action-dictionary', 'review-uri'], array_column($annotations[0]['annotations'][1]['actions'], 'safety'));
        $t->same(['S'], $annotations[0]['annotations'][1]['actions'][0]['duplicate_keys'] ?? null);
        $t->same('action_dictionary_duplicate_keys', $annotations[0]['annotations'][1]['actions'][0]['duplicate_key_review']['source'] ?? null);
        $t->same(['S' => 2], $annotations[0]['annotations'][1]['actions'][0]['duplicate_key_review']['declared_entry_counts'] ?? null);
        $t->same(['S' => 1], $annotations[0]['annotations'][1]['actions'][0]['duplicate_key_review']['selected_entry_indexes'] ?? null);
        $t->same(null, $annotations[0]['annotations'][1]['actions'][0]['uri'] ?? null);
        $t->same(true, $annotations[0]['annotations'][1]['actions'][1]['chained'] ?? null);
        $t->same('https://example.com/duplicate-subtype-followup-review', $annotations[0]['annotations'][1]['actions'][1]['uri'] ?? null);
        $t->same(['review-uri'], array_column($annotations[0]['annotations'][2]['actions'], 'safety'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 9], array_column($links[0]['links'], 'annotation_object'));
        $t->same(['https://example.com/safe-docs', 'https://example.com/safe-tail'], array_column($links[0]['links'], 'uri'));

        $linkedPages = $linkExtractor->applyLinksToPages($linkDuplicateActionSubtypeBoundaryPages(), $pdf);
        $spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/safe-docs', $spans[0]['link_uri']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->same('https://example.com/safe-tail', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('[Safe docs](https://example.com/safe-docs) Duplicate subtype [Safe tail](https://example.com/safe-tail)', $blocks[0]['text']);

        $encodedLinks = $encoded([$links, $linkedPages]);
        foreach ([
            'duplicate-subtype-should-not-promote',
            'duplicate-subtype-followup-review',
            'staleDuplicateSubtypeReview',
        ] as $reviewOnlyPayload) {
            $t->same(false, str_contains($encodedLinks, $reviewOnlyPayload));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Safe docs Duplicate subtype Safe tail', $plainText);
        foreach ([
            'safe-docs',
            'duplicate-subtype-should-not-promote',
            'duplicate-subtype-followup-review',
            'staleDuplicateSubtypeReview',
            'Duplicate subtype action review',
            'Safe tail review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
