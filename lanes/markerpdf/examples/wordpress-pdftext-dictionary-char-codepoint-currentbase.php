<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];
$page = [
    'page' => 54,
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'width' => 600.0,
    'height' => 800.0,
    'rotation' => 0,
    'blocks' => [[
        'bbox' => [0.10, 0.10, 0.44, 0.14],
        'lines' => [[
            'bbox' => [0.10, 0.10, 0.44, 0.14],
            'spans' => [[
                'text' => "Emoji character rows\n",
                'bbox' => [0.10, 0.10, 0.44, 0.14],
                'font' => $font,
                'rotation' => 0,
                'char_start_idx' => 0,
                'char_end_idx' => 1,
                'chars' => [
                    [
                        'char' => "\u{1F600}",
                        'bbox' => [0.10, 0.10, 0.12, 0.14],
                        'rotation' => 0,
                        'font' => $font,
                        'char_idx' => 0,
                    ],
                    [
                        'char' => "\u{00E9}",
                        'bbox' => [0.12, 0.10, 0.14, 0.14],
                        'rotation' => 0,
                        'font' => $font,
                        'char_idx' => 1,
                    ],
                ],
            ]],
        ]],
    ]],
];

$extractor = new PdfTextDocumentExtractor();
$document = $extractor->getTextBlocks([$page], maxPages: 1, keepChars: true);
$span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0] ?? [];
$charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0] ?? [];

$emptyCharPage = $page;
$emptyCharPage['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char'] = '';
$emptyCharRejected = false;
try {
    $extractor->getTextBlocks([$emptyCharPage], maxPages: 1, keepChars: true);
} catch (InvalidArgumentException) {
    $emptyCharRejected = true;
}

$twoCharacterPage = $page;
$twoCharacterPage['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char'] = 'AB';
$twoCharacterRejected = false;
try {
    $extractor->getTextBlocks([$twoCharacterPage], maxPages: 1, keepChars: true);
} catch (InvalidArgumentException) {
    $twoCharacterRejected = true;
}

$combiningSequencePage = $page;
$combiningSequencePage['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char'] = "e\u{0301}";
$combiningSequenceRejected = false;
try {
    $extractor->getTextBlocks([$combiningSequencePage], maxPages: 1, keepChars: true);
} catch (InvalidArgumentException) {
    $combiningSequenceRejected = true;
}

$singleCodepointAccepted = ($span['chars'][0]['char'] ?? null) === "\u{1F600}"
    && ($span['chars'][1]['char'] ?? null) === "\u{00E9}"
    && ($charSpan['chars'][0]['char'] ?? null) === "\u{1F600}"
    && ($charSpan['chars'][1]['char'] ?? null) === "\u{00E9}";

if (!$singleCodepointAccepted || !$emptyCharRejected || !$twoCharacterRejected || !$combiningSequenceRejected) {
    throw new RuntimeException('Expected pdftext kept-character rows to contain exactly one Unicode codepoint.');
}

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));

echo '<!-- markerpdf-pdftext-dictionary-char-codepoint-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-char-codepoint-currentbase',
    'source_truth' => 'pdftext.pdf.chars.get_chars emits one chr(FPDFText_GetUnicode(...)) value per kept character row before dictionary_output stores optional chars',
    'support_component' => 'pdf-text-dictionary-core',
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'single_codepoint_character_rows_accepted' => $singleCodepointAccepted,
    'empty_character_row_rejected' => $emptyCharRejected,
    'two_character_row_rejected' => $twoCharacterRejected,
    'combining_sequence_row_rejected' => $combiningSequenceRejected,
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
