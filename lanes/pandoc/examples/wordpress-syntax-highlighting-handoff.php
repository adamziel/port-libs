<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\SyntaxHighlighter;
use PortLibs\Pandoc\WordPressBlockWriter;

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
$writerHighlightedBlocks = (new WordPressBlockWriter([
    'highlightCodeBlocks' => true,
    'highlightStyle' => 'kate',
]))->write($document);
$numberedCodeBlock = new AstNode('code_block', [
    'id' => 'migration-review',
    'classes' => ['php', 'numberLines', 'lineAnchors'],
    'attributes' => ['startFrom' => '42'],
    'text' => "<?php\necho esc_html(\$title);",
]);
$numbered = $highlighter->highlightCodeBlock($numberedCodeBlock, 'pygments');
$numberedWordpressBlock = $highlighter->wordpressHtmlBlock($numberedCodeBlock, 'pygments');
$haskellCodeBlock = new AstNode('code_block', [
    'classes' => ['sourceCode', 'literate-haskell'],
    'attributes' => [],
    'text' => implode("\n", [
        '{- migration review -}',
        'module Review.Import where',
        'import Text.Pandoc (Pandoc)',
        'renderBlocks :: Pandoc -> Text',
        'status = Just 42',
    ]),
]);
$haskell = $highlighter->highlightCodeBlock($haskellCodeBlock, 'zenburn');
$haskellWordpressBlock = $highlighter->wordpressHtmlBlock($haskellCodeBlock, 'zenburn');
$latexCodeBlock = $document->children[2] ?? null;
if (!$latexCodeBlock instanceof PortLibs\Pandoc\AstNode || $latexCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a LaTeX code block');
}
$latex = $highlighter->highlightCodeBlock($latexCodeBlock, 'haddock');
$latexWordpressBlock = $highlighter->wordpressHtmlBlock($latexCodeBlock, 'haddock');

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
    if (!str_contains($writerHighlightedBlocks, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected WordPress writer highlight style metadata');
    }
    if (!str_contains($writerHighlightedBlocks, '<pre class="sourceCode php"><code class="sourceCode php">')) {
        throw new RuntimeException('Expected WordPress writer sourceCode handoff');
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
    if (($haskell['language'] ?? '') !== 'haskell') {
        throw new RuntimeException('Expected literate Haskell alias handoff');
    }
    if (!str_contains($haskell['html'], '<span class="kw">module</span> <span class="dt">Review.Import</span>')) {
        throw new RuntimeException('Expected Haskell module token handoff');
    }
    if (!str_contains($haskellWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected Haskell WordPress style metadata');
    }
    if (!str_contains($haskellWordpressBlock, '<span class="cn">Just</span> <span class="dv">42</span>')) {
        throw new RuntimeException('Expected Haskell constructor and number token handoff');
    }
    if (($latex['language'] ?? '') !== 'tex') {
        throw new RuntimeException('Expected LaTeX alias to normalize to TeX');
    }
    if (!str_contains($latex['html'], '<span class="kw">\\documentclass</span>')) {
        throw new RuntimeException('Expected TeX documentclass token handoff');
    }
    if (!str_contains($latex['html'], '<span class="va">$title$</span>')) {
        throw new RuntimeException('Expected Pandoc template variable token handoff inside TeX');
    }
    if (!str_contains($latexWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected TeX WordPress style metadata');
    }
    if (!str_contains($latexWordpressBlock, '<span class="fu">\\includegraphics</span>')) {
        throw new RuntimeException('Expected TeX includegraphics token handoff');
    }

    echo "syntax highlighting handoff self-test ok\n";
    exit(0);
}

echo "Syntax highlighting handoff for WordPress import:\n";
echo "language: " . $highlighted['language'] . "\n";
echo "highlightedHtml:\n" . $highlighted['html'] . "\n";
echo "numberedHighlightedHtml:\n" . $numbered['html'] . "\n";
echo "haskellHighlightedHtml:\n" . $haskell['html'] . "\n";
echo "latexHighlightedHtml:\n" . $latex['html'] . "\n";
echo "wordpressBlock:\n" . $wordpressBlock . "\n";
echo "writerHighlightedBlocks:\n" . $writerHighlightedBlocks . "\n";
