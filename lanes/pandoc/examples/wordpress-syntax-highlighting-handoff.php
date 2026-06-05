<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
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
$numberedCodeBlock = new AstNode('code_block', [
    'id' => 'migration-review',
    'classes' => ['php', 'numberLines', 'lineAnchors'],
    'attributes' => ['startFrom' => '42'],
    'text' => "<?php\necho esc_html(\$title);",
]);
$numbered = $highlighter->highlightCodeBlock($numberedCodeBlock, 'pygments');
$numberedWordpressBlock = $highlighter->wordpressHtmlBlock($numberedCodeBlock, 'pygments');

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
    if (($numbered['lineNumbering']['start'] ?? null) !== 42) {
        throw new RuntimeException('Expected Pandoc startFrom line-number handoff');
    }
    if (!str_contains($numberedWordpressBlock, '<pre class="sourceCode numberSource php numberLines lineAnchors">')) {
        throw new RuntimeException('Expected Pandoc numberSource class handoff');
    }
    if (!str_contains($numberedWordpressBlock, '<span id="migration-review-42"><a href="#migration-review-42"></a>')) {
        throw new RuntimeException('Expected Pandoc line anchor handoff');
    }

    echo "syntax highlighting handoff self-test ok\n";
    exit(0);
}

echo "Syntax highlighting handoff for WordPress import:\n";
echo "language: " . $highlighted['language'] . "\n";
echo "highlightedHtml:\n" . $highlighted['html'] . "\n";
echo "numberedHighlightedHtml:\n" . $numbered['html'] . "\n";
echo "wordpressBlock:\n" . $wordpressBlock . "\n";
