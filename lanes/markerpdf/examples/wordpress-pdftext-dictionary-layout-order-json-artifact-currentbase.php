<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, array $lines): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'blocks' => [[
            'lines' => array_map(
                static fn (array $line): array => [
                    'bbox' => $line['bbox'],
                    'spans' => [[
                        'text' => $line['text'],
                        'bbox' => $line['bbox'],
                        'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$jsonDecodedList = static function (array $value): array {
    $decoded = json_decode(
        json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        false,
        512,
        JSON_THROW_ON_ERROR
    );
    if (!is_array($decoded)) {
        throw new RuntimeException('Expected JSON-decoded supplied artifact fixture to remain a list.');
    }

    return $decoded;
};

$path = sys_get_temp_dir() . '/markerpdf-json-artifact-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% json-decoded pdftext layout order boundary current-base smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        $jsonDecodedList([
            $page(701, [
                ['text' => 'JSON artifact cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(702, [
                ['text' => 'Second JSON artifact import column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First JSON artifact import heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ]),
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => $jsonDecodedList([
                ['metadata' => ['document_page' => 701], 'image' => 'json-artifact-cover-layout-render'],
                ['metadata' => ['document_page' => 702], 'image' => 'json-artifact-selected-layout-render'],
            ]),
            'layout_results' => $jsonDecodedList([
                [
                    'metadata' => ['document_page' => 701],
                    'layout_result' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ],
                    ],
                    'raw_payload' => 'hidden json cover layout payload',
                ],
                [
                    'metadata' => ['document_page' => 702],
                    'layout_result' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                    ],
                    'raw_payload' => 'hidden json selected layout payload',
                ],
            ]),
            'order_images' => $jsonDecodedList([
                ['metadata' => ['document_page' => 701], 'image' => 'json-artifact-cover-order-render'],
                ['metadata' => ['document_page' => 702], 'image' => 'json-artifact-selected-order-render'],
            ]),
            'order_results' => $jsonDecodedList([
                [
                    'metadata' => ['document_page' => 701],
                    'order_result' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ],
                    ],
                    'raw_payload' => 'hidden json cover order payload',
                ],
                [
                    'metadata' => ['document_page' => 702],
                    'order_result' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                    ],
                    'raw_payload' => 'hidden json selected order payload',
                ],
            ]),
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$headingPromoted = str_contains($text, '# First Json Artifact Import Heading.');
$bodyPreserved = str_contains($text, 'Second JSON artifact import column.');
$headingBeforeBody = strpos($text, '# First Json Artifact Import Heading.') < strpos($text, 'Second JSON artifact import column.');
$coverExcluded = !str_contains($text, 'JSON artifact cover should not import.');
$payloadExcluded = !str_contains($encoded, 'hidden json cover layout payload')
    && !str_contains($encoded, 'hidden json selected layout payload')
    && !str_contains($encoded, 'hidden json cover order payload')
    && !str_contains($encoded, 'hidden json selected order payload');

if (
    !$headingPromoted
    || !$bodyPreserved
    || !$headingBeforeBody
    || !$coverExcluded
    || !$payloadExcluded
    || ($result['metadata']['layout_plan']['assigned_pages'] ?? null) !== 1
    || ($result['metadata']['order_plan']['assigned_pages'] ?? null) !== 1
) {
    throw new RuntimeException('Expected JSON-decoded supplied layout/order artifacts to align to the selected pdftext page without payload leakage.');
}

foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $block) {
    $block = trim($block);
    if ($block === '') {
        continue;
    }

    if (str_starts_with($block, '# ')) {
        echo "<!-- wp:heading {\"level\":1} -->\n";
        echo '<h1>' . htmlspecialchars(substr($block, 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h1>\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-json-artifact-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-json-artifact-currentbase',
    'source_truth' => 'markerPDF obtains selected pdftext.dictionary_output pages, renders the trimmed PDFium document, then zips layout and order model results to selected pages before WordPress Markdown finalization',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'json_decoded_plain_objects_normalized' => true,
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_assigned_pages' => $result['metadata']['layout_plan']['assigned_pages'] ?? null,
    'order_assigned_pages' => $result['metadata']['order_plan']['assigned_pages'] ?? null,
    'heading_promoted' => $headingPromoted,
    'body_preserved' => $bodyPreserved,
    'heading_before_body' => $headingBeforeBody,
    'cover_excluded' => $coverExcluded,
    'payload_excluded' => $payloadExcluded,
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
