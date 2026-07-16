<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];
$mojibakeEAcute = (string) hex2bin('c383c2a9');
$mojibakeRightQuote = (string) hex2bin('c3a2e282ace284a2');
$repairedEAcute = (string) hex2bin('c3a9');
$repairedRightQuote = (string) hex2bin('e28099');
$validATilde = (string) hex2bin('c3a3');

$page = [
    'page' => 52,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'blocks' => [[
        'bbox' => [72.0, 144.0, 430.0, 176.0],
        'lines' => [[
            'bbox' => [72.0, 144.0, 430.0, 176.0],
            'spans' => [
                [
                    'text' => "Plugin caf{$mojibakeEAcute} d{$mojibakeRightQuote}import\n",
                    'bbox' => [72.0, 144.0, 260.0, 158.0],
                    'font' => $font,
                    'raw_encoding_payload' => 'hidden mojibake payload must not cross dictionary_output',
                ],
                [
                    'text' => " keeps S{$validATilde}o Paulo r{$repairedEAcute}sum{$repairedEAcute} intact\n",
                    'bbox' => [260.0, 144.0, 430.0, 158.0],
                    'font' => $font,
                ],
            ],
        ]],
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$visibleText = $blocks[0]['text'] ?? '';
$visibleSpan = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0] ?? [];
$sourceSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$expectedVisible = "Plugin caf{$repairedEAcute} d{$repairedRightQuote}import keeps S{$validATilde}o Paulo r{$repairedEAcute}sum{$repairedEAcute} intact";
if ($visibleText !== $expectedVisible
    || str_contains($visibleText, $mojibakeEAcute)
    || str_contains($visibleText, $mojibakeRightQuote)
    || !str_contains((string) ($sourceSpan['text'] ?? ''), $mojibakeEAcute)
    || str_contains($encoded, 'hidden mojibake payload must not cross dictionary_output')
) {
    throw new RuntimeException('Expected marker visible spans to repair mojibake while preserving pdftext dictionary source text.');
}

echo '<!-- markerpdf-pdftext-dictionary-unicode-repair-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-unicode-repair-currentbase',
    'source_truth' => 'marker Span text is passed through ftfy.fix_text after pdftext.dictionary_output postprocessing; native PHP repairs common Windows-1252/UTF-8 mojibake only for visible spans',
    'support_component' => 'pdf-text-dictionary-core',
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'visible_text' => $visibleText,
    'visible_span_text' => $visibleSpan['text'] ?? null,
    'char_block_source_text_preserved' => str_contains((string) ($sourceSpan['text'] ?? ''), $mojibakeEAcute),
    'visible_mojibake_repaired' => !str_contains($visibleText, $mojibakeEAcute) && !str_contains($visibleText, $mojibakeRightQuote),
    'valid_utf8_accents_preserved' => str_contains($visibleText, "S{$validATilde}o Paulo") && str_contains($visibleText, "r{$repairedEAcute}sum{$repairedEAcute}"),
    'non_core_payload_excluded' => !str_contains($encoded, 'hidden mojibake payload must not cross dictionary_output'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
