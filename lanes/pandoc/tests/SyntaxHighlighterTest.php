<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\SyntaxHighlighter;
use PortLibs\Pandoc\WordPressBlockWriter;

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
        $t->same('haskell', SyntaxHighlighter::normalizeLanguage('lhs'));
        $t->same('haskell', SyntaxHighlighter::normalizeLanguage('literate-haskell'));
        $t->same('diff', SyntaxHighlighter::normalizeLanguage('patch'));
        $t->same('diff', SyntaxHighlighter::normalizeLanguage('unified-diff'));
        $t->same('dockerfile', SyntaxHighlighter::normalizeLanguage('Dockerfile'));
        $t->same('dockerfile', SyntaxHighlighter::normalizeLanguage('Containerfile'));
        $t->same('dockerfile', SyntaxHighlighter::normalizeLanguage('language-docker'));
        $t->same('dot', SyntaxHighlighter::normalizeLanguage('dot'));
        $t->same('dot', SyntaxHighlighter::normalizeLanguage('graphviz'));
        $t->same('dot', SyntaxHighlighter::normalizeLanguage('gv'));
        $t->same('apache', SyntaxHighlighter::normalizeLanguage('apache'));
        $t->same('apache', SyntaxHighlighter::normalizeLanguage('apacheconf'));
        $t->same('apache', SyntaxHighlighter::normalizeLanguage('apache-config'));
        $t->same('apache', SyntaxHighlighter::normalizeLanguage('htaccess'));
        $t->same('apache', SyntaxHighlighter::normalizeLanguage('httpd-conf'));
        $t->same('rst', SyntaxHighlighter::normalizeLanguage('rst'));
        $t->same('rst', SyntaxHighlighter::normalizeLanguage('rest'));
        $t->same('rst', SyntaxHighlighter::normalizeLanguage('reStructuredText'));
        $t->same('rst', SyntaxHighlighter::normalizeLanguage('language-restructured-text'));
        $t->same('html', SyntaxHighlighter::normalizeLanguage('HTML5'));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('mustache'));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('handlebars'));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('hbs'));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('ractive'));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('html.mst'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('language-js'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('mjs'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('cjs'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('node'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('nodejs'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('ecmascript'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('es6'));
        $t->same('jsx', SyntaxHighlighter::normalizeLanguage('jsx'));
        $t->same('jsx', SyntaxHighlighter::normalizeLanguage('javascript-react'));
        $t->same('c', SyntaxHighlighter::normalizeLanguage('c'));
        $t->same('c', SyntaxHighlighter::normalizeLanguage('h'));
        $t->same('cpp', SyntaxHighlighter::normalizeLanguage('c++'));
        $t->same('cpp', SyntaxHighlighter::normalizeLanguage('cpp'));
        $t->same('cpp', SyntaxHighlighter::normalizeLanguage('cxx'));
        $t->same('cpp', SyntaxHighlighter::normalizeLanguage('hpp'));
        $t->same('csharp', SyntaxHighlighter::normalizeLanguage('cs'));
        $t->same('csharp', SyntaxHighlighter::normalizeLanguage('csharp'));
        $t->same('csharp', SyntaxHighlighter::normalizeLanguage('C#'));
        $t->same('csharp', SyntaxHighlighter::normalizeLanguage('csx'));
        $t->same('csharp', SyntaxHighlighter::normalizeLanguage('language-cs'));
        $t->same('cmake', SyntaxHighlighter::normalizeLanguage('cmake'));
        $t->same('cmake', SyntaxHighlighter::normalizeLanguage('CMakeLists.txt'));
        $t->same('cmake', SyntaxHighlighter::normalizeLanguage('language-cmake'));
        $t->same('tex', SyntaxHighlighter::normalizeLanguage('latex'));
        $t->same('tex', SyntaxHighlighter::normalizeLanguage('TeX'));
        $t->same('ini', SyntaxHighlighter::normalizeLanguage('ini'));
        $t->same('ini', SyntaxHighlighter::normalizeLanguage('cfg'));
        $t->same('ini', SyntaxHighlighter::normalizeLanguage('gitconfig'));
        $t->same('ini', SyntaxHighlighter::normalizeLanguage('editorconfig'));
        $t->same('toml', SyntaxHighlighter::normalizeLanguage('toml'));
        $t->same('toml', SyntaxHighlighter::normalizeLanguage('Cargo.lock'));
        $t->same('yaml', SyntaxHighlighter::normalizeLanguage('yml'));
        $t->same('markdown', SyntaxHighlighter::normalizeLanguage('md'));
        $t->same('markdown', SyntaxHighlighter::normalizeLanguage('pandoc-markdown'));
        $t->same('markdown', SyntaxHighlighter::normalizeLanguage('commonmark'));
        $t->same('markdown', SyntaxHighlighter::normalizeLanguage('gfm'));
        $t->same('mermaid', SyntaxHighlighter::normalizeLanguage('mermaid'));
        $t->same('mermaid', SyntaxHighlighter::normalizeLanguage('mermaid-js'));
        $t->same('mermaid', SyntaxHighlighter::normalizeLanguage('language-mermaidjs'));
        $t->same('go', SyntaxHighlighter::normalizeLanguage('go'));
        $t->same('go', SyntaxHighlighter::normalizeLanguage('golang'));
        $t->same('go', SyntaxHighlighter::normalizeLanguage('language-go'));
        $t->same('nix', SyntaxHighlighter::normalizeLanguage('nix'));
        $t->same('nix', SyntaxHighlighter::normalizeLanguage('nix-expr'));
        $t->same('nix', SyntaxHighlighter::normalizeLanguage('nix-shell'));
        $t->same('nginx', SyntaxHighlighter::normalizeLanguage('nginx'));
        $t->same('nginx', SyntaxHighlighter::normalizeLanguage('nginxconf'));
        $t->same('nginx', SyntaxHighlighter::normalizeLanguage('nginx-config'));
        $t->same('nginx', SyntaxHighlighter::normalizeLanguage('language-nginx'));
        $t->same('makefile', SyntaxHighlighter::normalizeLanguage('make'));
        $t->same('makefile', SyntaxHighlighter::normalizeLanguage('makefile'));
        $t->same('makefile', SyntaxHighlighter::normalizeLanguage('GNUmakefile'));
        $t->same('makefile', SyntaxHighlighter::normalizeLanguage('mk'));
        $t->same('perl', SyntaxHighlighter::normalizeLanguage('perl'));
        $t->same('perl', SyntaxHighlighter::normalizeLanguage('pl'));
        $t->same('perl', SyntaxHighlighter::normalizeLanguage('PL'));
        $t->same('perl', SyntaxHighlighter::normalizeLanguage('pm'));
        $t->same('powershell', SyntaxHighlighter::normalizeLanguage('powershell'));
        $t->same('powershell', SyntaxHighlighter::normalizeLanguage('posh'));
        $t->same('powershell', SyntaxHighlighter::normalizeLanguage('ps1'));
        $t->same('powershell', SyntaxHighlighter::normalizeLanguage('psd1'));
        $t->same('powershell', SyntaxHighlighter::normalizeLanguage('psm1'));
        $t->same('powershell', SyntaxHighlighter::normalizeLanguage('pwsh'));
        $t->same('powershell', SyntaxHighlighter::normalizeLanguage('language-ps1'));
        $t->same('java', SyntaxHighlighter::normalizeLanguage('java'));
        $t->same('xml', SyntaxHighlighter::normalizeLanguage('xml'));
        $t->same('xml', SyntaxHighlighter::normalizeLanguage('svg'));
        $t->same('xml', SyntaxHighlighter::normalizeLanguage('xsd'));
        $t->same('xslt', SyntaxHighlighter::normalizeLanguage('xsl'));
        $t->same('xslt', SyntaxHighlighter::normalizeLanguage('xslt'));
        $t->same('ruby', SyntaxHighlighter::normalizeLanguage('rb'));
        $t->same('ruby', SyntaxHighlighter::normalizeLanguage('rake'));
        $t->same('rust', SyntaxHighlighter::normalizeLanguage('rs'));
        $t->same('rust', SyntaxHighlighter::normalizeLanguage('rust'));
        $t->same('rust', SyntaxHighlighter::normalizeLanguage('language-rs'));
        $t->same('scss', SyntaxHighlighter::normalizeLanguage('scss'));
        $t->same('scss', SyntaxHighlighter::normalizeLanguage('language-scss'));
        $t->same('sass', SyntaxHighlighter::normalizeLanguage('sass'));
        $t->same('lua', SyntaxHighlighter::normalizeLanguage('lua'));
        $t->same('lua', SyntaxHighlighter::normalizeLanguage('pandoc-lua'));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('bash'));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('sh'));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('shell'));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('console'));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('language-sh'));
        $t->same('python', SyntaxHighlighter::normalizeLanguage('py'));
        $t->same('python', SyntaxHighlighter::normalizeLanguage('py3'));
        $t->same('python', SyntaxHighlighter::normalizeLanguage('python3'));
        $t->same('r', SyntaxHighlighter::normalizeLanguage('r'));
        $t->same('r', SyntaxHighlighter::normalizeLanguage('Rscript'));
        $t->same('r', SyntaxHighlighter::normalizeLanguage('S'));
        $t->same('r', SyntaxHighlighter::normalizeLanguage('language-q'));
        $t->same('typescript', SyntaxHighlighter::normalizeLanguage('ts'));
        $t->same('typescript', SyntaxHighlighter::normalizeLanguage('typescript'));
        $t->same('tsx', SyntaxHighlighter::normalizeLanguage('tsx'));
        $t->same('tsx', SyntaxHighlighter::normalizeLanguage('typescript-react'));
        $t->same('tsx', SyntaxHighlighter::normalizeLanguage('language-tsx'));
        $t->same(null, SyntaxHighlighter::normalizeLanguage('sourceCode'));
        $t->same(null, SyntaxHighlighter::normalizeLanguage('lineAnchors'));
        $t->same(null, SyntaxHighlighter::normalizeLanguage('number-lines'));
        $t->same(null, SyntaxHighlighter::normalizeLanguage('tokenTitles'));
        $t->same(null, SyntaxHighlighter::normalizeLanguage('token-titles'));
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
    'renders opt in token title attributes for reviewer metadata' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[20] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a token-title PHP code block');
        }

        $highlighter = new SyntaxHighlighter();
        $plain = $highlighter->highlight('<?php echo esc_html($title);', 'php');
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'kate');
        $attributeEnabled = $highlighter->highlight(
            'echo esc_html($title);',
            'php',
            'pygments',
            ['attributes' => ['data-token-titles' => 'true']]
        );

        $t->same('php', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same(false, $plain['tokenTitles']);
        $t->same(false, str_contains($plain['html'], 'title="KeywordTok"'));
        $t->same('php', $highlighted['language']);
        $t->same(true, $highlighted['tokenTitles']);
        $t->same(3, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource php numberLines tokenTitles"><code class="sourceCode php" style="counter-reset: source-line 2;">', $highlighted['html']);
        $t->contains('<span id="token-title-review-3"><a href="#token-title-review-3"></a><span class="pp" title="PreprocessorTok">&lt;?php</span></span>', $highlighted['html']);
        $t->contains('<span id="token-title-review-4"><a href="#token-title-review-4"></a><span class="kw" title="KeywordTok">echo</span> <span class="fu" title="FunctionTok">esc_html</span>', $highlighted['html']);
        $t->contains('<span class="va" title="VariableTok">$title</span><span class="op" title="OperatorTok">);</span> <span class="co" title="CommentTok">// reviewer token titles</span>', $highlighted['html']);
        $t->same(true, $attributeEnabled['tokenTitles']);
        $t->contains('<span class="kw" title="KeywordTok">echo</span> <span class="fu" title="FunctionTok">esc_html</span>', $attributeEnabled['html']);
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
    'highlights css block theme snippets with at rules selectors and custom properties' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[21] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a CSS code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'espresso');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'espresso');
        $directCss = (new SyntaxHighlighter())->highlight(
            '@supports (display: grid) { #site-header::before { content: "Review"; } }',
            'css'
        );

        $t->same('css', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('css', $highlighted['language']);
        $t->same('css', $highlighted['requestedLanguage']);
        $t->same('espresso', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(70, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource css numberLines"><code class="sourceCode css" style="counter-reset: source-line 69;">', $highlighted['html']);
        $t->contains('<span id="css-review-70"><a href="#css-review-70"></a><span class="co">/* WordPress block style review */</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">@media</span> <span class="op">(</span><span class="ot">min-width</span><span class="op">:</span> <span class="dv">48rem</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="dt">.wp-block-import-card</span> <span class="op">&gt;</span> <span class="dt">a</span><span class="fu">:hover</span>', $highlighted['html']);
        $t->contains('<span class="dt">.wp-block-import-card</span><span class="fu">:focus-visible</span>', $highlighted['html']);
        $t->contains('<span class="ot">--accent-color</span><span class="op">:</span> <span class="cn">#005cc5</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">margin-block</span><span class="op">:</span> <span class="dv">1.5rem</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">color</span><span class="op">:</span> <span class="fu">var</span><span class="op">(</span><span class="ot">--accent-color</span><span class="op">)</span> <span class="kw">!important</span>', $highlighted['html']);
        $t->contains('<span class="ot">content</span><span class="op">:</span> <span class="st">&quot;Read more&quot;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="espresso">', $wordpressBlock);
        $t->contains('<span class="cn">#005cc5</span>', $wordpressBlock);
        $t->same('css', $directCss['language']);
        $t->contains('<span class="kw">@supports</span> <span class="op">(</span><span class="ot">display</span><span class="op">:</span> <span class="kw">grid</span><span class="op">)</span>', $directCss['html']);
        $t->contains('<span class="dt">#site-header</span><span class="fu">::before</span>', $directCss['html']);
    },
    'highlights rust review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[22] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Rust code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'zenburn');
        $directRust = (new SyntaxHighlighter())->highlight('let block: Option<&str> = Some(r#"ok"#);', 'rust');

        $t->same('rs', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('rust', SyntaxHighlighter::normalizeLanguage('rs'));
        $t->same('rust', $highlighted['language']);
        $t->same('rs', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(88, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource rs numberLines"><code class="sourceCode rust" style="counter-reset: source-line 87;">', $highlighted['html']);
        $t->contains('<span id="rust-review-88"><a href="#rust-review-88"></a><span class="co">// WordPress import review helper</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">use</span> <span class="va">serde_json</span><span class="op">::</span><span class="dt">Value</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">#[derive(Debug)]</span>', $highlighted['html']);
        $t->contains('<span class="kw">pub</span> <span class="kw">struct</span> <span class="dt">ReviewPacket</span><span class="op">&lt;</span><span class="ot">&#039;a</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">pub</span> <span class="va">title</span><span class="op">:</span> <span class="dt">Option</span><span class="op">&lt;&amp;</span><span class="ot">&#039;a</span> <span class="dt">str</span><span class="op">&gt;,</span>', $highlighted['html']);
        $t->contains('<span class="va">source_id</span><span class="op">:</span> <span class="dt">u64</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="kw">impl</span><span class="op">&lt;</span><span class="ot">&#039;a</span><span class="op">&gt;</span> <span class="dt">ReviewPacket</span><span class="op">&lt;</span><span class="ot">&#039;a</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">pub</span> <span class="kw">fn</span> <span class="fu">normalized_title</span><span class="op">(&amp;</span><span class="kw">self</span><span class="op">)</span> <span class="op">-&gt;</span> <span class="dt">String</span>', $highlighted['html']);
        $t->contains('<span class="kw">let</span> <span class="va">title</span> <span class="op">=</span> <span class="kw">self</span><span class="op">.</span><span class="va">title</span><span class="op">.</span><span class="fu">unwrap_or</span><span class="op">(</span><span class="st">&quot;Untitled&quot;</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="va">title</span><span class="op">.</span><span class="fu">trim</span><span class="op">().</span><span class="fu">is_empty</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="fu">format!</span><span class="op">(</span><span class="st">&quot;import-{}&quot;</span><span class="op">,</span> <span class="kw">self</span><span class="op">.</span><span class="va">source_id</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span><span class="op">.</span><span class="fu">to_string</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<span class="fu">format!</span><span class="op">(</span><span class="st">&quot;import-{}&quot;</span>', $wordpressBlock);
        $t->same('rust', $directRust['language']);
        $t->contains('<span class="kw">let</span> <span class="va">block</span><span class="op">:</span> <span class="dt">Option</span><span class="op">&lt;&amp;</span><span class="dt">str</span><span class="op">&gt;</span> <span class="op">=</span> <span class="cn">Some</span><span class="op">(</span><span class="st">r#&quot;ok&quot;#</span><span class="op">);</span>', $directRust['html']);
    },
    'highlights nix deployment review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[23] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Nix code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'kate');
        $directNix = (new SyntaxHighlighter())->highlight('{ lib ? import <nixpkgs/lib> }: rec { enabled = true; }', 'nix-expr');

        $t->same('nix', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('nix', $highlighted['language']);
        $t->same('nix', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(101, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource nix numberLines"><code class="sourceCode nix" style="counter-reset: source-line 100;">', $highlighted['html']);
        $t->contains('<span id="nix-review-101"><a href="#nix-review-101"></a><span class="co"># WordPress deployment expression review</span></span>', $highlighted['html']);
        $t->contains('<span class="op">{</span> <span class="va">pkgs</span> <span class="op">?</span> <span class="fu">import</span> <span class="cn">&lt;nixpkgs&gt;</span> <span class="op">{}</span> <span class="op">}:</span>', $highlighted['html']);
        $t->contains('<span class="kw">let</span>', $highlighted['html']);
        $t->contains('<span class="kw">inherit</span> <span class="op">(</span><span class="va">pkgs</span><span class="op">)</span> <span class="va">stdenv</span> <span class="va">writeText</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">pluginSlug</span> <span class="op">=</span> <span class="st">&quot;legacy-import&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">mediaPaths</span> <span class="op">=</span> <span class="op">[</span> <span class="st">./uploads</span> <span class="st">./assets</span> <span class="op">];</span>', $highlighted['html']);
        $t->contains('<span class="ot">reviewer</span> <span class="op">=</span> <span class="kw">if</span> <span class="va">stdenv</span><span class="op">.</span><span class="va">isLinux</span> <span class="kw">then</span> <span class="st">&quot;wp-cli&quot;</span> <span class="kw">else</span> <span class="st">&quot;manual&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">in</span>', $highlighted['html']);
        $t->contains('<span class="va">pkgs</span><span class="op">.</span><span class="va">writeText</span> <span class="st">&quot;${pluginSlug}-review.json&quot;</span> <span class="st">&#039;&#039;', $highlighted['html']);
        $t->contains('<span class="st">  {&quot;reviewer&quot;:&quot;${reviewer}&quot;,&quot;media&quot;:${builtins.toJSON mediaPaths}}</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="va">pkgs</span><span class="op">.</span><span class="va">writeText</span>', $wordpressBlock);
        $t->same('nix', $directNix['language']);
        $t->same('nix-expr', $directNix['requestedLanguage']);
        $t->contains('<span class="op">{</span> <span class="va">lib</span> <span class="op">?</span> <span class="fu">import</span> <span class="cn">&lt;nixpkgs/lib&gt;</span>', $directNix['html']);
        $t->contains('<span class="kw">rec</span> <span class="op">{</span> <span class="ot">enabled</span> <span class="op">=</span> <span class="cn">true</span><span class="op">;</span>', $directNix['html']);
    },
    'highlights scss block theme snippets with sass aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[24] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an SCSS code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'espresso');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'espresso');
        $directSass = (new SyntaxHighlighter())->highlight(implode("\n", [
            '@use "sass:color"',
            '$gap: 1rem',
            '.wp-block',
            '  margin: $gap',
        ]), 'sass');

        $t->same('scss', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('scss', $highlighted['language']);
        $t->same('scss', $highlighted['requestedLanguage']);
        $t->same('espresso', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(120, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource scss numberLines"><code class="sourceCode scss" style="counter-reset: source-line 119;">', $highlighted['html']);
        $t->contains('<span id="scss-review-120"><a href="#scss-review-120"></a><span class="co">// WordPress theme Sass review</span></span>', $highlighted['html']);
        $t->contains('<span class="va">$accent-color</span><span class="op">:</span> <span class="cn">#005cc5</span> <span class="kw">!default</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="va">$breakpoints</span><span class="op">:</span> <span class="op">(</span><span class="st">&quot;desktop&quot;</span><span class="op">:</span> <span class="dv">48rem</span>', $highlighted['html']);
        $t->contains('<span class="kw">@mixin</span> <span class="fu">import-card</span><span class="op">(</span><span class="va">$selector</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="op">#{</span><span class="va">$selector</span><span class="op">}</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">color</span><span class="op">:</span> <span class="va">$accent-color</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="op">&amp;</span><span class="fu">:hover</span> <span class="op">{</span> <span class="ot">color</span><span class="op">:</span> <span class="fu">darken</span><span class="op">(</span><span class="va">$accent-color</span><span class="op">,</span> <span class="dv">10%</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">@include</span> <span class="fu">import-card</span><span class="op">(</span><span class="st">&quot;.wp-block-import-card&quot;</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="espresso">', $wordpressBlock);
        $t->contains('<span class="kw">@include</span> <span class="fu">import-card</span>', $wordpressBlock);
        $t->same('sass', $directSass['language']);
        $t->same('sass', $directSass['requestedLanguage']);
        $t->contains('<span class="kw">@use</span> <span class="st">&quot;sass:color&quot;</span>', $directSass['html']);
        $t->contains('<span class="va">$gap</span><span class="op">:</span> <span class="dv">1rem</span>', $directSass['html']);
        $t->contains('<span class="dt">.wp-block</span>', $directSass['html']);
        $t->contains('<span class="ot">margin</span><span class="op">:</span> <span class="va">$gap</span>', $directSass['html']);
    },
    'highlights go review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[25] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Go code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'tango');
        $directGo = (new SyntaxHighlighter())->highlight('go func() { defer close(done); done <- "ok" }()', 'golang');

        $t->same('go', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('go', $highlighted['language']);
        $t->same('go', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(135, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource go numberLines"><code class="sourceCode go" style="counter-reset: source-line 134;">', $highlighted['html']);
        $t->contains('<span id="go-review-135"><a href="#go-review-135"></a><span class="co">// WordPress import packet normalizer</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">package</span> <span class="va">review</span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="op">(</span>', $highlighted['html']);
        $t->contains('<span class="st">&quot;context&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">type</span> <span class="dt">ReviewPacket</span> <span class="kw">struct</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="dt">Title</span> <span class="dt">string</span> <span class="st">`json:&quot;title&quot;`</span>', $highlighted['html']);
        $t->contains('<span class="dt">Meta</span> <span class="kw">map</span><span class="op">[</span><span class="dt">string</span><span class="op">]</span><span class="dt">any</span>', $highlighted['html']);
        $t->contains('<span class="kw">func</span> <span class="fu">NormalizeTitle</span><span class="op">(</span><span class="va">ctx</span> <span class="va">context</span><span class="op">.</span><span class="dt">Context</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="va">packet</span> <span class="op">==</span> <span class="cn">nil</span> <span class="op">||</span>', $highlighted['html']);
        $t->contains('<span class="kw">var</span> <span class="va">payload</span> <span class="kw">map</span><span class="op">[</span><span class="dt">string</span><span class="op">]</span><span class="dt">any</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="va">err</span> <span class="op">:=</span> <span class="va">json</span><span class="op">.</span><span class="fu">Unmarshal</span><span class="op">([]</span><span class="dt">byte</span>', $highlighted['html']);
        $t->contains('<span class="kw">go</span> <span class="kw">func</span><span class="op">()</span> <span class="op">{</span> <span class="va">_</span> <span class="op">=</span> <span class="va">ctx</span><span class="op">.</span><span class="fu">Err</span><span class="op">()</span> <span class="op">}()</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="va">json</span><span class="op">.</span><span class="fu">Unmarshal</span>', $wordpressBlock);
        $t->same('go', $directGo['language']);
        $t->same('golang', $directGo['requestedLanguage']);
        $t->contains('<span class="kw">go</span> <span class="kw">func</span><span class="op">()</span>', $directGo['html']);
        $t->contains('<span class="kw">defer</span> <span class="fu">close</span><span class="op">(</span><span class="va">done</span><span class="op">);</span>', $directGo['html']);
        $t->contains('<span class="va">done</span> <span class="op">&lt;-</span> <span class="st">&quot;ok&quot;</span>', $directGo['html']);
    },
    'highlights powershell migration review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[26] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a PowerShell code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directPowerShell = (new SyntaxHighlighter())->highlight('Get-Content -LiteralPath $Env:WP_IMPORT | ConvertFrom-Json', 'pwsh');

        $t->same('ps1', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('powershell', $highlighted['language']);
        $t->same('ps1', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(150, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource ps1 numberLines"><code class="sourceCode powershell" style="counter-reset: source-line 149;">', $highlighted['html']);
        $t->contains('<span id="powershell-review-150"><a href="#powershell-review-150"></a><span class="co"># WordPress Windows import review</span></span>', $highlighted['html']);
        $t->contains('<span class="ot">[CmdletBinding()]</span>', $highlighted['html']);
        $t->contains('<span class="kw">param</span><span class="op">(</span>', $highlighted['html']);
        $t->contains('<span class="dt">[string]</span><span class="va">$SourcePath</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="dt">[switch]</span><span class="va">$DryRun</span>', $highlighted['html']);
        $t->contains('<span class="va">$packet</span> <span class="op">=</span> <span class="fu">Get-Content</span> <span class="ot">-LiteralPath</span> <span class="va">$SourcePath</span> <span class="op">|</span> <span class="fu">ConvertFrom-Json</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="cn">$null</span> <span class="op">-eq</span> <span class="va">$packet</span><span class="op">.</span><span class="va">title</span> <span class="op">-or</span>', $highlighted['html']);
        $t->contains('<span class="va">$packet</span><span class="op">.</span><span class="va">title</span><span class="op">.</span><span class="fu">Trim</span><span class="op">()</span> <span class="op">-eq</span> <span class="st">&quot;&quot;</span>', $highlighted['html']);
        $t->contains('<span class="fu">Write-Warning</span> <span class="st">&quot;Missing title in $SourcePath&quot;</span>', $highlighted['html']);
        $t->contains('<span class="va">$blocks</span> <span class="op">=</span> <span class="op">@(</span>', $highlighted['html']);
        $t->contains('<span class="st">&quot;&lt;!-- wp:paragraph --&gt;&lt;p&gt;$($packet.title)&lt;/p&gt;&lt;!-- /wp:paragraph --&gt;&quot;</span>', $highlighted['html']);
        $t->contains('<span class="va">$meta</span> <span class="op">=</span> <span class="op">@{</span>', $highlighted['html']);
        $t->contains('<span class="ot">source</span> <span class="op">=</span> <span class="va">$SourcePath</span>', $highlighted['html']);
        $t->contains('<span class="va">$blocks</span> <span class="op">|</span> <span class="fu">ForEach-Object</span> <span class="op">{</span> <span class="va">$_</span><span class="op">.</span><span class="fu">Trim</span><span class="op">()</span> <span class="op">}</span> <span class="op">|</span> <span class="fu">Set-Content</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="fu">Set-Content</span> <span class="ot">-LiteralPath</span> <span class="st">&quot;.\\review.html&quot;</span>', $wordpressBlock);
        $t->same('powershell', $directPowerShell['language']);
        $t->same('pwsh', $directPowerShell['requestedLanguage']);
        $t->contains('<span class="fu">Get-Content</span> <span class="ot">-LiteralPath</span> <span class="va">$Env:WP_IMPORT</span> <span class="op">|</span> <span class="fu">ConvertFrom-Json</span>', $directPowerShell['html']);
    },
    'highlights graphviz dot workflow review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[27] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Graphviz DOT code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'monochrome');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'monochrome');
        $directGraphviz = (new SyntaxHighlighter())->highlight('graph Review { draft -- published [weight=2]; }', 'graphviz');

        $t->same('dot', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('dot', $highlighted['language']);
        $t->same('dot', $highlighted['requestedLanguage']);
        $t->same('monochrome', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(170, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource dot numberLines"><code class="sourceCode dot" style="counter-reset: source-line 169;">', $highlighted['html']);
        $t->contains('<span id="dot-review-170"><a href="#dot-review-170"></a><span class="co">// WordPress import workflow graph</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">digraph</span> <span class="va">ImportFlow</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">graph</span> <span class="op">[</span><span class="ot">rankdir</span><span class="op">=</span><span class="cn">LR</span><span class="op">,</span> <span class="ot">label</span><span class="op">=</span><span class="st">&quot;Legacy import&quot;</span><span class="op">];</span>', $highlighted['html']);
        $t->contains('<span class="kw">node</span> <span class="op">[</span><span class="ot">shape</span><span class="op">=</span><span class="cn">box</span><span class="op">,</span> <span class="ot">style</span><span class="op">=</span><span class="st">&quot;rounded,filled&quot;</span><span class="op">,</span> <span class="ot">color</span><span class="op">=</span><span class="st">&quot;#005cc5&quot;</span><span class="op">];</span>', $highlighted['html']);
        $t->contains('<span class="va">review</span> <span class="op">[</span><span class="ot">label</span><span class="op">=</span><span class="st">&quot;Reviewer Queue&quot;</span><span class="op">,</span> <span class="ot">URL</span><span class="op">=</span><span class="st">&quot;https://example.test/wp-admin/edit.php&quot;</span><span class="op">];</span>', $highlighted['html']);
        $t->contains('<span class="va">ingest</span> <span class="op">-&gt;</span> <span class="va">review</span> <span class="op">[</span><span class="ot">label</span><span class="op">=</span><span class="st">&quot;normalize&quot;</span><span class="op">];</span>', $highlighted['html']);
        $t->contains('<span class="va">review</span> <span class="op">-&gt;</span> <span class="va">publish</span> <span class="op">[</span><span class="ot">label</span><span class="op">=</span><span class="st">&quot;approve&quot;</span><span class="op">,</span> <span class="ot">weight</span><span class="op">=</span><span class="dv">2</span><span class="op">];</span>', $highlighted['html']);
        $t->contains('<span class="kw">subgraph</span> <span class="va">cluster_media</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="monochrome">', $wordpressBlock);
        $t->contains('<span class="va">review</span> <span class="op">-&gt;</span> <span class="va">publish</span>', $wordpressBlock);
        $t->same('dot', $directGraphviz['language']);
        $t->same('graphviz', $directGraphviz['requestedLanguage']);
        $t->contains('<span class="kw">graph</span> <span class="va">Review</span> <span class="op">{</span>', $directGraphviz['html']);
        $t->contains('<span class="va">draft</span> <span class="op">--</span> <span class="va">published</span> <span class="op">[</span><span class="ot">weight</span><span class="op">=</span><span class="dv">2</span><span class="op">];</span>', $directGraphviz['html']);
    },
    'highlights javascript gutenberg module snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[28] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a JavaScript module code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'kate');
        $directNode = (new SyntaxHighlighter())->highlight(
            '#!/usr/bin/env node' . "\n" . 'console.log(JSON.stringify({ ok: true, count: 2n }))',
            'node'
        );
        $directCjs = (new SyntaxHighlighter())->highlight('module.exports = require("@wordpress/scripts");', 'cjs');

        $t->same('mjs', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('javascript', $highlighted['language']);
        $t->same('mjs', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(190, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource mjs numberLines"><code class="sourceCode javascript" style="counter-reset: source-line 189;">', $highlighted['html']);
        $t->contains('<span id="gutenberg-js-review-190"><a href="#gutenberg-js-review-190"></a><span class="co">// Gutenberg import block registration review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="op">{</span> <span class="va">registerBlockType</span> <span class="op">}</span> <span class="kw">from</span> <span class="st">&quot;@wordpress/blocks&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">const</span> <span class="va">slugify</span> <span class="op">=</span> <span class="op">(</span><span class="va">title</span> <span class="op">=</span> <span class="st">&quot;Untitled&quot;</span><span class="op">)</span> <span class="op">=&gt;</span>', $highlighted['html']);
        $t->contains('<span class="fu">replace</span><span class="op">(</span><span class="st">/\\s+/gu</span><span class="op">,</span> <span class="st">&quot;-&quot;</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">export</span> <span class="kw">async</span> <span class="kw">function</span> <span class="fu">registerImportBlock</span><span class="op">(</span><span class="va">sourceId</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">await</span> <span class="fu">apiFetch</span><span class="op">({</span> <span class="ot">path</span><span class="op">:</span> <span class="st">&quot;/wp/v2/posts?per_page=1&quot;</span> <span class="op">});</span>', $highlighted['html']);
        $t->contains('<span class="fu">registerBlockType</span><span class="op">(</span><span class="st">&quot;legacy/import-review&quot;</span><span class="op">,</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">attributes</span><span class="op">:</span> <span class="op">{</span> <span class="ot">sourceId</span><span class="op">:</span> <span class="op">{</span> <span class="ot">type</span><span class="op">:</span> <span class="st">&quot;string&quot;</span>', $highlighted['html']);
        $t->contains('<span class="dt">console</span><span class="op">.</span><span class="fu">log</span><span class="op">(</span><span class="dt">JSON</span><span class="op">.</span><span class="fu">stringify</span><span class="op">(</span><span class="va">response</span><span class="op">));</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="va">wp</span><span class="op">.</span><span class="va">element</span><span class="op">.</span><span class="fu">createElement</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="fu">registerBlockType</span><span class="op">(</span><span class="st">&quot;legacy/import-review&quot;</span>', $wordpressBlock);
        $t->same('javascript', $directNode['language']);
        $t->same('node', $directNode['requestedLanguage']);
        $t->contains('<span class="co">#!/usr/bin/env node</span>', $directNode['html']);
        $t->contains('<span class="dt">console</span><span class="op">.</span><span class="fu">log</span><span class="op">(</span><span class="dt">JSON</span><span class="op">.</span><span class="fu">stringify</span>', $directNode['html']);
        $t->contains('<span class="ot">ok</span><span class="op">:</span> <span class="cn">true</span><span class="op">,</span> <span class="ot">count</span><span class="op">:</span> <span class="dv">2n</span>', $directNode['html']);
        $t->same('javascript', $directCjs['language']);
        $t->same('cjs', $directCjs['requestedLanguage']);
        $t->contains('<span class="va">module</span><span class="op">.</span><span class="va">exports</span> <span class="op">=</span> <span class="fu">require</span><span class="op">(</span><span class="st">&quot;@wordpress/scripts&quot;</span><span class="op">);</span>', $directCjs['html']);
    },
    'highlights csharp aspnet review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[29] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a C# code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'haddock');
        $directCsharp = (new SyntaxHighlighter())->highlight('await Console.Out.WriteLineAsync("ok");', 'csharp');

        $t->same('cs', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('csharp', $highlighted['language']);
        $t->same('cs', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(210, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource cs numberLines"><code class="sourceCode csharp" style="counter-reset: source-line 209;">', $highlighted['html']);
        $t->contains('<span id="csharp-review-210"><a href="#csharp-review-210"></a><span class="co">// ASP.NET legacy import packet review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">using</span> <span class="dt">System</span><span class="op">.</span><span class="dt">Text</span><span class="op">.</span><span class="dt">Json</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">namespace</span> <span class="dt">Legacy</span><span class="op">.</span><span class="dt">Import</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">public</span> <span class="kw">sealed</span> <span class="kw">record</span> <span class="dt">ReviewPacket</span><span class="op">(</span>', $highlighted['html']);
        $t->contains('<span class="ot">[property: JsonPropertyName(&quot;title&quot;)]</span> <span class="dt">string</span><span class="op">?</span> <span class="dt">Title</span>', $highlighted['html']);
        $t->contains('<span class="kw">public</span> <span class="kw">static</span> <span class="kw">async</span> <span class="dt">Task</span><span class="op">&lt;</span><span class="dt">string</span><span class="op">&gt;</span> <span class="fu">RenderAsync</span>', $highlighted['html']);
        $t->contains('<span class="dt">JsonSerializer</span><span class="op">.</span><span class="fu">Deserialize</span><span class="op">&lt;</span><span class="dt">ReviewPacket</span><span class="op">&gt;(</span><span class="va">rawJson</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="va">packet</span><span class="op">?.</span><span class="dt">Title</span> <span class="op">??</span> <span class="st">&quot;Untitled&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="dt">string</span><span class="op">.</span><span class="fu">IsNullOrWhiteSpace</span><span class="op">(</span><span class="va">title</span><span class="op">))</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="st">$&quot;&lt;!-- wp:paragraph --&gt;&lt;p&gt;Import {packet?.SourceId}&lt;/p&gt;&lt;!-- /wp:paragraph --&gt;&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">await</span> <span class="dt">Console</span><span class="op">.</span><span class="dt">Out</span><span class="op">.</span><span class="fu">WriteLineAsync</span><span class="op">(</span><span class="va">title</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="va">title</span><span class="op">.</span><span class="fu">Trim</span><span class="op">();</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="st">$&quot;&lt;!-- wp:paragraph --&gt;&lt;p&gt;Import {packet?.SourceId}&lt;/p&gt;&lt;!-- /wp:paragraph --&gt;&quot;</span>', $wordpressBlock);
        $t->same('csharp', $directCsharp['language']);
        $t->same('csharp', $directCsharp['requestedLanguage']);
        $t->contains('<span class="kw">await</span> <span class="dt">Console</span><span class="op">.</span><span class="dt">Out</span><span class="op">.</span><span class="fu">WriteLineAsync</span><span class="op">(</span><span class="st">&quot;ok&quot;</span><span class="op">);</span>', $directCsharp['html']);
    },
    'highlights tsx gutenberg typed component snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[36] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a TSX code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'kate');
        $directTsx = $highlighter->highlight(
            'type Props = { title?: string }; export const Edit = (props: Props) => <PanelBody title={props.title ?? "Import"} />;',
            'typescript-react'
        );

        $t->same('tsx', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('tsx', SyntaxHighlighter::normalizeLanguage('tsx'));
        $t->same('tsx', SyntaxHighlighter::normalizeLanguage('typescript-react'));
        $t->same('tsx', $highlighted['language']);
        $t->same('tsx', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(350, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource tsx numberLines"><code class="sourceCode tsx" style="counter-reset: source-line 349;">', $highlighted['html']);
        $t->contains('<span id="tsx-review-350"><a href="#tsx-review-350"></a><span class="co">// Gutenberg typed block inspector review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="kw">type</span> <span class="op">{</span> <span class="dt">BlockEditProps</span> <span class="op">}</span> <span class="kw">from</span> <span class="st">&quot;@wordpress/blocks&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">type</span> <span class="dt">ReviewAttributes</span> <span class="op">=</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span><span class="op">?:</span> <span class="dt">string</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">export</span> <span class="kw">const</span> <span class="dt">Edit</span>', $highlighted['html']);
        $t->contains('<span class="dt">BlockEditProps</span><span class="op">&lt;</span><span class="dt">ReviewAttributes</span><span class="op">&gt;)</span> <span class="op">=&gt;</span>', $highlighted['html']);
        $t->contains('<span class="fu">&lt;InspectorControls</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="fu">&lt;PanelBody</span> <span class="ot">title</span><span class="op">={</span><span class="st">`Import ${attributes.sourceId}`</span><span class="op">}&gt;</span>', $highlighted['html']);
        $t->contains('<span class="fu">&lt;TextControl</span>', $highlighted['html']);
        $t->contains('<span class="ot">value</span><span class="op">={</span><span class="va">attributes</span><span class="op">.</span><span class="va">title</span> <span class="op">??</span> <span class="st">&quot;Untitled&quot;</span><span class="op">}</span>', $highlighted['html']);
        $t->contains('<span class="ot">onChange</span><span class="op">={(</span><span class="va">title</span><span class="op">:</span> <span class="dt">string</span><span class="op">)</span> <span class="op">=&gt;</span> <span class="fu">setAttributes</span><span class="op">({</span> <span class="va">title</span> <span class="op">})}</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="fu">&lt;TextControl</span>', $wordpressBlock);
        $t->same('tsx', $directTsx['language']);
        $t->same('typescript-react', $directTsx['requestedLanguage']);
        $t->contains('<span class="kw">type</span> <span class="dt">Props</span> <span class="op">=</span>', $directTsx['html']);
        $t->contains('<span class="kw">export</span> <span class="kw">const</span> <span class="dt">Edit</span>', $directTsx['html']);
        $t->contains('<span class="fu">&lt;PanelBody</span> <span class="ot">title</span><span class="op">={</span><span class="va">props</span><span class="op">.</span><span class="va">title</span> <span class="op">??</span> <span class="st">&quot;Import&quot;</span><span class="op">}</span> <span class="op">/&gt;;</span>', $directTsx['html']);
    },
    'highlights cmake build review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[37] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a CMake code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'zenburn');
        $directCMake = $highlighter->highlight('message(STATUS "review ok")', 'CMakeLists.txt');

        $t->same('cmake', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('cmake', SyntaxHighlighter::normalizeLanguage('cmake'));
        $t->same('cmake', SyntaxHighlighter::normalizeLanguage('CMakeLists.txt'));
        $t->same('cmake', $highlighted['language']);
        $t->same('cmake', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(370, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource cmake numberLines"><code class="sourceCode cmake" style="counter-reset: source-line 369;">', $highlighted['html']);
        $t->contains('<span id="cmake-review-370"><a href="#cmake-review-370"></a><span class="co"># WordPress native extension build review</span></span>', $highlighted['html']);
        $t->contains('<span class="fu">cmake_minimum_required</span><span class="op">(</span><span class="kw">VERSION</span> <span class="dv">3.20</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">project</span><span class="op">(</span><span class="va">WPImportReview</span> <span class="kw">VERSION</span> <span class="dv">1.0</span> <span class="kw">LANGUAGES</span> <span class="dt">C</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">set</span><span class="op">(</span><span class="va">PLUGIN_SLUG</span> <span class="st">&quot;legacy-import&quot;</span> <span class="kw">CACHE</span> <span class="dt">STRING</span>', $highlighted['html']);
        $t->contains('<span class="fu">option</span><span class="op">(</span><span class="va">WP_IMPORT_BUILD_SHARED</span> <span class="st">&quot;Build shared review helper&quot;</span> <span class="cn">ON</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">add_library</span><span class="op">(</span><span class="va">wp_import_review</span> <span class="dt">MODULE</span> <span class="va">src</span><span class="op">/</span><span class="va">review</span><span class="op">.</span><span class="dt">c</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">target_compile_definitions</span><span class="op">(</span><span class="va">wp_import_review</span> <span class="kw">PRIVATE</span>', $highlighted['html']);
        $t->contains('<span class="ot">PLUGIN_SLUG</span><span class="op">=</span><span class="st">&quot;${PLUGIN_SLUG}&quot;</span>', $highlighted['html']);
        $t->contains('<span class="va">$&lt;$&lt;CONFIG:Debug&gt;:WP_IMPORT_DEBUG=1&gt;</span>', $highlighted['html']);
        $t->contains('<span class="fu">target_include_directories</span><span class="op">(</span><span class="va">wp_import_review</span> <span class="kw">PRIVATE</span> <span class="va">${CMAKE_CURRENT_SOURCE_DIR}</span><span class="op">/</span><span class="va">include</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">install</span><span class="op">(</span><span class="kw">TARGETS</span> <span class="va">wp_import_review</span> <span class="kw">LIBRARY</span> <span class="kw">DESTINATION</span> <span class="va">lib</span><span class="op">/</span><span class="va">wordpress</span><span class="op">/</span><span class="va">plugins</span><span class="op">/</span><span class="va">${PLUGIN_SLUG}</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<span class="fu">target_compile_definitions</span>', $wordpressBlock);
        $t->same('cmake', $directCMake['language']);
        $t->same('CMakeLists.txt', $directCMake['requestedLanguage']);
        $t->contains('<span class="fu">message</span><span class="op">(</span><span class="va">STATUS</span> <span class="st">&quot;review ok&quot;</span><span class="op">)</span>', $directCMake['html']);
    },
    'highlights nginx server review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[38] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an Nginx code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'tango');
        $directNginx = $highlighter->highlight('location ~ \.php$ { fastcgi_pass unix:/run/php/php-fpm.sock; }', 'nginxconf');

        $t->same('nginx', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('nginx', SyntaxHighlighter::normalizeLanguage('nginx'));
        $t->same('nginx', SyntaxHighlighter::normalizeLanguage('nginxconf'));
        $t->same('nginx', SyntaxHighlighter::normalizeLanguage('nginx-config'));
        $t->same('nginx', $highlighted['language']);
        $t->same('nginx', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(390, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource nginx numberLines"><code class="sourceCode nginx" style="counter-reset: source-line 389;">', $highlighted['html']);
        $t->contains('<span id="nginx-review-390"><a href="#nginx-review-390"></a><span class="co"># WordPress Nginx permalink and PHP-FPM review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">server</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">listen</span> <span class="dv">443</span> <span class="cn">ssl</span> <span class="cn">http2</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">server_name</span> <span class="va">example.test</span> <span class="va">www.example.test</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">root</span> <span class="st">/srv/www/legacy-import</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">location</span> <span class="st">/</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">try_files</span> <span class="va">$uri</span> <span class="va">$uri</span><span class="st">/</span> <span class="st">/index.php?</span><span class="va">$args</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">location</span> <span class="op">~</span> <span class="st">\\.php$</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">include</span> <span class="va">fastcgi_params</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">fastcgi_param</span> <span class="va">SCRIPT_FILENAME</span> <span class="va">$document_root$fastcgi_script_name</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">fastcgi_pass</span> <span class="st">unix:/run/php/php-fpm.sock</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">add_header</span> <span class="va">X-Import-Source</span> <span class="st">&quot;legacy&quot;</span> <span class="cn">always</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">rewrite</span> <span class="st">^</span> <span class="st">/index.php</span> <span class="cn">last</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="kw">fastcgi_pass</span> <span class="st">unix:/run/php/php-fpm.sock</span>', $wordpressBlock);
        $t->same('nginx', $directNginx['language']);
        $t->same('nginxconf', $directNginx['requestedLanguage']);
        $t->contains('<span class="kw">location</span> <span class="op">~</span> <span class="st">\\.php$</span> <span class="op">{</span> <span class="kw">fastcgi_pass</span> <span class="st">unix:/run/php/php-fpm.sock</span><span class="op">;</span> <span class="op">}</span>', $directNginx['html']);
    },
    'highlights twig timber template snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[39] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Twig template code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'espresso');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'espresso');
        $directTwig = $highlighter->highlight('{{ post.title|default("Untitled")|e }}', 'html+twig');

        $t->same('twig', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('twig', SyntaxHighlighter::normalizeLanguage('twig'));
        $t->same('twig', SyntaxHighlighter::normalizeLanguage('timber'));
        $t->same('twig', SyntaxHighlighter::normalizeLanguage('html+twig'));
        $t->same('twig', $highlighted['language']);
        $t->same('twig', $highlighted['requestedLanguage']);
        $t->same('espresso', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(410, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource twig numberLines"><code class="sourceCode twig" style="counter-reset: source-line 409;">', $highlighted['html']);
        $t->contains('<span id="twig-template-review-410"><a href="#twig-template-review-410"></a><span class="co">{# Timber theme template review #}</span></span>', $highlighted['html']);
        $t->contains('<span class="op">{%</span> <span class="kw">extends</span> <span class="st">&quot;base.twig&quot;</span> <span class="op">%}</span>', $highlighted['html']);
        $t->contains('<span class="op">{%</span> <span class="kw">set</span> <span class="va">blocks</span> <span class="op">=</span> <span class="op">[</span><span class="st">&quot;core/paragraph&quot;</span><span class="op">,</span> <span class="st">&quot;core/image&quot;</span><span class="op">]</span> <span class="op">%}</span>', $highlighted['html']);
        $t->contains('<span class="op">{%</span> <span class="kw">for</span> <span class="va">item</span> <span class="kw">in</span> <span class="va">posts</span> <span class="kw">if</span> <span class="va">item</span><span class="op">.</span><span class="va">status</span> <span class="op">==</span> <span class="st">&quot;publish&quot;</span> <span class="op">%}</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;article</span> <span class="ot">class</span><span class="op">=</span><span class="st">&quot;wp-block-import-card&quot;</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;h2</span><span class="op">&gt;{{</span> <span class="va">item</span><span class="op">.</span><span class="va">title</span><span class="op">|</span><span class="fu">default</span><span class="op">(</span><span class="st">&quot;Untitled&quot;</span><span class="op">)|</span><span class="fu">e</span> <span class="op">}}</span><span class="kw">&lt;/h2</span>', $highlighted['html']);
        $t->contains('<span class="op">{{</span> <span class="fu">function</span><span class="op">(</span><span class="st">&quot;wp_kses_post&quot;</span><span class="op">,</span> <span class="va">item</span><span class="op">.</span><span class="va">content</span><span class="op">)|</span><span class="fu">raw</span> <span class="op">}}</span>', $highlighted['html']);
        $t->contains('<span class="op">{%</span> <span class="kw">else</span> <span class="op">%}</span>', $highlighted['html']);
        $t->contains('<span class="op">{{</span> <span class="fu">include</span><span class="op">(</span><span class="st">&quot;partials/empty.twig&quot;</span><span class="op">,</span> <span class="op">{</span> <span class="ot">source</span><span class="op">:</span> <span class="va">sourceId</span> <span class="op">})</span> <span class="op">}}</span>', $highlighted['html']);
        $t->contains('<span class="op">{%</span> <span class="kw">endfor</span> <span class="op">%}</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="espresso">', $wordpressBlock);
        $t->contains('<span class="st">&quot;wp_kses_post&quot;</span>', $wordpressBlock);
        $t->same('twig', $directTwig['language']);
        $t->same('html+twig', $directTwig['requestedLanguage']);
        $t->contains('<span class="op">{{</span> <span class="va">post</span><span class="op">.</span><span class="va">title</span><span class="op">|</span><span class="fu">default</span><span class="op">(</span><span class="st">&quot;Untitled&quot;</span><span class="op">)|</span><span class="fu">e</span> <span class="op">}}</span>', $directTwig['html']);
    },
    'highlights mustache handlebars template snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[40] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Handlebars template code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'kate');
        $directHandlebars = $highlighter->highlight('{{#each posts}}{{title}}{{else}}{{default "Untitled"}}{{/each}}', 'handlebars');

        $t->same('hbs', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('hbs'));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('handlebars'));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('html.mst'));
        $t->same('mustache', $highlighted['language']);
        $t->same('hbs', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(430, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource hbs numberLines"><code class="sourceCode mustache" style="counter-reset: source-line 429;">', $highlighted['html']);
        $t->contains('<span id="handlebars-template-review-430"><a href="#handlebars-template-review-430"></a><span class="co">{{!-- Handlebars theme migration review --}}</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;section</span> <span class="ot">class</span><span class="op">=</span><span class="st">&quot;wp-block-import-card&quot;</span> <span class="ot">data-source</span><span class="op">={{</span><span class="va">sourceId</span><span class="op">}}&gt;</span>', $highlighted['html']);
        $t->contains('<span class="op">{{#</span><span class="kw">if</span> <span class="va">title</span><span class="op">}}</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;h2</span><span class="op">&gt;{{</span><span class="fu">default</span> <span class="st">&quot;Untitled&quot;</span><span class="op">}}</span><span class="kw">&lt;/h2</span>', $highlighted['html']);
        $t->contains('<span class="op">{{#</span><span class="kw">each</span> <span class="va">media</span><span class="op">}}</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;img</span> <span class="ot">src</span><span class="op">={{</span><span class="va">url</span><span class="op">}}</span> <span class="ot">alt</span><span class="op">={{</span><span class="va">alt</span><span class="op">}}</span> <span class="op">/&gt;</span>', $highlighted['html']);
        $t->contains('<span class="op">{{{</span><span class="va">rawBlock</span><span class="op">}}}</span>', $highlighted['html']);
        $t->contains('<span class="op">{{&gt;</span> <span class="va">footer</span> <span class="ot">source</span><span class="op">=</span><span class="va">sourceId</span> <span class="ot">count</span><span class="op">=</span><span class="dv">2</span><span class="op">}}</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="op">{{&gt;</span> <span class="va">footer</span>', $wordpressBlock);
        $t->same('mustache', $directHandlebars['language']);
        $t->same('handlebars', $directHandlebars['requestedLanguage']);
        $t->contains('<span class="op">{{#</span><span class="kw">each</span> <span class="va">posts</span><span class="op">}}{{</span><span class="va">title</span>', $directHandlebars['html']);
        $t->contains('<span class="op">}}{{</span><span class="kw">else</span><span class="op">}}{{</span>', $directHandlebars['html']);
        $t->contains('<span class="fu">default</span> <span class="st">&quot;Untitled&quot;</span><span class="op">}}{{/</span><span class="kw">each</span>', $directHandlebars['html']);
    },
    'highlights mermaid diagram review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[41] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Mermaid diagram code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'tango');
        $directMermaid = $highlighter->highlight('sequenceDiagram' . "\n" . '  participant Editor' . "\n" . '  Editor->>Queue: approve', 'mermaid-js');

        $t->same('mermaid', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('mermaid', SyntaxHighlighter::normalizeLanguage('mermaid'));
        $t->same('mermaid', SyntaxHighlighter::normalizeLanguage('mermaid-js'));
        $t->same('mermaid', SyntaxHighlighter::normalizeLanguage('mermaidjs'));
        $t->same('mermaid', $highlighted['language']);
        $t->same('mermaid', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(450, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource mermaid numberLines"><code class="sourceCode mermaid" style="counter-reset: source-line 449;">', $highlighted['html']);
        $t->contains('<span id="mermaid-review-450"><a href="#mermaid-review-450"></a><span class="co">%% WordPress import workflow diagram review</span></span>', $highlighted['html']);
        $t->contains('<span class="pp">%%{ init: { &quot;theme&quot;: &quot;base&quot; } }%%</span>', $highlighted['html']);
        $t->contains('<span class="kw">flowchart</span> <span class="cn">LR</span>', $highlighted['html']);
        $t->contains('<span class="va">ingest</span><span class="st">[Read WXR]</span> <span class="op">--&gt;</span> <span class="va">normalize</span><span class="st">{Normalize blocks}</span>', $highlighted['html']);
        $t->contains('<span class="va">normalize</span> <span class="op">--&gt;</span><span class="st">|safe HTML|</span> <span class="va">review</span><span class="st">[Reviewer Queue]</span>', $highlighted['html']);
        $t->contains('<span class="va">normalize</span> <span class="op">--</span> <span class="va">media</span> <span class="op">--&gt;</span> <span class="va">media</span><span class="st">[(Attachment Library)]</span>', $highlighted['html']);
        $t->contains('<span class="va">review</span> <span class="op">-.</span> <span class="va">approve</span> <span class="op">.-&gt;</span> <span class="va">publish</span><span class="st">[Publish]</span>', $highlighted['html']);
        $t->contains('<span class="kw">classDef</span> <span class="va">warning</span> <span class="ot">fill</span><span class="op">:#</span><span class="va">fff4ce</span>', $highlighted['html']);
        $t->contains('<span class="kw">class</span> <span class="va">normalize</span> <span class="va">warning</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="kw">flowchart</span> <span class="cn">LR</span>', $wordpressBlock);
        $t->same('mermaid', $directMermaid['language']);
        $t->same('mermaid-js', $directMermaid['requestedLanguage']);
        $t->contains('<span class="kw">sequenceDiagram</span>', $directMermaid['html']);
        $t->contains('<span class="kw">participant</span> <span class="va">Editor</span>', $directMermaid['html']);
        $t->contains('<span class="va">Editor</span><span class="op">-&gt;&gt;</span><span class="ot">Queue</span><span class="op">:</span> <span class="va">approve</span>', $directMermaid['html']);
    },
    'highlights embedded css and javascript inside html review snippets' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[42] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an embedded HTML asset code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'pygments');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'pygments');
        $directHtml = $highlighter->highlight(
            '<style>.wp-block { color: var(--accent-color); }</style>' . "\n"
            . '<script>const block = wp.element.createElement("p", null, "ok");</script>',
            'html'
        );

        $t->same('html', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('html', $highlighted['language']);
        $t->same('html', $highlighted['requestedLanguage']);
        $t->same('pygments', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(470, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource html numberLines"><code class="sourceCode html" style="counter-reset: source-line 469;">', $highlighted['html']);
        $t->contains('<span id="html-embedded-review-470"><a href="#html-embedded-review-470"></a><span class="co">&lt;!-- WordPress embedded asset review --&gt;</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;style</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="dt">.wp-block-import-card</span> <span class="op">{</span> <span class="ot">color</span><span class="op">:</span> <span class="fu">var</span><span class="op">(</span><span class="ot">--accent-color</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">@media</span> <span class="op">(</span><span class="ot">min-width</span><span class="op">:</span> <span class="dv">48rem</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="ot">margin-block</span><span class="op">:</span> <span class="dv">1rem</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;script</span> <span class="ot">type</span><span class="op">=</span><span class="st">&quot;module&quot;</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">const</span> <span class="va">block</span> <span class="op">=</span> <span class="va">wp</span><span class="op">.</span><span class="va">element</span><span class="op">.</span><span class="fu">createElement</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="dt">window</span><span class="op">.</span><span class="va">wp</span><span class="op">?.</span><span class="va">data</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="dt">console</span><span class="op">.</span><span class="fu">log</span><span class="op">(</span><span class="dt">JSON</span><span class="op">.</span><span class="fu">stringify</span><span class="op">({</span> <span class="ot">ok</span><span class="op">:</span> <span class="cn">true</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="pygments">', $wordpressBlock);
        $t->contains('<span class="fu">createElement</span><span class="op">(</span><span class="st">&quot;p&quot;</span>', $wordpressBlock);
        $t->same('html', $directHtml['language']);
        $t->contains('<span class="dt">.wp-block</span> <span class="op">{</span> <span class="ot">color</span><span class="op">:</span> <span class="fu">var</span><span class="op">(</span><span class="ot">--accent-color</span><span class="op">);</span>', $directHtml['html']);
        $t->contains('<span class="kw">const</span> <span class="va">block</span> <span class="op">=</span> <span class="va">wp</span><span class="op">.</span><span class="va">element</span><span class="op">.</span><span class="fu">createElement</span>', $directHtml['html']);
    },
    'highlights embedded php islands inside html template snippets' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[43] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an HTML/PHP template code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directHtml = $highlighter->highlight(
            '<article><?php if (! empty($title)) : ?><h2><?= esc_html($title) ?></h2><?php endif; ?></article>',
            'html'
        );

        $t->same('html', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('html', $highlighted['language']);
        $t->same('html', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(490, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource html numberLines"><code class="sourceCode html" style="counter-reset: source-line 489;">', $highlighted['html']);
        $t->contains('<span id="html-php-template-review-490"><a href="#html-php-template-review-490"></a><span class="co">&lt;!-- WordPress PHP template review --&gt;</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;article</span> <span class="ot">class</span><span class="op">=</span><span class="st">&quot;wp-block-import-card&quot;</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="pp">&lt;?php</span> <span class="kw">if</span>', $highlighted['html']);
        $t->contains('<span class="fu">empty</span><span class="op">(</span><span class="va">$post_title</span><span class="op">))</span> <span class="op">:</span> <span class="pp">?&gt;</span>', $highlighted['html']);
        $t->contains('<span class="pp">&lt;?=</span> <span class="fu">esc_html</span><span class="op">(</span><span class="va">$post_title</span><span class="op">)</span> <span class="pp">?&gt;</span>', $highlighted['html']);
        $t->contains('<span class="pp">&lt;?php</span> <span class="kw">else</span> <span class="op">:</span> <span class="pp">?&gt;</span>', $highlighted['html']);
        $t->contains('<span class="pp">&lt;?php</span> <span class="kw">endif</span><span class="op">;</span> <span class="pp">?&gt;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="fu">esc_html</span><span class="op">(</span><span class="va">$post_title</span>', $wordpressBlock);
        $t->same('html', $directHtml['language']);
        $t->contains('<span class="pp">&lt;?php</span> <span class="kw">if</span>', $directHtml['html']);
        $t->contains('<span class="pp">&lt;?=</span> <span class="fu">esc_html</span><span class="op">(</span><span class="va">$title</span><span class="op">)</span> <span class="pp">?&gt;</span>', $directHtml['html']);
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
    'highlights sql migration snippets with mysql and sqlite aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[30] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a SQL migration code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'tango');
        $sqlite = $highlighter->highlight(
            'WITH posts AS (SELECT 42 AS id, \'Imported\' AS "post_title") SELECT "post_title" FROM posts WHERE id = $1',
            'sqlite3'
        );

        $t->same('mysql', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('sql', SyntaxHighlighter::normalizeLanguage('mysql'));
        $t->same('sql', SyntaxHighlighter::normalizeLanguage('mariadb'));
        $t->same('sql', SyntaxHighlighter::normalizeLanguage('sqlite3'));
        $t->same('sql', $highlighted['language']);
        $t->same('mysql', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(230, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource mysql numberLines"><code class="sourceCode sql" style="counter-reset: source-line 229;">', $highlighted['html']);
        $t->contains('<span id="sql-migration-review-230"><a href="#sql-migration-review-230"></a><span class="co">-- WordPress SQL migration review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">START</span> <span class="kw">TRANSACTION</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">CREATE</span> <span class="kw">TABLE</span> <span class="ot">`wp_posts`</span>', $highlighted['html']);
        $t->contains('<span class="ot">`ID`</span> <span class="dt">bigint</span><span class="op">(</span><span class="dv">20</span><span class="op">)</span> <span class="kw">unsigned</span> <span class="kw">NOT</span> <span class="cn">NULL</span> <span class="kw">AUTO_INCREMENT</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="kw">PRIMARY</span> <span class="kw">KEY</span> <span class="op">(</span><span class="ot">`ID`</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">ON</span> <span class="kw">DUPLICATE</span> <span class="kw">KEY</span> <span class="kw">UPDATE</span> <span class="ot">`post_title`</span> <span class="op">=</span> <span class="kw">VALUES</span><span class="op">(</span><span class="ot">`post_title`</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">SELECT</span> <span class="fu">JSON_EXTRACT</span><span class="op">(</span><span class="ot">`meta_value`</span><span class="op">,</span> <span class="st">&#039;$.title&#039;</span><span class="op">)</span> <span class="kw">AS</span> <span class="ot">`title`</span>', $highlighted['html']);
        $t->contains('<span class="kw">WHERE</span> <span class="ot">`post_id`</span> <span class="op">=</span> <span class="va">:post_id</span> <span class="kw">AND</span> <span class="ot">`meta_key`</span> <span class="kw">LIKE</span> <span class="st">&#039;review\\_%&#039;</span> <span class="kw">ESCAPE</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="fu">JSON_EXTRACT</span><span class="op">(</span><span class="ot">`meta_value`</span>', $wordpressBlock);
        $t->same('sql', $sqlite['language']);
        $t->same('sqlite3', $sqlite['requestedLanguage']);
        $t->contains('<span class="kw">WITH</span> <span class="va">posts</span> <span class="kw">AS</span> <span class="op">(</span><span class="kw">SELECT</span> <span class="dv">42</span> <span class="kw">AS</span> <span class="va">id</span><span class="op">,</span> <span class="st">&#039;Imported&#039;</span> <span class="kw">AS</span> <span class="ot">&quot;post_title&quot;</span><span class="op">)</span>', $sqlite['html']);
        $t->contains('<span class="kw">WHERE</span> <span class="va">id</span> <span class="op">=</span> <span class="va">$1</span>', $sqlite['html']);
    },
    'highlights postgresql dollar quoted review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[31] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a PostgreSQL trigger code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directPgsql = $highlighter->highlight(
            'DO $$ BEGIN RAISE NOTICE \'Imported %\', 42; END $$;',
            'plpgsql'
        );
        $taggedDollar = $highlighter->highlight(
            'SELECT $wp_import$<!-- wp:paragraph --><p>Imported</p><!-- /wp:paragraph -->$wp_import$::text;',
            'pgsql'
        );

        $t->same('pgsql', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('sql', SyntaxHighlighter::normalizeLanguage('pgsql'));
        $t->same('sql', SyntaxHighlighter::normalizeLanguage('plpgsql'));
        $t->same('sql', $highlighted['language']);
        $t->same('pgsql', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(250, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource pgsql numberLines"><code class="sourceCode sql" style="counter-reset: source-line 249;">', $highlighted['html']);
        $t->contains('<span id="postgres-trigger-review-250"><a href="#postgres-trigger-review-250"></a><span class="co">-- PostgreSQL trigger review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">CREATE</span> <span class="kw">OR</span> <span class="kw">REPLACE</span> <span class="kw">FUNCTION</span> <span class="fu">wp_review_notice</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<span class="kw">RETURNS</span> <span class="dt">trigger</span>', $highlighted['html']);
        $t->contains('<span class="kw">LANGUAGE</span> <span class="dt">plpgsql</span>', $highlighted['html']);
        $t->contains('<span class="kw">AS</span> <span class="st">$review$</span>', $highlighted['html']);
        $t->contains('<span id="postgres-trigger-review-255"><a href="#postgres-trigger-review-255"></a><span class="st">BEGIN</span></span>', $highlighted['html']);
        $t->contains('<span class="st">  RAISE NOTICE &#039;import %&#039;, NEW.post_title;</span>', $highlighted['html']);
        $t->contains('<span class="st">$review$</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">CREATE</span> <span class="dt">TRIGGER</span> <span class="va">wp_review_before_insert</span>', $highlighted['html']);
        $t->contains('<span class="kw">FOR</span> <span class="kw">EACH</span> <span class="kw">ROW</span> <span class="kw">EXECUTE</span> <span class="kw">FUNCTION</span> <span class="fu">wp_review_notice</span><span class="op">();</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="st">$review$</span>', $wordpressBlock);
        $t->same('sql', $directPgsql['language']);
        $t->same('plpgsql', $directPgsql['requestedLanguage']);
        $t->contains('<span class="kw">DO</span> <span class="st">$$ BEGIN RAISE NOTICE &#039;Imported %&#039;, 42; END $$</span><span class="op">;</span>', $directPgsql['html']);
        $t->same('sql', $taggedDollar['language']);
        $t->contains('<span class="kw">SELECT</span> <span class="st">$wp_import$&lt;!-- wp:paragraph --&gt;&lt;p&gt;Imported&lt;/p&gt;&lt;!-- /wp:paragraph --&gt;$wp_import$</span><span class="op">::</span><span class="dt">text</span><span class="op">;</span>', $taggedDollar['html']);
    },
    'highlights apache htaccess rewrite snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[32] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an htaccess rewrite code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'kate');
        $directApache = $highlighter->highlight(
            'Header always set X-Frame-Options "SAMEORIGIN"',
            'apacheconf'
        );

        $t->same('htaccess', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('apache', SyntaxHighlighter::normalizeLanguage('htaccess'));
        $t->same('apache', SyntaxHighlighter::normalizeLanguage('apacheconf'));
        $t->same('apache', $highlighted['language']);
        $t->same('htaccess', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(270, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource htaccess numberLines"><code class="sourceCode apache" style="counter-reset: source-line 269;">', $highlighted['html']);
        $t->contains('<span id="htaccess-review-270"><a href="#htaccess-review-270"></a><span class="co"># WordPress permalink review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;IfModule</span> <span class="dt">mod_rewrite.c</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">RewriteEngine</span> <span class="cn">On</span>', $highlighted['html']);
        $t->contains('<span class="kw">RewriteBase</span> <span class="st">/</span>', $highlighted['html']);
        $t->contains('<span class="kw">RewriteCond</span> <span class="va">%{REQUEST_FILENAME}</span> <span class="op">!-f</span>', $highlighted['html']);
        $t->contains('<span class="kw">RewriteRule</span> <span class="op">.</span> <span class="st">/index.php</span> <span class="ot">[L]</span>', $highlighted['html']);
        $t->contains('<span class="kw">Header</span> <span class="kw">set</span> <span class="va">X-Import-Source</span> <span class="st">&quot;legacy&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;/IfModule</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="kw">RewriteRule</span> <span class="op">.</span> <span class="st">/index.php</span>', $wordpressBlock);
        $t->same('apache', $directApache['language']);
        $t->same('apacheconf', $directApache['requestedLanguage']);
        $t->contains('<span class="kw">Header</span> <span class="kw">always</span> <span class="kw">set</span> <span class="va">X-Frame-Options</span> <span class="st">&quot;SAMEORIGIN&quot;</span>', $directApache['html']);
    },
    'highlights restructuredtext review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[35] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a reStructuredText code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'haddock');
        $directRest = $highlighter->highlight('See :ref:`review queue` and https://example.test/import', 'rest');

        $t->same('rst', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('rst', SyntaxHighlighter::normalizeLanguage('rest'));
        $t->same('rst', SyntaxHighlighter::normalizeLanguage('reStructuredText'));
        $t->same('rst', $highlighted['language']);
        $t->same('rst', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(330, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource rst numberLines"><code class="sourceCode rst" style="counter-reset: source-line 329;">', $highlighted['html']);
        $t->contains('<span id="rst-review-330"><a href="#rst-review-330"></a><span class="co">.. WordPress import review note</span></span>', $highlighted['html']);
        $t->contains('<span class="re">=============</span>', $highlighted['html']);
        $t->contains('<span class="fu">:source:</span> legacy<span class="op">-</span>doc', $highlighted['html']);
        $t->contains('<span class="fu">:status:</span> <span class="kw">**needs review**</span>', $highlighted['html']);
        $t->contains('<span class="dt">.. _review queue: https://example.test/wp-admin/edit.php</span>', $highlighted['html']);
        $t->contains('<span class="dt">.. code-block:: php</span>', $highlighted['html']);
        $t->contains('<span class="dt">   echo esc_html($title);</span>', $highlighted['html']);
        $t->contains('<span class="dt">``legacy_shortcode``</span>', $highlighted['html']);
        $t->contains('<span class="kw">:doc:</span><span class="cn">`media map &lt;uploads&gt;`</span>', $highlighted['html']);
        $t->contains('<span class="ot">`queue link`_</span>', $highlighted['html']);
        $t->contains('<span class="ot">https://example.test/review</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="dt">.. code-block:: php</span>', $wordpressBlock);
        $t->same('rst', $directRest['language']);
        $t->same('rest', $directRest['requestedLanguage']);
        $t->contains('<span class="kw">:ref:</span><span class="cn">`review queue`</span>', $directRest['html']);
        $t->contains('<span class="ot">https://example.test/import</span>', $directRest['html']);
    },
    'highlights haskell and literate haskell review snippets' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'classes' => ['sourceCode', 'literate-haskell'],
            'attributes' => [],
            'text' => implode("\n", [
                '{- migration review -}',
                'module Review.Import where',
                'import Text.Pandoc (Pandoc)',
                'renderBlocks :: Pandoc -> Text',
                'renderBlocks post = writeMarkdown def post',
                'status = Just 42',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'zenburn');

        $t->same('literate-haskell', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('haskell', $highlighted['language']);
        $t->same('literate-haskell', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->contains('<pre class="sourceCode haskell"><code class="sourceCode haskell">', $highlighted['html']);
        $t->contains('<span class="co">{- migration review -}</span>', $highlighted['html']);
        $t->contains('<span class="kw">module</span> <span class="dt">Review.Import</span> <span class="kw">where</span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="dt">Text.Pandoc</span> <span class="op">(</span><span class="dt">Pandoc</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="va">renderBlocks</span> <span class="op">::</span> <span class="dt">Pandoc</span> <span class="op">-&gt;</span> <span class="dt">Text</span>', $highlighted['html']);
        $t->contains('<span class="cn">Just</span> <span class="dv">42</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<span class="kw">import</span> <span class="dt">Text.Pandoc</span>', $wordpressBlock);
    },
    'highlights tex and latex review snippets with pandoc alias handoff' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'classes' => ['sourceCode', 'language-latex'],
            'attributes' => [],
            'text' => implode("\n", [
                '\\documentclass[11pt]{article}',
                '\\usepackage{graphicx}',
                '% WordPress import review note',
                '\\newcommand{\\ReviewTitle}{$title$}',
                '\\begin{document}',
                '\\section{Import 42}',
                '\\includegraphics[width=0.5\\textwidth]{media.png}',
                '\\end{document}',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'haddock');

        $t->same('latex', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('tex', $highlighted['language']);
        $t->same('latex', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->contains('<pre class="sourceCode tex"><code class="sourceCode tex">', $highlighted['html']);
        $t->contains('<span class="kw">\\documentclass</span><span class="op">[</span><span class="dv">11</span><span class="va">pt</span><span class="op">]</span><span class="dt">{article}</span>', $highlighted['html']);
        $t->contains('<span class="co">% WordPress import review note</span>', $highlighted['html']);
        $t->contains('<span class="kw">\\newcommand</span><span class="dt">{\\ReviewTitle}</span><span class="op">{</span><span class="va">$title$</span><span class="op">}</span>', $highlighted['html']);
        $t->contains('<span class="kw">\\begin</span><span class="dt">{document}</span>', $highlighted['html']);
        $t->contains('<span class="fu">\\includegraphics</span><span class="op">[</span><span class="va">width</span><span class="op">=</span><span class="dv">0.5</span><span class="fu">\\textwidth</span><span class="op">]</span><span class="dt">{media.png}</span>', $highlighted['html']);
        $t->contains('<span class="kw">\\end</span><span class="dt">{document}</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="kw">\\usepackage</span><span class="dt">{graphicx}</span>', $wordpressBlock);
    },
    'highlights unified diff and patch review snippets' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'source-diff',
            'classes' => ['sourceCode', 'patch', 'numberLines'],
            'attributes' => ['startFrom' => '9'],
            'text' => implode("\n", [
                'diff --git a/content.php b/content.php',
                'index 1111111..2222222 100644',
                '--- a/content.php',
                '+++ b/content.php',
                '@@ -1,3 +1,4 @@',
                '-echo $old_title;',
                '+echo esc_html($new_title);',
                ' context line',
                '\ No newline at end of file',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'tango');

        $t->same('patch', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('diff', $highlighted['language']);
        $t->same('patch', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(9, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource patch numberLines"><code class="sourceCode diff" style="counter-reset: source-line 8;">', $highlighted['html']);
        $t->contains('<span id="source-diff-9"><a href="#source-diff-9"></a><span class="re">diff --git a/content.php b/content.php</span></span>', $highlighted['html']);
        $t->contains('<span id="source-diff-10"><a href="#source-diff-10"></a><span class="ot">index 1111111..2222222 100644</span></span>', $highlighted['html']);
        $t->contains('<span class="re">@@ -1,3 +1,4 @@</span>', $highlighted['html']);
        $t->contains('<span class="al">-echo $old_title;</span>', $highlighted['html']);
        $t->contains('<span class="in">+echo esc_html($new_title);</span>', $highlighted['html']);
        $t->contains('<span class="co">\ No newline at end of file</span>', $highlighted['html']);
        $t->contains('.sourceCode .re', $highlighted['css']);
        $t->contains('.sourceCode .in', $highlighted['css']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="in">+echo esc_html($new_title);</span>', $wordpressBlock);
    },
    'highlights markdown family review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'markdown-review',
            'classes' => ['sourceCode', 'md', 'numberLines'],
            'attributes' => ['startFrom' => '5'],
            'text' => implode("\n", [
                '# Migration Review',
                '',
                '- [x] Preserve [media](uploads/hero.png)',
                '- Keep `legacy_shortcode` visible',
                '> Reviewer note with <https://example.test/post>',
                '',
                '[asset]: uploads/hero.png "Hero image"',
                '',
                '``` {.php}',
                'echo esc_html($title);',
                '```',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'kate');

        $t->same('md', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('markdown', $highlighted['language']);
        $t->same('md', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(5, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource md numberLines"><code class="sourceCode markdown" style="counter-reset: source-line 4;">', $highlighted['html']);
        $t->contains('<span id="markdown-review-5"><a href="#markdown-review-5"></a><span class="re"># Migration Review</span></span>', $highlighted['html']);
        $t->contains('<span class="op">- </span><span class="cn">[x]</span> Preserve <span class="ot">[media](uploads/hero.png)</span>', $highlighted['html']);
        $t->contains('<span class="st">`legacy_shortcode`</span>', $highlighted['html']);
        $t->contains('<span class="op">&gt; </span>Reviewer note with <span class="ot">&lt;https://example.test/post&gt;</span>', $highlighted['html']);
        $t->contains('<span class="ot">[asset]: uploads/hero.png &quot;Hero image&quot;</span>', $highlighted['html']);
        $t->contains('<span class="pp">``` {.php}</span>', $highlighted['html']);
        $t->contains('<span class="pp">```</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="re"># Migration Review</span>', $wordpressBlock);

        $commonmark = (new SyntaxHighlighter())->highlight('## Imported Notes', 'commonmark');
        $t->same('markdown', $commonmark['language']);
        $t->contains('<pre class="sourceCode markdown"><code class="sourceCode markdown"><span class="re">## Imported Notes</span></code></pre>', $commonmark['html']);
    },
    'highlights ruby and rake review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'ruby-review',
            'classes' => ['sourceCode', 'rb'],
            'attributes' => [],
            'text' => implode("\n", [
                '# WordPress import audit task',
                "require 'json'",
                'module Migration',
                '  class ReviewPacket',
                '    def initialize(path:)',
                '      @path = path',
                '    end',
                '',
                '    def call',
                "      puts JSON.parse(File.read(@path))['title']",
                '    rescue JSON::ParserError => error',
                '      warn "invalid import: #{error.message}"',
                '      nil',
                '    end',
                '  end',
                'end',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'espresso');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'espresso');
        $rake = (new SyntaxHighlighter())->highlight("task :import do\n  puts 'ok'\nend", 'rake');

        $t->same('rb', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('ruby', $highlighted['language']);
        $t->same('rb', $highlighted['requestedLanguage']);
        $t->same('espresso', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->contains('<pre class="sourceCode ruby"><code class="sourceCode ruby">', $highlighted['html']);
        $t->contains('<span class="co"># WordPress import audit task</span>', $highlighted['html']);
        $t->contains('<span class="fu">require</span> <span class="st">&#039;json&#039;</span>', $highlighted['html']);
        $t->contains('<span class="kw">module</span> <span class="dt">Migration</span>', $highlighted['html']);
        $t->contains('<span class="kw">class</span> <span class="dt">ReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="kw">def</span> <span class="fu">initialize</span><span class="op">(</span><span class="ot">path:</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="va">@path</span> <span class="op">=</span> <span class="va">path</span>', $highlighted['html']);
        $t->contains('<span class="kw">rescue</span> <span class="dt">JSON::ParserError</span> <span class="op">=&gt;</span> <span class="va">error</span>', $highlighted['html']);
        $t->contains('<span class="fu">warn</span> <span class="st">&quot;invalid import: #{error.message}&quot;</span>', $highlighted['html']);
        $t->contains('<span class="cn">nil</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="espresso">', $wordpressBlock);
        $t->contains('<span class="dt">JSON</span><span class="op">.</span><span class="fu">parse</span>', $wordpressBlock);
        $t->same('ruby', $rake['language']);
        $t->contains('<span class="fu">task</span> <span class="ot">:import</span> <span class="kw">do</span>', $rake['html']);
    },
    'highlights lua filter review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'lua-filter-review',
            'classes' => ['sourceCode', 'pandoc-lua', 'numberLines'],
            'attributes' => ['startFrom' => '3'],
            'text' => implode("\n", [
                '-- WordPress import Lua filter',
                'function Header(el)',
                '  local title = pandoc.utils.stringify(el.content)',
                '  if el.level == 1 then',
                '    return pandoc.Div({el}, {class = "import-title"})',
                '  end',
                '  return nil',
                'end',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directLua = (new SyntaxHighlighter())->highlight('return pandoc.Str("ok")', 'lua');

        $t->same('pandoc-lua', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('lua', $highlighted['language']);
        $t->same('pandoc-lua', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(3, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource pandoc-lua numberLines"><code class="sourceCode lua" style="counter-reset: source-line 2;">', $highlighted['html']);
        $t->contains('<span id="lua-filter-review-3"><a href="#lua-filter-review-3"></a><span class="co">-- WordPress import Lua filter</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">function</span> <span class="fu">Header</span><span class="op">(</span><span class="va">el</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">local</span> <span class="va">title</span> <span class="op">=</span> <span class="dt">pandoc</span><span class="op">.</span><span class="va">utils</span><span class="op">.</span><span class="fu">stringify</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="va">el</span><span class="op">.</span><span class="va">level</span> <span class="op">==</span> <span class="dv">1</span> <span class="kw">then</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="dt">pandoc</span><span class="op">.</span><span class="fu">Div</span><span class="op">({</span><span class="va">el</span><span class="op">},</span> <span class="op">{</span><span class="va">class</span> <span class="op">=</span> <span class="st">&quot;import-title&quot;</span><span class="op">})</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="cn">nil</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="dt">pandoc</span><span class="op">.</span><span class="fu">Div</span>', $wordpressBlock);
        $t->same('lua', $directLua['language']);
        $t->contains('<span class="kw">return</span> <span class="dt">pandoc</span><span class="op">.</span><span class="fu">Str</span><span class="op">(</span><span class="st">&quot;ok&quot;</span><span class="op">)</span>', $directLua['html']);
    },
    'highlights typescript review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'ts-review',
            'classes' => ['sourceCode', 'ts', 'numberLines'],
            'attributes' => ['startFrom' => '12'],
            'text' => implode("\n", [
                '// Gutenberg block migration packet',
                'type BlockPayload = {',
                '  title?: string;',
                '  meta: Record<string, unknown>;',
                '};',
                '',
                'export async function migrateBlock(payload: BlockPayload): Promise<void> {',
                '  const title = payload.title ?? `Untitled`;',
                '  if (payload.meta?.sourceId !== undefined) {',
                '    console.log(`import:${payload.meta.sourceId}`);',
                '  }',
                '  return;',
                '}',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'kate');
        $directTypescript = (new SyntaxHighlighter())->highlight('interface ReviewBlock { readonly title: string }', 'typescript');

        $t->same('ts', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('typescript', $highlighted['language']);
        $t->same('ts', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(12, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource ts numberLines"><code class="sourceCode typescript" style="counter-reset: source-line 11;">', $highlighted['html']);
        $t->contains('<span id="ts-review-12"><a href="#ts-review-12"></a><span class="co">// Gutenberg block migration packet</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">type</span> <span class="dt">BlockPayload</span> <span class="op">=</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span><span class="op">?:</span> <span class="dt">string</span>', $highlighted['html']);
        $t->contains('<span class="va">meta</span><span class="op">:</span> <span class="dt">Record</span><span class="op">&lt;</span><span class="dt">string</span><span class="op">,</span> <span class="dt">unknown</span><span class="op">&gt;;</span>', $highlighted['html']);
        $t->contains('<span class="kw">export</span> <span class="kw">async</span> <span class="kw">function</span> <span class="fu">migrateBlock</span>', $highlighted['html']);
        $t->contains('<span class="dt">Promise</span><span class="op">&lt;</span><span class="dt">void</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="va">payload</span><span class="op">.</span><span class="va">title</span> <span class="op">??</span> <span class="st">`Untitled`</span>', $highlighted['html']);
        $t->contains('<span class="va">payload</span><span class="op">.</span><span class="va">meta</span><span class="op">?.</span><span class="va">sourceId</span> <span class="op">!==</span> <span class="dt">undefined</span>', $highlighted['html']);
        $t->contains('<span class="va">console</span><span class="op">.</span><span class="fu">log</span><span class="op">(</span><span class="st">`import:${payload.meta.sourceId}`</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="kw">export</span> <span class="kw">async</span> <span class="kw">function</span>', $wordpressBlock);
        $t->same('typescript', $directTypescript['language']);
        $t->contains('<span class="kw">interface</span> <span class="dt">ReviewBlock</span>', $directTypescript['html']);
        $t->contains('<span class="kw">readonly</span> <span class="va">title</span><span class="op">:</span> <span class="dt">string</span>', $directTypescript['html']);
    },
    'highlights jsx react review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[12] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a JSX code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directJsx = (new SyntaxHighlighter())->highlight('return <ReviewCard title={post.title} />;', 'javascript-react');

        $t->same('jsx', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('jsx', $highlighted['language']);
        $t->same('jsx', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(18, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource jsx numberLines"><code class="sourceCode jsx" style="counter-reset: source-line 17;">', $highlighted['html']);
        $t->contains('<span id="jsx-review-18"><a href="#jsx-review-18"></a><span class="co">// Gutenberg block preview component</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="dt">React</span> <span class="kw">from</span> <span class="st">&#039;react&#039;</span>', $highlighted['html']);
        $t->contains('<span class="kw">export</span> <span class="kw">default</span> <span class="kw">function</span> <span class="fu">ImportPreview</span>', $highlighted['html']);
        $t->contains('<span class="kw">const</span> <span class="op">{</span> <span class="va">title</span><span class="op">,</span> <span class="va">sourceId</span> <span class="op">}</span> <span class="op">=</span> <span class="va">props</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="kw">&lt;section</span> <span class="ot">className</span><span class="op">=</span><span class="st">&quot;wp-block-import&quot;</span> <span class="ot">data-source</span><span class="op">={</span><span class="va">sourceId</span><span class="op">}&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;h2</span><span class="op">&gt;{</span><span class="va">title</span><span class="op">}</span><span class="kw">&lt;/h2</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="fu">&lt;InnerBlocks</span> <span class="ot">allowedBlocks</span><span class="op">={[</span><span class="st">&quot;core/paragraph&quot;</span><span class="op">]}</span> <span class="op">/&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;/section</span><span class="op">&gt;;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="fu">&lt;InnerBlocks</span>', $wordpressBlock);
        $t->same('jsx', $directJsx['language']);
        $t->contains('<span class="kw">return</span> <span class="fu">&lt;ReviewCard</span> <span class="ot">title</span><span class="op">={</span><span class="va">post</span><span class="op">.</span><span class="va">title</span><span class="op">}</span> <span class="op">/&gt;;</span>', $directJsx['html']);
    },
    'highlights r script review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[13] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an R code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'espresso');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'espresso');
        $directR = (new SyntaxHighlighter())->highlight('`post title` <- c("Draft", NA)', 'Rscript');

        $t->same('r', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('r', $highlighted['language']);
        $t->same('r', $highlighted['requestedLanguage']);
        $t->same('espresso', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(27, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource r numberLines"><code class="sourceCode r" style="counter-reset: source-line 26;">', $highlighted['html']);
        $t->contains('<span id="r-review-27"><a href="#r-review-27"></a><span class="co">## WordPress import analysis</span></span>', $highlighted['html']);
        $t->contains('<span class="fu">library</span><span class="op">(</span><span class="va">dplyr</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="va">scores</span> <span class="ot">&lt;-</span> <span class="fu">data.frame</span>', $highlighted['html']);
        $t->contains('<span class="ot">title</span> <span class="ot">=</span> <span class="fu">c</span><span class="op">(</span><span class="st">&quot;Draft&quot;</span><span class="op">,</span> <span class="st">&quot;Published&quot;</span><span class="op">),</span>', $highlighted['html']);
        $t->contains('<span class="ot">views</span> <span class="ot">=</span> <span class="fu">c</span><span class="op">(</span><span class="dv">10L</span><span class="op">,</span> <span class="cn">NA_integer_</span><span class="op">))</span>', $highlighted['html']);
        $t->contains('<span class="va">scores</span> <span class="op">|&gt;</span>', $highlighted['html']);
        $t->contains('<span class="va">dplyr</span><span class="op">::</span><span class="fu">filter</span><span class="op">(!</span><span class="fu">is.na</span><span class="op">(</span><span class="va">title</span><span class="op">),</span> <span class="va">views</span> <span class="op">&gt;=</span> <span class="dv">10</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">mutate</span><span class="op">(</span><span class="ot">slug</span> <span class="ot">=</span> <span class="fu">tolower</span><span class="op">(</span><span class="fu">gsub</span><span class="op">(</span><span class="st">&quot;[^a-z0-9]+&quot;</span><span class="op">,</span> <span class="st">&quot;-&quot;</span><span class="op">,</span> <span class="va">title</span><span class="op">)))</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="fu">any</span><span class="op">(</span><span class="va">scores</span><span class="op">$</span><span class="va">views</span> <span class="op">&gt;</span> <span class="dv">100</span><span class="op">))</span>', $highlighted['html']);
        $t->contains('<span class="fu">print</span><span class="op">(</span><span class="st">&quot;popular import&quot;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="espresso">', $wordpressBlock);
        $t->contains('<span class="va">scores</span> <span class="op">|&gt;</span>', $wordpressBlock);
        $t->same('r', $directR['language']);
        $t->contains('<span class="ot">`post title`</span> <span class="ot">&lt;-</span> <span class="fu">c</span><span class="op">(</span><span class="st">&quot;Draft&quot;</span><span class="op">,</span> <span class="cn">NA</span><span class="op">)</span>', $directR['html']);
    },
    'highlights python3 review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'python-review',
            'classes' => ['sourceCode', 'python3', 'numberLines'],
            'attributes' => ['startFrom' => '20'],
            'text' => implode("\n", [
                '# WordPress import JSON cleanup',
                'from dataclasses import dataclass',
                'from pathlib import Path',
                '@dataclass',
                'class ReviewPacket:',
                '    source_id: int',
                '    title: str | None = None',
                '',
                'def normalize_title(packet: ReviewPacket) -> str:',
                '    raw = json.loads(Path(packet.source_path).read_text())["title"]',
                '    if raw is None:',
                '        return "Untitled"',
                '    return raw.strip()',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'monochrome');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'monochrome');
        $directPython = (new SyntaxHighlighter())->highlight('async def load(): await fetch()', 'py3');

        $t->same('python3', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('python', $highlighted['language']);
        $t->same('python3', $highlighted['requestedLanguage']);
        $t->same('monochrome', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(20, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource python3 numberLines"><code class="sourceCode python" style="counter-reset: source-line 19;">', $highlighted['html']);
        $t->contains('<span id="python-review-20"><a href="#python-review-20"></a><span class="co"># WordPress import JSON cleanup</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">from</span> <span class="va">dataclasses</span> <span class="kw">import</span> <span class="va">dataclass</span>', $highlighted['html']);
        $t->contains('<span class="kw">from</span> <span class="va">pathlib</span> <span class="kw">import</span> <span class="dt">Path</span>', $highlighted['html']);
        $t->contains('<span class="ot">@dataclass</span>', $highlighted['html']);
        $t->contains('<span class="kw">class</span> <span class="dt">ReviewPacket</span><span class="op">:</span>', $highlighted['html']);
        $t->contains('<span class="va">source_id</span><span class="op">:</span> <span class="dt">int</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span><span class="op">:</span> <span class="dt">str</span> <span class="op">|</span> <span class="cn">None</span>', $highlighted['html']);
        $t->contains('<span class="kw">def</span> <span class="fu">normalize_title</span><span class="op">(</span><span class="va">packet</span><span class="op">:</span> <span class="dt">ReviewPacket</span><span class="op">)</span> <span class="op">-&gt;</span> <span class="dt">str</span>', $highlighted['html']);
        $t->contains('<span class="va">json</span><span class="op">.</span><span class="fu">loads</span><span class="op">(</span><span class="dt">Path</span><span class="op">(</span><span class="va">packet</span><span class="op">.</span><span class="va">source_path</span><span class="op">).</span><span class="fu">read_text</span><span class="op">())[</span><span class="st">&quot;title&quot;</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="va">raw</span> <span class="kw">is</span> <span class="cn">None</span><span class="op">:</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="va">raw</span><span class="op">.</span><span class="fu">strip</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="monochrome">', $wordpressBlock);
        $t->contains('<span class="ot">@dataclass</span>', $wordpressBlock);
        $t->same('python', $directPython['language']);
        $t->contains('<span class="kw">async</span> <span class="kw">def</span> <span class="fu">load</span>', $directPython['html']);
        $t->contains('<span class="kw">await</span> <span class="fu">fetch</span><span class="op">()</span>', $directPython['html']);
    },
    'highlights c and cpp review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'cpp-review',
            'classes' => ['sourceCode', 'cpp', 'numberLines'],
            'attributes' => ['startFrom' => '30'],
            'text' => implode("\n", [
                '#include <string>',
                '#include "wp_import.h"',
                '// WordPress import extension review',
                'namespace Migration {',
                'class ReviewPacket {',
                'public:',
                '    explicit ReviewPacket(std::string title) : title_(std::move(title)) {}',
                '    bool is_draft() const { return title_.empty() || title_ == "Draft"; }',
                'private:',
                '    std::string title_;',
                '};',
                '}',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'pygments');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'pygments');
        $directC = (new SyntaxHighlighter())->highlight('static const char *title = "Draft";', 'h');

        $t->same('cpp', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('cpp', $highlighted['language']);
        $t->same('cpp', $highlighted['requestedLanguage']);
        $t->same('pygments', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(30, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource cpp numberLines"><code class="sourceCode cpp" style="counter-reset: source-line 29;">', $highlighted['html']);
        $t->contains('<span id="cpp-review-30"><a href="#cpp-review-30"></a><span class="pp">#include &lt;string&gt;</span></span>', $highlighted['html']);
        $t->contains('<span class="pp">#include &quot;wp_import.h&quot;</span>', $highlighted['html']);
        $t->contains('<span class="co">// WordPress import extension review</span>', $highlighted['html']);
        $t->contains('<span class="kw">namespace</span> <span class="dt">Migration</span>', $highlighted['html']);
        $t->contains('<span class="kw">class</span> <span class="dt">ReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="kw">public</span><span class="op">:</span>', $highlighted['html']);
        $t->contains('<span class="kw">explicit</span> <span class="dt">ReviewPacket</span><span class="op">(</span><span class="dt">std</span><span class="op">::</span><span class="dt">string</span> <span class="va">title</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">title_</span><span class="op">(</span><span class="dt">std</span><span class="op">::</span><span class="fu">move</span><span class="op">(</span><span class="va">title</span><span class="op">))</span>', $highlighted['html']);
        $t->contains('<span class="dt">bool</span> <span class="fu">is_draft</span><span class="op">()</span> <span class="kw">const</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="va">title_</span><span class="op">.</span><span class="fu">empty</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<span class="va">title_</span> <span class="op">==</span> <span class="st">&quot;Draft&quot;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="pygments">', $wordpressBlock);
        $t->contains('<span class="dt">std</span><span class="op">::</span><span class="dt">string</span>', $wordpressBlock);
        $t->same('c', $directC['language']);
        $t->contains('<span class="kw">static</span> <span class="kw">const</span> <span class="dt">char</span> <span class="op">*</span><span class="va">title</span> <span class="op">=</span> <span class="st">&quot;Draft&quot;</span><span class="op">;</span>', $directC['html']);
    },
    'highlights dockerfile and containerfile review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'docker-review',
            'classes' => ['sourceCode', 'Dockerfile', 'numberLines'],
            'attributes' => ['startFrom' => '4'],
            'text' => implode("\n", [
                '# syntax=docker/dockerfile:1.7',
                'FROM wordpress:php8.3-apache AS source',
                'ARG WP_ENV=production',
                'ENV WORDPRESS_CONFIG_EXTRA="define(\'WP_DEBUG\', false);"',
                'COPY --from=source /var/www/html /review/html',
                'RUN set -eux; \\',
                '    php -m | grep json',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'tango');
        $containerfile = (new SyntaxHighlighter())->highlight('RUN echo "$WP_ENV"', 'Containerfile');

        $t->same('Dockerfile', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('dockerfile', $highlighted['language']);
        $t->same('Dockerfile', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(4, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource Dockerfile numberLines"><code class="sourceCode dockerfile" style="counter-reset: source-line 3;">', $highlighted['html']);
        $t->contains('<span id="docker-review-4"><a href="#docker-review-4"></a><span class="ot"># syntax=docker/dockerfile:1.7</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">FROM</span> wordpress<span class="op">:</span>php<span class="dv">8.3</span><span class="op">-</span>apache <span class="kw">AS</span> source', $highlighted['html']);
        $t->contains('<span class="kw">ARG</span> <span class="ot">WP_ENV</span><span class="op">=</span>production', $highlighted['html']);
        $t->contains('<span class="kw">ENV</span> <span class="ot">WORDPRESS_CONFIG_EXTRA</span><span class="op">=</span><span class="st">&quot;define(&#039;WP_DEBUG&#039;, false);&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">COPY</span> <span class="op">--from=source</span> /var/www/html /review/html', $highlighted['html']);
        $t->contains('<span class="kw">RUN</span> <span class="fu">set</span> <span class="op">-</span>eux<span class="op">;</span> <span class="op">\\</span>', $highlighted['html']);
        $t->contains('<span class="fu">php</span> <span class="op">-</span>m <span class="op">|</span> <span class="fu">grep</span> json', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="kw">FROM</span> wordpress<span class="op">:</span>php', $wordpressBlock);
        $t->same('dockerfile', $containerfile['language']);
        $t->contains('<span class="kw">RUN</span> <span class="fu">echo</span> <span class="st">&quot;$WP_ENV&quot;</span>', $containerfile['html']);
    },
    'highlights makefile review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[11] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Makefile code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'zenburn');
        $directMakefile = (new SyntaxHighlighter())->highlight("include .env\nclean:\n\trm -rf build", 'mk');

        $t->same('Makefile', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('makefile', $highlighted['language']);
        $t->same('Makefile', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(6, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource Makefile numberLines"><code class="sourceCode makefile" style="counter-reset: source-line 5;">', $highlighted['html']);
        $t->contains('<span id="make-review-6"><a href="#make-review-6"></a><span class="co"># WordPress asset build review</span></span>', $highlighted['html']);
        $t->contains('<span class="ot">PLUGIN_VERSION</span> <span class="op">?=</span> <span class="dv">1.2.3</span>', $highlighted['html']);
        $t->contains('<span class="re">assets/build</span><span class="op">:</span> <span class="va">package.json</span> <span class="va">src/block.js</span>', $highlighted['html']);
        $t->contains('<span class="va">$(NPM)</span> <span class="va">run</span> <span class="va">build</span>', $highlighted['html']);
        $t->contains('<span class="fu">wp</span> <span class="va">i18n</span> <span class="va">make-pot</span> <span class="op">.</span> <span class="va">languages/plugin.pot</span>', $highlighted['html']);
        $t->contains('<span class="re">deploy</span><span class="op">:</span>', $highlighted['html']);
        $t->contains('<span class="op">@</span><span class="va">$(WP_CLI)</span> <span class="va">plugin</span> <span class="va">update</span> <span class="va">my-plugin</span> <span class="op">--version</span> <span class="va">$(PLUGIN_VERSION)</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<span class="re">assets/build</span><span class="op">:</span>', $wordpressBlock);
        $t->same('makefile', $directMakefile['language']);
        $t->contains('<span class="kw">include</span> <span class="va">.env</span>', $directMakefile['html']);
        $t->contains('<span class="re">clean</span><span class="op">:</span>', $directMakefile['html']);
        $t->contains('<span class="fu">rm</span> <span class="op">-rf</span> <span class="va">build</span>', $directMakefile['html']);
    },
    'highlights ini config review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[14] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an INI code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'haddock');
        $directCfg = (new SyntaxHighlighter())->highlight("enabled = True\nerror_reporting = ~E_ALL", 'cfg');

        $t->same('ini', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('ini', $highlighted['language']);
        $t->same('ini', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(2, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource ini numberLines"><code class="sourceCode ini" style="counter-reset: source-line 1;">', $highlighted['html']);
        $t->contains('<span id="php-ini-review-2"><a href="#php-ini-review-2"></a><span class="co">; WordPress hosting php.ini review</span></span>', $highlighted['html']);
        $t->contains('<span id="php-ini-review-3"><a href="#php-ini-review-3"></a><span class="kw">[PHP]</span></span>', $highlighted['html']);
        $t->contains('<span class="dt">memory_limit</span> <span class="op">=</span> <span class="st">256M</span>', $highlighted['html']);
        $t->contains('<span class="dt">upload_max_filesize</span> <span class="op">=</span> <span class="st">64M</span>', $highlighted['html']);
        $t->contains('<span class="dt">display_errors</span> <span class="op">=</span> <span class="kw">Off</span>', $highlighted['html']);
        $t->contains('<span class="dt">error_reporting</span> <span class="op">=</span> <span class="kw">E_ALL</span>', $highlighted['html']);
        $t->contains('<span class="kw">[opcache]</span>', $highlighted['html']);
        $t->contains('<span class="dt">opcache.enable</span> <span class="op">=</span> <span class="dv">1</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="dt">display_errors</span> <span class="op">=</span> <span class="kw">Off</span>', $wordpressBlock);
        $t->same('ini', $directCfg['language']);
        $t->contains('<span class="dt">enabled</span> <span class="op">=</span> <span class="kw">True</span>', $directCfg['html']);
        $t->contains('<span class="dt">error_reporting</span> <span class="op">=</span> <span class="op">~</span><span class="kw">E_ALL</span>', $directCfg['html']);
    },
    'highlights toml configuration review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[15] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a TOML code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'kate');
        $cargoLock = (new SyntaxHighlighter())->highlight("[[package]]\nname = \"wp-import\"\nversion = \"1.0.0\"", 'Cargo.lock');

        $t->same('toml', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('toml', $highlighted['language']);
        $t->same('toml', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(11, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource toml numberLines"><code class="sourceCode toml" style="counter-reset: source-line 10;">', $highlighted['html']);
        $t->contains('<span id="toml-review-11"><a href="#toml-review-11"></a><span class="co"># WordPress static export review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">[tool.wordpress-import]</span>', $highlighted['html']);
        $t->contains('<span class="dt">enabled</span> <span class="op">=</span> <span class="cn">true</span>', $highlighted['html']);
        $t->contains('<span class="dt">source</span> <span class="op">=</span> <span class="st">&quot;markdown&quot;</span>', $highlighted['html']);
        $t->contains('<span class="dt">published_at</span> <span class="op">=</span> <span class="cn">2026-06-05T08:40:00Z</span>', $highlighted['html']);
        $t->contains('<span class="dt">max_posts</span> <span class="op">=</span> <span class="dv">250</span>', $highlighted['html']);
        $t->contains('<span class="dt">media_paths</span> <span class="op">=</span> <span class="op">[</span><span class="st">&quot;uploads&quot;</span><span class="op">,</span> <span class="st">&quot;assets&quot;</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="kw">[theme.variation]</span>', $highlighted['html']);
        $t->contains('<span class="dt">palette</span> <span class="op">=</span> <span class="op">{</span> <span class="dt">primary</span> <span class="op">=</span> <span class="st">&quot;#005cc5&quot;</span><span class="op">,</span> <span class="dt">contrast</span> <span class="op">=</span> <span class="st">&quot;#ffffff&quot;</span> <span class="op">}</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="kw">[tool.wordpress-import]</span>', $wordpressBlock);
        $t->same('toml', $cargoLock['language']);
        $t->contains('<span class="kw">[[package]]</span>', $cargoLock['html']);
        $t->contains('<span class="dt">name</span> <span class="op">=</span> <span class="st">&quot;wp-import&quot;</span>', $cargoLock['html']);
    },
    'highlights perl migration review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[16] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Perl code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'zenburn');
        $module = (new SyntaxHighlighter())->highlight("package WP::Import;\nuse utf8;\n1;", 'pm');

        $t->same('pl', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('perl', $highlighted['language']);
        $t->same('pl', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(14, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource pl numberLines"><code class="sourceCode perl" style="counter-reset: source-line 13;">', $highlighted['html']);
        $t->contains('<span id="perl-review-14"><a href="#perl-review-14"></a><span class="kw">#!/usr/bin/env perl</span></span>', $highlighted['html']);
        $t->contains('<span class="fu">use</span> <span class="kw">strict</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="fu">use</span> <span class="kw">warnings</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">package</span> <span class="dt">WP::ImportReview</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">sub</span> <span class="fu">normalize_title</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">my</span> <span class="op">(</span><span class="va">$packet</span><span class="op">)</span> <span class="op">=</span> <span class="va">@_</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">my</span> <span class="op">(</span><span class="va">$title</span><span class="op">)</span> <span class="op">=</span> <span class="va">$packet</span><span class="op">-&gt;{</span><span class="ot">title</span><span class="op">}</span> <span class="op">//</span> <span class="st">&#039;Untitled&#039;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="va">$title</span> <span class="op">=~</span> <span class="st">s/^\\s+|\\s+$//g</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="va">$title</span> <span class="op">eq</span> <span class="st">&#039;&#039;</span><span class="op">)</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="fu">warn</span> <span class="st">&quot;empty title for $packet-&gt;{id}&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="cn">undef</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="fu">lc</span> <span class="va">$title</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<span class="kw">package</span> <span class="dt">WP::ImportReview</span>', $wordpressBlock);
        $t->same('perl', $module['language']);
        $t->contains('<span class="kw">package</span> <span class="dt">WP::Import</span><span class="op">;</span>', $module['html']);
        $t->contains('<span class="fu">use</span> <span class="kw">utf8</span><span class="op">;</span>', $module['html']);
    },
    'highlights java migration review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[17] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Java code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'tango');
        $record = (new SyntaxHighlighter())->highlight('record ImportTask(String title, int count) {}', 'java');

        $t->same('java', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('java', $highlighted['language']);
        $t->same('java', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(21, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource java numberLines"><code class="sourceCode java" style="counter-reset: source-line 20;">', $highlighted['html']);
        $t->contains('<span id="java-review-21"><a href="#java-review-21"></a><span class="kw">package</span> <span class="va">org</span><span class="op">.</span><span class="va">wordpress</span><span class="op">.</span><span class="va">importer</span><span class="op">;</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="va">java</span><span class="op">.</span><span class="va">nio</span><span class="op">.</span><span class="va">file</span><span class="op">.</span><span class="dt">Files</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="co">// WordPress import review helper</span>', $highlighted['html']);
        $t->contains('<span class="kw">public</span> <span class="kw">final</span> <span class="kw">class</span> <span class="dt">ReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="kw">private</span> <span class="kw">final</span> <span class="dt">Path</span> <span class="va">sourcePath</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">this</span><span class="op">.</span><span class="va">sourcePath</span> <span class="op">=</span> <span class="va">sourcePath</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">@Deprecated</span>', $highlighted['html']);
        $t->contains('<span class="kw">public</span> <span class="dt">Optional</span><span class="op">&lt;</span><span class="dt">String</span><span class="op">&gt;</span> <span class="fu">title</span><span class="op">()</span> <span class="kw">throws</span> <span class="dt">IOException</span>', $highlighted['html']);
        $t->contains('<span class="kw">var</span> <span class="va">json</span> <span class="op">=</span> <span class="dt">Files</span><span class="op">.</span><span class="fu">readString</span><span class="op">(</span><span class="va">sourcePath</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="va">json</span><span class="op">.</span><span class="fu">isBlank</span><span class="op">())</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="dt">Optional</span><span class="op">.</span><span class="fu">of</span><span class="op">(</span><span class="st">&quot;Imported&quot;</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="dt">Optional</span><span class="op">.</span><span class="fu">empty</span><span class="op">();</span>', $wordpressBlock);
        $t->same('java', $record['language']);
        $t->contains('<span class="kw">record</span> <span class="dt">ImportTask</span><span class="op">(</span><span class="dt">String</span> <span class="va">title</span><span class="op">,</span> <span class="dt">int</span> <span class="va">count</span><span class="op">)</span>', $record['html']);
    },
    'highlights xml and xslt review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[18] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an XML code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'haddock');
        $xslt = (new SyntaxHighlighter())->highlight(
            "<xsl:template match=\"/rss/channel/item\">\n  <xsl:value-of select=\"normalize-space(title)\"/>\n</xsl:template>",
            'xsl'
        );
        $svg = (new SyntaxHighlighter())->highlight('<svg viewBox="0 0 10 10"><use href="#icon"/></svg>', 'svg');

        $t->same('xml', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('xml', $highlighted['language']);
        $t->same('xml', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(33, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource xml numberLines"><code class="sourceCode xml" style="counter-reset: source-line 32;">', $highlighted['html']);
        $t->contains('<span id="wxr-xml-review-33"><a href="#wxr-xml-review-33"></a><span class="pp">&lt;?xml</span> <span class="ot">version</span><span class="op">=</span><span class="st">&quot;1.0&quot;</span> <span class="ot">encoding</span><span class="op">=</span><span class="st">&quot;UTF-8&quot;</span><span class="op">?&gt;</span></span>', $highlighted['html']);
        $t->contains('<span class="pp">&lt;!DOCTYPE</span> rss <span class="op">[</span><span class="pp">&lt;!ENTITY</span> legacy <span class="st">&quot;Legacy&quot;</span><span class="op">&gt;]&gt;</span>', $highlighted['html']);
        $t->contains('<span class="co">&lt;!-- WordPress WXR media review --&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;rss</span> <span class="ot">version</span><span class="op">=</span><span class="st">&quot;2.0&quot;</span>', $highlighted['html']);
        $t->contains('<span class="ot">xmlns:wp</span><span class="op">=</span><span class="st">&quot;http://wordpress.org/export/1.2/&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;wp:wxr_version</span><span class="op">&gt;</span><span class="dv">1.2</span><span class="kw">&lt;/wp:wxr_version</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;item</span> <span class="ot">data-source</span><span class="op">=</span><span class="st">&quot;legacy-42&quot;</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;title</span><span class="op">&gt;</span><span class="cn">&amp;legacy;</span> <span class="cn">&amp;amp;</span> Reviewed<span class="kw">&lt;/title</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="st">&lt;![CDATA[&lt;!-- wp:paragraph --&gt;&lt;p&gt;Legacy shortcode [gallery]&lt;/p&gt;]]&gt;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="kw">&lt;content:encoded</span><span class="op">&gt;</span>', $wordpressBlock);
        $t->same('xslt', $xslt['language']);
        $t->same('xsl', $xslt['requestedLanguage']);
        $t->contains('<pre class="sourceCode xslt"><code class="sourceCode xslt"><span class="kw">&lt;xsl:template</span> <span class="ot">match</span><span class="op">=</span><span class="st">&quot;/rss/channel/item&quot;</span><span class="op">&gt;</span>', $xslt['html']);
        $t->contains('<span class="kw">&lt;xsl:value-of</span> <span class="ot">select</span><span class="op">=</span><span class="st">&quot;normalize-space(title)&quot;</span><span class="op">/&gt;</span>', $xslt['html']);
        $t->same('xml', $svg['language']);
        $t->contains('<span class="kw">&lt;svg</span> <span class="ot">viewBox</span><span class="op">=</span><span class="st">&quot;0 0 10 10&quot;</span><span class="op">&gt;</span>', $svg['html']);
        $t->contains('<span class="kw">&lt;use</span> <span class="ot">href</span><span class="op">=</span><span class="st">&quot;#icon&quot;</span><span class="op">/&gt;</span>', $svg['html']);
    },
    'highlights bash shell review snippets with heredoc state and pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[19] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Bash shell code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'pygments');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'pygments');
        $console = (new SyntaxHighlighter())->highlight('printf "%s\n" "$title"', 'console');

        $t->same('sh', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('bash', $highlighted['language']);
        $t->same('sh', $highlighted['requestedLanguage']);
        $t->same('pygments', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(50, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource sh numberLines"><code class="sourceCode bash" style="counter-reset: source-line 49;">', $highlighted['html']);
        $t->contains('<span id="shell-review-50"><a href="#shell-review-50"></a><span class="kw">#!/usr/bin/env bash</span></span>', $highlighted['html']);
        $t->contains('<span class="fu">set</span> <span class="op">-euo</span> <span class="va">pipefail</span>', $highlighted['html']);
        $t->contains('<span class="fu">wp</span> <span class="va">post</span> <span class="va">list</span> <span class="ot">--post_type</span><span class="op">=</span><span class="va">post</span>', $highlighted['html']);
        $t->contains('<span class="kw">while</span> <span class="fu">read</span> <span class="op">-r</span> <span class="va">post_id</span><span class="op">;</span> <span class="kw">do</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span><span class="op">=$(</span><span class="fu">wp</span> <span class="va">post</span> <span class="va">get</span> <span class="st">&quot;$post_id&quot;</span> <span class="ot">--field</span><span class="op">=</span><span class="va">post_title</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">[[</span> <span class="op">-z</span> <span class="st">&quot;$title&quot;</span> <span class="op">]];</span> <span class="kw">then</span>', $highlighted['html']);
        $t->contains('<span class="fu">cat</span> <span class="op">&lt;&lt;</span><span class="st">&#039;HTML&#039;</span> <span class="op">&gt;</span> <span class="st">&quot;$TMPDIR/post-$post_id.html&quot;</span>', $highlighted['html']);
        $t->contains('<span class="st">&lt;!-- wp:paragraph --&gt;&lt;p&gt;Missing title&lt;/p&gt;&lt;!-- /wp:paragraph --&gt;</span>', $highlighted['html']);
        $t->contains('<span class="re">HTML</span>', $highlighted['html']);
        $t->contains('<span class="kw">done</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="pygments">', $wordpressBlock);
        $t->contains('<span class="st">&lt;!-- wp:paragraph --&gt;&lt;p&gt;Missing title&lt;/p&gt;', $wordpressBlock);
        $t->same('bash', $console['language']);
        $t->same('console', $console['requestedLanguage']);
        $t->contains('<span class="fu">printf</span> <span class="st">&quot;%s\\n&quot;</span> <span class="st">&quot;$title&quot;</span>', $console['html']);
    },
    'highlights lua long bracket strings and comments for pandoc filters' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[33] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Lua long-bracket code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directLua = $highlighter->highlight("--[==[review note]==]\nlocal html = [==[<p>ok</p>]==]", 'pandoc-lua');

        $t->same('pandoc-lua', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('lua', $highlighted['language']);
        $t->same('pandoc-lua', $highlighted['requestedLanguage']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(290, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource pandoc-lua numberLines"><code class="sourceCode lua" style="counter-reset: source-line 289;">', $highlighted['html']);
        $t->contains('<span id="lua-long-bracket-review-290"><a href="#lua-long-bracket-review-290"></a><span class="co">--[=[ WordPress block fixture can contain &lt;!-- comments --&gt; ]=]</span></span>', $highlighted['html']);
        $t->contains('<span id="lua-long-bracket-review-291"><a href="#lua-long-bracket-review-291"></a><span class="kw">local</span> <span class="va">rawBlock</span> <span class="op">=</span> <span class="st">[=[</span></span>', $highlighted['html']);
        $t->contains('<span id="lua-long-bracket-review-292"><a href="#lua-long-bracket-review-292"></a><span class="st">&lt;!-- wp:paragraph --&gt;</span></span>', $highlighted['html']);
        $t->contains('<span id="lua-long-bracket-review-293"><a href="#lua-long-bracket-review-293"></a><span class="st">&lt;p&gt;Imported ${title}&lt;/p&gt;</span></span>', $highlighted['html']);
        $t->contains('<span id="lua-long-bracket-review-295"><a href="#lua-long-bracket-review-295"></a><span class="st">]=]</span></span>', $highlighted['html']);
        $t->contains('<span id="lua-long-bracket-review-296"><a href="#lua-long-bracket-review-296"></a><span class="kw">return</span> <span class="dt">pandoc</span><span class="op">.</span><span class="fu">RawBlock</span><span class="op">(</span><span class="st">&quot;html&quot;</span><span class="op">,</span> <span class="va">rawBlock</span><span class="op">)</span></span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="st">&lt;p&gt;Imported ${title}&lt;/p&gt;</span>', $wordpressBlock);
        $t->same('lua', $directLua['language']);
        $t->contains('<span class="co">--[==[review note]==]</span>', $directLua['html']);
        $t->contains('<span class="st">[==[&lt;p&gt;ok&lt;/p&gt;]==]</span>', $directLua['html']);
    },
    'highlights php heredoc and nowdoc wordpress block strings' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[34] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a PHP heredoc code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'pygments');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'pygments');
        $directNowdoc = $highlighter->highlight("<?php\n\$raw = <<<'HTML'\n<!-- wp:shortcode -->\n[gallery]\nHTML;\n", 'php');

        $t->same('php', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('php', $highlighted['language']);
        $t->same('php', $highlighted['requestedLanguage']);
        $t->same('pygments', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(310, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource php numberLines"><code class="sourceCode php" style="counter-reset: source-line 309;">', $highlighted['html']);
        $t->contains('<span id="php-heredoc-review-310"><a href="#php-heredoc-review-310"></a><span class="pp">&lt;?php</span></span>', $highlighted['html']);
        $t->contains('<span id="php-heredoc-review-311"><a href="#php-heredoc-review-311"></a><span class="va">$block</span> <span class="op">=</span> <span class="st">&lt;&lt;&lt;HTML</span></span>', $highlighted['html']);
        $t->contains('<span class="st">&lt;!-- wp:paragraph --&gt;</span>', $highlighted['html']);
        $t->contains('<span class="st">&lt;p&gt;Imported {$title}&lt;/p&gt;</span>', $highlighted['html']);
        $t->contains('<span class="st">HTML;</span>', $highlighted['html']);
        $t->contains('<span class="va">$raw</span> <span class="op">=</span> <span class="st">&lt;&lt;&lt;&#039;NOWDOC&#039;</span>', $highlighted['html']);
        $t->contains('<span class="st">&lt;div data-source=&quot;legacy&quot;&gt;raw&lt;/div&gt;</span>', $highlighted['html']);
        $t->contains('<span class="st">NOWDOC;</span>', $highlighted['html']);
        $t->contains('<span class="kw">echo</span> <span class="va">$block</span> <span class="op">.</span> <span class="va">$raw</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="pygments">', $wordpressBlock);
        $t->contains('<span class="st">&lt;!-- wp:html --&gt;</span>', $wordpressBlock);
        $t->same('php', $directNowdoc['language']);
        $t->contains('<span class="va">$raw</span> <span class="op">=</span> <span class="st">&lt;&lt;&lt;&#039;HTML&#039;', $directNowdoc['html']);
        $t->contains('&lt;!-- wp:shortcode --&gt;', $directNowdoc['html']);
        $t->contains('[gallery]', $directNowdoc['html']);
        $t->contains('HTML;</span>', $directNowdoc['html']);
    },
    'parses pandoc json theme files for custom syntax highlight css' => static function (TestRunner $t): void {
        $themeJson = json_encode([
            'name' => 'Review Import',
            'text-color' => '#F8F8F2',
            'background-color' => '#101820',
            'line-number-color' => '#8F9AAE',
            'line-number-background-color' => '#202A35',
            'token-styles' => [
                'Normal' => ['text-color' => '#F8F8F2'],
                'KeywordTok' => ['text-color' => '#FFCC00', 'bold' => true],
                'StringTok' => ['text-color' => '#7BD88F'],
                'CommentTok' => ['text-color' => '#7F8C8D', 'italic' => true],
                'FunctionTok' => ['text-color' => '#80DFFF', 'underline' => true],
                'VariableTok' => ['text-color' => '#FF9F43'],
                'OperatorTok' => ['text-color' => '#FF6B6B'],
                'AttributeTok' => ['text-color' => '#C3E88D'],
                'AlertTok' => ['text-color' => '#FF5555', 'bold' => true],
                'FutureTok' => ['text-color' => '#ABCDEF'],
            ],
        ], JSON_THROW_ON_ERROR);

        $parsed = SyntaxHighlighter::parseThemeJson($themeJson);
        $css = SyntaxHighlighter::stylesheetFromThemeJson($themeJson);

        $t->same('review-import', $parsed['name']);
        $t->same('#101820', $parsed['colors']['background']);
        $t->same('#f8f8f2', $parsed['colors']['text']);
        $t->same('#ffcc00', $parsed['colors']['keyword']);
        $t->same('#ff5555', $parsed['colors']['warning']);
        $t->same(1, count($parsed['diagnostics']));
        $t->same('unsupported-theme-token', $parsed['diagnostics'][0]['code'] ?? null);
        $t->contains('.sourceCode { background: #101820; color: #f8f8f2; }', $css);
        $t->contains('.sourceCode .kw { color: #ffcc00; font-weight: 700; }', $css);
        $t->contains('.sourceCode .fu { color: #80dfff; text-decoration: underline; }', $css);
        $t->contains('.sourceCode .co { color: #7f8c8d; font-style: italic; }', $css);
        $t->contains('.sourceCode .al { color: #ff5555; font-weight: 700; }', $css);
        $t->contains('color: #8f9aae; background-color: #202a35;', $css);

        $highlighted = (new SyntaxHighlighter())->highlight(
            'echo esc_html($title); // review',
            'php',
            'pygments',
            [
                'themeJson' => $themeJson,
                'id' => 'custom-theme',
                'classes' => ['numberLines'],
                'attributes' => ['startFrom' => '10'],
            ]
        );

        $t->same('review-import', $highlighted['style']);
        $t->same('unsupported-theme-token', $highlighted['diagnostics'][0]['code'] ?? null);
        $t->same(10, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource numberLines"><code class="sourceCode php" style="counter-reset: source-line 9;">', $highlighted['html']);
        $t->contains('<span id="custom-theme-10"><a href="#custom-theme-10"></a><span class="kw">echo</span> <span class="fu">esc_html</span>', $highlighted['html']);
        $t->contains('.sourceCode .va { color: #ff9f43; }', $highlighted['css']);

        $block = (new SyntaxHighlighter())->wordpressHtmlBlock(new AstNode('code_block', [
            'id' => 'custom-theme',
            'classes' => ['php', 'numberLines'],
            'attributes' => ['startFrom' => '10'],
            'text' => 'echo esc_html($title); // review',
        ]), 'pygments', ['themeJson' => $themeJson]);

        $t->contains('<style data-pandoc-highlight-style="review-import">', $block);
        $t->contains('.sourceCode .kw { color: #ffcc00; font-weight: 700; }', $block);
        $t->contains('<span id="custom-theme-10"><a href="#custom-theme-10"></a><span class="kw">echo</span>', $block);
    },
    'rejects invalid pandoc json theme payloads' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => SyntaxHighlighter::parseThemeJson("\xEF\xBB\xBF{}"));
        $t->throws(InvalidArgumentException::class, static fn (): array => SyntaxHighlighter::parseThemeJson('{"token-styles": [{"KeywordTok": {"text-color": "#ffffff"}}]}'));
        $t->throws(InvalidArgumentException::class, static fn (): array => SyntaxHighlighter::parseThemeJson('{"text-color": "#12"}'));
        $t->throws(InvalidArgumentException::class, static fn (): array => SyntaxHighlighter::parseThemeJson('{"token-styles": {"KeywordTok": {"bold": "sometimes"}}}'));
    },
    'writes highlighted wordpress blocks through writer opt in' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '``` {.php #migration-review .numberLines .lineAnchors startFrom=42}',
            '<?php',
            'function render_title($post) {',
            "    return esc_html(\$post['title']);",
            '}',
            '```',
        ]));

        $plainBlocks = (new WordPressBlockWriter())->write($document);
        $highlightedBlocks = (new WordPressBlockWriter([
            'highlightCodeBlocks' => true,
            'highlightStyle' => 'kate',
        ]))->write($document);

        $t->contains('<!-- wp:code -->', $plainBlocks);
        $t->contains('<pre class="wp-block-code"><code class="language-php">&lt;?php', $plainBlocks);
        $t->contains('<!-- wp:html -->', $highlightedBlocks);
        $t->contains('<style data-pandoc-highlight-style="kate">', $highlightedBlocks);
        $t->contains('.sourceCode .kw', $highlightedBlocks);
        $t->contains('<div class="sourceCode"><pre class="sourceCode numberSource php numberLines lineAnchors"><code class="sourceCode php" style="counter-reset: source-line 41;">', $highlightedBlocks);
        $t->contains('<span id="migration-review-42"><a href="#migration-review-42"></a><span class="pp">&lt;?php</span></span>', $highlightedBlocks);
        $t->contains('<span id="migration-review-43"><a href="#migration-review-43"></a><span class="kw">function</span> <span class="fu">render_title</span>', $highlightedBlocks);
        $t->contains('<span class="va">$post</span><span class="op">[</span><span class="st">&#039;title&#039;</span><span class="op">]);</span>', $highlightedBlocks);
        $t->same(false, str_contains($highlightedBlocks, '<!-- wp:code -->'));
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
