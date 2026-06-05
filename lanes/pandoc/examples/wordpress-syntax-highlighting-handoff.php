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
$diffCodeBlock = $document->children[3] ?? null;
if (!$diffCodeBlock instanceof PortLibs\Pandoc\AstNode || $diffCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a diff code block');
}
$diff = $highlighter->highlightCodeBlock($diffCodeBlock, 'tango');
$diffWordpressBlock = $highlighter->wordpressHtmlBlock($diffCodeBlock, 'tango');
$markdownCodeBlock = $document->children[4] ?? null;
if (!$markdownCodeBlock instanceof PortLibs\Pandoc\AstNode || $markdownCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Markdown code block');
}
$markdown = $highlighter->highlightCodeBlock($markdownCodeBlock, 'kate');
$markdownWordpressBlock = $highlighter->wordpressHtmlBlock($markdownCodeBlock, 'kate');
$rubyCodeBlock = $document->children[5] ?? null;
if (!$rubyCodeBlock instanceof PortLibs\Pandoc\AstNode || $rubyCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Ruby code block');
}
$ruby = $highlighter->highlightCodeBlock($rubyCodeBlock, 'espresso');
$rubyWordpressBlock = $highlighter->wordpressHtmlBlock($rubyCodeBlock, 'espresso');
$luaCodeBlock = $document->children[6] ?? null;
if (!$luaCodeBlock instanceof PortLibs\Pandoc\AstNode || $luaCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Lua code block');
}
$lua = $highlighter->highlightCodeBlock($luaCodeBlock, 'breezedark');
$luaWordpressBlock = $highlighter->wordpressHtmlBlock($luaCodeBlock, 'breezedark');
$typescriptCodeBlock = $document->children[7] ?? null;
if (!$typescriptCodeBlock instanceof PortLibs\Pandoc\AstNode || $typescriptCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a TypeScript code block');
}
$typescript = $highlighter->highlightCodeBlock($typescriptCodeBlock, 'kate');
$typescriptWordpressBlock = $highlighter->wordpressHtmlBlock($typescriptCodeBlock, 'kate');
$customThemeJson = json_encode([
    'name' => 'Review Import',
    'text-color' => '#f8f8f2',
    'background-color' => '#101820',
    'line-number-color' => '#8f9aae',
    'line-number-background-color' => '#202a35',
    'token-styles' => [
        'KeywordTok' => ['text-color' => '#ffcc00', 'bold' => true],
        'StringTok' => ['text-color' => '#7bd88f'],
        'CommentTok' => ['text-color' => '#7f8c8d', 'italic' => true],
        'FunctionTok' => ['text-color' => '#80dfff', 'underline' => true],
        'VariableTok' => ['text-color' => '#ff9f43'],
        'OperatorTok' => ['text-color' => '#ff6b6b'],
    ],
], JSON_THROW_ON_ERROR);
$customThemeCodeBlock = new AstNode('code_block', [
    'id' => 'custom-theme-review',
    'classes' => ['php', 'numberLines'],
    'attributes' => ['startFrom' => '10'],
    'text' => 'echo esc_html($title); // review',
]);
$customTheme = $highlighter->highlightCodeBlock($customThemeCodeBlock, 'pygments', ['themeJson' => $customThemeJson]);
$customThemeWordpressBlock = $highlighter->wordpressHtmlBlock($customThemeCodeBlock, 'pygments', ['themeJson' => $customThemeJson]);

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
    if (($diff['language'] ?? '') !== 'diff') {
        throw new RuntimeException('Expected patch alias to normalize to diff');
    }
    if (($diff['lineNumbering']['start'] ?? null) !== 9) {
        throw new RuntimeException('Expected diff source startFrom line-number handoff');
    }
    if (!str_contains($diff['html'], '<pre class="sourceCode numberSource patch numberLines"><code class="sourceCode diff" style="counter-reset: source-line 8;">')) {
        throw new RuntimeException('Expected numbered diff source wrapper handoff');
    }
    if (!str_contains($diff['html'], '<span class="re">diff --git a/content.php b/content.php</span>')) {
        throw new RuntimeException('Expected diff header token handoff');
    }
    if (!str_contains($diff['html'], '<span class="al">-echo $old_title;</span>')) {
        throw new RuntimeException('Expected deleted diff line token handoff');
    }
    if (!str_contains($diff['html'], '<span class="in">+echo esc_html($new_title);</span>')) {
        throw new RuntimeException('Expected added diff line token handoff');
    }
    if (!str_contains($diffWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected diff WordPress style metadata');
    }
    if (!str_contains($diffWordpressBlock, '<span class="co">\ No newline at end of file</span>')) {
        throw new RuntimeException('Expected diff no-newline diagnostic token handoff');
    }
    if (($markdown['language'] ?? '') !== 'markdown') {
        throw new RuntimeException('Expected md alias to normalize to Markdown highlighting');
    }
    if (($markdown['lineNumbering']['start'] ?? null) !== 5) {
        throw new RuntimeException('Expected Markdown source startFrom line-number handoff');
    }
    if (!str_contains($markdown['html'], '<span class="re"># Migration Review</span>')) {
        throw new RuntimeException('Expected Markdown heading token handoff');
    }
    if (!str_contains($markdown['html'], '<span class="op">- </span><span class="cn">[x]</span> Preserve <span class="ot">[media](uploads/hero.png)</span>')) {
        throw new RuntimeException('Expected Markdown task-list and link token handoff');
    }
    if (!str_contains($markdown['html'], '<span class="st">`legacy_shortcode`</span>')) {
        throw new RuntimeException('Expected Markdown code-span token handoff');
    }
    if (!str_contains($markdownWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected Markdown WordPress style metadata');
    }
    if (!str_contains($markdownWordpressBlock, '<span class="pp">``` {.php}</span>')) {
        throw new RuntimeException('Expected nested Markdown fence token handoff');
    }
    if (($ruby['language'] ?? '') !== 'ruby') {
        throw new RuntimeException('Expected rb alias to normalize to Ruby highlighting');
    }
    if (!str_contains($ruby['html'], '<span class="fu">require</span> <span class="st">&#039;json&#039;</span>')) {
        throw new RuntimeException('Expected Ruby require token handoff');
    }
    if (!str_contains($ruby['html'], '<span class="kw">class</span> <span class="dt">ReviewPacket</span>')) {
        throw new RuntimeException('Expected Ruby class token handoff');
    }
    if (!str_contains($ruby['html'], '<span class="va">@path</span> <span class="op">=</span> <span class="va">path</span>')) {
        throw new RuntimeException('Expected Ruby instance/local variable token handoff');
    }
    if (!str_contains($rubyWordpressBlock, '<style data-pandoc-highlight-style="espresso">')) {
        throw new RuntimeException('Expected Ruby WordPress style metadata');
    }
    if (!str_contains($rubyWordpressBlock, '<span class="dt">JSON</span><span class="op">.</span><span class="fu">parse</span>')) {
        throw new RuntimeException('Expected Ruby constant and method token handoff');
    }
    if (($lua['language'] ?? '') !== 'lua') {
        throw new RuntimeException('Expected pandoc-lua alias to normalize to Lua highlighting');
    }
    if (($lua['lineNumbering']['start'] ?? null) !== 3) {
        throw new RuntimeException('Expected Lua source startFrom line-number handoff');
    }
    if (!str_contains($lua['html'], '<span class="kw">function</span> <span class="fu">Header</span>')) {
        throw new RuntimeException('Expected Lua function token handoff');
    }
    if (!str_contains($lua['html'], '<span class="dt">pandoc</span><span class="op">.</span><span class="va">utils</span><span class="op">.</span><span class="fu">stringify</span>')) {
        throw new RuntimeException('Expected Lua pandoc.utils method token handoff');
    }
    if (!str_contains($luaWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected Lua WordPress style metadata');
    }
    if (!str_contains($luaWordpressBlock, '<span class="kw">return</span> <span class="dt">pandoc</span><span class="op">.</span><span class="fu">Div</span>')) {
        throw new RuntimeException('Expected Lua pandoc constructor handoff');
    }
    if (($typescript['language'] ?? '') !== 'typescript') {
        throw new RuntimeException('Expected ts alias to normalize to TypeScript highlighting');
    }
    if (($typescript['lineNumbering']['start'] ?? null) !== 12) {
        throw new RuntimeException('Expected TypeScript source startFrom line-number handoff');
    }
    if (!str_contains($typescript['html'], '<span class="kw">type</span> <span class="dt">BlockPayload</span>')) {
        throw new RuntimeException('Expected TypeScript type alias token handoff');
    }
    if (!str_contains($typescript['html'], '<span class="dt">Record</span><span class="op">&lt;</span><span class="dt">string</span><span class="op">,</span> <span class="dt">unknown</span><span class="op">&gt;;</span>')) {
        throw new RuntimeException('Expected TypeScript generic type token handoff');
    }
    if (!str_contains($typescript['html'], '<span class="va">payload</span><span class="op">.</span><span class="va">meta</span><span class="op">?.</span><span class="va">sourceId</span> <span class="op">!==</span> <span class="dt">undefined</span>')) {
        throw new RuntimeException('Expected TypeScript optional chaining token handoff');
    }
    if (!str_contains($typescriptWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected TypeScript WordPress style metadata');
    }
    if (!str_contains($typescriptWordpressBlock, '<span class="kw">export</span> <span class="kw">async</span> <span class="kw">function</span> <span class="fu">migrateBlock</span>')) {
        throw new RuntimeException('Expected TypeScript async function token handoff');
    }
    if (($customTheme['style'] ?? '') !== 'review-import') {
        throw new RuntimeException('Expected custom Pandoc JSON theme name handoff');
    }
    if (!str_contains($customTheme['css'], '.sourceCode .kw { color: #ffcc00; font-weight: 700; }')) {
        throw new RuntimeException('Expected custom theme keyword CSS handoff');
    }
    if (!str_contains($customTheme['css'], 'color: #8f9aae; background-color: #202a35;')) {
        throw new RuntimeException('Expected custom theme line-number CSS handoff');
    }
    if (!str_contains($customThemeWordpressBlock, '<style data-pandoc-highlight-style="review-import">')) {
        throw new RuntimeException('Expected custom theme WordPress style metadata');
    }
    if (!str_contains($customThemeWordpressBlock, '<span id="custom-theme-review-10"><a href="#custom-theme-review-10"></a><span class="kw">echo</span>')) {
        throw new RuntimeException('Expected custom theme numbered code handoff');
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
echo "diffHighlightedHtml:\n" . $diff['html'] . "\n";
echo "markdownHighlightedHtml:\n" . $markdown['html'] . "\n";
echo "rubyHighlightedHtml:\n" . $ruby['html'] . "\n";
echo "luaHighlightedHtml:\n" . $lua['html'] . "\n";
echo "typescriptHighlightedHtml:\n" . $typescript['html'] . "\n";
echo "customThemeHighlightedHtml:\n" . $customTheme['html'] . "\n";
echo "wordpressBlock:\n" . $wordpressBlock . "\n";
echo "writerHighlightedBlocks:\n" . $writerHighlightedBlocks . "\n";
echo "customThemeWordpressBlock:\n" . $customThemeWordpressBlock . "\n";
