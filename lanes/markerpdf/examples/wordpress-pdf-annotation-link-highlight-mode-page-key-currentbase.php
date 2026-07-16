<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Push link Tailed push Real page decoy) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Target page link) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 5 0 R /Annots [7 0 R 8 0 R 10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 6 0 R /Annots [10 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Valid push highlight review) /H /P /A << /S /URI /URI (https://example.com/push-link) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 250 718] /Contents (Tailed push highlight review) /H /P 4 0 R /A << /S /URI /URI (https://example.com/tailed-push) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /P 4 0 R /Rect [72 700 178 718] /Contents (Real page reference review) /H /O /A << /S /URI /URI (https://example.com/real-page-reference) >> >>\nendobj\n"
    . "%%EOF";

$pages = [
    [
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 360.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 360.0, 718.0],
                'spans' => [
                    ['text' => 'Push link', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed push', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Real page decoy', 'bbox' => [260.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ],
    [
        'pnum' => 1,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 178.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 178.0, 718.0],
                'spans' => [
                    ['text' => 'Target page link', 'bbox' => [72.0, 700.0, 178.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ],
];

$linkExtractor = new PdfLinkAnnotationExtractor();
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-annotation-link-highlight-mode-page-key-boundary',
    'native_boundary' => 'Link annotation highlight-mode /P operands do not masquerade as annotation page /P references',
    'page_one_annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'page_two_annotation_objects' => array_column($annotations[1]['annotations'] ?? [], 'annotation_object'),
    'page_one_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'page_two_link_objects' => array_column($links[1]['links'] ?? [], 'annotation_object'),
    'tailed_highlight_metadata_imported' => isset($links[0]['links'][1]['highlight_mode']),
    'real_page_reference_leaked_to_page_one' => str_contains(json_encode([$links[0] ?? []], JSON_UNESCAPED_SLASHES) ?: '', 'real-page-reference'),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_excludes_annotation_payloads' => !str_contains($plainText, 'Tailed push highlight review')
        && !str_contains($plainText, 'real-page-reference'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $summary['page_one_annotation_objects'] !== [7, 8]
    || $summary['page_two_annotation_objects'] !== [10]
    || $summary['page_one_link_objects'] !== [7, 8]
    || $summary['page_two_link_objects'] !== [10]
    || $summary['tailed_highlight_metadata_imported'] !== false
    || $summary['real_page_reference_leaked_to_page_one'] !== false
    || $summary['visible_text_excludes_annotation_payloads'] !== true
    || str_contains($encodedReview, 'staleActionExecution')
) {
    throw new RuntimeException('Unexpected markerPDF annotation link highlight-mode page-key boundary smoke output.');
}

echo '<!-- markerpdf:pdf-annotation-link-highlight-mode-page-key-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($linkedPages as $page) {
    echo "<!-- wp:paragraph -->\n<p>";
    foreach (($page['blocks'][0]['lines'][0]['spans'] ?? []) as $span) {
        $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if (isset($span['link_uri'])) {
            $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $highlight = htmlspecialchars((string) ($span['link_annotation_highlight_mode_label'] ?? 'metadata-suppressed'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo '<a href="' . $href . '" data-markerpdf-link-highlight="' . $highlight . '">' . $text . '</a>';
            continue;
        }

        echo $text;
    }
    echo "</p>\n<!-- /wp:paragraph -->\n";
}
