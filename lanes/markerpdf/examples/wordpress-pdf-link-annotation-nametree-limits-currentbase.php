<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Current named jump Stale named jump Direct URI) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Current named destination body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 184 718] /Contents (Current named destination review) /Dest (Current Link) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [194 700 300 718] /Contents (Stale named destination review) /Dest (Stale Link) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [310 700 386 718] /Contents (Direct URI review) /A << /S /URI /URI (https://example.com/direct-current) >> >>\nendobj\n"
    . "10 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Limits [(Current Link) (Current Summary)] /Kids [21 0 R 22 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [(Current Link) (Current Summary)] /Names [(Current Link) [4 0 R /FitH 700] (Current Summary) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [(Stale Link) (Stale Link)] /Names [(Stale Link) [4 0 R /FitH 111] (zz-stale-link) [3 0 R /Fit]] >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 386.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 386.0, 718.0],
            'spans' => [
                ['text' => 'Current named jump', 'bbox' => [72.0, 700.0, 184.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale named jump', 'bbox' => [194.0, 700.0, 300.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Direct URI', 'bbox' => [310.0, 700.0, 386.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedLinks = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$markdown = (string) ($blocks[0]['text'] ?? '');

if (array_column($links[0]['links'] ?? [], 'annotation_object') !== [7, 9]) {
    throw new RuntimeException('Expected only current in-limits destination and direct URI annotations to promote.');
}
if (str_contains($encodedLinks, 'Stale Link') || str_contains($encodedLinks, 'zz-stale-link')) {
    throw new RuntimeException('Out-of-limits name-tree destination leaked into promoted link metadata.');
}
if ($markdown !== 'Current named jump Stale named jump [Direct URI](https://example.com/direct-current)') {
    throw new RuntimeException('Unexpected WordPress Markdown link rendering for name-tree limits boundary.');
}
if (str_contains($plainText, 'Stale Link') || str_contains($plainText, 'direct-current')) {
    throw new RuntimeException('Annotation destination/action payload leaked into visible PDF text.');
}

$summary = [
    'support_component' => 'native-pdf-link-annotation-nametree-limits-boundary',
    'native_boundary' => 'Link annotation named destinations honor /Names /Dests name-tree /Limits before WordPress span promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_action_safety_chains' => array_map(
        static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
        $annotations[0]['annotations'] ?? []
    ),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'current_destination_page' => $links[0]['links'][0]['destination_page'] ?? null,
    'stale_destination_promoted' => str_contains($encodedLinks, 'Stale Link') || str_contains($encodedLinks, 'zz-stale-link'),
    'direct_uri_promoted' => str_contains($markdown, 'https://example.com/direct-current'),
    'visible_text_imported' => str_contains($plainText, 'Current named jump Stale named jump Direct URI'),
    'annotation_payload_text_visible' => str_contains($plainText, 'Stale Link') || str_contains($plainText, 'direct-current'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-link-annotation-nametree-limits-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
