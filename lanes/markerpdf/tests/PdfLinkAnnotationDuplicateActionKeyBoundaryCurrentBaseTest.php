<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkDuplicateActionKeyBoundaryPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Duplicate docs Duplicate jump) Tj ET';
    $targetContent = 'BT /F1 12 Tf 72 720 Td (Current duplicate target page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 165 718] /Contents (Duplicate action review) /A 10 0 R /A 11 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [175 700 275 718] /Contents (Duplicate destination review) /Dest (stale-target) /Dest (current-target) >>\nendobj\n"
        . "10 0 obj\n<< /S /URI /URI (https://stale.example.com/first-action) /Next << /S /Launch /F (stale-helper.exe) >> >>\nendobj\n"
        . "11 0 obj\n<< /S /URI /URI (https://stale.example.com/first-uri) /URI (https://example.com/current-duplicate-action) /Next 12 0 R /Next 13 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /JavaScript /JS (staleDuplicateActionReview\\(\\)) >>\nendobj\n"
        . "13 0 obj\n<< /S /GoTo /D (current-target) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(current-target) [4 0 R /FitH 720] (stale-target) [3 0 R /FitH 720]] >>\nendobj\n"
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$linkDuplicateActionKeyBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 275.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 275.0, 718.0],
                'spans' => [
                    ['text' => 'Duplicate docs', 'bbox' => [72.0, 700.0, 165.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Duplicate jump', 'bbox' => [175.0, 700.0, 275.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'reviews duplicate Link action keys while selecting the last top-level action target' => static function (
        TestRunner $t
    ) use ($linkDuplicateActionKeyBoundaryPdf, $linkDuplicateActionKeyBoundaryPages): void {
        $pdf = $linkDuplicateActionKeyBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['A'], $annotations[0]['annotations'][0]['duplicate_action_keys'] ?? null);
        $t->same('annotation_action_duplicate_keys', $annotations[0]['annotations'][0]['duplicate_action_key_review']['source'] ?? null);
        $t->same(['A' => 2], $annotations[0]['annotations'][0]['duplicate_action_key_review']['declared_entry_counts'] ?? null);
        $t->same(['A' => 1], $annotations[0]['annotations'][0]['duplicate_action_key_review']['selected_entry_indexes'] ?? null);
        $t->same(['URI', 'GoTo'], array_column($annotations[0]['annotations'][0]['actions'], 'action_type'));
        $t->same(['URI', 'Next'], $annotations[0]['annotations'][0]['actions'][0]['duplicate_keys'] ?? null);
        $t->same('action_dictionary_duplicate_keys', $annotations[0]['annotations'][0]['actions'][0]['duplicate_key_review']['source'] ?? null);
        $t->same(['URI' => 2, 'Next' => 2], $annotations[0]['annotations'][0]['actions'][0]['duplicate_key_review']['declared_entry_counts'] ?? null);
        $t->same(['URI' => 1, 'Next' => 1], $annotations[0]['annotations'][0]['actions'][0]['duplicate_key_review']['selected_entry_indexes'] ?? null);
        $t->same('https://example.com/current-duplicate-action', $annotations[0]['annotations'][0]['actions'][0]['uri']);
        $t->same(1, $annotations[0]['annotations'][0]['actions'][1]['destination_page']);
        $t->same(['Dest'], $annotations[0]['annotations'][1]['duplicate_action_keys'] ?? null);
        $t->same(['Dest' => 2], $annotations[0]['annotations'][1]['duplicate_action_key_review']['declared_entry_counts'] ?? null);
        $t->same('current-target', $annotations[0]['annotations'][1]['actions'][0]['destination'] ?? null);

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/current-duplicate-action', $links[0]['links'][0]['uri']);
        $t->same(['A'], $links[0]['links'][0]['duplicate_action_keys'] ?? null);
        $t->same(['URI', 'Next'], $links[0]['links'][0]['actions'][0]['duplicate_keys'] ?? null);
        $t->same('local-destination', $links[0]['links'][1]['safety']);
        $t->same('current-target', $links[0]['links'][1]['destination']);
        $t->same(['Dest'], $links[0]['links'][1]['duplicate_action_keys'] ?? null);

        $pages = $linkExtractor->applyLinksToPages($linkDuplicateActionKeyBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/current-duplicate-action', $spans[0]['link_uri']);
        $t->same(['A'], $spans[0]['link_duplicate_action_keys']);
        $t->same(['URI', 'Next'], $spans[0]['link_actions_review'][0]['duplicate_keys'] ?? null);
        $t->same(1, $spans[1]['link_destination_page']);
        $t->same(['Dest'], $spans[1]['link_duplicate_action_keys']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Duplicate docs](https://example.com/current-duplicate-action) Duplicate jump', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains("Duplicate docs Duplicate jump\nCurrent duplicate target page", $plainText);
        foreach ([
            'stale.example.com',
            'stale-helper.exe',
            'staleDuplicateActionReview',
            'stale-target',
        ] as $staleReviewText) {
            $t->same(false, str_contains($encoded([$annotations, $links, $pages]), $staleReviewText));
            $t->same(false, str_contains($plainText, $staleReviewText));
        }
        foreach ([
            'Duplicate action review',
            'Duplicate destination review',
            'current-duplicate-action',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
