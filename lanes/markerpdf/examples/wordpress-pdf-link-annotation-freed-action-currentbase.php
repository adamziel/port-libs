<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Current action docs Freed action decoy) Tj ET';
$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset,
    $generation,
    $state
);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
$addObject(7, '<< /Type /Annot /Subtype /Link /Rect [72 700 190 718] /Contents (Current direct link review) /A << /S /URI /URI (https://example.com/current-action-docs) >> >>');
$addObject(8, '<< /Type /Annot /Subtype /Link /Rect [202 700 332 718] /Contents (Freed action review) /A 20 0 R >>');
$addObject(20, '<< /S /URI /URI (https://stale.example.com/freed-action) /Next << /S /JavaScript /JS (freedActionReview\(\)) >> >>');

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 21\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 20; $objectNumber++) {
    if ($objectNumber === 20) {
        $pdf .= $xrefRow(0, 1, 'f');
        continue;
    }

    $pdf .= isset($offsets[$objectNumber])
        ? $xrefRow($offsets[$objectNumber])
        : $xrefRow(0, 0, 'f');
}
$pdf .= "trailer\n<< /Size 21 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 332.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 332.0, 718.0],
            'spans' => [
                ['text' => 'Current action docs', 'bbox' => [72.0, 700.0, 190.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Freed action decoy', 'bbox' => [202.0, 700.0, 332.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-annotation-xref-free-action-boundary',
    'free_action_object_marked' => isset($freeObjects[20]),
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_action_counts' => array_map(static fn (array $annotation): int => count($annotation['actions'] ?? []), $annotations[0]['annotations'] ?? []),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'link_uri' => $links[0]['links'][0]['uri'] ?? null,
    'freed_action_promoted' => str_contains($encodedReview, 'stale.example.com/freed-action'),
    'freed_javascript_reviewed' => str_contains($encodedReview, 'freedActionReview'),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_imported' => str_contains($plainText, 'Current action docs Freed action decoy'),
    'annotation_payload_text_visible' => str_contains($plainText, 'Current direct link review') || str_contains($plainText, 'Freed action review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-annotation-freed-action-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
