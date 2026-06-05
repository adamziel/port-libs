<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\SyntaxHighlighter;

return [
    'normalizes pandoc and skylighting language aliases and styles' => static function (TestRunner $t): void {
        $node = new AstNode('code_block', [
            'classes' => ['sourceCode', 'numberLines', 'language-php'],
            'attributes' => [],
            'text' => 'echo "ok";',
        ]);
        $attributeNode = new AstNode('code_block', [
            'classes' => [],
            'attributes' => ['data-language' => 'YML'],
            'text' => 'title: Review',
        ]);
        $lineNumberNode = new AstNode('code_block', [
            'classes' => ['sourceCode', 'number-lines', 'line-anchors', 'php'],
            'attributes' => [],
            'text' => 'echo "ok";',
        ]);

        $t->same('php', SyntaxHighlighter::languageFromCodeBlock($node));
        $t->same('php', SyntaxHighlighter::languageFromCodeBlock($lineNumberNode));
        $t->same('html', SyntaxHighlighter::normalizeLanguage('HTML5'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('language-js'));
        $t->same('yaml', SyntaxHighlighter::normalizeLanguage('yml'));
        $t->same(null, SyntaxHighlighter::normalizeLanguage('sourceCode'));
        $t->same(null, SyntaxHighlighter::normalizeLanguage('lineAnchors'));
        $t->same(null, SyntaxHighlighter::normalizeLanguage('number-lines'));
        $t->same('breezedark', SyntaxHighlighter::normalizeStyle('breezeDark'));
        $t->same('pygments', SyntaxHighlighter::normalizeStyle('unknown-theme'));
        $t->same('yaml', (new SyntaxHighlighter())->highlightCodeBlock($attributeNode)['language']);
    },
    'highlights php code blocks from markdown fixture without invoking pandoc' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[0];
        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'pygments');

        $t->same('code_block', $codeBlock->type);
        $t->same('php', $highlighted['language']);
        $t->same('pygments', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->contains('<pre class="sourceCode php"><code class="sourceCode php">', $highlighted['html']);
        $t->contains('<span class="pp">&lt;?php</span>', $highlighted['html']);
        $t->contains('<span class="kw">function</span>', $highlighted['html']);
        $t->contains('<span class="fu">render_title</span>', $highlighted['html']);
        $t->contains('<span class="va">$post</span>', $highlighted['html']);
        $t->contains('<span class="st">&#039;title&#039;</span>', $highlighted['html']);
        $t->contains('<span class="co">// WordPress-safe title</span>', $highlighted['html']);
        $t->contains('.sourceCode .kw', $highlighted['css']);
    },
    'hands highlighted code to wordpress html blocks with style metadata' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'classes' => ['php'],
            'attributes' => [],
            'text' => "<?php\necho esc_html(\$title);",
        ]);
        $block = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'breezedark');

        $t->contains('<!-- wp:html -->', $block);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $block);
        $t->contains('.sourceCode .kw', $block);
        $t->contains('<pre class="sourceCode php"><code class="sourceCode php">', $block);
        $t->contains('<span class="kw">echo</span>', $block);
        $t->contains('<span class="fu">esc_html</span>', $block);
        $t->contains('<!-- /wp:html -->', $block);
    },
    'renders pandoc numbered source lines and anchors from code block attributes' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'migration-review',
            'classes' => ['php', 'numberLines', 'lineAnchors'],
            'attributes' => ['startFrom' => '42'],
            'text' => "<?php\necho esc_html(\$title);",
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'kate');

        $t->same('php', $highlighted['language']);
        $t->same('kate', $highlighted['style']);
        $t->same([
            'enabled' => true,
            'anchors' => true,
            'start' => 42,
            'lineIdPrefix' => 'migration-review-',
        ], $highlighted['lineNumbering']);
        $t->contains('<div class="sourceCode"><pre class="sourceCode numberSource php numberLines lineAnchors"><code class="sourceCode php" style="counter-reset: source-line 41;">', $highlighted['html']);
        $t->contains('<span id="migration-review-42"><a href="#migration-review-42"></a><span class="pp">&lt;?php</span></span>', $highlighted['html']);
        $t->contains('<span id="migration-review-43"><a href="#migration-review-43"></a><span class="kw">echo</span> <span class="fu">esc_html</span>', $highlighted['html']);
        $t->contains('pre.numberSource code > span', $highlighted['css']);
        $t->contains('counter(source-line)', $highlighted['css']);

        $anchorOnly = (new SyntaxHighlighter())->highlight('echo "ok";', 'php', 'pygments', [
            'id' => 'anchor-only',
            'classes' => ['line-anchors'],
        ]);

        $t->same(false, $anchorOnly['lineNumbering']['enabled']);
        $t->same(true, $anchorOnly['lineNumbering']['anchors']);
        $t->contains('<pre class="sourceCode line-anchors"><code class="sourceCode php">', $anchorOnly['html']);
        $t->contains('<span id="anchor-only-1"><a href="#anchor-only-1" aria-hidden="true" tabindex="-1"></a><span class="kw">echo</span>', $anchorOnly['html']);
    },
    'preserves numbered plain text fallback for unsupported languages' => static function (TestRunner $t): void {
        $highlighted = (new SyntaxHighlighter())->highlight(
            "legacy << token\nsecond line",
            'unknown-review-language',
            'pygments',
            [
                'id' => 'raw-code',
                'classes' => ['number-lines'],
                'attributes' => ['startFrom' => '7'],
            ]
        );

        $t->same('', $highlighted['language']);
        $t->same('unsupported-language', $highlighted['diagnostics'][0]['code'] ?? null);
        $t->same(true, $highlighted['lineNumbering']['enabled']);
        $t->same(false, $highlighted['lineNumbering']['anchors']);
        $t->contains('<pre class="sourceCode numberSource number-lines"><code class="sourceCode" style="counter-reset: source-line 6;">', $highlighted['html']);
        $t->contains('<span id="raw-code-7"><a href="#raw-code-7"></a>legacy &lt;&lt; token</span>', $highlighted['html']);
        $t->contains('<span id="raw-code-8"><a href="#raw-code-8"></a>second line</span>', $highlighted['html']);
    },
    'highlights json and yaml keys scalars comments and punctuation' => static function (TestRunner $t): void {
        $highlighter = new SyntaxHighlighter();
        $json = $highlighter->highlight('{"title":"Legacy post","draft":false,"count":2}', 'json');
        $yaml = $highlighter->highlight("---\ntitle: \"Legacy post\"\ndraft: false\n# review note\n", 'yaml');

        $t->same('json', $json['language']);
        $t->contains('<span class="ot">&quot;title&quot;</span><span class="op">:</span><span class="st">&quot;Legacy post&quot;</span>', $json['html']);
        $t->contains('<span class="cn">false</span>', $json['html']);
        $t->contains('<span class="dv">2</span>', $json['html']);
        $t->same('yaml', $yaml['language']);
        $t->contains('<span class="op">---</span>', $yaml['html']);
        $t->contains('<span class="ot">title</span><span class="op">:</span> <span class="st">&quot;Legacy post&quot;</span>', $yaml['html']);
        $t->contains('<span class="co"># review note</span>', $yaml['html']);
    },
    'highlights html attributes sql keywords and functions for review packets' => static function (TestRunner $t): void {
        $highlighter = new SyntaxHighlighter();
        $html = $highlighter->highlight('<section data-id="42"><code>$post</code></section>', 'html5');
        $sql = $highlighter->highlight("select count(*) from posts where post_status = 'publish'", 'postgresql');

        $t->same('html', $html['language']);
        $t->contains('<span class="kw">&lt;section</span> <span class="ot">data-id</span><span class="op">=</span><span class="st">&quot;42&quot;</span><span class="op">&gt;</span>', $html['html']);
        $t->contains('<span class="kw">&lt;/section</span><span class="op">&gt;</span>', $html['html']);
        $t->same('sql', $sql['language']);
        $t->contains('<span class="kw">select</span> <span class="fu">count</span><span class="op">(*)</span> <span class="kw">from</span>', $sql['html']);
        $t->contains('<span class="st">&#039;publish&#039;</span>', $sql['html']);
    },
    'falls back safely for unsupported languages' => static function (TestRunner $t): void {
        $highlighted = (new SyntaxHighlighter())->highlight('<danger>& text', 'brainfuck');

        $t->same('', $highlighted['language']);
        $t->same('brainfuck', $highlighted['requestedLanguage']);
        $t->same('unsupported-language', $highlighted['diagnostics'][0]['code'] ?? null);
        $t->same([['type' => 'text', 'text' => '<danger>& text', 'class' => '']], $highlighted['tokens']);
        $t->contains('<pre class="sourceCode"><code class="sourceCode">&lt;danger&gt;&amp; text</code></pre>', $highlighted['html']);
    },
];
