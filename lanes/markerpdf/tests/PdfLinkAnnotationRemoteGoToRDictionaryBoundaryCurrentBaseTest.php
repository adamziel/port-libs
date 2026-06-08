<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$remoteGoToRDictionaryBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Remote dict Duplicate dest Safe URI) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 170 718] /Contents (Remote dict review) /A << /S /GoToR /F (remote-dict.pdf) /D 20 0 R /NewWindow true >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [180 700 300 718] /Contents (Duplicate remote dest review) /A << /S /GoToR /F (duplicate-remote.pdf) /D 21 0 R /NewWindow false >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [310 700 390 718] /Contents (Safe URI review) /A << /S /URI /URI (https://example.com/remote-dictionary-boundary) >> >>\nendobj\n"
        . "20 0 obj\n<< /D [2 /FitH 720] >>\nendobj\n"
        . "21 0 obj\n<< /D [3 /FitH 640] /D (Stale Remote Target) >>\nendobj\n"
        . "%%EOF";
};

$remoteGoToRDictionaryBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 390.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 390.0, 718.0],
                'spans' => [
                    ['text' => 'Remote dict', 'bbox' => [72.0, 700.0, 170.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Duplicate dest', 'bbox' => [180.0, 700.0, 300.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [310.0, 700.0, 390.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects duplicate remote GoToR destination dictionary keys before WordPress link promotion' => static function (
        TestRunner $t
    ) use ($remoteGoToRDictionaryBoundaryPdf, $remoteGoToRDictionaryBoundaryPages): void {
        $pdf = $remoteGoToRDictionaryBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same([
            'remote-document-review',
            'unsupported-action-review',
            'review-uri',
        ], array_map(
            static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
            $annotations[0]['annotations']
        ));
        $t->same('remote-dict.pdf', $annotations[0]['annotations'][0]['actions'][0]['file']);
        $t->same(2, $annotations[0]['annotations'][0]['actions'][0]['destination_page']);
        $t->same('FitH', $annotations[0]['annotations'][0]['actions'][0]['view_mode']);
        $t->same(null, $annotations[0]['annotations'][1]['actions'][0]['file'] ?? null);
        $t->same(null, $annotations[0]['annotations'][1]['actions'][0]['destination'] ?? null);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 9], array_column($links[0]['links'], 'annotation_object'));
        $t->same('remote-dict.pdf', $links[0]['links'][0]['file']);
        $t->same(2, $links[0]['links'][0]['destination_page']);
        $t->same('FitH', $links[0]['links'][0]['view_mode']);
        $t->same('https://example.com/remote-dictionary-boundary', $links[0]['links'][1]['uri']);

        $pages = $extractor->applyLinksToPages($remoteGoToRDictionaryBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('remote-dict.pdf', $spans[0]['link_remote_file']);
        $t->same(2, $spans[0]['link_remote_destination_page']);
        $t->same('FitH', $spans[0]['link_remote_view_mode']);
        $t->true(!isset($spans[1]['link_remote_file']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->same('https://example.com/remote-dictionary-boundary', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Remote dict Duplicate dest [Safe URI](https://example.com/remote-dictionary-boundary)', $blocks[0]['text']);

        $encodedPromotedRows = $encoded([$links, $pages]);
        foreach ([
            'duplicate-remote.pdf',
            'Stale Remote Target',
            'Duplicate remote dest review',
        ] as $duplicateDestinationPayload) {
            $t->same(false, str_contains($encodedPromotedRows, $duplicateDestinationPayload));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->same('Remote dict Duplicate dest Safe URI', $plainText);
        foreach ([
            'remote-dict.pdf',
            'duplicate-remote.pdf',
            'Stale Remote Target',
            'Remote dict review',
            'Duplicate remote dest review',
            'remote-dictionary-boundary',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
