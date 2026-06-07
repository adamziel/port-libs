<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

$content = 'BT /F1 12 Tf 72 720 Td (Guide link Query link Parent file Network path Backslash path) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 148 718] /Contents (Guide path review) /A << /S /URI /URI (guide.html#setup) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [158 700 238 718] /Contents (Query path review) /A << /S /URI /URI (?download=1) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [248 700 332 718] /Contents (Parent path review) /A << /S /URI /URI (../media/spec.pdf#attachment) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [342 700 432 718] /Contents (Network path review) /A << /S /URI /URI (//evil.example/import.pdf) >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [442 700 532 718] /Contents (Backslash path review) /A << /S /URI /URI (..\\\\evil\\\\import.pdf) >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 532.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 532.0, 718.0],
            'spans' => [
                ['text' => 'Guide link', 'bbox' => [72.0, 700.0, 148.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Query link', 'bbox' => [158.0, 700.0, 238.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Parent file', 'bbox' => [248.0, 700.0, 332.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Network path', 'bbox' => [342.0, 700.0, 432.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Backslash path', 'bbox' => [442.0, 700.0, 532.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));

$encodedLinks = json_encode($links, JSON_UNESCAPED_SLASHES) ?: '';
$result = [
    'scenario' => 'wordpress-pdf-link-annotation-relative-path-currentbase',
    'annotation_action_safety' => array_map(
        static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
        $annotationPages[0]['annotations'] ?? []
    ),
    'promoted_annotation_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'promoted_uris' => array_column($links[0]['links'] ?? [], 'uri'),
    'relative_flags' => array_column($links[0]['links'] ?? [], 'uri_relative'),
    'base_resolution_flags' => array_column($links[0]['links'] ?? [], 'uri_resolved_from_base'),
    'markdown' => $blocks[0]['text'] ?? '',
    'path_relative_without_base_promoted' => ($links[0]['links'][0]['uri'] ?? null) === 'guide.html#setup',
    'query_relative_without_base_promoted' => ($links[0]['links'][1]['uri'] ?? null) === '?download=1',
    'parent_relative_without_base_promoted' => ($links[0]['links'][2]['uri'] ?? null) === '../media/spec.pdf#attachment',
    'network_path_rejected' => !str_contains($encodedLinks, 'evil.example'),
    'backslash_path_rejected' => !str_contains($encodedLinks, '..\\\\evil'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (
    $result['promoted_annotation_objects'] !== [7, 8, 9]
    || $result['path_relative_without_base_promoted'] !== true
    || $result['query_relative_without_base_promoted'] !== true
    || $result['parent_relative_without_base_promoted'] !== true
    || $result['network_path_rejected'] !== true
    || $result['backslash_path_rejected'] !== true
    || str_contains($result['markdown'], 'evil.example')
) {
    throw new RuntimeException('Path-relative Link annotation smoke failed.');
}
