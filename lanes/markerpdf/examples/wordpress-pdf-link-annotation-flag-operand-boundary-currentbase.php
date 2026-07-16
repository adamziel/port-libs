<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Clean flags Tailed hidden Tailed print Indirect hidden Valid hidden) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 158 718] /F 4 /Contents (Clean flag review) /A << /S /URI /URI (https://example.com/clean-flags) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [168 700 270 718] /F 2 90 0 R /Contents (Tailed hidden flag review) /A << /S /URI /URI (https://example.com/tailed-hidden-flag) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [280 700 374 718] /F 4 90 0 R /Contents (Tailed print flag review) /A << /S /URI /URI (https://example.com/tailed-print-flag) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [384 700 496 718] /F 20 0 R /Contents (Indirect hidden flag review) /A << /S /URI /URI (https://example.com/indirect-hidden-flag) >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [506 700 600 718] /F 2 /Contents (Valid hidden flag review) /A << /S /URI /URI (https://example.com/valid-hidden-flag) >> >>\nendobj\n"
    . "20 0 obj\n2 90 0 R\nendobj\n"
    . "90 0 obj\n<< /S /JavaScript /JS (flagOperandTailReview\\(\\)) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 600.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 600.0, 718.0],
            'spans' => [
                ['text' => 'Clean flags', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed hidden', 'bbox' => [168.0, 700.0, 270.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed print', 'bbox' => [280.0, 700.0, 374.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Indirect hidden', 'bbox' => [384.0, 700.0, 496.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Valid hidden', 'bbox' => [506.0, 700.0, 600.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$linkExtractor = new PdfLinkAnnotationExtractor();
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$encodedPromotedReview = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-annotation-flag-operand-boundary',
    'native_boundary' => 'tailed Link annotation /F flag operands do not donate hidden or print bits before WordPress link promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_flags' => array_column($annotations[0]['annotations'] ?? [], 'annotation_flags'),
    'annotation_visibility' => array_column($annotations[0]['annotations'] ?? [], 'annotation_visibility'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'promoted_link_flags' => array_column($links[0]['links'] ?? [], 'annotation_flags'),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'valid_hidden_promoted' => str_contains($encodedPromotedReview, 'valid-hidden-flag'),
    'tail_action_leaked' => str_contains($encodedPromotedReview, 'flagOperandTailReview'),
    'visible_text_excludes_annotation_payloads' => !str_contains($plainText, 'Clean flag review')
        && !str_contains($plainText, 'Tailed hidden flag review')
        && !str_contains($plainText, 'Tailed print flag review')
        && !str_contains($plainText, 'Indirect hidden flag review')
        && !str_contains($plainText, 'Valid hidden flag review')
        && !str_contains($plainText, 'flagOperandTailReview'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $summary['annotation_flags'] !== [4, 0, 0, 0, 2]
    || $summary['annotation_visibility'] !== ['visible', 'visible', 'visible', 'visible', 'hidden']
    || $summary['promoted_link_objects'] !== [7, 8, 9, 10]
    || $summary['promoted_link_flags'] !== [4, 0, 0, 0]
    || $summary['valid_hidden_promoted'] !== false
    || $summary['tail_action_leaked'] !== false
    || $summary['visible_text_excludes_annotation_payloads'] !== true
) {
    throw new RuntimeException('Unexpected markerPDF link annotation flag operand boundary smoke output.');
}

echo '<!-- markerpdf:pdf-link-annotation-flag-operand-boundary-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $flags = htmlspecialchars(implode(' ', $span['link_annotation_flag_names'] ?? []), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '" data-markerpdf-annotation-flags="' . $flags . '">' . $text . '</a>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
