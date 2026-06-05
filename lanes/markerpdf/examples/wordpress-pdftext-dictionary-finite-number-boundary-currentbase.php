<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'page' => 44,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'refs' => [[
        'page' => 44,
        'idx' => 1,
        'coord' => [72.0, 96.0],
    ]],
    'blocks' => [[
        'bbox' => [0.10, 0.12, 0.58, 0.15],
        'lines' => [[
            'bbox' => [0.10, 0.12, 0.58, 0.15],
            'spans' => [[
                'text' => 'Finite pdftext geometry imports into WordPress.',
                'bbox' => [0.10, 0.12, 0.58, 0.15],
                'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                'char_start_idx' => 0,
                'char_end_idx' => 6,
                'chars' => [[
                    'char' => 'F',
                    'bbox' => [0.10, 0.12, 0.12, 0.15],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'char_idx' => 0,
                ]],
            ]],
        ]],
    ]],
];

$extractor = new PdfTextDocumentExtractor();
$document = $extractor->getTextBlocks([$page], maxPages: 1, keepChars: true);

$malformedCases = [
    'page_bbox_infinity' => static function (array $candidate): array {
        $candidate['bbox'][2] = INF;
        return $candidate;
    },
    'span_bbox_nan' => static function (array $candidate): array {
        $candidate['blocks'][0]['lines'][0]['spans'][0]['bbox'][2] = NAN;
        return $candidate;
    },
    'font_size_infinity' => static function (array $candidate): array {
        $candidate['blocks'][0]['lines'][0]['spans'][0]['font']['size'] = INF;
        return $candidate;
    },
    'char_bbox_nan' => static function (array $candidate): array {
        $candidate['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['bbox'][3] = NAN;
        return $candidate;
    },
    'ref_coord_infinity' => static function (array $candidate): array {
        $candidate['refs'][0]['coord'][1] = INF;
        return $candidate;
    },
];

$rejected = [];
foreach ($malformedCases as $name => $mutate) {
    try {
        $extractor->getTextBlocks([$mutate($page)], maxPages: 1, keepChars: true);
    } catch (InvalidArgumentException) {
        $rejected[] = $name;
    }
}

if (count($rejected) !== count($malformedCases)) {
    throw new RuntimeException('Expected non-finite pdftext dictionary numeric values to be rejected.');
}

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0] ?? [];
$char = $span['chars'][0] ?? [];

echo '<!-- markerpdf:pdftext-dictionary-finite-number-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'pdf-text-dictionary-core',
    'source_truth' => 'pdftext.dictionary_output and markerPDF receive finite PDFium page, bbox, font, and reference numeric metadata before WordPress block conversion',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'valid_page_imported' => ($document['pages'][0]['pnum'] ?? null) === 44,
    'span_bbox' => $span['bbox'] ?? null,
    'char_bbox' => $char['bbox'] ?? null,
    'reference_url' => $document['pages'][0]['pdftext_source']['refs'][0]['url'] ?? null,
    'non_finite_cases_rejected' => $rejected,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $block['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
