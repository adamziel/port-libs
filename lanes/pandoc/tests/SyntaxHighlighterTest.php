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
        $t->same('html', SyntaxHighlighter::normalizeLanguage('HTML5'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('language-js'));
        $t->same('tex', SyntaxHighlighter::normalizeLanguage('latex'));
        $t->same('tex', SyntaxHighlighter::normalizeLanguage('TeX'));
        $t->same('yaml', SyntaxHighlighter::normalizeLanguage('yml'));
        $t->same('markdown', SyntaxHighlighter::normalizeLanguage('md'));
        $t->same('markdown', SyntaxHighlighter::normalizeLanguage('pandoc-markdown'));
        $t->same('markdown', SyntaxHighlighter::normalizeLanguage('commonmark'));
        $t->same('markdown', SyntaxHighlighter::normalizeLanguage('gfm'));
        $t->same('ruby', SyntaxHighlighter::normalizeLanguage('rb'));
        $t->same('ruby', SyntaxHighlighter::normalizeLanguage('rake'));
        $t->same('lua', SyntaxHighlighter::normalizeLanguage('lua'));
        $t->same('lua', SyntaxHighlighter::normalizeLanguage('pandoc-lua'));
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
