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
$pythonCodeBlock = $document->children[8] ?? null;
if (!$pythonCodeBlock instanceof PortLibs\Pandoc\AstNode || $pythonCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Python code block');
}
$python = $highlighter->highlightCodeBlock($pythonCodeBlock, 'monochrome');
$pythonWordpressBlock = $highlighter->wordpressHtmlBlock($pythonCodeBlock, 'monochrome');
$cppCodeBlock = $document->children[9] ?? null;
if (!$cppCodeBlock instanceof PortLibs\Pandoc\AstNode || $cppCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a C++ code block');
}
$cpp = $highlighter->highlightCodeBlock($cppCodeBlock, 'pygments');
$cppWordpressBlock = $highlighter->wordpressHtmlBlock($cppCodeBlock, 'pygments');
$dockerfileCodeBlock = $document->children[10] ?? null;
if (!$dockerfileCodeBlock instanceof PortLibs\Pandoc\AstNode || $dockerfileCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Dockerfile code block');
}
$dockerfile = $highlighter->highlightCodeBlock($dockerfileCodeBlock, 'tango');
$dockerfileWordpressBlock = $highlighter->wordpressHtmlBlock($dockerfileCodeBlock, 'tango');
$makefileCodeBlock = $document->children[11] ?? null;
if (!$makefileCodeBlock instanceof PortLibs\Pandoc\AstNode || $makefileCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Makefile code block');
}
$makefile = $highlighter->highlightCodeBlock($makefileCodeBlock, 'zenburn');
$makefileWordpressBlock = $highlighter->wordpressHtmlBlock($makefileCodeBlock, 'zenburn');
$jsxCodeBlock = $document->children[12] ?? null;
if (!$jsxCodeBlock instanceof PortLibs\Pandoc\AstNode || $jsxCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a JSX code block');
}
$jsx = $highlighter->highlightCodeBlock($jsxCodeBlock, 'breezedark');
$jsxWordpressBlock = $highlighter->wordpressHtmlBlock($jsxCodeBlock, 'breezedark');
$rCodeBlock = $document->children[13] ?? null;
if (!$rCodeBlock instanceof PortLibs\Pandoc\AstNode || $rCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an R code block');
}
$rScript = $highlighter->highlightCodeBlock($rCodeBlock, 'espresso');
$rWordpressBlock = $highlighter->wordpressHtmlBlock($rCodeBlock, 'espresso');
$iniCodeBlock = $document->children[14] ?? null;
if (!$iniCodeBlock instanceof PortLibs\Pandoc\AstNode || $iniCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an INI code block');
}
$ini = $highlighter->highlightCodeBlock($iniCodeBlock, 'haddock');
$iniWordpressBlock = $highlighter->wordpressHtmlBlock($iniCodeBlock, 'haddock');
$tomlCodeBlock = $document->children[15] ?? null;
if (!$tomlCodeBlock instanceof PortLibs\Pandoc\AstNode || $tomlCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a TOML code block');
}
$toml = $highlighter->highlightCodeBlock($tomlCodeBlock, 'kate');
$tomlWordpressBlock = $highlighter->wordpressHtmlBlock($tomlCodeBlock, 'kate');
$perlCodeBlock = $document->children[16] ?? null;
if (!$perlCodeBlock instanceof PortLibs\Pandoc\AstNode || $perlCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Perl code block');
}
$perl = $highlighter->highlightCodeBlock($perlCodeBlock, 'zenburn');
$perlWordpressBlock = $highlighter->wordpressHtmlBlock($perlCodeBlock, 'zenburn');
$javaCodeBlock = $document->children[17] ?? null;
if (!$javaCodeBlock instanceof PortLibs\Pandoc\AstNode || $javaCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Java code block');
}
$java = $highlighter->highlightCodeBlock($javaCodeBlock, 'tango');
$javaWordpressBlock = $highlighter->wordpressHtmlBlock($javaCodeBlock, 'tango');
$xmlCodeBlock = $document->children[18] ?? null;
if (!$xmlCodeBlock instanceof PortLibs\Pandoc\AstNode || $xmlCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an XML code block');
}
$xml = $highlighter->highlightCodeBlock($xmlCodeBlock, 'haddock');
$xmlWordpressBlock = $highlighter->wordpressHtmlBlock($xmlCodeBlock, 'haddock');
$xslt = $highlighter->highlight(
    "<xsl:template match=\"/rss/channel/item\">\n  <xsl:value-of select=\"normalize-space(title)\"/>\n</xsl:template>",
    'xsl'
);
$shellCodeBlock = $document->children[19] ?? null;
if (!$shellCodeBlock instanceof PortLibs\Pandoc\AstNode || $shellCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Bash shell code block');
}
$shell = $highlighter->highlightCodeBlock($shellCodeBlock, 'pygments');
$shellWordpressBlock = $highlighter->wordpressHtmlBlock($shellCodeBlock, 'pygments');
$tokenTitleCodeBlock = $document->children[20] ?? null;
if (!$tokenTitleCodeBlock instanceof PortLibs\Pandoc\AstNode || $tokenTitleCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a token-title PHP code block');
}
$tokenTitle = $highlighter->highlightCodeBlock($tokenTitleCodeBlock, 'kate');
$tokenTitleWordpressBlock = $highlighter->wordpressHtmlBlock($tokenTitleCodeBlock, 'kate');
$cssCodeBlock = $document->children[21] ?? null;
if (!$cssCodeBlock instanceof PortLibs\Pandoc\AstNode || $cssCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a CSS code block');
}
$css = $highlighter->highlightCodeBlock($cssCodeBlock, 'espresso');
$cssWordpressBlock = $highlighter->wordpressHtmlBlock($cssCodeBlock, 'espresso');
$rustCodeBlock = $document->children[22] ?? null;
if (!$rustCodeBlock instanceof PortLibs\Pandoc\AstNode || $rustCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Rust code block');
}
$rust = $highlighter->highlightCodeBlock($rustCodeBlock, 'zenburn');
$rustWordpressBlock = $highlighter->wordpressHtmlBlock($rustCodeBlock, 'zenburn');
$nixCodeBlock = $document->children[23] ?? null;
if (!$nixCodeBlock instanceof PortLibs\Pandoc\AstNode || $nixCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Nix code block');
}
$nix = $highlighter->highlightCodeBlock($nixCodeBlock, 'kate');
$nixWordpressBlock = $highlighter->wordpressHtmlBlock($nixCodeBlock, 'kate');
$scssCodeBlock = $document->children[24] ?? null;
if (!$scssCodeBlock instanceof PortLibs\Pandoc\AstNode || $scssCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an SCSS code block');
}
$scss = $highlighter->highlightCodeBlock($scssCodeBlock, 'espresso');
$scssWordpressBlock = $highlighter->wordpressHtmlBlock($scssCodeBlock, 'espresso');
$goCodeBlock = $document->children[25] ?? null;
if (!$goCodeBlock instanceof PortLibs\Pandoc\AstNode || $goCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Go code block');
}
$go = $highlighter->highlightCodeBlock($goCodeBlock, 'tango');
$goWordpressBlock = $highlighter->wordpressHtmlBlock($goCodeBlock, 'tango');
$powershellCodeBlock = $document->children[26] ?? null;
if (!$powershellCodeBlock instanceof PortLibs\Pandoc\AstNode || $powershellCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a PowerShell code block');
}
$powershell = $highlighter->highlightCodeBlock($powershellCodeBlock, 'breezedark');
$powershellWordpressBlock = $highlighter->wordpressHtmlBlock($powershellCodeBlock, 'breezedark');
$dotCodeBlock = $document->children[27] ?? null;
if (!$dotCodeBlock instanceof PortLibs\Pandoc\AstNode || $dotCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Graphviz DOT code block');
}
$dot = $highlighter->highlightCodeBlock($dotCodeBlock, 'monochrome');
$dotWordpressBlock = $highlighter->wordpressHtmlBlock($dotCodeBlock, 'monochrome');
$javascriptCodeBlock = $document->children[28] ?? null;
if (!$javascriptCodeBlock instanceof PortLibs\Pandoc\AstNode || $javascriptCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a JavaScript module code block');
}
$javascript = $highlighter->highlightCodeBlock($javascriptCodeBlock, 'kate');
$javascriptWordpressBlock = $highlighter->wordpressHtmlBlock($javascriptCodeBlock, 'kate');
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
    if (($python['language'] ?? '') !== 'python') {
        throw new RuntimeException('Expected python3 alias to normalize to Python highlighting');
    }
    if (($python['lineNumbering']['start'] ?? null) !== 20) {
        throw new RuntimeException('Expected Python source startFrom line-number handoff');
    }
    if (!str_contains($python['html'], '<span class="ot">@dataclass</span>')) {
        throw new RuntimeException('Expected Python decorator token handoff');
    }
    if (!str_contains($python['html'], '<span class="kw">class</span> <span class="dt">ReviewPacket</span><span class="op">:</span>')) {
        throw new RuntimeException('Expected Python class datatype token handoff');
    }
    if (!str_contains($python['html'], '<span class="kw">def</span> <span class="fu">normalize_title</span>')) {
        throw new RuntimeException('Expected Python function token handoff');
    }
    if (!str_contains($python['html'], '<span class="va">json</span><span class="op">.</span><span class="fu">loads</span>')) {
        throw new RuntimeException('Expected Python module function token handoff');
    }
    if (!str_contains($pythonWordpressBlock, '<style data-pandoc-highlight-style="monochrome">')) {
        throw new RuntimeException('Expected Python WordPress style metadata');
    }
    if (!str_contains($pythonWordpressBlock, '<span class="kw">return</span> <span class="va">raw</span><span class="op">.</span><span class="fu">strip</span><span class="op">()</span>')) {
        throw new RuntimeException('Expected Python method call token handoff');
    }
    if (($cpp['language'] ?? '') !== 'cpp') {
        throw new RuntimeException('Expected cpp alias to normalize to C++ highlighting');
    }
    if (($cpp['lineNumbering']['start'] ?? null) !== 30) {
        throw new RuntimeException('Expected C++ source startFrom line-number handoff');
    }
    if (!str_contains($cpp['html'], '<span class="pp">#include &lt;string&gt;</span>')) {
        throw new RuntimeException('Expected C++ preprocessor token handoff');
    }
    if (!str_contains($cpp['html'], '<span class="kw">class</span> <span class="dt">ReviewPacket</span>')) {
        throw new RuntimeException('Expected C++ class datatype token handoff');
    }
    if (!str_contains($cpp['html'], '<span class="dt">std</span><span class="op">::</span><span class="dt">string</span>')) {
        throw new RuntimeException('Expected C++ std::string token handoff');
    }
    if (!str_contains($cpp['html'], '<span class="kw">return</span> <span class="va">title_</span><span class="op">.</span><span class="fu">empty</span><span class="op">()</span>')) {
        throw new RuntimeException('Expected C++ method-call token handoff');
    }
    if (!str_contains($cppWordpressBlock, '<style data-pandoc-highlight-style="pygments">')) {
        throw new RuntimeException('Expected C++ WordPress style metadata');
    }
    if (!str_contains($cppWordpressBlock, '<span class="st">&quot;Draft&quot;</span>')) {
        throw new RuntimeException('Expected C++ string token handoff');
    }
    if (($dockerfile['language'] ?? '') !== 'dockerfile') {
        throw new RuntimeException('Expected Dockerfile alias to normalize to Dockerfile highlighting');
    }
    if (($dockerfile['lineNumbering']['start'] ?? null) !== 4) {
        throw new RuntimeException('Expected Dockerfile source startFrom line-number handoff');
    }
    if (!str_contains($dockerfile['html'], '<span class="ot"># syntax=docker/dockerfile:1.7</span>')) {
        throw new RuntimeException('Expected Dockerfile syntax directive token handoff');
    }
    if (!str_contains($dockerfile['html'], '<span class="kw">FROM</span> wordpress<span class="op">:</span>php')) {
        throw new RuntimeException('Expected Dockerfile FROM keyword token handoff');
    }
    if (!str_contains($dockerfile['html'], '<span class="op">--from=source</span>')) {
        throw new RuntimeException('Expected Dockerfile option token handoff');
    }
    if (!str_contains($dockerfile['html'], '<span class="fu">php</span> <span class="op">-</span>m <span class="op">|</span> <span class="fu">grep</span> json')) {
        throw new RuntimeException('Expected Dockerfile shell-form command token handoff');
    }
    if (!str_contains($dockerfileWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected Dockerfile WordPress style metadata');
    }
    if (!str_contains($dockerfileWordpressBlock, '<span class="kw">ENV</span> <span class="ot">WORDPRESS_CONFIG_EXTRA</span>')) {
        throw new RuntimeException('Expected Dockerfile environment assignment token handoff');
    }
    if (($makefile['language'] ?? '') !== 'makefile') {
        throw new RuntimeException('Expected Makefile alias to normalize to Makefile highlighting');
    }
    if (($makefile['lineNumbering']['start'] ?? null) !== 6) {
        throw new RuntimeException('Expected Makefile source startFrom line-number handoff');
    }
    if (!str_contains($makefile['html'], '<span class="ot">PLUGIN_VERSION</span> <span class="op">?=</span> <span class="dv">1.2.3</span>')) {
        throw new RuntimeException('Expected Makefile assignment token handoff');
    }
    if (!str_contains($makefile['html'], '<span class="re">assets/build</span><span class="op">:</span>')) {
        throw new RuntimeException('Expected Makefile target token handoff');
    }
    if (!str_contains($makefile['html'], '<span class="fu">wp</span> <span class="va">i18n</span> <span class="va">make-pot</span>')) {
        throw new RuntimeException('Expected Makefile wp-cli recipe token handoff');
    }
    if (!str_contains($makefileWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected Makefile WordPress style metadata');
    }
    if (!str_contains($makefileWordpressBlock, '<span class="op">@</span><span class="va">$(WP_CLI)</span>')) {
        throw new RuntimeException('Expected Makefile quiet recipe variable handoff');
    }
    if (($jsx['language'] ?? '') !== 'jsx') {
        throw new RuntimeException('Expected jsx alias to normalize to JSX highlighting');
    }
    if (($jsx['lineNumbering']['start'] ?? null) !== 18) {
        throw new RuntimeException('Expected JSX source startFrom line-number handoff');
    }
    if (!str_contains($jsx['html'], '<span class="kw">import</span> <span class="dt">React</span> <span class="kw">from</span> <span class="st">&#039;react&#039;</span>')) {
        throw new RuntimeException('Expected JSX import token handoff');
    }
    if (!str_contains($jsx['html'], '<span class="kw">return</span> <span class="kw">&lt;section</span> <span class="ot">className</span>')) {
        throw new RuntimeException('Expected JSX element tag and attribute token handoff');
    }
    if (!str_contains($jsx['html'], '<span class="fu">&lt;InnerBlocks</span> <span class="ot">allowedBlocks</span>')) {
        throw new RuntimeException('Expected JSX component tag and attribute token handoff');
    }
    if (!str_contains($jsxWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected JSX WordPress style metadata');
    }
    if (!str_contains($jsxWordpressBlock, '<span class="fu">&lt;InnerBlocks</span>')) {
        throw new RuntimeException('Expected JSX WordPress component token handoff');
    }
    if (($rScript['language'] ?? '') !== 'r') {
        throw new RuntimeException('Expected R alias to normalize to R highlighting');
    }
    if (($rScript['lineNumbering']['start'] ?? null) !== 27) {
        throw new RuntimeException('Expected R source startFrom line-number handoff');
    }
    if (!str_contains($rScript['html'], '<span class="fu">library</span><span class="op">(</span><span class="va">dplyr</span><span class="op">)</span>')) {
        throw new RuntimeException('Expected R function-call token handoff');
    }
    if (!str_contains($rScript['html'], '<span class="va">scores</span> <span class="ot">&lt;-</span> <span class="fu">data.frame</span>')) {
        throw new RuntimeException('Expected R assignment and data.frame token handoff');
    }
    if (!str_contains($rScript['html'], '<span class="va">scores</span> <span class="op">|&gt;</span>')) {
        throw new RuntimeException('Expected R native pipe token handoff');
    }
    if (!str_contains($rScript['html'], '<span class="cn">NA_integer_</span>')) {
        throw new RuntimeException('Expected R typed NA constant handoff');
    }
    if (!str_contains($rScript['html'], '<span class="kw">if</span> <span class="op">(</span><span class="fu">any</span>')) {
        throw new RuntimeException('Expected R control-flow token handoff');
    }
    if (!str_contains($rWordpressBlock, '<style data-pandoc-highlight-style="espresso">')) {
        throw new RuntimeException('Expected R WordPress style metadata');
    }
    if (!str_contains($rWordpressBlock, '<span class="fu">mutate</span><span class="op">(</span><span class="ot">slug</span>')) {
        throw new RuntimeException('Expected R WordPress mutate named-argument handoff');
    }
    if (($ini['language'] ?? '') !== 'ini') {
        throw new RuntimeException('Expected INI alias to normalize to INI highlighting');
    }
    if (($ini['lineNumbering']['start'] ?? null) !== 2) {
        throw new RuntimeException('Expected INI source startFrom line-number handoff');
    }
    if (!str_contains($ini['html'], '<span class="kw">[PHP]</span>')) {
        throw new RuntimeException('Expected INI section token handoff');
    }
    if (!str_contains($ini['html'], '<span class="dt">display_errors</span> <span class="op">=</span> <span class="kw">Off</span>')) {
        throw new RuntimeException('Expected INI keyword value token handoff');
    }
    if (!str_contains($ini['html'], '<span class="dt">opcache.enable</span> <span class="op">=</span> <span class="dv">1</span>')) {
        throw new RuntimeException('Expected INI numeric value token handoff');
    }
    if (!str_contains($iniWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected INI WordPress style metadata');
    }
    if (!str_contains($iniWordpressBlock, '<span class="dt">error_reporting</span> <span class="op">=</span> <span class="kw">E_ALL</span>')) {
        throw new RuntimeException('Expected INI PHP constant token handoff');
    }
    if (($toml['language'] ?? '') !== 'toml') {
        throw new RuntimeException('Expected TOML alias to normalize to TOML highlighting');
    }
    if (($toml['lineNumbering']['start'] ?? null) !== 11) {
        throw new RuntimeException('Expected TOML source startFrom line-number handoff');
    }
    if (!str_contains($toml['html'], '<span class="kw">[tool.wordpress-import]</span>')) {
        throw new RuntimeException('Expected TOML table token handoff');
    }
    if (!str_contains($toml['html'], '<span class="dt">enabled</span> <span class="op">=</span> <span class="cn">true</span>')) {
        throw new RuntimeException('Expected TOML boolean token handoff');
    }
    if (!str_contains($toml['html'], '<span class="dt">published_at</span> <span class="op">=</span> <span class="cn">2026-06-05T08:40:00Z</span>')) {
        throw new RuntimeException('Expected TOML datetime token handoff');
    }
    if (!str_contains($toml['html'], '<span class="dt">media_paths</span> <span class="op">=</span> <span class="op">[</span><span class="st">&quot;uploads&quot;</span>')) {
        throw new RuntimeException('Expected TOML array token handoff');
    }
    if (!str_contains($tomlWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected TOML WordPress style metadata');
    }
    if (!str_contains($tomlWordpressBlock, '<span class="dt">palette</span> <span class="op">=</span> <span class="op">{</span> <span class="dt">primary</span>')) {
        throw new RuntimeException('Expected TOML inline table token handoff');
    }
    if (($perl['language'] ?? '') !== 'perl') {
        throw new RuntimeException('Expected pl alias to normalize to Perl highlighting');
    }
    if (($perl['lineNumbering']['start'] ?? null) !== 14) {
        throw new RuntimeException('Expected Perl source startFrom line-number handoff');
    }
    if (!str_contains($perl['html'], '<span class="kw">#!/usr/bin/env perl</span>')) {
        throw new RuntimeException('Expected Perl shebang token handoff');
    }
    if (!str_contains($perl['html'], '<span class="fu">use</span> <span class="kw">strict</span>')) {
        throw new RuntimeException('Expected Perl pragma token handoff');
    }
    if (!str_contains($perl['html'], '<span class="kw">package</span> <span class="dt">WP::ImportReview</span>')) {
        throw new RuntimeException('Expected Perl package token handoff');
    }
    if (!str_contains($perl['html'], '<span class="va">$title</span> <span class="op">=~</span> <span class="st">s/^\\s+|\\s+$//g</span>')) {
        throw new RuntimeException('Expected Perl substitution token handoff');
    }
    if (!str_contains($perl['html'], '<span class="fu">warn</span> <span class="st">&quot;empty title for $packet-&gt;{id}&quot;</span>')) {
        throw new RuntimeException('Expected Perl warn string token handoff');
    }
    if (!str_contains($perlWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected Perl WordPress style metadata');
    }
    if (!str_contains($perlWordpressBlock, '<span class="kw">return</span> <span class="fu">lc</span> <span class="va">$title</span>')) {
        throw new RuntimeException('Expected Perl return/function token handoff');
    }
    if (($java['language'] ?? '') !== 'java') {
        throw new RuntimeException('Expected Java alias to normalize to Java highlighting');
    }
    if (($java['lineNumbering']['start'] ?? null) !== 21) {
        throw new RuntimeException('Expected Java source startFrom line-number handoff');
    }
    if (!str_contains($java['html'], '<span class="kw">public</span> <span class="kw">final</span> <span class="kw">class</span> <span class="dt">ReviewPacket</span>')) {
        throw new RuntimeException('Expected Java class token handoff');
    }
    if (!str_contains($java['html'], '<span class="dt">Files</span><span class="op">.</span><span class="fu">readString</span>')) {
        throw new RuntimeException('Expected Java static method token handoff');
    }
    if (!str_contains($java['html'], '<span class="ot">@Deprecated</span>')) {
        throw new RuntimeException('Expected Java annotation token handoff');
    }
    if (!str_contains($javaWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected Java WordPress style metadata');
    }
    if (!str_contains($javaWordpressBlock, '<span class="dt">Optional</span><span class="op">.</span><span class="fu">empty</span><span class="op">();</span>')) {
        throw new RuntimeException('Expected Java Optional method token handoff');
    }
    if (($xml['language'] ?? '') !== 'xml') {
        throw new RuntimeException('Expected XML alias to normalize to XML highlighting');
    }
    if (($xml['lineNumbering']['start'] ?? null) !== 33) {
        throw new RuntimeException('Expected XML source startFrom line-number handoff');
    }
    if (!str_contains($xml['html'], '<span class="pp">&lt;?xml</span> <span class="ot">version</span>')) {
        throw new RuntimeException('Expected XML declaration token handoff');
    }
    if (!str_contains($xml['html'], '<span class="pp">&lt;!DOCTYPE</span> rss <span class="op">[</span><span class="pp">&lt;!ENTITY</span> legacy')) {
        throw new RuntimeException('Expected XML doctype/entity token handoff');
    }
    if (!str_contains($xml['html'], '<span class="kw">&lt;wp:wxr_version</span><span class="op">&gt;</span><span class="dv">1.2</span>')) {
        throw new RuntimeException('Expected XML namespaced tag token handoff');
    }
    if (!str_contains($xml['html'], '<span class="cn">&amp;legacy;</span> <span class="cn">&amp;amp;</span> Reviewed')) {
        throw new RuntimeException('Expected XML entity token handoff');
    }
    if (!str_contains($xml['html'], '<span class="st">&lt;![CDATA[&lt;!-- wp:paragraph --&gt;&lt;p&gt;Legacy shortcode [gallery]&lt;/p&gt;]]&gt;</span>')) {
        throw new RuntimeException('Expected XML CDATA token handoff');
    }
    if (!str_contains($xmlWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected XML WordPress style metadata');
    }
    if (($xslt['language'] ?? '') !== 'xslt') {
        throw new RuntimeException('Expected XSL alias to normalize to XSLT highlighting');
    }
    if (!str_contains($xslt['html'], '<span class="kw">&lt;xsl:value-of</span> <span class="ot">select</span>')) {
        throw new RuntimeException('Expected XSLT value-of token handoff');
    }
    if (($shell['language'] ?? '') !== 'bash') {
        throw new RuntimeException('Expected shell alias to normalize to Bash highlighting');
    }
    if (($shell['lineNumbering']['start'] ?? null) !== 50) {
        throw new RuntimeException('Expected shell source startFrom line-number handoff');
    }
    if (!str_contains($shell['html'], '<span class="kw">#!/usr/bin/env bash</span>')) {
        throw new RuntimeException('Expected shell shebang token handoff');
    }
    if (!str_contains($shell['html'], '<span class="fu">wp</span> <span class="va">post</span> <span class="va">list</span> <span class="ot">--post_type</span>')) {
        throw new RuntimeException('Expected wp-cli command and long-option token handoff');
    }
    if (!str_contains($shell['html'], '<span class="kw">if</span> <span class="op">[[</span> <span class="op">-z</span> <span class="st">&quot;$title&quot;</span>')) {
        throw new RuntimeException('Expected shell test expression token handoff');
    }
    if (!str_contains($shell['html'], '<span class="fu">cat</span> <span class="op">&lt;&lt;</span><span class="st">&#039;HTML&#039;</span>')) {
        throw new RuntimeException('Expected shell heredoc delimiter token handoff');
    }
    if (!str_contains($shell['html'], '<span class="st">&lt;!-- wp:paragraph --&gt;&lt;p&gt;Missing title&lt;/p&gt;&lt;!-- /wp:paragraph --&gt;</span>')) {
        throw new RuntimeException('Expected shell heredoc body string token handoff');
    }
    if (!str_contains($shellWordpressBlock, '<style data-pandoc-highlight-style="pygments">')) {
        throw new RuntimeException('Expected shell WordPress style metadata');
    }
    if (!str_contains($shellWordpressBlock, '<span class="re">HTML</span>')) {
        throw new RuntimeException('Expected shell heredoc close token handoff');
    }
    if (($tokenTitle['tokenTitles'] ?? false) !== true) {
        throw new RuntimeException('Expected token-title opt-in metadata handoff');
    }
    if (!str_contains($tokenTitle['html'], '<span class="kw" title="KeywordTok">echo</span> <span class="fu" title="FunctionTok">esc_html</span>')) {
        throw new RuntimeException('Expected token title attributes on highlighted PHP tokens');
    }
    if (!str_contains($tokenTitleWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected token-title WordPress style metadata');
    }
    if (!str_contains($tokenTitleWordpressBlock, '<span class="co" title="CommentTok">// reviewer token titles</span>')) {
        throw new RuntimeException('Expected token-title WordPress comment metadata');
    }
    if (($css['language'] ?? '') !== 'css') {
        throw new RuntimeException('Expected CSS language handoff');
    }
    if (($css['lineNumbering']['start'] ?? null) !== 70) {
        throw new RuntimeException('Expected CSS source startFrom line-number handoff');
    }
    if (!str_contains($css['html'], '<span class="kw">@media</span> <span class="op">(</span><span class="ot">min-width</span>')) {
        throw new RuntimeException('Expected CSS at-rule token handoff');
    }
    if (!str_contains($css['html'], '<span class="dt">.wp-block-import-card</span> <span class="op">&gt;</span> <span class="dt">a</span><span class="fu">:hover</span>')) {
        throw new RuntimeException('Expected CSS selector and pseudo-class handoff');
    }
    if (!str_contains($css['html'], '<span class="ot">--accent-color</span><span class="op">:</span> <span class="cn">#005cc5</span>')) {
        throw new RuntimeException('Expected CSS custom property and color token handoff');
    }
    if (!str_contains($css['html'], '<span class="fu">var</span><span class="op">(</span><span class="ot">--accent-color</span><span class="op">)</span> <span class="kw">!important</span>')) {
        throw new RuntimeException('Expected CSS var() and important token handoff');
    }
    if (!str_contains($cssWordpressBlock, '<style data-pandoc-highlight-style="espresso">')) {
        throw new RuntimeException('Expected CSS WordPress style metadata');
    }
    if (($rust['language'] ?? '') !== 'rust') {
        throw new RuntimeException('Expected Rust alias to normalize to Rust highlighting');
    }
    if (($rust['lineNumbering']['start'] ?? null) !== 88) {
        throw new RuntimeException('Expected Rust source startFrom line-number handoff');
    }
    if (!str_contains($rust['html'], '<span class="kw">use</span> <span class="va">serde_json</span><span class="op">::</span><span class="dt">Value</span>')) {
        throw new RuntimeException('Expected Rust use path token handoff');
    }
    if (!str_contains($rust['html'], '<span class="kw">pub</span> <span class="kw">struct</span> <span class="dt">ReviewPacket</span>')) {
        throw new RuntimeException('Expected Rust struct token handoff');
    }
    if (!str_contains($rust['html'], '<span class="kw">return</span> <span class="fu">format!</span>')) {
        throw new RuntimeException('Expected Rust macro token handoff');
    }
    if (!str_contains($rustWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected Rust WordPress style metadata');
    }
    if (($nix['language'] ?? '') !== 'nix') {
        throw new RuntimeException('Expected Nix language handoff');
    }
    if (($nix['lineNumbering']['start'] ?? null) !== 101) {
        throw new RuntimeException('Expected Nix source startFrom line-number handoff');
    }
    if (!str_contains($nix['html'], '<span class="fu">import</span> <span class="cn">&lt;nixpkgs&gt;</span>')) {
        throw new RuntimeException('Expected Nix import and angle-path token handoff');
    }
    if (!str_contains($nix['html'], '<span class="kw">inherit</span> <span class="op">(</span><span class="va">pkgs</span><span class="op">)</span> <span class="va">stdenv</span>')) {
        throw new RuntimeException('Expected Nix inherit token handoff');
    }
    if (!str_contains($nix['html'], '<span class="ot">mediaPaths</span> <span class="op">=</span> <span class="op">[</span> <span class="st">./uploads</span> <span class="st">./assets</span>')) {
        throw new RuntimeException('Expected Nix path token handoff');
    }
    if (!str_contains($nixWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected Nix WordPress style metadata');
    }
    if (!str_contains($nixWordpressBlock, '<span class="va">pkgs</span><span class="op">.</span><span class="va">writeText</span>')) {
        throw new RuntimeException('Expected Nix function-application handoff');
    }
    if (($scss['language'] ?? '') !== 'scss') {
        throw new RuntimeException('Expected SCSS language handoff');
    }
    if (($scss['lineNumbering']['start'] ?? null) !== 120) {
        throw new RuntimeException('Expected SCSS source startFrom line-number handoff');
    }
    if (!str_contains($scss['html'], '<span class="va">$accent-color</span><span class="op">:</span> <span class="cn">#005cc5</span> <span class="kw">!default</span>')) {
        throw new RuntimeException('Expected SCSS variable, color, and default flag token handoff');
    }
    if (!str_contains($scss['html'], '<span class="kw">@mixin</span> <span class="fu">import-card</span><span class="op">(</span><span class="va">$selector</span>')) {
        throw new RuntimeException('Expected SCSS mixin token handoff');
    }
    if (!str_contains($scss['html'], '<span class="op">&amp;</span><span class="fu">:hover</span>')) {
        throw new RuntimeException('Expected SCSS parent selector pseudo-class token handoff');
    }
    if (!str_contains($scss['html'], '<span class="kw">@include</span> <span class="fu">import-card</span>')) {
        throw new RuntimeException('Expected SCSS include token handoff');
    }
    if (!str_contains($scssWordpressBlock, '<style data-pandoc-highlight-style="espresso">')) {
        throw new RuntimeException('Expected SCSS WordPress style metadata');
    }
    if (($go['language'] ?? '') !== 'go') {
        throw new RuntimeException('Expected Go language handoff');
    }
    if (($go['lineNumbering']['start'] ?? null) !== 135) {
        throw new RuntimeException('Expected Go source startFrom line-number handoff');
    }
    if (!str_contains($go['html'], '<span class="kw">package</span> <span class="va">review</span>')) {
        throw new RuntimeException('Expected Go package token handoff');
    }
    if (!str_contains($go['html'], '<span class="kw">type</span> <span class="dt">ReviewPacket</span> <span class="kw">struct</span>')) {
        throw new RuntimeException('Expected Go struct token handoff');
    }
    if (!str_contains($go['html'], '<span class="kw">func</span> <span class="fu">NormalizeTitle</span>')) {
        throw new RuntimeException('Expected Go function token handoff');
    }
    if (!str_contains($go['html'], '<span class="va">json</span><span class="op">.</span><span class="fu">Unmarshal</span>')) {
        throw new RuntimeException('Expected Go selector/function token handoff');
    }
    if (!str_contains($go['html'], '<span class="kw">go</span> <span class="kw">func</span><span class="op">()</span>')) {
        throw new RuntimeException('Expected Go goroutine token handoff');
    }
    if (!str_contains($goWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected Go WordPress style metadata');
    }
    if (($powershell['language'] ?? '') !== 'powershell') {
        throw new RuntimeException('Expected PowerShell alias to normalize to PowerShell highlighting');
    }
    if (($powershell['lineNumbering']['start'] ?? null) !== 150) {
        throw new RuntimeException('Expected PowerShell source startFrom line-number handoff');
    }
    if (!str_contains($powershell['html'], '<span class="fu">Get-Content</span> <span class="ot">-LiteralPath</span> <span class="va">$SourcePath</span>')) {
        throw new RuntimeException('Expected PowerShell Get-Content command and parameter token handoff');
    }
    if (!str_contains($powershell['html'], '<span class="cn">$null</span> <span class="op">-eq</span>')) {
        throw new RuntimeException('Expected PowerShell null comparison token handoff');
    }
    if (!str_contains($powershell['html'], '<span class="op">@{</span>')) {
        throw new RuntimeException('Expected PowerShell hashtable token handoff');
    }
    if (!str_contains($powershellWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected PowerShell WordPress style metadata');
    }
    if (!str_contains($powershellWordpressBlock, '<span class="fu">Set-Content</span> <span class="ot">-LiteralPath</span>')) {
        throw new RuntimeException('Expected PowerShell Set-Content token handoff');
    }
    if (($dot['language'] ?? '') !== 'dot') {
        throw new RuntimeException('Expected Graphviz DOT alias to normalize to dot highlighting');
    }
    if (($dot['lineNumbering']['start'] ?? null) !== 170) {
        throw new RuntimeException('Expected DOT source startFrom line-number handoff');
    }
    if (!str_contains($dot['html'], '<span class="kw">digraph</span> <span class="va">ImportFlow</span>')) {
        throw new RuntimeException('Expected DOT digraph token handoff');
    }
    if (!str_contains($dot['html'], '<span class="ot">rankdir</span><span class="op">=</span><span class="cn">LR</span>')) {
        throw new RuntimeException('Expected DOT graph attribute token handoff');
    }
    if (!str_contains($dot['html'], '<span class="va">review</span> <span class="op">-&gt;</span> <span class="va">publish</span>')) {
        throw new RuntimeException('Expected DOT directed edge token handoff');
    }
    if (!str_contains($dotWordpressBlock, '<style data-pandoc-highlight-style="monochrome">')) {
        throw new RuntimeException('Expected DOT WordPress style metadata');
    }
    if (($javascript['language'] ?? '') !== 'javascript') {
        throw new RuntimeException('Expected JavaScript module alias to normalize to JavaScript highlighting');
    }
    if (($javascript['lineNumbering']['start'] ?? null) !== 190) {
        throw new RuntimeException('Expected JavaScript source startFrom line-number handoff');
    }
    if (!str_contains($javascript['html'], '<span class="kw">import</span> <span class="op">{</span> <span class="va">registerBlockType</span>')) {
        throw new RuntimeException('Expected JavaScript import binding token handoff');
    }
    if (!str_contains($javascript['html'], '<span class="fu">replace</span><span class="op">(</span><span class="st">/\\s+/gu</span>')) {
        throw new RuntimeException('Expected JavaScript regex literal token handoff');
    }
    if (!str_contains($javascript['html'], '<span class="kw">await</span> <span class="fu">apiFetch</span><span class="op">({</span> <span class="ot">path</span>')) {
        throw new RuntimeException('Expected JavaScript await call and object-key token handoff');
    }
    if (!str_contains($javascript['html'], '<span class="dt">console</span><span class="op">.</span><span class="fu">log</span><span class="op">(</span><span class="dt">JSON</span><span class="op">.</span><span class="fu">stringify</span>')) {
        throw new RuntimeException('Expected JavaScript built-in/function token handoff');
    }
    if (!str_contains($javascriptWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected JavaScript WordPress style metadata');
    }
    if (!str_contains($javascriptWordpressBlock, '<span class="fu">registerBlockType</span><span class="op">(</span><span class="st">&quot;legacy/import-review&quot;</span>')) {
        throw new RuntimeException('Expected JavaScript Gutenberg registration token handoff');
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
echo "pythonHighlightedHtml:\n" . $python['html'] . "\n";
echo "cppHighlightedHtml:\n" . $cpp['html'] . "\n";
echo "dockerfileHighlightedHtml:\n" . $dockerfile['html'] . "\n";
echo "makefileHighlightedHtml:\n" . $makefile['html'] . "\n";
echo "jsxHighlightedHtml:\n" . $jsx['html'] . "\n";
echo "rHighlightedHtml:\n" . $rScript['html'] . "\n";
echo "iniHighlightedHtml:\n" . $ini['html'] . "\n";
echo "tomlHighlightedHtml:\n" . $toml['html'] . "\n";
echo "perlHighlightedHtml:\n" . $perl['html'] . "\n";
echo "javaHighlightedHtml:\n" . $java['html'] . "\n";
echo "xmlHighlightedHtml:\n" . $xml['html'] . "\n";
echo "xsltHighlightedHtml:\n" . $xslt['html'] . "\n";
echo "shellHighlightedHtml:\n" . $shell['html'] . "\n";
echo "tokenTitleHighlightedHtml:\n" . $tokenTitle['html'] . "\n";
echo "cssHighlightedHtml:\n" . $css['html'] . "\n";
echo "rustHighlightedHtml:\n" . $rust['html'] . "\n";
echo "nixHighlightedHtml:\n" . $nix['html'] . "\n";
echo "scssHighlightedHtml:\n" . $scss['html'] . "\n";
echo "goHighlightedHtml:\n" . $go['html'] . "\n";
echo "powershellHighlightedHtml:\n" . $powershell['html'] . "\n";
echo "dotHighlightedHtml:\n" . $dot['html'] . "\n";
echo "javascriptHighlightedHtml:\n" . $javascript['html'] . "\n";
echo "customThemeHighlightedHtml:\n" . $customTheme['html'] . "\n";
echo "wordpressBlock:\n" . $wordpressBlock . "\n";
echo "writerHighlightedBlocks:\n" . $writerHighlightedBlocks . "\n";
echo "pythonWordpressBlock:\n" . $pythonWordpressBlock . "\n";
echo "cppWordpressBlock:\n" . $cppWordpressBlock . "\n";
echo "dockerfileWordpressBlock:\n" . $dockerfileWordpressBlock . "\n";
echo "makefileWordpressBlock:\n" . $makefileWordpressBlock . "\n";
echo "jsxWordpressBlock:\n" . $jsxWordpressBlock . "\n";
echo "rWordpressBlock:\n" . $rWordpressBlock . "\n";
echo "iniWordpressBlock:\n" . $iniWordpressBlock . "\n";
echo "tomlWordpressBlock:\n" . $tomlWordpressBlock . "\n";
echo "perlWordpressBlock:\n" . $perlWordpressBlock . "\n";
echo "javaWordpressBlock:\n" . $javaWordpressBlock . "\n";
echo "xmlWordpressBlock:\n" . $xmlWordpressBlock . "\n";
echo "shellWordpressBlock:\n" . $shellWordpressBlock . "\n";
echo "tokenTitleWordpressBlock:\n" . $tokenTitleWordpressBlock . "\n";
echo "cssWordpressBlock:\n" . $cssWordpressBlock . "\n";
echo "rustWordpressBlock:\n" . $rustWordpressBlock . "\n";
echo "nixWordpressBlock:\n" . $nixWordpressBlock . "\n";
echo "scssWordpressBlock:\n" . $scssWordpressBlock . "\n";
echo "goWordpressBlock:\n" . $goWordpressBlock . "\n";
echo "powershellWordpressBlock:\n" . $powershellWordpressBlock . "\n";
echo "dotWordpressBlock:\n" . $dotWordpressBlock . "\n";
echo "javascriptWordpressBlock:\n" . $javascriptWordpressBlock . "\n";
echo "customThemeWordpressBlock:\n" . $customThemeWordpressBlock . "\n";
