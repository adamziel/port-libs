<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkFileSpecDuplicateKeyBoundaryPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Remote ok Duplicate UF Duplicate F Safe URI) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Remote ok review) /A << /S /GoToR /F 20 0 R /D (Remote Appendix) /NewWindow true >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 260 718] /Contents (Duplicate UF review) /A << /S /GoToR /F 21 0 R /D (Duplicate UF Target) /NewWindow true >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 360 718] /Contents (Duplicate F review) /A << /S /GoToR /F 22 0 R /D [2 /FitH 720] /NewWindow false >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [370 700 450 718] /Contents (Safe URI review) /A << /S /URI /URI (https://example.com/filespec-boundary) >> >>\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (fallback-current.pdf) /UF (remote-current.pdf) >>\nendobj\n"
        . "21 0 obj\n<< /Type /Filespec /F (fallback-duplicate-uf.pdf) /UF (safe-duplicate-uf.pdf) /UF (evil-duplicate-uf.pdf) >>\nendobj\n"
        . "22 0 obj\n<< /Type /Filespec /F (safe-duplicate-f.pdf) /F (evil-duplicate-f.pdf) >>\nendobj\n"
        . "%%EOF";
};

$linkFileSpecDuplicateKeyBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 450.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 450.0, 718.0],
                'spans' => [
                    ['text' => 'Remote ok', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Duplicate UF', 'bbox' => [160.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Duplicate F', 'bbox' => [270.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [370.0, 700.0, 450.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects duplicate Filespec file-name keys before remote Link promotion' => static function (
        TestRunner $t
    ) use ($linkFileSpecDuplicateKeyBoundaryPdf, $linkFileSpecDuplicateKeyBoundaryPages): void {
        $pdf = $linkFileSpecDuplicateKeyBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same([
            'remote-document-review',
            'unsupported-action-review',
            'unsupported-action-review',
            'review-uri',
        ], array_map(
            static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
            $annotations[0]['annotations']
        ));
        $t->same('remote-current.pdf', $annotations[0]['annotations'][0]['actions'][0]['file']);
        $t->same(null, $annotations[0]['annotations'][1]['actions'][0]['file'] ?? null);
        $t->same(null, $annotations[0]['annotations'][2]['actions'][0]['file'] ?? null);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 10], array_column($links[0]['links'], 'annotation_object'));
        $t->same('remote-current.pdf', $links[0]['links'][0]['file']);
        $t->same('Remote Appendix', $links[0]['links'][0]['destination']);
        $t->same('remote-document-review', $links[0]['links'][0]['safety']);
        $t->same('https://example.com/filespec-boundary', $links[0]['links'][1]['uri']);

        $pages = $extractor->applyLinksToPages($linkFileSpecDuplicateKeyBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('remote-current.pdf', $spans[0]['link_remote_file']);
        $t->same('Remote Appendix', $spans[0]['link_remote_destination']);
        $t->true(!isset($spans[1]['link_remote_file']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->true(!isset($spans[2]['link_remote_file']));
        $t->true(!isset($spans[2]['link_actions_review']));
        $t->same('https://example.com/filespec-boundary', $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Remote ok Duplicate UF Duplicate F [Safe URI](https://example.com/filespec-boundary)', $blocks[0]['text']);

        $encodedReview = $encoded([$annotations, $links, $pages]);
        foreach ([
            'safe-duplicate-uf.pdf',
            'evil-duplicate-uf.pdf',
            'fallback-duplicate-uf.pdf',
            'safe-duplicate-f.pdf',
            'evil-duplicate-f.pdf',
            'Duplicate UF Target',
        ] as $duplicateReviewOnlyText) {
            $t->same(false, str_contains($encodedReview, $duplicateReviewOnlyText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->same('Remote ok Duplicate UF Duplicate F Safe URI', $plainText);
        foreach ([
            'remote-current.pdf',
            'fallback-current.pdf',
            'safe-duplicate-uf.pdf',
            'evil-duplicate-uf.pdf',
            'safe-duplicate-f.pdf',
            'evil-duplicate-f.pdf',
            'Remote ok review',
            'Duplicate UF review',
            'Duplicate F review',
            'filespec-boundary',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
