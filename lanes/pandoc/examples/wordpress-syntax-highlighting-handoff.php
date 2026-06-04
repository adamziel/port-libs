<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\SyntaxHighlighter;

$fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
if (!is_string($fixture)) {
    throw new RuntimeException('Unable to read syntax highlight fixture');
}

$document = (new MarkdownReader())->read($fixture);
$codeBlock = $document->children[0] ?? null;
if (!$codeBlock instanceof PortLibs\Pandoc\AstNode || $codeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to start with a code block');
}

$highlighter = new SyntaxHighlighter();
$highlighted = $highlighter->highlightCodeBlock($codeBlock, 'pygments');
$wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'pygments');

if (($argv[1] ?? '') === '--self-test') {
    if (($highlighted['language'] ?? '') !== 'php') {
        throw new RuntimeException('Expected PHP language alias handoff');
    }
    if (!str_contains($highlighted['html'], '<span class="kw">function</span>')) {
        throw new RuntimeException('Expected keyword token span');
    }
    if (!str_contains($highlighted['html'], '<span class="fu">render_title</span>')) {
        throw new RuntimeException('Expected function token span');
    }
    if (!str_contains($wordpressBlock, '<style data-pandoc-highlight-style="pygments">')) {
        throw new RuntimeException('Expected WordPress highlight style metadata');
    }

    echo "syntax highlighting handoff self-test ok\n";
    exit(0);
}

echo "Syntax highlighting handoff for WordPress import:\n";
echo "language: " . $highlighted['language'] . "\n";
echo "highlightedHtml:\n" . $highlighted['html'] . "\n";
echo "wordpressBlock:\n" . $wordpressBlock . "\n";
