<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Current comment trailer action docs) Tj ET';
$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset,
    $generation,
    $state
);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /OpenAction 8 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 276 718] /F 4 /Contents (Current comment trailer action review) /A 8 0 R /AA << /E 9 0 R >> >>');
$addObject(8, 0, '<< /S /URI /URI (https://example.com/current-comment-trailer-action) >>');
$addObject(9, 0, '<< /S /URI /URI (mailto:current-comment-trailer@example.test) >>');

$pdf .= "xref\n"
    . "0 6\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets['1:0:0'])
    . $xrefRow($offsets['2:0:1'])
    . $xrefRow($offsets['3:0:2'])
    . $xrefRow($offsets['4:0:3'])
    . $xrefRow($offsets['5:0:4'])
    . "% trailer << /Size 40 /Root 20 0 R /Prev 0 >>\n"
    . "7 3\n"
    . $xrefRow($offsets['7:0:5'])
    . $xrefRow($offsets['8:0:6'])
    . $xrefRow($offsets['9:0:7'])
    . "trailer\n<< /Size 40 /Root 1 0 R >>\n"
    . "startxref\n999999\n%%EOF\n";

$addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 276 718] /F 4 /Contents (Stale comment trailer action review) /A 8 0 R /AA << /E 9 0 R >> >>');
$addObject(8, 0, '<< /S /URI /URI (https://stale.example.com/comment-trailer-action-decoy) >>');
$addObject(9, 0, '<< /S /JavaScript /JS (staleCommentTrailerAction\(\)) >>');

$text = (new PdfTextExtractor())->extractPlainText($pdf);
$pages = [[
    'page' => 1,
    'blocks' => [[
        'type' => 'text',
        'lines' => [[
            'spans' => [[
                'text' => 'Current comment trailer action docs',
                'bbox' => [72.0, 700.0, 276.0, 718.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]];

$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$encodedLinks = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$smoke = [
    'scenario' => 'wordpress-pdf-xref-classic-rebuild-action-trailer-comment-currentbase',
    'native_boundary' => 'classic xref rebuild skips trailer tokens inside PDF comments before action review row selection',
    'current_text_selected' => $text === 'Current comment trailer action docs',
    'current_link_selected' => ($links[0]['links'][0]['uri'] ?? null) === 'https://example.com/current-comment-trailer-action',
    'current_additional_action_selected' => ($links[0]['links'][0]['additional_actions'][0]['uri'] ?? null) === 'mailto:current-comment-trailer@example.test',
    'wordpress_markdown_link_selected' => ($blocks[0]['text'] ?? null) === '[Current comment trailer action docs](https://example.com/current-comment-trailer-action)',
    'stale_action_excluded' => !str_contains($encodedLinks, 'https://stale.example.com/comment-trailer-action-decoy')
        && !str_contains($encodedLinks, 'staleCommentTrailerAction'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
];

foreach ([
    'current_text_selected',
    'current_link_selected',
    'current_additional_action_selected',
    'wordpress_markdown_link_selected',
    'stale_action_excluded',
] as $required) {
    if (($smoke[$required] ?? false) !== true) {
        throw new RuntimeException('Classic xref action trailer-comment smoke failed: ' . $required);
    }
}

echo '<!-- markerpdf-xref-classic-rebuild-action-trailer-comment-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES
) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p><a href="https://example.com/current-comment-trailer-action">Current comment trailer action docs</a></p>' . "\n";
echo "<!-- /wp:paragraph -->\n";
