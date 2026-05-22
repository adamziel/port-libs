<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'joins hyphenated text lines like marker markdown postprocessing' => static function (TestRunner $t): void {
        $markdown = (new MarkdownPostProcessor())->mergeLines([
            'Clean hyphen-',
            'ated PDF lines continue',
            'into one paragraph.',
        ]);

        $t->same('Clean hyphenated PDF lines continue into one paragraph.', $markdown);
    },
    'keeps sentence boundaries as markdown paragraphs' => static function (TestRunner $t): void {
        $markdown = (new MarkdownPostProcessor())->mergeLines([
            'First imported sentence.',
            'Second imported sentence.',
        ]);

        $t->same("First imported sentence.\n\nSecond imported sentence.", $markdown);
    },
    'surrounds headings and escapes markdown-sensitive hash characters' => static function (TestRunner $t): void {
        $processor = new MarkdownPostProcessor();

        $t->same("\n## Data Liberation Notes\n", $processor->surroundBlock('data liberation notes', 'Section-header', 2));
        $t->same('Use \#tags in imported captions', $processor->surroundBlock('Use #tags in imported captions', 'Text'));
        $t->same("Item with \\#anchor\n", $processor->surroundBlock('Item with #anchor', 'List-item'));
    },
    'dewraps extracted PDF lines for WordPress import paragraphs' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Clean hyphen-) Tj T* (ated paragraphs keep) Tj T* (WordPress imports readable.) Tj ET';
        $lines = (new PdfTextExtractor())->extractTextLines($pdfWithContent($content));
        $markdown = (new MarkdownPostProcessor())->mergeLines($lines);

        $t->same('Clean hyphenated paragraphs keep WordPress imports readable.', $markdown);
    },
];
