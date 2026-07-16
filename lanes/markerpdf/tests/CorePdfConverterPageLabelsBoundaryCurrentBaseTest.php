<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\CorePdfConverter;

$makeTempFile = static function (string $bytes): string {
    $path = sys_get_temp_dir() . '/markerpdf-core-page-labels-' . bin2hex(random_bytes(4)) . '.pdf';
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Unable to write temporary markerPDF page-label conversion fixture.');
    }

    return $path;
};

return [
    'carries supplied page labels into convert_single_pdf metadata and pipeline context' => static function (TestRunner $t) use ($makeTempFile): void {
        $path = $makeTempFile("%PDF-1.4\n% supplied page label boundary\n%%EOF\n");
        try {
            $seen = [];
            $result = (new CorePdfConverter())->convertWithSuppliedPages(
                $path,
                [
                    ['pnum' => 8, 'page_label' => 'Front iv', 'text' => 'Preface text', 'blocks' => []],
                    ['pnum' => 9, 'page_label' => 'Body 4', 'text' => 'Chapter text', 'blocks' => []],
                    ['pnum' => 10, 'page_label' => '', 'text' => 'Empty label text', 'blocks' => []],
                    ['pnum' => 11, 'page_label' => 12, 'text' => 'Malformed label text', 'blocks' => []],
                    ['pnum' => 12, 'text' => 'Unlabeled text', 'blocks' => []],
                    ['pnum' => 13, 'page_label' => 'Appendix-Z', 'text' => 'Appendix text', 'blocks' => []],
                ],
                [['title' => 'Appendix', 'level' => 1, 'page_index' => 5]],
                static function (array $pages, array $context) use (&$seen): array {
                    $seen = ['pages' => $pages, 'context' => $context];

                    return [
                        'text' => "Preface text\n\nChapter text\n\nAppendix text",
                        'images' => [],
                        'metadata' => [
                            'pipeline_page_labels' => $context['page_labels'] ?? null,
                            'pipeline_page_label_rows' => $context['page_label_rows'] ?? null,
                        ],
                    ];
                },
                startPage: 8,
                metadata: ['languages' => ['English']],
                documentPageCount: 14
            );

            $expectedRows = [
                ['page_index' => 0, 'page_number' => 1, 'page_label' => 'Front iv'],
                ['page_index' => 1, 'page_number' => 2, 'page_label' => 'Body 4'],
                ['page_index' => 5, 'page_number' => 6, 'page_label' => 'Appendix-Z'],
            ];

            $t->same(['Front iv', 'Body 4', 'Appendix-Z'], $result['metadata']['page_labels']);
            $t->same($expectedRows, $result['metadata']['page_label_rows']);
            $t->same(['Front iv', 'Body 4', 'Appendix-Z'], $result['metadata']['pipeline_page_labels']);
            $t->same($expectedRows, $result['metadata']['pipeline_page_label_rows']);
            $t->same(['Front iv', 'Body 4', 'Appendix-Z'], $seen['context']['page_labels']);
            $t->same($expectedRows, $seen['context']['page_label_rows']);
            $t->same('supplied-pages', $seen['context']['stage']);
            $t->same(6, $seen['context']['trimmed_document_page_count']);
            $t->same(['Front iv', 'Body 4', '', 'Appendix-Z'], array_values(array_filter(array_column($seen['pages'], 'page_label'), static fn (mixed $label): bool => is_string($label))));
            $t->same(false, str_contains($result['text'], 'Front iv'));
            $t->same([0, 1, 5], array_column($result['metadata']['page_label_rows'], 'page_index'));
            $t->same('Appendix', $result['metadata']['pdf_toc'][0]['title']);
        } finally {
            unlink($path);
        }
    },
    'keeps unlabeled supplied pages free of invented page-label metadata' => static function (TestRunner $t) use ($makeTempFile): void {
        $path = $makeTempFile("%PDF-1.4\n% unlabeled supplied page boundary\n%%EOF\n");
        try {
            $seen = [];
            $result = (new CorePdfConverter())->convertWithSuppliedPages(
                $path,
                [
                    ['pnum' => 0, 'text' => 'Plain page', 'blocks' => []],
                    ['pnum' => 1, 'page_label' => '', 'text' => 'Blank label page', 'blocks' => []],
                ],
                [],
                static function (array $pages, array $context) use (&$seen): array {
                    $seen = ['pages' => $pages, 'context' => $context];

                    return ['text' => 'Plain page', 'images' => [], 'metadata' => []];
                },
                documentPageCount: 2
            );

            $t->same(false, array_key_exists('page_labels', $result['metadata']));
            $t->same(false, array_key_exists('page_label_rows', $result['metadata']));
            $t->same(false, array_key_exists('page_labels', $seen['context']));
            $t->same(false, array_key_exists('page_label_rows', $seen['context']));
            $t->same(2, $result['metadata']['pages']);
            $t->same(2, $seen['context']['page_count']);
            $t->same(2, count($seen['pages']));
        } finally {
            unlink($path);
        }
    },
];
