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
        $t->same('html', SyntaxHighlighter::normalizeLanguage('HTML5'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('language-js'));
        $t->same('jsx', SyntaxHighlighter::normalizeLanguage('jsx'));
        $t->same('jsx', SyntaxHighlighter::normalizeLanguage('javascript-react'));
        $t->same('c', SyntaxHighlighter::normalizeLanguage('c'));
        $t->same('c', SyntaxHighlighter::normalizeLanguage('h'));
        $t->same('cpp', SyntaxHighlighter::normalizeLanguage('c++'));
        $t->same('cpp', SyntaxHighlighter::normalizeLanguage('cpp'));
        $t->same('cpp', SyntaxHighlighter::normalizeLanguage('cxx'));
        $t->same('cpp', SyntaxHighlighter::normalizeLanguage('hpp'));
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
        $t->same('makefile', SyntaxHighlighter::normalizeLanguage('make'));
        $t->same('makefile', SyntaxHighlighter::normalizeLanguage('makefile'));
        $t->same('makefile', SyntaxHighlighter::normalizeLanguage('GNUmakefile'));
        $t->same('makefile', SyntaxHighlighter::normalizeLanguage('mk'));
        $t->same('perl', SyntaxHighlighter::normalizeLanguage('perl'));
        $t->same('perl', SyntaxHighlighter::normalizeLanguage('pl'));
        $t->same('perl', SyntaxHighlighter::normalizeLanguage('PL'));
        $t->same('perl', SyntaxHighlighter::normalizeLanguage('pm'));
        $t->same('java', SyntaxHighlighter::normalizeLanguage('java'));
        $t->same('ruby', SyntaxHighlighter::normalizeLanguage('rb'));
        $t->same('ruby', SyntaxHighlighter::normalizeLanguage('rake'));
        $t->same('lua', SyntaxHighlighter::normalizeLanguage('lua'));
        $t->same('lua', SyntaxHighlighter::normalizeLanguage('pandoc-lua'));
        $t->same('python', SyntaxHighlighter::normalizeLanguage('py'));
        $t->same('python', SyntaxHighlighter::normalizeLanguage('py3'));
        $t->same('python', SyntaxHighlighter::normalizeLanguage('python3'));
        $t->same('r', SyntaxHighlighter::normalizeLanguage('r'));
        $t->same('r', SyntaxHighlighter::normalizeLanguage('Rscript'));
        $t->same('r', SyntaxHighlighter::normalizeLanguage('S'));
        $t->same('r', SyntaxHighlighter::normalizeLanguage('language-q'));
        $t->same('typescript', SyntaxHighlighter::normalizeLanguage('ts'));
        $t->same('typescript', SyntaxHighlighter::normalizeLanguage('typescript'));
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
