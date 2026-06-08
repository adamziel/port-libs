<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkAnnotationNewWindowOperandBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Remote true Remote false Launch blocked Safe docs) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Contents (Remote true review) /A << /S /GoToR /F (remote-true.pdf) /D [2 /FitH 720] /NewWindow 20 0 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 270 718] /Contents (Remote false review) /A << /S /GoToR /F (remote-false.pdf) /D (Remote Appendix) /NewWindow 21 0 R >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [280 700 380 718] /Contents (Launch blocked review) /A << /S /Launch /F (blocked-helper.exe) /NewWindow 20 0 R /Next << /S /URI /URI (https://example.com/launch-followup-review) >> >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [390 700 470 718] /Contents (Safe docs review) /A << /S /URI /URI (https://example.com/safe-docs-new-window-boundary) >> >>\nendobj\n"
        . "20 0 obj\ntrue\nendobj\n"
        . "21 0 obj\nfalse\nendobj\n"
        . "%%EOF";
};

$linkAnnotationNewWindowOperandBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 470.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 470.0, 718.0],
                'spans' => [
                    ['text' => 'Remote true', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Remote false', 'bbox' => [170.0, 700.0, 270.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Launch blocked', 'bbox' => [280.0, 700.0, 380.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe docs', 'bbox' => [390.0, 700.0, 470.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'resolves indirect Link action NewWindow booleans before WordPress review metadata promotion' => static function (
        TestRunner $t
    ) use ($linkAnnotationNewWindowOperandBoundaryPdf, $linkAnnotationNewWindowOperandBoundaryPages): void {
        $pdf = $linkAnnotationNewWindowOperandBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $annotationRows = $annotations[0]['annotations'];
        $t->same([7, 8, 9, 10], array_column($annotationRows, 'annotation_object'));
        $t->same([
            'remote-document-review',
            'remote-document-review',
            'blocked-launch',
            'review-uri',
        ], array_map(static fn (array $row): ?string => $row['actions'][0]['safety'] ?? null, $annotationRows));
        $t->same(true, $annotationRows[0]['actions'][0]['new_window']);
        $t->same(false, $annotationRows[1]['actions'][0]['new_window']);
        $t->same(true, $annotationRows[2]['actions'][0]['new_window']);
        $t->same(['Launch', 'URI'], array_column($annotationRows[2]['actions'], 'action_type'));
        $t->same(true, $annotationRows[2]['actions'][1]['chained']);
        $t->same(null, $annotationRows[3]['actions'][0]['new_window']);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $linkRows = $links[0]['links'];
        $t->same([7, 8, 10], array_column($linkRows, 'annotation_object'), 'Launch actions and their chained URI payloads remain review-only and do not donate a WordPress link.');
        $t->same(['remote-document-review', 'remote-document-review', 'review-uri'], array_column($linkRows, 'safety'));
        $t->same(['remote-true.pdf', 'remote-false.pdf', null], array_map(static fn (array $row): ?string => $row['file'] ?? null, $linkRows));
        $t->same(true, $linkRows[0]['new_window']);
        $t->same(false, $linkRows[1]['new_window']);
        $t->same(null, $linkRows[2]['new_window']);
        $t->same('https://example.com/safe-docs-new-window-boundary', $linkRows[2]['uri']);

        $pages = $extractor->applyLinksToPages($linkAnnotationNewWindowOperandBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('remote-true.pdf', $spans[0]['link_remote_file']);
        $t->same(true, $spans[0]['link_remote_new_window']);
        $t->same('remote-false.pdf', $spans[1]['link_remote_file']);
        $t->same(false, $spans[1]['link_remote_new_window']);
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['link_actions_review']));
        $t->same('https://example.com/safe-docs-new-window-boundary', $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Remote true Remote false Launch blocked [Safe docs](https://example.com/safe-docs-new-window-boundary)', $blocks[0]['text']);

        $encodedPromotedRows = $encoded([$links, $pages]);
        foreach (['blocked-helper.exe', 'launch-followup-review', 'Launch blocked review'] as $blockedLaunchPayload) {
            $t->same(false, str_contains($encodedPromotedRows, $blockedLaunchPayload));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->same('Remote true Remote false Launch blocked Safe docs', $plainText);
        foreach ([
            'remote-true.pdf',
            'remote-false.pdf',
            'blocked-helper.exe',
            'safe-docs-new-window-boundary',
            'Remote true review',
            'Remote false review',
            'Launch blocked review',
            'Safe docs review',
            'launch-followup-review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
