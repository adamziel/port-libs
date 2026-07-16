<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkAnnotationIsMapOperandBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Static docs Indirect map Tailed map Named map) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Static IsMap false review) /A << /S /URI /URI (https://example.com/static-ismap-docs) /IsMap false >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 260 718] /Contents (Indirect IsMap true review) /A << /S /URI /URI (https://maps.example.com/indirect-map) /IsMap 20 0 R >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 360 718] /Contents (Tailed IsMap review) /A << /S /URI /URI (https://maps.example.com/tailed-map) /IsMap 21 0 R >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [370 700 460 718] /Contents (Named IsMap review) /A << /S /URI /URI (https://maps.example.com/named-map) /IsMap /Maybe >> >>\nendobj\n"
        . "20 0 obj\ntrue\nendobj\n"
        . "21 0 obj\ntrue 30 0 R\nendobj\n"
        . "30 0 obj\nfalse\nendobj\n"
        . "%%EOF";
};

$linkAnnotationIsMapOperandBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 460.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 460.0, 718.0],
                'spans' => [
                    ['text' => 'Static docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Indirect map', 'bbox' => [160.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed map', 'bbox' => [270.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Named map', 'bbox' => [370.0, 700.0, 460.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'treats malformed Link annotation IsMap operands as coordinate dependent before WordPress span promotion' => static function (
        TestRunner $t
    ) use ($linkAnnotationIsMapOperandBoundaryPdf, $linkAnnotationIsMapOperandBoundaryPages): void {
        $pdf = $linkAnnotationIsMapOperandBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $annotationRows = $annotations[0]['annotations'];
        $t->same([7, 8, 9, 10], array_column($annotationRows, 'annotation_object'));
        $t->same([
            'review-uri',
            'coordinate-dependent-uri-review',
            'coordinate-dependent-uri-review',
            'coordinate-dependent-uri-review',
        ], array_map(static fn (array $row): ?string => $row['actions'][0]['safety'] ?? null, $annotationRows));
        $t->same([false, true, true, true], array_map(
            static fn (array $row): ?bool => $row['actions'][0]['uri_is_map'] ?? null,
            $annotationRows
        ));
        $t->same([false, false, true, true], array_map(
            static fn (array $row): bool => (bool) ($row['actions'][0]['uri_is_map_operand_malformed'] ?? false),
            $annotationRows
        ));
        $t->same('uri_action_ismap_boolean_operand', $annotationRows[2]['actions'][0]['uri_is_map_operand_review']['source'] ?? null);
        $t->same('coordinate_dependent_review_for_malformed_boolean', $annotationRows[2]['actions'][0]['uri_is_map_operand_review']['selected_value_policy'] ?? null);
        $t->same('uri_action_ismap_boolean_operand', $annotationRows[3]['actions'][0]['uri_is_map_operand_review']['source'] ?? null);
        $t->same(false, (bool) ($annotationRows[2]['actions'][0]['uri_is_map_operand_review']['payload_included'] ?? true));
        $t->same(false, (bool) ($annotationRows[3]['actions'][0]['uri_is_map_operand_review']['visible_text_source'] ?? true));

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'), 'Only the clean non-map URI is promoted as a WordPress link.');
        $t->same('https://example.com/static-ismap-docs', $links[0]['links'][0]['uri']);
        $t->same(false, $links[0]['links'][0]['uri_is_map']);
        $t->same(false, str_contains($encoded($links), 'maps.example.com'));

        $pages = $extractor->applyLinksToPages($linkAnnotationIsMapOperandBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/static-ismap-docs', $spans[0]['link_uri']);
        $t->same(false, $spans[0]['link_actions_review'][0]['uri_is_map']);
        foreach ([1, 2, 3] as $spanIndex) {
            $t->true(!isset($spans[$spanIndex]['link_uri']));
            $t->true(!isset($spans[$spanIndex]['link_actions_review']));
        }

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Static docs](https://example.com/static-ismap-docs) Indirect map Tailed map Named map', $blocks[0]['text']);
        $t->same(false, str_contains($blocks[0]['text'], 'maps.example.com'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->same('Static docs Indirect map Tailed map Named map', $plainText);
        foreach ([
            'static-ismap-docs',
            'maps.example.com',
            'Static IsMap false review',
            'Indirect IsMap true review',
            'Tailed IsMap review',
            'Named IsMap review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
