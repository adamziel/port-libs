<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\CorePdfConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$path = sys_get_temp_dir() . '/markerpdf-core-page-labels-smoke-' . bin2hex(random_bytes(4)) . '.pdf';
if (file_put_contents($path, "%PDF-1.4\n% WordPress supplied page labels smoke\n%%EOF\n") === false) {
    throw new RuntimeException('Unable to write temporary markerPDF supplied page label smoke fixture.');
}

try {
    $pages = [
        ['pnum' => 0, 'page_label' => 'Front iv', 'text' => 'Preface text for the imported handbook.'],
        ['pnum' => 1, 'page_label' => 'Body 4', 'text' => 'Chapter text that should stay searchable.'],
        ['pnum' => 2, 'page_label' => 'Appendix-Z', 'text' => 'Appendix text for review.'],
    ];

    $result = (new CorePdfConverter())->convertWithSuppliedPages(
        $path,
        $pages,
        [],
        static function (array $pages, array $context): array {
            $rows = $context['page_label_rows'] ?? [];
            if (!is_array($rows) || count($rows) !== count($pages)) {
                throw new RuntimeException('Expected supplied page labels in the conversion context.');
            }

            $rowsByIndex = [];
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['page_index'], $row['page_label'])) {
                    $rowsByIndex[(int) $row['page_index']] = (string) $row['page_label'];
                }
            }

            $html = '';
            foreach (array_values($pages) as $index => $page) {
                $label = $rowsByIndex[$index] ?? 'page ' . ($index + 1);
                $text = (string) ($page['text'] ?? '');
                $html .= '<!-- wp:separator {"className":"markerpdf-page-break","metadata":{"name":"PDF page '
                    . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '"}} -->' . "\n";
                $html .= '<hr class="wp-block-separator has-alpha-channel-opacity markerpdf-page-break"/>' . "\n";
                $html .= "<!-- /wp:separator -->\n\n";
                $html .= "<!-- wp:paragraph -->\n";
                $html .= '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
                $html .= "<!-- /wp:paragraph -->\n\n";
            }

            return [
                'text' => $html,
                'images' => [],
                'metadata' => [
                    'pipeline_page_labels' => $context['page_labels'] ?? [],
                    'pipeline_page_label_rows' => $rows,
                ],
            ];
        },
        metadata: ['languages' => ['English']],
        documentPageCount: count($pages)
    );

    $expected = ['Front iv', 'Body 4', 'Appendix-Z'];
    if (($result['metadata']['page_labels'] ?? []) !== $expected || ($result['metadata']['pipeline_page_labels'] ?? []) !== $expected) {
        throw new RuntimeException('Expected CorePdfConverter to preserve supplied PageLabels in metadata and pipeline context.');
    }

    echo '<!-- markerpdf-core-page-labels-supplied-boundary-smoke ' . htmlspecialchars(json_encode([
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
        'native_boundary' => 'convert_single_pdf supplied pages preserve page_label metadata for WordPress page breaks',
        'page_labels' => $result['metadata']['page_labels'],
        'pipeline_page_labels' => $result['metadata']['pipeline_page_labels'],
        'page_label_rows' => $result['metadata']['page_label_rows'],
        'labels_excluded_from_visible_paragraph_text' => !str_contains(strip_tags($result['text']), 'Front iv')
            && !str_contains(strip_tags($result['text']), 'Body 4')
            && !str_contains(strip_tags($result['text']), 'Appendix-Z'),
    ], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
    echo $result['text'];
} finally {
    @unlink($path);
}
