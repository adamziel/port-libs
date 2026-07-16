<?php

declare(strict_types=1);

use PortLibs\Difftastic\AnsiSyntaxHighlighter;
use PortLibs\Difftastic\DiffCommandRunner;
use PortLibs\Difftastic\DirectoryDiffer;
use PortLibs\Difftastic\FileContentDecoder;
use PortLibs\Difftastic\GitExternalDiffMetadata;
use PortLibs\Difftastic\HtmlDiffRenderer;
use PortLibs\Difftastic\InlineDiffRenderer;
use PortLibs\Difftastic\JsonDiffRenderer;
use PortLibs\Difftastic\LanguageCatalog;
use PortLibs\Difftastic\SideBySideDiffRenderer;
use PortLibs\Difftastic\TokenDiffer;

return [
    'tokenizes identifiers numbers strings and punctuation separately' => static function (TestRunner $t): void {
        $tokens = (new TokenDiffer())->tokenize('fn add(x, 2) { return "ok"; }');
        $t->same('identifier', $tokens[0]->kind);
        $t->same('fn', $tokens[0]->text);
        $t->same('number', $tokens[5]->kind);
        $t->same('string', $tokens[9]->kind);
    },
    'tokenizes rust lifetimes separately from character strings' => static function (TestRunner $t): void {
        $tokens = (new TokenDiffer())->tokenize("fn render<'a>(x: &'a str) { 'x'; }\n", ['language' => 'rust']);
        $byText = [];
        foreach ($tokens as $token) {
            $byText[] = $token->text . ':' . $token->kind;
        }
        $encoded = implode("\n", $byText);

        $t->contains("':punctuation\na:identifier\n>:punctuation", $encoded);
        $t->contains("'x':string", $encoded);
    },
    'classifies comments and delimiter anchors' => static function (TestRunner $t): void {
        $tokens = (new TokenDiffer())->tokenize('items([1, /* keep */ 2])');
        $kinds = array_map(static fn ($token): string => $token->kind, $tokens);
        $t->contains('comment', implode(',', $kinds));
        $t->same('open', $tokens[1]->delimiterRole);
        $t->same('open', $tokens[2]->delimiterRole);
        $t->same('close', $tokens[8]->delimiterRole);
    },
    'diff operates on tokens rather than raw lines' => static function (TestRunner $t): void {
        $ops = (new TokenDiffer())->diff('return a + b;', 'return a - b;');
        $encoded = implode('', array_map(static fn (array $op): string => $op['op'] . $op['text'], $ops));
        $t->contains('-+', $encoded);
        $t->contains('+-', $encoded);
    },
    'matches upstream ignore comments cli behavior' => static function (TestRunner $t): void {
        $old = 'funName(1 /* foo */ , /* bar */)';
        $new = 'funName(1 /* kinda like bar */ , /* foo */)';
        $differ = new TokenDiffer();

        $t->true($differ->hasChanges($old, $new));
        $t->same(false, $differ->hasChanges($old, $new, ['ignoreComments' => true]));
    },
    'maps upstream strip cr default for multiline comment atoms' => static function (TestRunner $t): void {
        $before = "/**\r\n * Legacy block copy.\r\n */\r\nrender_card();\r\n";
        $after = "/**\n * Legacy block copy.\n */\nrender_card();\n";
        $differ = new TokenDiffer();

        $t->same(false, $differ->hasChanges($before, $after, ['language' => 'php']));
        $t->same(true, $differ->hasChanges($before, $after, [
            'language' => 'php',
            'stripCr' => false,
        ]));
        $t->same([], $differ->diffSyntaxLists($before, $after, ['language' => 'php']));
    },
    'maps every upstream compare expected sample pair through php renderer' => static function (TestRunner $t): void {
        $manifestPath = dirname(__DIR__) . '/fixtures/upstream-sample-pairs.json';
        $fixtureRoot = dirname(__DIR__) . '/fixtures/upstream-sample-files';
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $renderer = new JsonDiffRenderer();
        $catalog = new LanguageCatalog();
        $seen = [];
        $rendered = 0;
        $oversized = 0;

        $t->same(111, $manifest['pairCount']);
        $t->same(109, $manifest['copiedPairCount']);
        $t->same(2, $manifest['oversizedMetadataPairCount']);

        foreach ($manifest['pairs'] as $pair) {
            $key = $pair['lhs'] . ' -> ' . $pair['rhs'];
            $t->same(false, isset($seen[$key]), $key . ' should be listed once');
            $seen[$key] = true;
            $t->true(is_string($pair['upstreamOutputMd5']) && strlen($pair['upstreamOutputMd5']) === 32, $key . ' should retain the upstream compare.expected hash');

            if ($pair['coverage'] === 'oversized-metadata') {
                $oversized++;
                $t->contains($pair['lhs'], "huge_cpp_1.cpp\nlong_line_1.txt");
                $t->true($pair['lhsBytes'] > 4_000_000, $key . ' should only use metadata for oversized upstream fixtures');
                $t->same('covered-by-size-sha256-metadata-and-targeted-oversized-fallback-tests', $pair['phpCoverage']);
                continue;
            }

            $lhsPath = $fixtureRoot . '/' . $pair['lhs'];
            $rhsPath = $fixtureRoot . '/' . $pair['rhs'];
            $t->true(is_file($lhsPath), $pair['lhs'] . ' should be copied into the repo fixture mirror');
            $t->true(is_file($rhsPath), $pair['rhs'] . ' should be copied into the repo fixture mirror');
            $oldBytes = (string) file_get_contents($lhsPath);
            $newBytes = (string) file_get_contents($rhsPath);
            $t->same($pair['lhsBytes'], strlen($oldBytes), $pair['lhs'] . ' byte size should match upstream');
            $t->same($pair['rhsBytes'], strlen($newBytes), $pair['rhs'] . ' byte size should match upstream');
            $t->same($pair['lhsSha256'], hash('sha256', $oldBytes), $pair['lhs'] . ' sha256 should match upstream');
            $t->same($pair['rhsSha256'], hash('sha256', $newBytes), $pair['rhs'] . ' sha256 should match upstream');

            $language = $catalog->languageForPath($pair['rhs'], $newBytes);
            $file = $renderer->fileBytesDiff(
                $oldBytes,
                $newBytes,
                'sample_files/' . $pair['rhs'],
                $language['display'],
                ['language' => $language['option']],
            );
            $encoded = json_encode($file, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $t->same('rendered-json-file-diff', $pair['phpCoverage']);
            $t->same($pair['phpLanguageOption'], $language['option'], $key . ' language option should remain stable');
            $t->same($pair['phpLanguage'], $file['language'] ?? null, $key . ' rendered language should remain stable');
            $t->same($pair['phpStatus'], $file['status'] ?? null, $key . ' rendered status should remain stable');
            $t->same($pair['phpChunkCount'], count($file['chunks'] ?? []), $key . ' rendered chunk count should remain stable');
            $t->same($pair['phpOutputSha256'], hash('sha256', (string) $encoded), $key . ' rendered JSON hash should remain stable');
            $rendered++;
        }

        $t->same($manifest['pairCount'], count($seen));
        $t->same($manifest['copiedPairCount'], $rendered);
        $t->same($manifest['oversizedMetadataPairCount'], $oversized);
    },
    'maps every upstream rust test attribute to php coverage' => static function (TestRunner $t): void {
        $manifestPath = dirname(__DIR__) . '/fixtures/upstream-rust-tests.json';
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $seen = [];

        $t->same(144, $manifest['testAttributeCount']);
        foreach ($manifest['tests'] as $test) {
            $key = $test['file'] . ':' . $test['line'] . ':' . $test['testName'];
            $t->same(false, isset($seen[$key]), $key . ' should be listed once');
            $seen[$key] = true;
            $t->true(str_ends_with($test['file'], '.rs'), $key . ' should point at an upstream Rust source file');
            $t->true(str_starts_with($test['attribute'], '#[test'), $key . ' should retain its upstream test attribute');
            $t->same('covered-by-lanes/difftastic/tests/TokenDifferTest.php', $test['phpCoverage'], $key . ' should have PHP coverage');
        }

        $t->same($manifest['testAttributeCount'], count($seen));
    },
    'ignores trailing commas before closing delimiters' => static function (TestRunner $t): void {
        $differ = new TokenDiffer();
        $old = 'const blocks = ["core/paragraph", "core/image"];';
        $new = 'const blocks = ["core/paragraph", "core/image",];';

        $t->same(false, $differ->hasChanges($old, $new));
    },
    'maps upstream trailing commas sample as formatting only' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-trailing-commas-1.js');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-trailing-commas-2.js');
        $differ = new TokenDiffer();
        $html = (new HtmlDiffRenderer())->renderTokenDiff($before, $after, [
            'title' => 'Upstream trailing commas sample',
        ]);

        $t->same(false, $differ->hasChanges($before, $after));
        $t->contains('No syntactic changes', $html);
    },
    'enables angle delimiters in markup-like modes only' => static function (TestRunner $t): void {
        $differ = new TokenDiffer();
        $defaultTokens = $differ->tokenize('<h1>');
        $htmlTokens = $differ->tokenize('<h1 id="title">Bar</h1>', ['language' => 'html']);
        $jsxTokens = $differ->tokenize('<PanelBody title="Settings">', ['language' => 'jsx']);

        $t->same('punctuation', $defaultTokens[0]->kind);
        $t->same('delimiter', $htmlTokens[0]->kind);
        $t->same('open', $htmlTokens[0]->delimiterRole);
        $t->same('close', $htmlTokens[5]->delimiterRole);
        $t->same('open', $htmlTokens[7]->delimiterRole);
        $t->same('close', $htmlTokens[10]->delimiterRole);
        $t->same('delimiter', $jsxTokens[0]->kind);
        $t->same('open', $jsxTokens[0]->delimiterRole);
    },
    'maps upstream html simple sample as tag list changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-simple-1.html');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-simple-2.html');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'html']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? ''),
            $changes
        ));

        $t->contains('- $[5][0] bodyclass="foo"', $encoded);
        $t->contains('+ $[5][0] bodyclass="bar"', $encoded);
        $t->contains('+ $[6][0] h1id="title"', $encoded);
        $t->contains('+ $[9] <strong>', $encoded);
        $t->contains('+ $[10] </strong>', $encoded);
    },
    'maps upstream xml sample as tag list changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-xml-1.xml');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-xml-2.xml');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'xml']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? ''),
            $changes
        ));

        $t->contains('+ $[3] <stuff/>', $encoded);
        $t->true(!str_contains($encoded, '<root>'), 'Stable root tags should remain matched in XML mode.');
    },
    'maps upstream python if sample as indentation block changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-if-1.py');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-if-2.py');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'python']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('- $py.if["x"][1] bar', $encoded);
        $t->contains('+ $py.root[0] bar', $encoded);
        $t->true(!str_contains($encoded, 'if x:'), 'Stable Python if headers should not be rendered as changed when only indentation moves a body item.');
    },
    'maps upstream python directory def excerpt as a header update' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-python-def-1.py');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-python-def-2.py');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'python']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('~ $py.def["function041"]/header def function041() def function041(**args)', $encoded);
        $t->true(!str_contains($encoded, 'function040'), 'Stable neighboring Python functions should stay matched when one signature changes.');
        $t->true(!str_contains($encoded, 'function042'), 'Stable following Python functions should stay matched when one signature changes.');
    },
    'maps upstream python directory nested def excerpt as nested block insertion' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-python-nested-def-1.py');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-python-nested-def-2.py');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'python']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('+ $py.def["function081"].if["True"] if True:', $encoded);
        $t->true(!str_contains($encoded, '- $py.def["function081"][0] pass'), 'The retained direct function body statement should stay matched after a nested if is inserted.');
        $t->true(!str_contains($encoded, 'function080'), 'Stable preceding Python functions should stay matched when a nested block is inserted.');
        $t->true(!str_contains($encoded, 'function082'), 'Stable following Python functions should stay matched when a nested block is inserted.');
    },
    'maps python elif else try except finally clauses as compound blocks' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/python-compound-clauses-before.py');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/python-compound-clauses-after.py');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'python']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('+ $py.if["status == \"draft\""].elif["status == \"private\""] elif status == "private":', $encoded);
        $t->contains('queue_private(post)', $encoded);
        $t->contains('+ $py.try["try"].finally["finally"] finally:', $encoded);
        $t->contains('cleanup_temp(post)', $encoded);
        $t->true(!str_contains($encoded, '- $py.if["status == \"draft\""]'), 'Stable Python if blocks should remain matched when an elif clause is inserted.');
        $t->true(!str_contains($encoded, '- $py.if["status == \"draft\""].else["else"]'), 'Stable Python else clauses should remain matched when an elif clause is inserted.');
        $t->true(!str_contains($encoded, '- $py.try["try"]'), 'Stable Python try blocks should remain matched when finally is inserted.');
        $t->true(!str_contains($encoded, '- $py.try["try"].except["ValueError as error"]'), 'Stable Python except clauses should remain matched when finally is inserted.');
    },
    'respects upstream python trailing comma tuple exception' => static function (TestRunner $t): void {
        $differ = new TokenDiffer();

        $t->same(false, $differ->hasChanges('blocks = ["core/paragraph", "core/image"]', 'blocks = ["core/paragraph", "core/image",]', ['language' => 'python']));
        $t->same(false, $differ->hasChanges('flags = {"migrate": True}', 'flags = {"migrate": True,}', ['language' => 'python']));
        $t->same(false, $differ->hasChanges('collect_blocks(blocks, flags)', 'collect_blocks(blocks, flags,)', ['language' => 'python']));
        $t->same(true, $differ->hasChanges('legacy_marker = ("classic-editor")', 'legacy_marker = ("classic-editor",)', ['language' => 'python']));
    },
    'wordpress python trailing comma diff ignores calls but keeps tuple changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-python-trailing-comma-before.py');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-python-trailing-comma-after.py');
        $ops = (new TokenDiffer())->diff($before, $after, ['language' => 'python']);
        $changes = array_values(array_filter($ops, static fn (array $op): bool => $op['op'] !== '='));
        $html = (new HtmlDiffRenderer())->renderTokenDiff($before, $after, [
            'language' => 'python',
            'title' => 'WordPress Python trailing comma diff',
        ]);

        $t->same([['op' => '-', 'text' => ',']], $changes);
        $t->contains('WordPress Python trailing comma diff', $html);
        $t->contains('<span class="dft-del" data-op="-">,</span>', $html);
        $t->true(!str_contains($html, 'class="dft-add"'), 'List, dict, and call trailing commas should stay formatting-only for migration scripts.');
    },
    'maps upstream css sample with selector and declaration alignment' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-css-1.css');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-css-2.css');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'css']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('+ $css[".foo1"][1] color:green;', $encoded);
        $t->contains('~ $css[".baz"][0] color:yellow; color:blue;', $encoded);
        $t->contains('~ $css[".baz"][1] font-family:"Before"; font-family:"After";', $encoded);
        $t->contains('~ $css[".another"][0] margin-left:0.5em; margin-left:1em;', $encoded);
        $t->contains('+ $css["p"] p{color:#000;}', $encoded);
        $t->true(!str_contains($encoded, '$css[".bar"]'), 'Reordered stable CSS selector blocks should stay matched.');
    },
    'maps upstream tailwind css at-rule item as a focused update' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-tailwind-1.css');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-tailwind-2.css');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'css']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('~ $css["select"][0]', $encoded);
        $t->contains('@applyrounded-mdbg-gray-600;', $encoded);
        $t->contains('@applyrounded-mdbg-hss-dark-gray;', $encoded);
        $t->true(!str_contains($encoded, '- $css["select"][0]'), 'Changed CSS at-rule items should stay aligned instead of being deleted and re-added.');
    },
    'maps upstream simple scss sample through mixin and nested rule alignment' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-simple-scss-1.scss');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-simple-scss-2.scss');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'scss']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('~ $css["@mixinbuttons"]/selector @mixinbuttons($basicBorder:1px,$gradient1:#fff,$gradient2:#d8dee7) @mixinbuttons($basicBorder:1px,$gradient1:#333,$gradient2:#d8dee7)', $encoded);
        $t->contains('~ $css["@mixinbuttons"][0]/{0}[0] border:$basicBordersolid#acbed3; border:$basicBorderdotted#acbed3;', $encoded);
        $t->contains('~ $css["@mixinbuttons"][0]/{0}[3] font-size:12px; font-size:1rem;', $encoded);
        $t->contains('~ $css["@mixinbuttons"][0]/{0}[7]/{0}[0] border:2pxsolid#3b557d; border:2pxdotted#3b557d;', $encoded);
        $t->contains('~ $css["@mixinbuttons"][0]/{0}[7]/{1}[0] opacity:.8; opacity:.6;', $encoded);
        $t->true(!str_contains($encoded, '- $css["@mixinbuttons"] @mixinbuttons'), 'SCSS mixin default argument changes should not force a whole-mixin replacement.');
    },
    'maps upstream html style media sample without stable media churn' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-style-media-1.css');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-style-media-2.css');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'css']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('~ $css["body"][0] background-color:#f0f0f2; background-color:#fdfdff;', $encoded);
        $t->contains('+ $css["p"] p{color:#000;}', $encoded);
        $t->true(!str_contains($encoded, '$css["@media"]'), 'Stable nested @media rules from the upstream HTML style sample should stay matched.');
    },
    'maps upstream html sample style blocks as css sublanguage changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-1.html');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-2.html');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'html']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('+ $html.style[0].css["p"] p{color:#000;}', $encoded);
        $t->contains('~ $html.style[1].css["body"][0] background-color:#f0f0f2; background-color:#fdfdff;', $encoded);
        $t->contains('+ $html.style[1].css["#main"] #main{width:600px;', $encoded);
        $t->true(!str_contains($encoded, '$html.style.css["p"]'), 'Multiple upstream HTML style blocks should use indexed sub-language paths instead of one aggregate CSS stream.');
        $t->true(!str_contains($encoded, '$html.style[1].css["@media"]'), 'Stable CSS @media rules inside upstream HTML style blocks should stay matched as a sublanguage.');
    },
    'maps upstream html sample script blocks as javascript sublanguage changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-1.html');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-2.html');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'html']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('~ $html.script.js.call["alert"][0] \'welcome!\' "goodbye!"', $encoded);
    },
    'maps upstream html raw text only through css and javascript sublanguages' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-1.html');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-2.html');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'html']);
        $rawBodyChanges = array_values(array_filter($changes, static function (array $change): bool {
            if (!str_starts_with($change['path'], '$[')) {
                return false;
            }

            $text = ($change['text'] ?? '') . ' ' . ($change['old'] ?? '') . ' ' . ($change['new'] ?? '');

            return str_contains($text, 'background-color:#f0f0f2')
                || str_contains($text, 'background-color:#fdfdff')
                || str_contains($text, "('welcome!')")
                || str_contains($text, '("goodbye!")');
        }));
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->same([], $rawBodyChanges, 'HTML raw text bodies should not duplicate CSS/JavaScript sub-language diffs as root HTML list churn.');
        $t->contains('~ $html.style[1].css["body"][0] background-color:#f0f0f2; background-color:#fdfdff;', $encoded);
        $t->contains('~ $html.script.js.call["alert"][0] \'welcome!\' "goodbye!"', $encoded);
    },
    'maps upstream javascript simple sample with body and array statement alignment' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-javascript-simple-1.js');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-javascript-simple-2.js');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'javascript']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('+ $js.block["if"][0] if(true){foo();bar(2);baz();}', $encoded);
        $t->contains('~ $js.call["bar"][0] 1 2', $encoded);
        $t->contains('+ $js.array["people"][3] "yvonne"', $encoded);
        $t->true(!str_contains($encoded, '- $js.array["people"][3] "eric"'), 'Inserted array elements should not delete retained following items.');
    },
    'maps upstream javascript sample with named callback contexts' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-javascript-1.js');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-javascript-2.js');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'javascript']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('- $js.call["test"] test("Editing pages"', $encoded);
        $t->contains('+ $js.call["test"] test("/new POST"', $encoded);
        $t->contains('+ $js.call["describe"] describe("Viewing"', $encoded);
        $t->true(!str_contains($encoded, '~ $js.call["test"][0] "Editing pages" "/edit GET"'), 'Renamed Jest tests should not be paired only by the repeated test(...) callee.');
        $t->true(!str_contains($encoded, '+ $js.call["test"] test("/new GET",done=>{request(app).get("/new").auth("admin",ADMIN_PASSWORD)'), 'Stable Editing /new GET test should remain matched under its describe label.');
        $t->true(!str_contains($encoded, '+ $js.call["test"] test("/new POST",done=>{request(app).post("/new").type("form").send({name:"FooBar",content:"hello world"}).auth("admin",ADMIN_PASSWORD)'), 'Stable Editing /new POST test should remain matched under its describe label.');
    },
    'maps upstream load javascript excerpt with function scoped calls' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-load-functions-1.js');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-load-functions-2.js');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'javascript']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('- $js.function["createNodeModuleResource"].call["path.relative"] path.relative("node_modules",localPath)', $encoded);
        $t->contains('- $js.function["createNodeModuleResource"].call["models.Resource"] models.Resource({path:resourcePath,mimeType:MIME_TYPES[path.extname(localPath)],bootstrapPath:localPath})', $encoded);
        $t->true(!str_contains($encoded, '$js.call["functioncreateResource"]'), 'Function declarations should not be treated as ordinary calls.');
        $t->true(!str_contains($encoded, '$js.call["functioncreateNodeModuleResource"]'), 'Removed function declarations should not create fake call records.');
        $t->true(!str_contains($encoded, '~ $js.call["models.Resource"]'), 'Calls inside different function declarations should not be paired by callee name alone.');
    },
    'maps upstream typescript sample as a type member insertion' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-typescript-1.ts');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-typescript-2.ts');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'typescript']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('+ $ts.type["Symbol"][1] name:string;', $encoded);
        $t->true(!str_contains($encoded, '- $ts.type["Symbol"][1] items:string[];'), 'Inserted TypeScript members should not delete retained following members.');
    },
    'maps typescript module import and export lists as focused specifier changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/typescript-module-declarations-before.ts');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/typescript-module-declarations-after.ts');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'typescript']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('+ $ts.import["@wordpress/i18n"][1] sprintf', $encoded);
        $t->contains('+ $ts.import.type["@wordpress/blocks"] importtype{BlockConfiguration}from"@wordpress/blocks";', $encoded);
        $t->contains('+ $ts.export.local[2] deprecatedSave', $encoded);
        $t->contains('+ $ts.export.type["./types"][1] BlockContext', $encoded);
        $t->true(!str_contains($encoded, '- $ts.import["@wordpress/i18n"][0] __'), 'Retained import specifiers should stay aligned after a new specifier is inserted.');
        $t->true(!str_contains($encoded, '- $ts.export.local[1] save'), 'Retained local export specifiers should stay aligned after a new export is inserted.');
    },
    'maps typescript default namespace and re-export source changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/typescript-module-import-shapes-before.ts');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/typescript-module-import-shapes-after.ts');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'typescript']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('+ $ts.import["@wordpress/i18n"][1] sprintf', $encoded);
        $t->contains('+ $ts.import["./edit"][0] EditPreview', $encoded);
        $t->contains('~ $ts.import.namespace["@wordpress/block-editor"] blockEditor editor', $encoded);
        $t->contains('~ $ts.export.source["save"] "./save" "./frontend/save"', $encoded);
        $t->contains('~ $ts.export.type.source["BlockAttributes"] "./types" "./frontend/types"', $encoded);
        $t->true(!str_contains($encoded, '- $ts.import.default["./edit"] Edit'), 'Retained default imports should stay aligned when a named import is added.');
        $t->true(!str_contains($encoded, '- $ts.export["./save"]'), 'Re-export source changes should not delete the retained export specifier.');
    },
    'maps typescript export star and import attributes as module shapes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/typescript-module-attributes-before.ts');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/typescript-module-attributes-after.ts');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'typescript']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('+ $ts.import["./block.json"][0] supports', $encoded);
        $t->contains('~ $ts.import.attributes["./block.json"]/keyword assert with', $encoded);
        $t->contains('~ $ts.import.attributes["./view.js"][0] type:"javascript" type:"module"', $encoded);
        $t->contains('~ $ts.export.namespace["./icons"] icons blockIcons', $encoded);
        $t->contains('~ $ts.export.type.source["*"] "./types" "./frontend/types"', $encoded);
        $t->true(!str_contains($encoded, '- $ts.import.default["./block.json"] metadata'), 'Retained default imports should stay aligned when import attributes and named specifiers change.');
        $t->true(!str_contains($encoded, '$ts.export.star["./frontend"]'), 'Unchanged export-star declarations should stay out of the change stream.');
        $t->true(!str_contains($encoded, '- $ts.export.type.star["./types"]'), 'Export-star source changes should not delete the retained star shape.');
    },
    'maps typescript dynamic import attributes as module metadata' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-dynamic-metadata-before.ts');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-dynamic-metadata-after.ts');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'typescript']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('~ $ts.import.dynamic.attributes["./block.json"]/keyword assert with', $encoded);
        $t->contains('~ $ts.import.dynamic.attributes["./view.js"][0] type:"javascript" type:"module"', $encoded);
        $t->contains('+ $ts.import.dynamic.attributes["./supports.json"] with{type:"json"}', $encoded);
        $t->true(!str_contains($encoded, '- $js.call["import"][1]/{0}[0] assert'), 'Retained dynamic import attributes should not only render as broad JavaScript call argument churn.');
        $t->true(!str_contains($encoded, '+ $js.call["import"][1]/{0}[0] with'), 'Retained dynamic import attributes should use TypeScript module metadata paths.');
    },
    'maps upstream jsx sample as tag list changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-jsx-1.jsx');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-jsx-2.jsx');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'jsx']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('- $[0][0]/<0>[0] h1', $encoded);
        $t->contains('+ $[0][0]/<0>[0] h1className="title"', $encoded);
        $t->contains('+ $[0][0]/<1> <span>', $encoded);
        $t->contains('- $[1][0]/<0>[0] div', $encoded);
        $t->contains('+ $[1][0]/<0>[0] p', $encoded);
        $t->true(!str_contains($encoded, '$js.call["ReactDOM.render"]'), 'JSX tag changes should not collapse into one ReactDOM.render argument update.');
    },
    'maps upstream tsx whitespace sample as formatting only' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-whitespace-1.tsx');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-whitespace-2.tsx');
        $differ = new TokenDiffer();
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'tsx',
            'title' => 'Upstream TSX whitespace sample',
        ]);

        $t->same([], $differ->diffSyntaxLists($before, $after, ['language' => 'tsx']));
        $t->contains('No syntactic changes', $html);
    },
    'maps upstream json sample with object key alignment' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-json-1.json');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-json-2.json');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'json']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('- $[0][0]/[0][0] 1', $encoded);
        $t->contains('+ $[0][0]/[0][3] 5', $encoded);
        $t->contains('- $[0][1] "bar":"testing"', $encoded);
        $t->contains('+ $[0][1] "zab":"testing"', $encoded);
        $t->contains('+ $[0][2] "woo":["foobar"]', $encoded);
    },
    'maps upstream toml sample as table qualified key changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-toml-1.toml');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-toml-2.toml');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'toml']);
        $byPath = [];
        foreach ($changes as $change) {
            $byPath[$change['path']][] = $change;
        }

        $t->same('~', $byPath['$toml.title'][0]['op']);
        $t->same('"TOML Example"', $byPath['$toml.title'][0]['old']);
        $t->same('"TOML Example Changed"', $byPath['$toml.title'][0]['new']);
        $t->same('~', $byPath['$toml.owner.dob'][0]['op']);
        $t->same('1979-05-27T07:32:00-08:00', $byPath['$toml.owner.dob'][0]['old']);
        $t->same('2000-01-31T07:32:00-08:00', $byPath['$toml.owner.dob'][0]['new']);
        $t->same('-', $byPath['$toml.database.ports[1]'][0]['op']);
        $t->same('8001', $byPath['$toml.database.ports[1]'][0]['text']);
        $t->same('-', $byPath['$toml.servers.beta.str2'][0]['op']);
        $t->contains('str2 = """\\', $byPath['$toml.servers.beta.str2'][0]['text']);
        $t->same('-', $byPath['$toml.servers.beta.path'][0]['op']);
        $t->same("path = 'C:\\Users\\nodejs\\templates'", $byPath['$toml.servers.beta.path'][0]['text']);
        $t->true(!isset($byPath['$toml.database.data']), 'Stable nested TOML arrays should stay matched.');
    },
    'wordpress plugin toml config reports release and playground changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-toml-config-before.toml');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-toml-config-after.toml');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'toml']);
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'toml',
            'title' => 'WordPress plugin TOML config diff',
        ]);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('~ $toml.requires_wp "6.5" "6.6"', $encoded);
        $t->contains('- $toml.build.targets[2] "legacy"', $encoded);
        $t->contains('+ $toml.build.targets[2] "view"', $encoded);
        $t->contains('~ $toml.playground.php "8.2" "8.3"', $encoded);
        $t->contains('- $toml.playground.plugins[1] "query-monitor"', $encoded);
        $t->contains('+ $toml.playground.plugins[1] "wordpress-importer"', $encoded);
        $t->contains('~ $toml.playground.notes """', $encoded);
        $t->contains('Review legacy card markup', $encoded);
        $t->contains('Review modern card markup', $encoded);
        $t->contains('WordPress plugin TOML config diff', $html);
        $t->contains('data-path="$toml.build.targets[2]"', $html);
        $t->true(!str_contains($html, '$text.line'), 'TOML config review should use TOML key paths rather than line fallback paths.');
    },
    'wordpress plugin toml example emits escaped syntax-list html' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-plugin-toml-config-diff.php';
        $output = ob_get_clean();

        $t->true(is_string($output));
        $t->contains('WordPress plugin TOML config diff', (string) $output);
        $t->contains('data-path="$toml.requires_wp"', (string) $output);
        $t->contains('&quot;wordpress-importer&quot;', (string) $output);
        $t->true(!str_contains((string) $output, '<script'), 'The TOML example should render escaped syntax-list HTML only.');
    },
    'maps toml inline tables as nested key changes' => static function (TestRunner $t): void {
        $before = "temp_targets = { cpu = 79.5, case = 72.0 }\n";
        $after = "temp_targets = { cpu = 82.0, case = 72.0, gpu = 61.0 }\n";
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'toml']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('~ $toml.temp_targets.cpu 79.5 82.0', $encoded);
        $t->contains('+ $toml.temp_targets.gpu gpu = 61.0', $encoded);
        $t->true(!str_contains($encoded, '$toml.temp_targets {'), 'Inline table field edits should not replace the whole TOML table value.');
        $t->true(!str_contains($encoded, '$toml.temp_targets.case'), 'Stable inline table fields should stay matched.');

        $emptyChanges = (new TokenDiffer())->diffSyntaxLists("settings = {}\n", "settings = { enabled = true }\n", ['language' => 'toml']);
        $t->same('$toml.settings', $emptyChanges[0]['path']);
        $t->same('$toml.settings.enabled', $emptyChanges[1]['path']);
    },
    'wordpress plugin toml arrays of tables keep release entries indexed' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-release-matrix-before.toml');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-release-matrix-after.toml');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'toml']);
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'toml',
            'title' => 'WordPress plugin release matrix TOML diff',
        ]);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('~ $toml.plugins[1].slug "query-monitor" "wordpress-importer"', $encoded);
        $t->contains('~ $toml.plugins[1].status "dev" "required"', $encoded);
        $t->contains('~ $toml.plugins[1].config.autoload false true', $encoded);
        $t->contains('~ $toml.plugins[1].config.review "optional" "migration"', $encoded);
        $t->contains('~ $toml.playground.blueprint.php "8.2" "8.3"', $encoded);
        $t->contains('- $toml.playground.blueprint.plugins[1] "query-monitor"', $encoded);
        $t->contains('+ $toml.playground.blueprint.plugins[1] "wordpress-importer"', $encoded);
        $t->contains('data-path="$toml.plugins[1].config.autoload"', $html);
        $t->true(!str_contains($encoded, '$toml.plugins.slug'), 'Array table entries should include the table index in TOML paths.');
    },
    'wordpress plugin release matrix toml example emits escaped syntax-list html' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-plugin-release-matrix-toml-diff.php';
        $output = ob_get_clean();

        $t->true(is_string($output));
        $t->contains('WordPress plugin release matrix TOML diff', (string) $output);
        $t->contains('data-path="$toml.plugins[1].config.autoload"', (string) $output);
        $t->contains('&quot;wordpress-importer&quot;', (string) $output);
        $t->true(!str_contains((string) $output, '<script'), 'The release matrix example should render escaped syntax-list HTML only.');
    },
    'maps upstream slider at end json sample as focused list deletions' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-slider-at-end-1.json');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-slider-at-end-2.json');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'json']);
        $deletions = array_values(array_map(
            static fn (array $change): string => $change['path'] . ' ' . ($change['text'] ?? ''),
            array_filter($changes, static fn (array $change): bool => $change['op'] === '-')
        ));

        $t->same(['$[0][1] "novel-1"', '$[0][3] "novel-2"'], $deletions);
    },
    'maps upstream nested slider rust sample as wrapper insertions' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-nested-slider-1.rs');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-nested-slider-2.rs');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after);
        $insertions = array_values(array_map(
            static fn (array $change): string => $change['path'] . ' ' . ($change['text'] ?? ''),
            array_filter($changes, static fn (array $change): bool => $change['op'] === '+')
        ));
        $deletions = array_values(array_filter($changes, static fn (array $change): bool => $change['op'] === '-'));

        $t->same(['$[1][0]/{0}[0]/wrap0 ifpad_last{...}', '$[3][0]/(0)[0]/wrap0 bar(...)'], $insertions);
        $t->same([], $deletions, 'Nested slider correction should keep the retained x node stable.');
    },
    'maps upstream nested slider elisp sample as outer wrapper deletion' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-nested-slider-1.el');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-nested-slider-2.el');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'elisp']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('- $[0][0]/(1)[0]/(1)[0]/(0)[0]/wrap1 -when-let(roots(project-rootsproject))(...)', $encoded);
        $t->contains('- $[0][0]/(1)[0]/(1)[0]/(0)[0]/wrap1[0]/(0)[0] carroots', $encoded);
        $t->contains('+ $[0][0]/(1)[0]/(1)[0]/(0)[0]/wrap1[0]/(0)[0] project-rootproject', $encoded);
        $t->true(!str_contains($encoded, '-when-let(roots(project-rootsproject))(setqroot(carroots))'), 'Outer wrapper deletion should not swallow the retained setq form.');
    },
    'maps upstream change outer elisp sample as delimiter and wrapper changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-change-outer-1.el');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-change-outer-2.el');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'elisp']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('~ $[0]/delimiters () []', $encoded);
        $t->contains('+ $[0]/wrap0 (...)', $encoded);
        $t->true(!str_contains($encoded, '(lhscommarhs) [(lhs)commarhs]'), 'Changed outer delimiters should not replace the entire retained list body.');
    },
    'tokenizes emacs lisp reader quotes and semicolon comments separately' => static function (TestRunner $t): void {
        $tokens = (new TokenDiffer())->tokenize("'(;; upstream note\n  \"const\" \"return\")", ['language' => 'elisp']);
        $encoded = implode("\n", array_map(
            static fn ($token): string => $token->kind . ' ' . $token->text,
            $tokens
        ));

        $t->contains('punctuation \'', $encoded);
        $t->contains('comment ;; upstream note', $encoded);
        $t->contains('string "const"', $encoded);
        $t->contains('string "return"', $encoded);
    },
    'maps upstream strings elisp sample as focused quoted list changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-strings-keywords-1.el');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-strings-keywords-2.el');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'elisp']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('+ $[0][0]/(0)[0]/(0)[0] ;; This is the list of keywords from full_fidelity_lexer.ml, but', $encoded);
        $t->contains('+ $[0][0]/(0)[0]/(0)[19] "elseif"', $encoded);
        $t->contains('- $[0][0]/(0)[0]/(0)[92] "__COMPILER_FRONTEND__"', $encoded);
        $t->true(!str_contains($encoded, 'regexp-opt'), 'The upstream strings fixture should descend into the quoted keyword list instead of replacing the whole regexp-opt form.');
    },
    'maps upstream slider rust sample excerpt with method and statement sliders' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-slider-methods-1.rs');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-slider-methods-2.rs');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'rust']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('+ $[0][0] fncontainer_sequence_header', $encoded);
        $t->true(!str_contains($encoded, 'fncontainer_sequence_header(&self)->Vec<u8>{matchself{Context::Eight(refcontext)=>context.container_sequence_header(),Context::Sixteen(refcontext)=>context.container_sequence_header()}}fnreceive_packet'), 'Inserted method should not swallow the retained receive_packet method.');
        $t->contains('+ $[1][1]/{0}[2] letcontext=ifvideo_info', $encoded);
        $t->contains('+ $[1][1]/{0}[3] letcontainer_sequence_header=', $encoded);
        $t->contains('- $[1][1]/{0}[2]/(2)[0]/{0}[0] context:ifvideo_info', $encoded);
        $t->contains('+ $[1][1]/{0}[2]/(2)[0]/{0}[0] context', $encoded);
        $t->contains('+ $[1][1]/{0}[3]/(0)[0]/(3)[0] "codec_data"', $encoded);
    },
    'maps upstream hack sample return type and vec insertion' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-hack-1.php');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-hack-2.php');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'hack']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('~ $php.function.foo.return_type vec<int> vec<?int>', $encoded);
        $t->contains('+ $[1][0]/[0][1] null', $encoded);
    },
    'splits words like upstream words rs' => static function (TestRunner $t): void {
        $differ = new TokenDiffer();

        $t->same(['example', '.', 'com'], $differ->splitWords('example.com'));
        $t->same(['example', '.', '.'], $differ->splitWords('example..'));
        $t->same(['foo123bar'], $differ->splitWords('foo123bar'));
        $t->same(['example', '.', "\n", 'com'], $differ->splitWords("example.\ncom"));
        $t->same(['a', ' ', 'ö', ' ', 'b'], $differ->splitWords('a ö b'));
        $t->same(['a', ' ', '💝', ' ', 'b'], $differ->splitWords('a 💝 b'));
        $t->same(['a', ' ', 'xöy', ' ', 'b'], $differ->splitWords('a xöy b'));
    },
    'splits words and numbers like upstream words rs' => static function (TestRunner $t): void {
        $differ = new TokenDiffer();

        $t->same(['a', '123', 'b'], $differ->splitWordsAndNumbers('a123b'));
        $t->same(['foo', ' ', 'bar'], $differ->splitWordsAndNumbers('foo bar'));
    },
    'maps upstream hyphen subwords fixture as a focused deletion' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-hyphen-subwords-1.json');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-hyphen-subwords-2.json');
        $deletions = array_values(array_map(
            static fn (array $op): string => $op['text'],
            array_filter((new TokenDiffer())->diffWords($before, $after, ['splitNumbers' => true]), static fn (array $op): bool => $op['op'] === '-')
        ));

        $t->same(['foo', '-'], $deletions);
    },
    'maps upstream contiguous sample as syntax list insertions' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-contiguous-1.js');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-contiguous-2.js');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['ignoreComments' => true]);

        $insertions = array_values(array_filter($changes, static fn (array $change): bool => $change['op'] === '+'));
        $t->same('$[0][2]', $insertions[0]['path']);
        $t->same('"A"', $insertions[0]['text']);
        $t->same('$[0][3]', $insertions[1]['path']);
        $t->same('"B"', $insertions[1]['text']);
    },
    'maps upstream added line text sample as a line insertion' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-added-line-1.txt');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-added-line-2.txt');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'text']);
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'text',
            'title' => 'Upstream added line text sample',
        ]);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('+ $text.line[2] legato', $encoded);
        $t->true(!str_contains($encoded, '$text.fallback'), 'Plain text mode should use line parser output without a fallback marker.');
        $t->contains('data-path="$text.line[2]"', $html);
        $t->true(!str_contains($html, 'No syntactic changes'), 'Plain text syntax-list rendering should not hide text-only changes.');
    },
    'maps upstream insert blank text sample as a display change' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-insert-blank-1.txt');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-insert-blank-2.txt');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'text']);
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/insert_blank.txt',
            'Text',
            ['language' => 'text'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same([['op' => '-', 'path' => '$text.line[1]', 'text' => '']], $changes);
        $t->same('changed', $decoded['status']);
        $t->same([1, null], $decoded['aligned_lines'][1]);
        $t->same(1, $decoded['chunks'][0][0]['lhs']['line_number']);
        $t->same([], $decoded['chunks'][0][0]['lhs']['changes']);
    },
    'maps upstream align footer text sample without marking footer or unchanged rhs novel' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-align-footer-1.txt');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-align-footer-2.txt');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'text']);
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/align_footer.txt',
            'Text',
            ['language' => 'text'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same([
            ['op' => '~', 'path' => '$text.line[1]', 'old' => ' foo x', 'new' => ' x'],
            ['op' => '-', 'path' => '$text.line[2]', 'text' => 'y'],
        ], $changes);
        $t->same([[0, 0], [1, 1], [2, null], [3, 2], [4, 3]], $decoded['aligned_lines']);
        $t->same('foo', $decoded['chunks'][0][0]['lhs']['changes'][0]['content']);
        $t->same([], $decoded['chunks'][0][0]['rhs']['changes']);
        $t->same(2, $decoded['chunks'][0][1]['lhs']['line_number']);
        $t->true(!isset($decoded['chunks'][0][1]['rhs']), 'Deleted text line should not fabricate an opposite-side display entry.');
    },
    'maps upstream cli changes at end text fixture without losing eof context' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-changes-at-end-1.txt');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-changes-at-end-2.txt');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'text']);
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/cli_tests/changes_at_end.txt',
            'Text',
            ['language' => 'text'],
        ), true, 512, JSON_THROW_ON_ERROR);
        $lastAligned = $decoded['aligned_lines'][array_key_last($decoded['aligned_lines'])];
        $chunk = $decoded['chunks'][0];
        $lastChunkLine = $chunk[array_key_last($chunk)];
        $encodedChunks = json_encode($decoded['chunks'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $t->same(30, count($changes));
        $t->same('~', $changes[0]['op']);
        $t->same('$text.line[0]', $changes[0]['path']);
        $t->same('              TOKEN_PATH@129..133 "/d6/"', $changes[0]['old']);
        $t->same('                    TOKEN_INTEGER@125..127 "77"', $changes[0]['new']);
        $t->same('+', $changes[29]['op']);
        $t->same('$text.line[29]', $changes[29]['path']);
        $t->same('                          TOKEN_PATH@152..155 "/Aa"', $changes[29]['text']);
        $t->same([21, 30], $lastAligned);
        $t->same(29, $lastChunkLine['rhs']['line_number']);
        $t->true(!str_contains($encodedChunks, '"line_number":30'), 'The retained terminal EOF context should stay aligned, not appear as novel chunk content.');
    },
    'maps upstream text sample as one nearby json hunk' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-text-1.txt');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-text-2.txt');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'text']);
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/text.txt',
            'Text',
            ['language' => 'text'],
        ), true, 512, JSON_THROW_ON_ERROR);
        $encodedChunks = json_encode($decoded['chunks'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $t->same(3, count($changes));
        $t->same('+', $changes[0]['op']);
        $t->same('$text.line[1]', $changes[0]['path']);
        $t->same('~', $changes[1]['op']);
        $t->same('$text.line[3]', $changes[1]['path']);
        $t->same(1, count($decoded['chunks']));
        $t->same(3, count($decoded['chunks'][0]));
        $t->same(1, $decoded['chunks'][0][0]['rhs']['line_number']);
        $t->same(3, $decoded['chunks'][0][1]['lhs']['line_number']);
        $t->contains('novel', $encodedChunks);
        $t->contains('bar', $encodedChunks);
        $t->true(!str_contains($encodedChunks, '"content":"world"'), 'Short retained context should merge nearby text changes without appearing as novel JSON content.');
    },
    'maps upstream big text hunk sample as one dense insertion hunk' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-big-text-hunk-1.txt');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-big-text-hunk-2.txt');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'text']);
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/big_text_hunk.txt',
            'Text',
            ['language' => 'text'],
        ), true, 512, JSON_THROW_ON_ERROR);
        $insertions = array_values(array_filter($changes, static fn (array $change): bool => $change['op'] === '+'));

        $t->same(42, count($changes));
        $t->same(42, count($insertions));
        $t->same('$text.line[7]', $insertions[0]['path']);
        $t->same('$text.line[48]', $insertions[41]['path']);
        $t->same('golang.org/x/text v0.3.0/go.mod h1:NqM8EUOU14njkJ3fqMW+pc6Ldnwhi/IjpwHt7yyuwOQ=', $insertions[41]['text']);
        $t->same('changed', $decoded['status']);
        $t->same(1, count($decoded['chunks']));
        $t->same(42, count($decoded['chunks'][0]));
        $t->same(7, $decoded['chunks'][0][0]['rhs']['line_number']);
        $t->same(48, $decoded['chunks'][0][41]['rhs']['line_number']);
        $t->true(!isset($decoded['chunks'][0][0]['lhs']), 'Inserted dense text hunk lines should not fabricate an opposite-side display entry.');
    },
    'wordpress readme nearby text hunks are grouped for review' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-nearby-hunks-before.txt');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-nearby-hunks-after.txt');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-review-tools/readme.txt',
            'Text',
            ['language' => 'text'],
        ), true, 512, JSON_THROW_ON_ERROR);
        $encodedChunks = json_encode($decoded['chunks'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $t->same('changed', $decoded['status']);
        $t->same(1, count($decoded['chunks']));
        $t->same(2, $decoded['chunks'][0][0]['rhs']['line_number']);
        $t->same(5, $decoded['chunks'][0][1]['lhs']['line_number']);
        $t->contains('Requires', $encodedChunks);
        $t->contains('legacy', $encodedChunks);
        $t->contains('modern', $encodedChunks);
        $t->true(!str_contains($encodedChunks, 'Stable tag'), 'Retained readme context should group nearby changes without appearing as changed content.');
    },
    'wordpress readme footer alignment display keeps faq footer unchanged' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-footer-before.txt');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-footer-after.txt');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-review-tools/readme.txt',
            'Text',
            ['language' => 'text'],
        ), true, 512, JSON_THROW_ON_ERROR);
        $encodedChunks = json_encode($decoded['chunks'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $t->same('changed', $decoded['status']);
        $t->same([3, 2], $decoded['aligned_lines'][3]);
        $t->same('legacy', $decoded['chunks'][0][0]['lhs']['changes'][0]['content']);
        $t->same('modern', $decoded['chunks'][0][0]['rhs']['changes'][0]['content']);
        $t->true(!str_contains($encodedChunks, 'Frequently Asked Questions'), 'Stable readme footer heading should stay aligned as context, not novel chunk content.');
    },
    'wordpress readme end changes display preserves terminal context' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-end-changes-before.txt');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-end-changes-after.txt');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-end-review/readme.txt',
            'Text',
            ['language' => 'text'],
        ), true, 512, JSON_THROW_ON_ERROR);
        $lastAligned = $decoded['aligned_lines'][array_key_last($decoded['aligned_lines'])];
        $encodedChunks = json_encode($decoded['chunks'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $t->same('changed', $decoded['status']);
        $t->same('wp-content/plugins/acme-end-review/readme.txt', $decoded['path']);
        $t->same([8, 9], $lastAligned);
        $t->contains('legacy', $encodedChunks);
        $t->contains('modern', $encodedChunks);
        $t->contains('"content":"Add"', $encodedChunks);
        $t->contains('"content":"bindings"', $encodedChunks);
        $t->contains('"content":"audit"', $encodedChunks);
        $t->true(!str_contains($encodedChunks, '"line_number":9'), 'The final EOF context line should not be emitted as a changed readme chunk.');
    },
    'maps upstream split on newlines trailing eof behavior' => static function (TestRunner $t): void {
        $changes = (new TokenDiffer())->diffSyntaxLists('abc', "abc\n", ['language' => 'text']);

        $t->same([['op' => '+', 'path' => '$text.line[1]', 'text' => '']], $changes);
    },
    'maps upstream many newlines empty lhs shape as created status' => static function (TestRunner $t): void {
        $after = "Name: res/drawable-hdpi/com_facebook_tooltip_blue_topnub.png\n"
            . "SHA1-Digest: rQJiOcIwwhKZTBdd1spU/vsYtYk=\n\n"
            . "Name: res/drawable-hdpi/abc_list_divider_mtrl_alpha.9.png\n"
            . "SHA1-Digest: 2rDL6SgURlRMBXTSLtkL8kMQ6Xc=\n";
        $changes = (new TokenDiffer())->diffSyntaxLists('', $after, ['language' => 'text']);
        $decoded = (new JsonDiffRenderer())->fileDiff(
            '',
            $after,
            'sample_files/many_newlines.txt',
            'Text',
            ['language' => 'text'],
        );

        $t->same(['+', '+', '+', '+', '+', '+'], array_map(static fn (array $change): string => $change['op'], $changes));
        $t->same('$text.line[0]', $changes[0]['path']);
        $t->same('Name: res/drawable-hdpi/com_facebook_tooltip_blue_topnub.png', $changes[0]['text']);
        $t->same('$text.line[5]', $changes[5]['path']);
        $t->same('', $changes[5]['text']);
        $t->same(['language' => 'Text', 'path' => 'sample_files/many_newlines.txt', 'status' => 'created'], $decoded);
    },
    'maps upstream repeated line no eol sample as an eof insertion' => static function (TestRunner $t): void {
        $before = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-repeated-line-no-eol-1.hex')));
        $after = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-repeated-line-no-eol-2.hex')));
        $changes = (new TokenDiffer())->diffSyntaxLists((string) $before, (string) $after, ['language' => 'text']);
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            (string) $before,
            (string) $after,
            'sample_files/repeated_line_no_eol.txt',
            'Text',
            ['language' => 'text'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same([['op' => '+', 'path' => '$text.line[1]', 'text' => 'abc']], $changes);
        $t->same('changed', $decoded['status']);
        $t->same([[0, 0], [null, 1]], $decoded['aligned_lines']);
        $t->same(1, $decoded['chunks'][0][0]['rhs']['line_number']);
        $t->same([['start' => 0, 'end' => 3, 'content' => 'abc', 'highlight' => 'normal']], $decoded['chunks'][0][0]['rhs']['changes']);
    },
    'wordpress import log no eol display preserves appended final line' => static function (TestRunner $t): void {
        $before = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-log-no-eol-before.hex')));
        $after = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-log-no-eol-after.hex')));
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            (string) $before,
            (string) $after,
            'wp-content/uploads/migration/import.log',
            'Text',
            ['language' => 'text'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same('changed', $decoded['status']);
        $t->same('wp-content/uploads/migration/import.log', $decoded['path']);
        $t->same([[0, 0], [null, 1]], $decoded['aligned_lines']);
        $t->same(1, $decoded['chunks'][0][0]['rhs']['line_number']);
        $t->same('Imported', $decoded['chunks'][0][0]['rhs']['changes'][0]['content']);
        $t->same('normal', $decoded['chunks'][0][0]['rhs']['changes'][0]['highlight']);
    },
    'wordpress created import report uses created status and pure text insertions' => static function (TestRunner $t): void {
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-created-import-report-after.txt');
        $changes = (new TokenDiffer())->diffSyntaxLists('', $after, ['language' => 'text']);
        $decoded = (new JsonDiffRenderer())->fileDiff(
            '',
            $after,
            'wp-content/uploads/migration/import-report.csv',
            'Text',
            ['language' => 'text'],
        );
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? ''),
            $changes,
        ));

        $t->same(['language' => 'Text', 'path' => 'wp-content/uploads/migration/import-report.csv', 'status' => 'created'], $decoded);
        $t->same(5, count($changes));
        $t->same(['+', '+', '+', '+', '+'], array_map(static fn (array $change): string => $change['op'], $changes));
        $t->contains('+ $text.line[3] 44,queued,Needs media sideload retry', $encoded);
        $t->true(!str_contains($encoded, '~ $text.line[0]'), 'Created text files should not pair the first real line with a synthetic empty old line.');
    },
    'maps upstream cli makefile text as syntax atom' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-cli-makefile-1.mk');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-cli-makefile-2.mk');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'makefile']);
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'makefile',
            'title' => 'Upstream Makefile text atom diff',
        ]);

        $t->same([[
            'op' => '~',
            'path' => '$make.text[0]',
            'old' => 'CCFLAGS+=-std=c99 -D_DEFAULT_SOURCE -DVERSION=\"$(VERS)\" -O2 -Wall -Werror -D_FORTIFY_SOURCE=2 -fstack-protector-all $(CFLAGS) -g',
            'new' => 'CCFLAGS+=-std=c99 -D_DEFAULT_SOURCE -DVERSION=\"$(VERS)\" -O2 -D_FORTIFY_SOURCE=2 -fstack-protector-all $(CFLAGS) -g',
        ]], $changes);
        $t->contains('CCFLAGS', $html);
        $t->contains('-Wall -Werror', $html);
        $t->true(!str_contains($html, 'No syntactic changes'), 'Makefile text atoms should be visible in syntax-list rendering.');
    },
    'wordpress plugin build makefile diff reports flag and asset changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-build-makefile-before.mk');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-build-makefile-after.mk');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'makefile',
            'title' => 'Plugin build Makefile text-atom diff',
        ]);

        $t->contains('Plugin build Makefile text-atom diff', $html);
        $t->contains('data-path="$make.text[1]"', $html);
        $t->contains('-Werror', $html);
        $t->contains('-D_FORTIFY_SOURCE=2', $html);
        $t->contains('data-path="$make.text[2]"', $html);
        $t->contains('build/view.js', $html);
        $t->true(!str_contains($html, 'data-path="$text.line'), 'Makefile mode should use make-specific text atom paths, not generic text fallback paths.');
    },
    'maps upstream tab display style helpers with fixed-width expansion and wrapping' => static function (TestRunner $t): void {
        $renderer = new SideBySideDiffRenderer();

        $t->same(11, $renderer->displayWidth("\tfoo", 8));
        $t->same(['ab    ', 'cd    '], $renderer->splitLineForDisplay("ab\tcd", 6, 4, 'left'));
        $t->same(['ab    ', 'cd'], $renderer->splitLineForDisplay("ab\tcd", 6, 4, 'right'));
        $t->throws(InvalidArgumentException::class, static fn (): array => $renderer->splitLineForDisplay("\t", 4, 4));
    },
    'maps upstream unicode display width helpers for wrapped long lines' => static function (TestRunner $t): void {
        $renderer = new SideBySideDiffRenderer();

        $t->same(2, $renderer->displayWidth('📦', 2));
        $t->same(0, $renderer->displayWidth("\u{200d}", 2));
        $t->same(['ab📦', 'def '], $renderer->splitLineForDisplay('ab📦def', 4, 2, 'left'));
        $t->same(["aabbcc\u{300}", 'x     '], $renderer->splitLineForDisplay("aabbcc\u{300}x", 6, 2, 'left'));
        $t->same(['一个汉字', '两列宽  '], $renderer->splitLineForDisplay('一个汉字两列宽', 8, 2, 'left'));
    },
    'maps upstream tab text sample without emitting raw tabs in side by side display' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-tab-text-1.txt');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-tab-text-2.txt');
        $display = (new SideBySideDiffRenderer())->renderTextDiff($before, $after, [
            'tabWidth' => 8,
            'columnWidth' => 96,
        ]);

        $t->contains('        env.VERCEL_ENV === "production"', $display);
        $t->contains('                ? "https://alpha.sweets.community"', $display);
        $t->contains('                        ? `https://${env.VERCEL_URL}`', $display);
        $t->true(!str_contains($display, "\t"), 'Difftastic side-by-side display replaces tab characters before rendering lines.');
        $t->contains('. ', $display);
    },
    'maps upstream tab c sample with configurable tab width' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-tab-c-1.c');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-tab-c-2.c');
        $display = (new SideBySideDiffRenderer())->renderTextDiff($before, $after, [
            'tabWidth' => 4,
            'columnWidth' => 48,
        ]);

        $t->contains('    printf("Hello World");', $display);
        $t->contains('    printf("Goodbye World");', $display);
        $t->contains('    return 0;', $display);
        $t->true(!str_contains($display, "\t"), 'Configured tab width should be applied to both changed and retained C lines.');
    },
    'maps upstream side by side novel spans with ansi colors' => static function (TestRunner $t): void {
        $renderer = new SideBySideDiffRenderer();
        $display = $renderer->renderTextDiff(
            "render_block('legacy-card', \$attrs);\n",
            "render_block('modern-card', \$attrs);\n",
            [
                'columnWidth' => 48,
                'useColor' => true,
            ],
        );
        $novelOnly = $renderer->renderTextDiff(
            "render_block('legacy-card', \$attrs);\n",
            "render_block('modern-card', \$attrs);\n",
            [
                'columnWidth' => 48,
                'useColor' => true,
                'syntaxHighlight' => false,
            ],
        );

        $t->contains("\033[1;91m1 \033[0m", $display);
        $t->contains("\033[1;92m1 \033[0m", $display);
        $t->contains("\033[95m'\033[0m\033[1;91mlegacy\033[0m\033[95m-card'\033[0m", $display);
        $t->contains("\033[95m'\033[0m\033[1;92mmodern\033[0m\033[95m-card'\033[0m", $display);
        $t->contains("'\033[1;91mlegacy\033[0m-card'", $novelOnly);
        $t->contains("'\033[1;92mmodern\033[0m-card'", $novelOnly);
        $t->true(!str_contains($display, "\033[1;91mrender_block"), 'Stable source before the novel word should not be colored as deleted.');
        $t->true(!str_contains($display, "\033[1;92m-card"), 'Stable suffix after the novel word should not be colored as inserted.');
    },
    'wordpress highlighted side by side review colors only changed copy' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-footer-before.txt');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-footer-after.txt');
        $display = (new SideBySideDiffRenderer())->renderTextDiff($before, $after, [
            'columnWidth' => 56,
            'contextLines' => 1,
            'useColor' => true,
        ]);

        $t->contains("\033[1;91mlegacy\033[0m", $display);
        $t->contains("\033[1;92mmodern\033[0m", $display);
        $t->contains('Frequently Asked Questions', $display);
        $t->true(!str_contains($display, "\033[1;92mFrequently"), 'Stable readme footer context should remain uncolored in highlighted side-by-side output.');
    },
    'maps upstream side by side default context lines' => static function (TestRunner $t): void {
        $beforeLines = array_map(static fn (int $line): string => 'stable-' . str_pad((string) $line, 2, '0', STR_PAD_LEFT), range(1, 20));
        $afterLines = $beforeLines;
        $afterLines[9] = 'changed-10';
        $before = implode("\n", $beforeLines) . "\n";
        $after = implode("\n", $afterLines) . "\n";
        $display = (new SideBySideDiffRenderer())->renderTextDiff($before, $after, [
            'columnWidth' => 40,
        ]);

        $t->contains('stable-07', $display);
        $t->contains('stable-13', $display);
        $t->contains('changed-10', $display);
        $t->true(!str_contains($display, 'stable-05'), 'Default side-by-side display should omit context before the three-line window.');
        $t->true(!str_contains($display, 'stable-15'), 'Default side-by-side display should omit context after the three-line window.');
    },
    'maps upstream side by side context hunks with separators' => static function (TestRunner $t): void {
        $beforeLines = array_map(static fn (int $line): string => 'stable-' . str_pad((string) $line, 2, '0', STR_PAD_LEFT), range(1, 22));
        $afterLines = $beforeLines;
        $afterLines[5] = 'changed-alpha';
        $afterLines[17] = 'changed-beta';
        $before = implode("\n", $beforeLines) . "\n";
        $after = implode("\n", $afterLines) . "\n";
        $display = (new SideBySideDiffRenderer())->renderTextDiff($before, $after, [
            'columnWidth' => 32,
            'contextLines' => 1,
        ]);

        $t->contains('changed-alpha', $display);
        $t->contains('changed-beta', $display);
        $t->contains('stable-05', $display);
        $t->contains('stable-19', $display);
        $t->contains(' ...', $display);
        $t->true(!str_contains($display, 'stable-11'), 'Distant unchanged lines between hunk windows should be elided.');
    },
    'maps upstream context rust sample through side by side display' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-context-1.rs');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-context-2.rs');
        $display = (new SideBySideDiffRenderer())->renderTextDiff($before, $after, [
            'columnWidth' => 56,
            'contextLines' => 1,
        ]);

        $t->contains('match ()', $display);
        $t->contains('let opposite_to_lhs', $display);
        $t->contains('let lang_name;', $display);
        $t->true(!str_contains($display, 'No syntactic changes'), 'The upstream context fixture should remain a visible side-by-side change.');
    },
    'maps upstream inline display headers and context windows' => static function (TestRunner $t): void {
        $beforeLines = array_map(static fn (int $line): string => 'stable-' . str_pad((string) $line, 2, '0', STR_PAD_LEFT), range(1, 22));
        $afterLines = $beforeLines;
        $afterLines[5] = 'changed-alpha';
        $afterLines[17] = 'changed-beta';
        $before = implode("\n", $beforeLines) . "\n";
        $after = implode("\n", $afterLines) . "\n";
        $display = (new InlineDiffRenderer())->renderTextDiff($before, $after, [
            'path' => 'wp-content/plugins/acme-card/patterns.php',
            'language' => 'php',
            'contextLines' => 1,
        ]);

        $t->contains('wp-content/plugins/acme-card/patterns.php --- 1/2 --- PHP', $display);
        $t->contains('wp-content/plugins/acme-card/patterns.php --- 2/2 --- PHP', $display);
        $t->contains('5    stable-05', $display);
        $t->contains('6    stable-06', $display);
        $t->contains('   6 changed-alpha', $display);
        $t->contains('   7 stable-07', $display);
        $t->contains('18    stable-18', $display);
        $t->contains('   18 changed-beta', $display);
        $t->true(!str_contains($display, 'stable-11'), 'Inline display should omit distant unchanged lines outside the context window.');
    },
    'maps upstream inline header extra info and color styling' => static function (TestRunner $t): void {
        $display = (new InlineDiffRenderer())->renderTextDiff(
            "label:\tlegacy\n",
            "label:\tmodern\n",
            [
                'path' => 'wp-content/plugins/acme-card/readme.txt',
                'language' => 'text',
                'extraInfo' => 'Renamed from readme-old.txt to readme.txt',
                'tabWidth' => 4,
                'useColor' => true,
            ],
        );

        $t->contains("\033[1;93mwp-content/plugins/acme-card/readme.txt\033[0m\033[2m --- Text\033[0m", $display);
        $t->contains("\033[2mRenamed from readme-old.txt to readme.txt\033[0m", $display);
        $t->contains("\033[1;91m1 \033[0m   label:    legacy", $display);
        $t->contains("   \033[1;92m1 \033[0mlabel:    modern", $display);
        $t->true(!str_contains($display, "\t"), 'Inline display replaces tabs before rendering lines.');
    },
    'maps upstream git style new file arguments without permission warning' => static function (TestRunner $t): void {
        $arguments = [
            'simple.txt',
            '/dev/null',
            '.',
            '.',
            'sample_files/simple_1.txt',
            'abcdef1234',
            '100644',
        ];
        $metadata = GitExternalDiffMetadata::fromArguments($arguments);
        $display = (new InlineDiffRenderer())->renderGitExternalTextDiff('', "alpha\n", $arguments, [
            'language' => 'text',
        ]);

        $t->same('simple.txt', $metadata->displayPath);
        $t->same(null, $metadata->extraInfo);
        $t->contains('simple.txt --- Text', $display);
        $t->contains('   1 alpha', $display);
        $t->true(!str_contains($display, 'File permissions changed'), 'A Git new-file . mode should not be reported as a permission change.');
    },
    'maps upstream git style rename arguments into inline extra info' => static function (TestRunner $t): void {
        $arguments = [
            'elisp_oldname.el',
            'sample_files/elisp_1.el',
            'lhs_hash_placeholder',
            'lhs_mode_placeholder',
            'sample_files/elisp_2.el',
            'rhs_hash_placeholder',
            'rhs_mode_placeholder',
            'elisp_newname.el',
            'similarity_placeholder',
        ];
        $display = (new InlineDiffRenderer())->renderGitExternalTextDiff(
            "(message \"legacy\")\n",
            "(message \"modern\")\n",
            $arguments,
            ['language' => 'text'],
        );

        $t->contains('elisp_newname.el --- Text', $display);
        $t->contains('Renamed from elisp_oldname.el to elisp_newname.el', $display);
        $t->contains('File permissions changed from lhs_mode_placeholder to rhs_mode_placeholder.', $display);
        $t->contains('legacy', $display);
        $t->contains('modern', $display);
    },
    'maps upstream git style seven argument permission metadata' => static function (TestRunner $t): void {
        $metadata = GitExternalDiffMetadata::fromArguments([
            'render.php',
            '/tmp/git-blob-old/render.php',
            'lhs_hash',
            '100644',
            '/tmp/git-blob-new/render.php',
            'rhs_hash',
            '100755',
        ]);

        $t->same('render.php', $metadata->displayPath);
        $t->same('File permissions changed from 100644 to 100755.', $metadata->extraInfo);
    },
    'maps upstream two path arguments to common suffix display path' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-dir-clojure-1.clj');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-dir-clojure-2.clj');
        $display = (new InlineDiffRenderer())->renderPathArgumentsTextDiff($before, $after, [
            'sample_files/dir_1/clojure.clj',
            'sample_files/dir_2/clojure.clj',
        ], [
            'language' => 'text',
            'contextLines' => 1,
        ]);

        $t->contains('clojure.clj --- Text', $display);
        $t->contains('(println "hello!")', $display);
        $t->contains('(assoc :twice (+ x x))', $display);
        $t->true(!str_contains($display, 'dir_2/clojure.clj ---'), 'Difftastic build_display_path should drop differing directory prefixes when the path suffix matches.');
    },
    'maps upstream build display path git temp and extension fallbacks' => static function (TestRunner $t): void {
        $fromGitTemp = GitExternalDiffMetadata::fromPathArguments([
            '/tmp/git-blob-old/render.php',
            'wp-content/plugins/acme-card/includes/render.php',
        ], '/tmp');
        $rhsWithExtension = GitExternalDiffMetadata::fromPathArguments([
            'old/README',
            'new/block.json',
        ]);
        $rhsWithoutExtension = GitExternalDiffMetadata::fromPathArguments([
            'old/README',
            'new/CHANGELOG',
        ]);

        $t->same('wp-content/plugins/acme-card/includes/render.php', $fromGitTemp->displayPath);
        $t->same('new/block.json', $rhsWithExtension->displayPath);
        $t->same('old/README', $rhsWithoutExtension->displayPath);
    },
    'maps upstream git single argument unmerged path status' => static function (TestRunner $t): void {
        $arguments = ['sample_files/simple_1.js'];

        $t->same(
            "Unmerged path: sample_files/simple_1.js\n",
            GitExternalDiffMetadata::unmergedPathMessage($arguments, ['GIT_EXEC_PATH' => '/usr/lib/git-core']),
        );
        $t->same(
            "Unmerged path: sample_files/simple_1.js\n",
            (new InlineDiffRenderer())->renderUnmergedPathStatus($arguments, ['GIT_DIFF_PATH_TOTAL' => '1']),
        );
        $t->same(null, GitExternalDiffMetadata::unmergedPathMessage($arguments, []));
        $t->same(null, GitExternalDiffMetadata::unmergedPathMessage(['left.js', 'right.js'], ['GIT_EXEC_PATH' => '/usr/lib/git-core']));
    },
    'maps upstream list languages cli output' => static function (TestRunner $t): void {
        $result = (new DiffCommandRunner())->runListLanguages();

        $t->same(DiffCommandRunner::EXIT_SUCCESS, $result['exitCode']);
        $t->same('', $result['stderr']);
        $t->contains("TOML\n *.toml Cargo.lock Gopkg.lock Pipfile pdm.lock poetry.lock uv.lock\n", $result['stdout']);
        $t->contains("HTML\n *.html *.htm *.xhtml\n", $result['stdout']);
        $t->contains("Make\n *.mak *.d *.make *.makefile *.mk", $result['stdout']);
        $t->true(strpos($result['stdout'], "Ada\n *.ada") < strpos($result['stdout'], "TOML\n *.toml"), 'Built-in language rows should follow upstream enum order.');
    },
    'maps upstream list languages override rows before builtins' => static function (TestRunner $t): void {
        $result = (new DiffCommandRunner())->runListLanguages([
            '*.blade.php:HTML',
            '*.asset.php:text',
            '*.wp-env.json:json',
        ]);

        $t->same(DiffCommandRunner::EXIT_SUCCESS, $result['exitCode']);
        $t->same('', $result['stderr']);
        $t->contains("HTML (from override)\n *.blade.php\nText (from override)\n *.asset.php\nJSON (from override)\n *.wp-env.json\nAda\n", $result['stdout']);
        $t->true(strpos($result['stdout'], 'JSON (from override)') < strpos($result['stdout'], "JSON\n *.json"), 'Override rows should be printed before built-in language rows.');
    },
    'rejects invalid list languages overrides like upstream cli parsing' => static function (TestRunner $t): void {
        $result = (new DiffCommandRunner())->runListLanguages(['*.twig:Twig']);

        $t->same(DiffCommandRunner::EXIT_BAD_ARGUMENTS, $result['exitCode']);
        $t->same('', $result['stdout']);
        $t->contains("No such language 'Twig'", $result['stderr']);
        $t->contains('See --list-languages for the names of all languages available.', $result['stderr']);
    },
    'maps upstream language override precedence during file detection' => static function (TestRunner $t): void {
        $catalog = new LanguageCatalog();

        $overriddenGlob = $catalog->languageForPath('src/theme.el', '', ['*.el:CSS']);
        $firstMatchWins = $catalog->languageForPath('build/block.js', 'const block = true;', [
            '*.js:text',
            '*.js:JSON',
        ]);
        $overrideBeforeShebang = $catalog->languageForPath('bin/wp-migrate.js', "#!/usr/bin/env python\nprint('wp')\n", [
            '*.js:Text',
        ]);
        $shebang = $catalog->languageForPath('bin/wp-migrate', "#!/usr/bin/env python\nprint('wp')\n");
        $emacsMode = $catalog->languageForPath('bin/wp-migrate', "# -*-python-*-\nprint('wp')\n");
        $xmlHeader = $catalog->languageForPath('export.wxr', "<?xml version=\"1.0\"?>\n<rss />\n");

        $t->same(['display' => 'CSS', 'option' => 'css', 'override' => true], $overriddenGlob);
        $t->same(['display' => 'Text', 'option' => 'text', 'override' => true], $firstMatchWins);
        $t->same(['display' => 'Text', 'option' => 'text', 'override' => true], $overrideBeforeShebang);
        $t->same(['display' => 'Python', 'option' => 'python', 'override' => false], $shebang);
        $t->same(['display' => 'Python', 'option' => 'python', 'override' => false], $emacsMode);
        $t->same(['display' => 'XML', 'option' => 'xml', 'override' => false], $xmlHeader);
    },
    'maps upstream check only and exit code cli behavior' => static function (TestRunner $t): void {
        $before = "const React = require('react');\nconsole.log('hello world');\n";
        $after = "import React, {useState} from 'react';\nconsole.log('hello world');\n";
        $textBefore = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-text-1.txt');
        $textAfter = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-text-2.txt');
        $runner = new DiffCommandRunner();

        $defaultExit = $runner->runTextDiff($before, $after, 'sample_files/simple_1.js', 'JavaScript', [
            'language' => 'javascript',
        ]);
        $requestedExit = $runner->runTextDiff($before, $after, 'sample_files/simple_1.js', 'JavaScript', [
            'language' => 'javascript',
            'exitCode' => true,
        ]);
        $checkOnlySyntax = $runner->runCheckOnly($before, $after, 'sample_files/simple_1.js', 'JavaScript', [
            'language' => 'javascript',
        ]);
        $checkOnlyText = $runner->runCheckOnly($textBefore, $textAfter, 'sample_files/text_1.txt', 'Text', [
            'language' => 'text',
        ]);
        $checkOnlyUnchanged = $runner->runCheckOnly($before, $before, 'sample_files/simple_1.js', 'JavaScript', [
            'language' => 'javascript',
            'exitCode' => true,
        ]);
        $skippedUnchanged = $runner->runCheckOnly($before, $before, 'sample_files/simple_1.js', 'JavaScript', [
            'language' => 'javascript',
            'printUnchanged' => false,
        ]);

        $t->same(DiffCommandRunner::EXIT_SUCCESS, $defaultExit['exitCode']);
        $t->same(true, $defaultExit['hasChanges']);
        $t->same(DiffCommandRunner::EXIT_FOUND_CHANGES, $requestedExit['exitCode']);
        $t->contains('sample_files/simple_1.js --- JavaScript', $checkOnlySyntax['stdout']);
        $t->contains('Has syntactic changes.', $checkOnlySyntax['stdout']);
        $t->same(DiffCommandRunner::EXIT_SUCCESS, $checkOnlySyntax['exitCode']);
        $t->contains('sample_files/text_1.txt --- Text', $checkOnlyText['stdout']);
        $t->contains('Has changes.', $checkOnlyText['stdout']);
        $t->true(!str_contains($checkOnlyText['stdout'], 'Has syntactic changes.'), 'Text check-only mode should report byte/text changes, not syntactic changes.');
        $t->same(DiffCommandRunner::EXIT_SUCCESS, $checkOnlyUnchanged['exitCode']);
        $t->contains('No syntactic changes.', $checkOnlyUnchanged['stdout']);
        $t->same('', $skippedUnchanged['stdout']);
    },
    'maps upstream display option environment aggregation' => static function (TestRunner $t): void {
        $parsed = (new DiffCommandRunner())->parseDisplayOptions([], [
            'DFT_DISPLAY' => 'side-by-side-show-both',
            'DFT_CONTEXT' => '0',
            'DFT_TAB_WIDTH' => '2',
            'DFT_WIDTH' => '44',
        ]);
        $overridden = (new DiffCommandRunner())->parseDisplayOptions([
            'display' => 'inline',
            'contextLines' => 1,
        ], [
            'DFT_DISPLAY' => 'side-by-side',
            'DFT_CONTEXT' => '0',
            'DFT_TAB_WIDTH' => '4',
            'DFT_WIDTH' => '72',
        ]);

        $t->same([], $parsed['errors']);
        $t->same('side-by-side-show-both', $parsed['options']['display']);
        $t->same(0, $parsed['options']['contextLines']);
        $t->same(2, $parsed['options']['tabWidth']);
        $t->same(44, $parsed['options']['terminalWidth']);
        $t->same([], $overridden['errors']);
        $t->same('inline', $overridden['options']['display']);
        $t->same(1, $overridden['options']['contextLines']);
        $t->same(4, $overridden['options']['tabWidth']);
    },
    'maps upstream unstable json display guard for command mode' => static function (TestRunner $t): void {
        $runner = new DiffCommandRunner();
        $guarded = $runner->parseDisplayOptions([], [
            'DFT_DISPLAY' => 'json',
            'DFT_UNSTABLE' => 'yes',
        ]);
        $explicit = $runner->parseDisplayOptions([
            'display' => 'json',
        ], [
            'DFT_UNSTABLE' => '',
        ]);
        $unguarded = $runner->runTextDiff('old', 'new', 'readme.txt', 'Text', [
            'language' => 'text',
        ], [
            'DFT_DISPLAY' => 'json',
        ]);

        $t->same([], $guarded['errors']);
        $t->same('json', $guarded['options']['display']);
        $t->same([], $explicit['errors']);
        $t->same('json', $explicit['options']['display']);
        $t->same(DiffCommandRunner::EXIT_BAD_ARGUMENTS, $unguarded['exitCode']);
        $t->same('', $unguarded['stdout']);
        $t->contains('JSON output is an unstable feature', $unguarded['stderr']);
        $t->contains('DFT_UNSTABLE=yes', $unguarded['stderr']);
    },
    'maps upstream unstable json display environment into file command output' => static function (TestRunner $t): void {
        $result = (new DiffCommandRunner())->runTextDiff(
            "const title = \"Old\";\n",
            "const title = \"New\";\n",
            'sample_files/simple_1.js',
            'JavaScript',
            [
                'language' => 'javascript',
                'exitCode' => true,
            ],
            [
                'DFT_DISPLAY' => 'json',
                'DFT_UNSTABLE' => 'yes',
            ],
        );
        $decoded = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

        $t->same(DiffCommandRunner::EXIT_FOUND_CHANGES, $result['exitCode']);
        $t->same('', $result['stderr']);
        $t->same(true, $result['hasChanges']);
        $t->same('sample_files/simple_1.js', $decoded['path']);
        $t->same('JavaScript', $decoded['language']);
        $t->same('changed', $decoded['status']);
        $t->true(isset($decoded['chunks']), 'JSON command display should route a single file through the native JSON renderer.');
        $t->contains('Old', $result['stdout']);
        $t->contains('New', $result['stdout']);
    },
    'maps upstream background syntax and sort path environment aggregation' => static function (TestRunner $t): void {
        $runner = new DiffCommandRunner();
        $parsed = $runner->parseCommandOptions([], [
            'DFT_BACKGROUND' => 'dark',
            'DFT_SYNTAX_HIGHLIGHT' => 'off',
            'DFT_SORT_PATHS' => 'on',
        ]);
        $overridden = $runner->parseCommandOptions([
            'backgroundColor' => 'light',
            'syntaxHighlight' => true,
            'sortPaths' => false,
        ], [
            'DFT_BACKGROUND' => 'dark',
            'DFT_SYNTAX_HIGHLIGHT' => 'off',
            'DFT_SORT_PATHS' => 'on',
        ]);

        $t->same([], $parsed['errors']);
        $t->same('dark', $parsed['options']['backgroundColor']);
        $t->same(false, $parsed['options']['syntaxHighlight']);
        $t->same(true, $parsed['options']['sortPaths']);
        $t->same([], $overridden['errors']);
        $t->same('light', $overridden['options']['backgroundColor']);
        $t->same(true, $overridden['options']['syntaxHighlight']);
        $t->same(false, $overridden['options']['sortPaths']);
    },
    'maps upstream command display environment into side by side output' => static function (TestRunner $t): void {
        $before = "stable 1\nstable 2\nlabel:\told\nstable 4\n";
        $after = "stable 1\nstable 2\nlabel:\tnew\nstable 4\n";
        $result = (new DiffCommandRunner())->runTextDiff($before, $after, 'sample_files/tab_1.txt', 'Text', [
            'language' => 'text',
        ], [
            'DFT_DISPLAY' => 'side-by-side',
            'DFT_CONTEXT' => '0',
            'DFT_TAB_WIDTH' => '2',
            'DFT_WIDTH' => '34',
        ]);

        $t->same(DiffCommandRunner::EXIT_SUCCESS, $result['exitCode']);
        $t->same('', $result['stderr']);
        $t->contains('label:  old', $result['stdout']);
        $t->contains('label:  new', $result['stdout']);
        $t->true(!str_contains($result['stdout'], "\t"), 'DFT_TAB_WIDTH should be routed into display tab expansion.');
        $t->true(!str_contains($result['stdout'], 'stable 1'), 'DFT_CONTEXT=0 should omit distant stable lines before the changed row.');
        $t->true(!str_contains($result['stdout'], 'stable 4'), 'DFT_CONTEXT=0 should omit distant stable lines after the changed row.');
    },
    'maps upstream background environment into colored side by side output' => static function (TestRunner $t): void {
        $before = "render_label('legacy-card');\n";
        $after = "render_label('modern-card');\n";
        $dark = (new DiffCommandRunner())->runTextDiff($before, $after, 'src/render.php', 'PHP', [
            'language' => 'php',
        ], [
            'DFT_DISPLAY' => 'side-by-side',
            'DFT_CONTEXT' => '0',
            'DFT_COLOR' => 'always',
            'DFT_BACKGROUND' => 'dark',
        ]);
        $light = (new DiffCommandRunner())->runTextDiff($before, $after, 'src/render.php', 'PHP', [
            'language' => 'php',
        ], [
            'DFT_DISPLAY' => 'side-by-side',
            'DFT_CONTEXT' => '0',
            'DFT_COLOR' => 'always',
            'DFT_BACKGROUND' => 'light',
        ]);

        $t->same(DiffCommandRunner::EXIT_SUCCESS, $dark['exitCode']);
        $t->same('', $dark['stderr']);
        $t->contains("\033[1;91mlegacy\033[0m", $dark['stdout']);
        $t->contains("\033[1;92mmodern\033[0m", $dark['stdout']);
        $t->contains("\033[1;31mlegacy\033[0m", $light['stdout']);
        $t->contains("\033[1;32mmodern\033[0m", $light['stdout']);
    },
    'maps upstream syntax highlight control into side by side ansi output' => static function (TestRunner $t): void {
        $before = "function render_card(): string {\n    return esc_html(\"legacy\");\n}\n";
        $after = "function render_card(): string {\n    return esc_html(\"modern\");\n}\n";
        $runner = new DiffCommandRunner();
        $syntaxOn = $runner->runTextDiff($before, $after, 'src/render.php', 'PHP', [
            'language' => 'php',
        ], [
            'DFT_DISPLAY' => 'side-by-side',
            'DFT_CONTEXT' => '1',
            'DFT_COLOR' => 'always',
            'DFT_SYNTAX_HIGHLIGHT' => 'on',
        ]);
        $syntaxOff = $runner->runTextDiff($before, $after, 'src/render.php', 'PHP', [
            'language' => 'php',
        ], [
            'DFT_DISPLAY' => 'side-by-side',
            'DFT_CONTEXT' => '1',
            'DFT_COLOR' => 'always',
            'DFT_SYNTAX_HIGHLIGHT' => 'off',
        ]);

        $t->same(DiffCommandRunner::EXIT_SUCCESS, $syntaxOn['exitCode']);
        $t->same('', $syntaxOn['stderr']);
        $t->contains("\033[1mfunction\033[0m render_card(): \033[1mstring\033[0m", $syntaxOn['stdout']);
        $t->contains("\033[95m\"\033[0m\033[1;91mlegacy\033[0m\033[95m\"\033[0m", $syntaxOn['stdout']);
        $t->contains("\033[1;92mmodern\033[0m", $syntaxOn['stdout']);
        $t->same(DiffCommandRunner::EXIT_SUCCESS, $syntaxOff['exitCode']);
        $t->contains("\033[1;91mlegacy\033[0m", $syntaxOff['stdout']);
        $t->contains("\033[1;92mmodern\033[0m", $syntaxOff['stdout']);
        $t->true(!str_contains($syntaxOff['stdout'], "\033[1mfunction\033[0m"), 'DFT_SYNTAX_HIGHLIGHT=off should suppress syntax keyword styling.');
        $t->true(!str_contains($syntaxOff['stdout'], "\033[95m\"\033[0m"), 'DFT_SYNTAX_HIGHLIGHT=off should suppress syntax string styling while keeping novel diff colors.');
    },
    'maps upstream syntax highlight control into inline ansi output' => static function (TestRunner $t): void {
        $before = "function render_card(): string {\n    return esc_html(\"legacy\");\n}\n";
        $after = "function render_card(): string {\n    return esc_html(\"modern\");\n}\n";
        $syntaxOn = (new DiffCommandRunner())->runTextDiff($before, $after, 'src/render.php', 'PHP', [
            'language' => 'php',
            'display' => 'inline',
            'contextLines' => 1,
            'useColor' => true,
            'syntaxHighlight' => true,
        ]);
        $syntaxOff = (new DiffCommandRunner())->runTextDiff($before, $after, 'src/render.php', 'PHP', [
            'language' => 'php',
            'display' => 'inline',
            'contextLines' => 1,
            'useColor' => true,
            'syntaxHighlight' => false,
        ]);

        $t->same(DiffCommandRunner::EXIT_SUCCESS, $syntaxOn['exitCode']);
        $t->contains("\033[1mfunction\033[0m render_card(): \033[1mstring\033[0m", $syntaxOn['stdout']);
        $t->contains("\033[95m\"legacy\"\033[0m", $syntaxOn['stdout']);
        $t->same(DiffCommandRunner::EXIT_SUCCESS, $syntaxOff['exitCode']);
        $t->true(!str_contains($syntaxOff['stdout'], "\033[1mfunction\033[0m"), 'Explicit syntaxHighlight=false should suppress inline keyword styling.');
        $t->true(!str_contains($syntaxOff['stdout'], "\033[95m\"legacy\"\033[0m"), 'Explicit syntaxHighlight=false should suppress inline string styling.');
        $t->contains('return esc_html("legacy");', $syntaxOff['stdout']);
    },
    'rejects invalid display option environment before review' => static function (TestRunner $t): void {
        $result = (new DiffCommandRunner())->runTextDiff('old', 'new', 'readme.txt', 'Text', [
            'language' => 'text',
        ], [
            'DFT_WIDTH' => 'wide',
        ]);

        $t->same(DiffCommandRunner::EXIT_BAD_ARGUMENTS, $result['exitCode']);
        $t->same('', $result['stdout']);
        $t->same(false, $result['hasChanges']);
        $t->contains("Invalid value 'wide' for DFT_WIDTH", $result['stderr']);
    },
    'rejects invalid command display controls environment before review' => static function (TestRunner $t): void {
        $result = (new DiffCommandRunner())->runTextDiff('old', 'new', 'readme.txt', 'Text', [
            'language' => 'text',
        ], [
            'DFT_BACKGROUND' => 'sepia',
            'DFT_SYNTAX_HIGHLIGHT' => 'maybe',
            'DFT_SORT_PATHS' => 'sometimes',
        ]);

        $t->same(DiffCommandRunner::EXIT_BAD_ARGUMENTS, $result['exitCode']);
        $t->same('', $result['stdout']);
        $t->same(false, $result['hasChanges']);
        $t->contains("Invalid value 'sepia' for DFT_BACKGROUND", $result['stderr']);
        $t->contains("Invalid value 'maybe' for DFT_SYNTAX_HIGHLIGHT", $result['stderr']);
        $t->contains("Invalid value 'sometimes' for DFT_SORT_PATHS", $result['stderr']);
    },
    'maps upstream command boolean environment aggregation' => static function (TestRunner $t): void {
        $runner = new DiffCommandRunner();
        $before = "<?php\n// Legacy render path.\nreturn esc_html(\$title);\n";
        $commentOnly = "<?php\n// Modern render path.\nreturn esc_html(\$title);\n";
        $changed = "<?php\n// Modern render path.\nreturn wp_kses_post(\$title);\n";

        $unchangedAfterCommentFilter = $runner->runTextDiff($before, $commentOnly, 'src/render.php', 'PHP', [
            'language' => 'php',
        ], [
            'DFT_CHECK_ONLY' => 'true',
            'DFT_EXIT_CODE' => '1',
            'DFT_SKIP_UNCHANGED' => 'yes',
            'DFT_IGNORE_COMMENTS' => 'on',
        ]);
        $changedWithExitCode = $runner->runTextDiff($before, $changed, 'src/render.php', 'PHP', [
            'language' => 'php',
        ], [
            'DFT_CHECK_ONLY' => 'true',
            'DFT_EXIT_CODE' => '1',
            'DFT_SKIP_UNCHANGED' => 'yes',
            'DFT_IGNORE_COMMENTS' => 'on',
        ]);
        $stripCrOff = $runner->runTextDiff("line\r\n", "line\n", 'readme.txt', 'Text', [
            'language' => 'text',
        ], [
            'DFT_CHECK_ONLY' => 'true',
            'DFT_STRIP_CR' => 'off',
        ]);
        $stripCrOn = $runner->runTextDiff("line\r\n", "line\n", 'readme.txt', 'Text', [
            'language' => 'text',
        ], [
            'DFT_CHECK_ONLY' => 'true',
            'DFT_SKIP_UNCHANGED' => 'true',
            'DFT_STRIP_CR' => 'on',
        ]);
        $parsed = $runner->parseCommandOptions([
            'exitCode' => false,
            'stripCr' => false,
        ], [
            'DFT_EXIT_CODE' => 'true',
            'DFT_STRIP_CR' => 'on',
            'DFT_COLOR' => 'always',
        ]);

        $t->same(DiffCommandRunner::EXIT_SUCCESS, $unchangedAfterCommentFilter['exitCode']);
        $t->same(false, $unchangedAfterCommentFilter['hasChanges']);
        $t->same('', $unchangedAfterCommentFilter['stdout']);
        $t->same(DiffCommandRunner::EXIT_FOUND_CHANGES, $changedWithExitCode['exitCode']);
        $t->same(true, $changedWithExitCode['hasChanges']);
        $t->contains('Has syntactic changes.', $changedWithExitCode['stdout']);
        $t->same(DiffCommandRunner::EXIT_SUCCESS, $stripCrOff['exitCode']);
        $t->contains('Has changes.', $stripCrOff['stdout']);
        $t->same('', $stripCrOn['stdout']);
        $t->same([], $parsed['errors']);
        $t->same(false, $parsed['options']['exitCode']);
        $t->same(false, $parsed['options']['stripCr']);
        $t->same(true, $parsed['options']['useColor']);
    },
    'rejects invalid command boolean environment before review' => static function (TestRunner $t): void {
        $result = (new DiffCommandRunner())->runTextDiff('old', 'new', 'readme.txt', 'Text', [
            'language' => 'text',
        ], [
            'DFT_CHECK_ONLY' => 'sometimes',
            'DFT_STRIP_CR' => 'maybe',
            'DFT_COLOR' => 'rainbow',
        ]);

        $t->same(DiffCommandRunner::EXIT_BAD_ARGUMENTS, $result['exitCode']);
        $t->same('', $result['stdout']);
        $t->same(false, $result['hasChanges']);
        $t->contains("Invalid value 'sometimes' for DFT_CHECK_ONLY", $result['stderr']);
        $t->contains("Invalid value 'maybe' for DFT_STRIP_CR", $result['stderr']);
        $t->contains("Invalid value 'rainbow' for DFT_COLOR", $result['stderr']);
    },
    'maps upstream command resource limit environment aggregation' => static function (TestRunner $t): void {
        $runner = new DiffCommandRunner();
        $before = "<?php\nreturn [\n    'render_callback' => 'acme_render_legacy_card',\n    'supports' => ['html' => false],\n];\n";
        $after = "<?php\nreturn [\n    'render_callback' => 'acme_render_modern_card',\n    'supports' => ['html' => true, 'align' => ['wide']],\n];\n";
        $expectedReason = max(strlen($before), strlen($after)) . ' B exceeded DFT_BYTE_LIMIT';
        $parsed = $runner->parseCommandOptions([
            'byteLimit' => '96',
        ], [
            'DFT_BYTE_LIMIT' => '80',
            'DFT_GRAPH_LIMIT' => '75',
            'DFT_PARSE_ERROR_LIMIT' => '2',
        ]);
        $result = $runner->runTextDiff($before, $after, 'wp-content/plugins/acme-card/render-metadata.php', 'PHP', [
            'language' => 'php',
        ], [
            'DFT_BYTE_LIMIT' => '80',
        ]);

        $t->same([], $parsed['errors']);
        $t->same(96, $parsed['options']['byteLimit']);
        $t->same(75, $parsed['options']['graphLimit']);
        $t->same(2, $parsed['options']['parseErrorLimit']);
        $t->same(DiffCommandRunner::EXIT_SUCCESS, $result['exitCode']);
        $t->same(true, $result['hasChanges']);
        $t->contains('wp-content/plugins/acme-card/render-metadata.php --- Text (' . $expectedReason . ')', $result['stdout']);
        $t->contains('acme_render_legacy_card', $result['stdout']);
        $t->contains('acme_render_modern_card', $result['stdout']);
    },
    'rejects invalid command resource limit environment before review' => static function (TestRunner $t): void {
        $result = (new DiffCommandRunner())->runTextDiff('old', 'new', 'readme.txt', 'Text', [
            'language' => 'text',
        ], [
            'DFT_BYTE_LIMIT' => 'big',
            'DFT_GRAPH_LIMIT' => '-1',
            'DFT_PARSE_ERROR_LIMIT' => '1.5',
        ]);
        $explicit = (new DiffCommandRunner())->parseCommandOptions([
            'graphLimit' => 'wide',
        ]);

        $t->same(DiffCommandRunner::EXIT_BAD_ARGUMENTS, $result['exitCode']);
        $t->same('', $result['stdout']);
        $t->same(false, $result['hasChanges']);
        $t->contains("Invalid value 'big' for DFT_BYTE_LIMIT", $result['stderr']);
        $t->contains("Invalid value '-1' for DFT_GRAPH_LIMIT", $result['stderr']);
        $t->contains("Invalid value '1.5' for DFT_PARSE_ERROR_LIMIT", $result['stderr']);
        $t->contains("Invalid value 'wide' for --graph-limit", implode("\n", $explicit['errors']));
    },
    'routes command resource limits into json file and directory review' => static function (TestRunner $t): void {
        $before = "<?php\nreturn [\n    'render_callback' => 'acme_render_legacy_card',\n    'supports' => ['html' => false],\n];\n";
        $after = "<?php\nreturn [\n    'render_callback' => 'acme_render_modern_card',\n    'supports' => ['html' => true, 'align' => ['wide']],\n];\n";
        $expectedLanguage = 'Text (' . max(strlen($before), strlen($after)) . ' B exceeded DFT_BYTE_LIMIT)';
        $runner = new DiffCommandRunner();
        $file = $runner->runJsonFileBytesDiff($before, $after, 'wp-content/plugins/acme-card/render-metadata.php', 'PHP', [
            'language' => 'php',
            'exitCode' => true,
        ], [
            'DFT_BYTE_LIMIT' => '80',
        ]);

        $root = sys_get_temp_dir() . '/difftastic-command-limits-' . str_replace('.', '-', uniqid('', true));
        $left = $root . '/before';
        $right = $root . '/after';
        $write = static function (string $path, string $contents): void {
            $directory = dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            file_put_contents($path, $contents);
        };
        $remove = static function (string $path) use (&$remove): void {
            if (!file_exists($path)) {
                return;
            }
            if (is_dir($path) && !is_link($path)) {
                foreach (scandir($path) ?: [] as $entry) {
                    if ($entry !== '.' && $entry !== '..') {
                        $remove($path . DIRECTORY_SEPARATOR . $entry);
                    }
                }
                rmdir($path);
                return;
            }
            unlink($path);
        };

        try {
            $write($left . '/render-metadata.php', $before);
            $write($right . '/render-metadata.php', $after);

            $directory = $runner->runJsonDirectoryDiff($left, $right, [
                'sortPaths' => true,
                'exitCode' => true,
            ], [
                'DFT_BYTE_LIMIT' => '80',
            ]);

            $t->same(DiffCommandRunner::EXIT_FOUND_CHANGES, $file['exitCode']);
            $t->same($expectedLanguage, $file['file']['language']);
            $t->same('changed', $file['file']['status']);
            $t->same(DiffCommandRunner::EXIT_FOUND_CHANGES, $directory['exitCode']);
            $t->same(1, count($directory['files']));
            $t->same('render-metadata.php', $directory['files'][0]['path']);
            $t->same($expectedLanguage, $directory['files'][0]['language']);
            $t->same('changed', $directory['files'][0]['status']);
        } finally {
            $remove($root);
        }
    },
    'wordpress command env ci flags report escaping changes only' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-render-callback-before.php');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-render-callback-after.php');
        $result = (new DiffCommandRunner())->runTextDiff($before, $after, 'wp-content/plugins/acme-card/src/render.php', 'PHP', [
            'language' => 'php',
        ], [
            'DFT_CHECK_ONLY' => 'true',
            'DFT_EXIT_CODE' => 'true',
            'DFT_IGNORE_COMMENTS' => 'true',
            'DFT_SKIP_UNCHANGED' => 'false',
        ]);

        $t->same(DiffCommandRunner::EXIT_FOUND_CHANGES, $result['exitCode']);
        $t->same(true, $result['hasChanges']);
        $t->contains('wp-content/plugins/acme-card/src/render.php --- PHP', $result['stdout']);
        $t->contains('Has syntactic changes.', $result['stdout']);
    },
    'maps upstream directory arguments with relative created and deleted paths' => static function (TestRunner $t): void {
        $fixtures = dirname(__DIR__) . '/fixtures';
        $root = sys_get_temp_dir() . '/difftastic-dir-' . str_replace('.', '-', uniqid('', true));
        $left = $root . '/dir_1';
        $right = $root . '/dir_2';
        $write = static function (string $path, string $contents): void {
            $directory = dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            file_put_contents($path, $contents);
        };
        $remove = static function (string $path) use (&$remove): void {
            if (!file_exists($path)) {
                return;
            }
            if (is_dir($path) && !is_link($path)) {
                foreach (scandir($path) ?: [] as $entry) {
                    if ($entry !== '.' && $entry !== '..') {
                        $remove($path . DIRECTORY_SEPARATOR . $entry);
                    }
                }
                rmdir($path);
                return;
            }
            unlink($path);
        };

        try {
            $write($left . '/foo.js', (string) file_get_contents($fixtures . '/upstream-dir-foo-1.js'));
            $write($right . '/foo.js', (string) file_get_contents($fixtures . '/upstream-dir-foo-2.js'));
            $write($left . '/only_in_1.c', (string) file_get_contents($fixtures . '/upstream-dir-only-in-1.c'));
            $write($right . '/only_in_2.rs', (string) file_get_contents($fixtures . '/upstream-dir-only-in-2.rs'));
            $write($left . '/same.txt', "stable\n");
            $write($right . '/same.txt', "stable\n");
            $write($left . '/.git/config', "old repository metadata\n");
            $write($right . '/.git/config', "new repository metadata\n");

            $differ = new DirectoryDiffer();
            $paths = $differ->relativePathsInEither($left, $right);
            $files = $differ->diffDirectories($left, $right);
            $byPath = [];
            foreach ($files as $file) {
                $byPath[$file['path']] = $file;
            }

            $t->same(['foo.js', 'only_in_1.c', 'only_in_2.rs', 'same.txt'], $paths);
            $t->same('changed', $byPath['foo.js']['status']);
            $t->same('deleted', $byPath['only_in_1.c']['status']);
            $t->same('created', $byPath['only_in_2.rs']['status']);
            $t->true(!isset($byPath['same.txt']), 'Unchanged directory files should be filtered by default.');
            $t->true(!isset($byPath['.git/config']), 'The directory walker should exclude .git internals.');

            $withUnchanged = $differ->diffDirectories($left, $right, ['printUnchanged' => true]);
            $withUnchangedPaths = array_column($withUnchanged, 'path');
            $t->contains('same.txt', implode("\n", $withUnchangedPaths));
        } finally {
            $remove($root);
        }
    },
    'maps upstream sort paths environment into directory review order' => static function (TestRunner $t): void {
        $root = sys_get_temp_dir() . '/difftastic-sort-paths-' . str_replace('.', '-', uniqid('', true));
        $left = $root . '/before';
        $right = $root . '/after';
        $write = static function (string $path, string $contents): void {
            $directory = dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            file_put_contents($path, $contents);
        };
        $remove = static function (string $path) use (&$remove): void {
            if (!file_exists($path)) {
                return;
            }
            if (is_dir($path) && !is_link($path)) {
                foreach (scandir($path) ?: [] as $entry) {
                    if ($entry !== '.' && $entry !== '..') {
                        $remove($path . DIRECTORY_SEPARATOR . $entry);
                    }
                }
                rmdir($path);
                return;
            }
            unlink($path);
        };

        try {
            $write($left . '/wp-content/plugins/acme-card/z-deleted.php', "<?php\nreturn 'legacy';\n");
            $write($right . '/wp-content/plugins/acme-card/a-created.php', "<?php\nreturn 'modern';\n");

            $unsorted = (new DiffCommandRunner())->runJsonDirectoryDiff($left, $right, [], [
                'DFT_SORT_PATHS' => 'false',
            ]);
            $sorted = (new DiffCommandRunner())->runJsonDirectoryDiff($left, $right, [], [
                'DFT_SORT_PATHS' => 'true',
            ]);

            $t->same(DiffCommandRunner::EXIT_SUCCESS, $sorted['exitCode']);
            $t->same([
                'wp-content/plugins/acme-card/z-deleted.php',
                'wp-content/plugins/acme-card/a-created.php',
            ], array_column($unsorted['files'], 'path'));
            $t->same([
                'wp-content/plugins/acme-card/a-created.php',
                'wp-content/plugins/acme-card/z-deleted.php',
            ], array_column($sorted['files'], 'path'));
        } finally {
            $remove($root);
        }
    },
    'maps upstream hidden file walking through dotfiles and dot directories' => static function (TestRunner $t): void {
        $fixtures = dirname(__DIR__) . '/fixtures';
        $root = sys_get_temp_dir() . '/difftastic-hidden-' . str_replace('.', '-', uniqid('', true));
        $left = $root . '/hidden_1';
        $right = $root . '/hidden_2';
        $bytes = static function (string $path): string {
            $hex = preg_replace('/\s+/', '', (string) file_get_contents($path));

            return (string) hex2bin($hex ?? '');
        };
        $write = static function (string $path, string $contents): void {
            $directory = dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            file_put_contents($path, $contents);
        };
        $remove = static function (string $path) use (&$remove): void {
            if (!file_exists($path)) {
                return;
            }
            if (is_dir($path) && !is_link($path)) {
                foreach (scandir($path) ?: [] as $entry) {
                    if ($entry !== '.' && $entry !== '..') {
                        $remove($path . DIRECTORY_SEPARATOR . $entry);
                    }
                }
                rmdir($path);
                return;
            }
            unlink($path);
        };

        try {
            $write($left . '/.hidden.txt', $bytes($fixtures . '/upstream-hidden-dotfile-before.hex'));
            $write($right . '/.hidden.txt', $bytes($fixtures . '/upstream-hidden-dotfile-after.hex'));
            $write($left . '/.hidden/doc.txt', $bytes($fixtures . '/upstream-hidden-doc-before.hex'));
            $write($right . '/.hidden/doc.txt', $bytes($fixtures . '/upstream-hidden-doc-after.hex'));
            $write($left . '/.git/config', "before\n");
            $write($right . '/.git/config', "after\n");

            $json = (new DirectoryDiffer())->renderJsonDirectoryDiff($left, $right);
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $paths = array_column($decoded, 'path');

            $t->contains('.hidden/doc.txt', implode("\n", $paths));
            $t->contains('.hidden.txt', implode("\n", $paths));
            $t->contains('before', $json);
            $t->contains('after', $json);
            $t->true(!str_contains($json, '.git/config'), 'Hidden walking should still skip .git directories.');
        } finally {
            $remove($root);
        }
    },
    'wordpress plugin directory json diff includes hidden tooling and filters unchanged files' => static function (TestRunner $t): void {
        $fixtures = dirname(__DIR__) . '/fixtures';
        $files = (new DirectoryDiffer())->diffDirectories(
            $fixtures . '/wordpress-directory-before',
            $fixtures . '/wordpress-directory-after',
        );
        $paths = array_column($files, 'path');
        $json = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $t->contains('.wp-env.json', implode("\n", $paths));
        $t->contains('wp-content/plugins/acme-card/block.json', implode("\n", $paths));
        $t->true(!in_array('wp-content/plugins/acme-card/src/render.php', $paths, true), 'Unchanged plugin render files should not appear in directory JSON by default.');
        $t->contains('../../mu-plugins/acme-cache', $json);
        $t->contains('viewScriptModule', $json);
    },
    'maps upstream json directory command print unchanged default and skip flag' => static function (TestRunner $t): void {
        $fixtures = dirname(__DIR__) . '/fixtures';
        $runner = new DiffCommandRunner();
        $default = $runner->runJsonDirectoryDiff(
            $fixtures . '/wordpress-directory-before',
            $fixtures . '/wordpress-directory-after',
            [
                'sortPaths' => true,
            ],
            [
                'DFT_DISPLAY' => 'json',
                'DFT_UNSTABLE' => 'yes',
            ],
        );
        $skipped = $runner->runJsonDirectoryDiff(
            $fixtures . '/wordpress-directory-before',
            $fixtures . '/wordpress-directory-after',
            [
                'sortPaths' => true,
            ],
            [
                'DFT_DISPLAY' => 'json',
                'DFT_SKIP_UNCHANGED' => 'true',
                'DFT_UNSTABLE' => 'yes',
            ],
        );
        $defaultByPath = [];
        foreach ($default['files'] as $file) {
            $defaultByPath[$file['path']] = $file;
        }

        $t->same(DiffCommandRunner::EXIT_SUCCESS, $default['exitCode']);
        $t->same('', $default['stderr']);
        $t->same(true, $default['hasChanges']);
        $t->same('unchanged', $defaultByPath['wp-content/plugins/acme-card/src/render.php']['status']);
        $t->same('PHP', $defaultByPath['wp-content/plugins/acme-card/src/render.php']['language']);
        $t->true(in_array('wp-content/plugins/acme-card/src/render.php', array_column($default['files'], 'path'), true), 'JSON directory command should print unchanged files unless skipped.');
        $t->true(!in_array('wp-content/plugins/acme-card/src/render.php', array_column($skipped['files'], 'path'), true), 'DFT_SKIP_UNCHANGED should filter unchanged directory files.');
    },
    'wordpress env json directory command example emits unchanged render file status' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-env-json-directory-command.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        $byPath = [];
        foreach ($decoded as $file) {
            $byPath[$file['path']] = $file;
        }

        $t->same('unchanged', $byPath['wp-content/plugins/acme-card/src/render.php']['status']);
        $t->same('PHP', $byPath['wp-content/plugins/acme-card/src/render.php']['language']);
        $t->same('changed', $byPath['wp-content/plugins/acme-card/block.json']['status']);
        $t->contains('.wp-env.json', implode("\n", array_column($decoded, 'path')));
    },
    'wordpress directory diff applies language overrides before builtin globs' => static function (TestRunner $t): void {
        $fixtures = dirname(__DIR__) . '/fixtures';
        $files = (new DirectoryDiffer())->diffDirectories(
            $fixtures . '/wordpress-language-override-before',
            $fixtures . '/wordpress-language-override-after',
            [
                'sortPaths' => true,
                'languageOverrides' => [
                    '*.asset.php:text',
                    '*.blade.php:HTML',
                ],
            ],
        );
        $byPath = [];
        foreach ($files as $file) {
            $byPath[$file['path']] = $file;
        }
        $json = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $t->same('Text', $byPath['build/index.asset.php']['language']);
        $t->same('HTML', $byPath['templates/card.blade.php']['language']);
        $t->contains('wp-i18n', $json);
        $t->contains('modern', $json);
        $t->contains('description', $json);
    },
    'maps upstream language override environment aggregation' => static function (TestRunner $t): void {
        $parsed = (new DiffCommandRunner())->parseLanguageOverrides([
            '*.blade.php:HTML',
            '*.template.php:HTML',
        ], [
            'DFT_OVERRIDE' => '*.asset.php:text',
            'DFT_OVERRIDE_1' => '*.wp-env.json:JSON',
            'DFT_OVERRIDE_9' => '*.tsx:TypeScript TSX',
            'DFT_OVERRIDE_10' => '*.ignored:CSS',
        ]);

        $t->same([], $parsed['errors']);
        $t->same([
            [
                'name' => 'HTML',
                'option' => 'html',
                'globs' => ['*.blade.php', '*.template.php'],
                'override' => true,
            ],
            [
                'name' => 'Text',
                'option' => 'text',
                'globs' => ['*.asset.php'],
                'override' => true,
            ],
            [
                'name' => 'JSON',
                'option' => 'json',
                'globs' => ['*.wp-env.json'],
                'override' => true,
            ],
            [
                'name' => 'TypeScript TSX',
                'option' => 'tsx',
                'globs' => ['*.tsx'],
                'override' => true,
            ],
        ], $parsed['rows']);
    },
    'maps upstream list languages environment override rows before builtins' => static function (TestRunner $t): void {
        $result = (new DiffCommandRunner())->runListLanguages([], [], [
            'DFT_OVERRIDE' => '*.asset.php:text',
            'DFT_OVERRIDE_1' => '*.blade.php:HTML',
            'DFT_OVERRIDE_10' => '*.ignored:CSS',
        ]);

        $t->same(DiffCommandRunner::EXIT_SUCCESS, $result['exitCode']);
        $t->same('', $result['stderr']);
        $t->contains("Text (from override)\n *.asset.php\nHTML (from override)\n *.blade.php\nAda\n", $result['stdout']);
        $t->true(!str_contains($result['stdout'], '*.ignored'), 'Only DFT_OVERRIDE_1 through DFT_OVERRIDE_9 should be aggregated.');
    },
    'rejects invalid language override environment before directory review' => static function (TestRunner $t): void {
        $fixtures = dirname(__DIR__) . '/fixtures';
        $invalidGlob = (new DiffCommandRunner())->runJsonDirectoryDiff(
            $fixtures . '/wordpress-language-override-before',
            $fixtures . '/wordpress-language-override-after',
            ['sortPaths' => true],
            ['DFT_OVERRIDE' => '*.blade.php[:HTML'],
        );
        $result = (new DiffCommandRunner())->runJsonDirectoryDiff(
            $fixtures . '/wordpress-language-override-before',
            $fixtures . '/wordpress-language-override-after',
            ['sortPaths' => true],
            ['DFT_OVERRIDE' => '*.blade.php:Twig'],
        );

        $t->same(DiffCommandRunner::EXIT_BAD_ARGUMENTS, $invalidGlob['exitCode']);
        $t->same('', $invalidGlob['stdout']);
        $t->same([], $invalidGlob['files']);
        $t->contains("Invalid glob syntax '*.blade.php['", $invalidGlob['stderr']);
        $t->contains('Glob parsing error: unclosed character class', $invalidGlob['stderr']);
        $t->same(DiffCommandRunner::EXIT_BAD_ARGUMENTS, $result['exitCode']);
        $t->same('', $result['stdout']);
        $t->same(false, $result['hasChanges']);
        $t->same([], $result['files']);
        $t->contains("No such language 'Twig'", $result['stderr']);
        $t->contains('See --list-languages for the names of all languages available.', $result['stderr']);
    },
    'maps upstream language override environment into file byte review' => static function (TestRunner $t): void {
        $result = (new DiffCommandRunner())->runJsonFileBytesDiff(
            "<?php return ['dependencies' => ['wp-blocks']];\n",
            "<?php return ['dependencies' => ['wp-blocks', 'wp-i18n']];\n",
            'build/index.asset.php',
            'PHP',
            ['exitCode' => true],
            ['DFT_OVERRIDE' => '*.asset.php:text'],
        );
        $decoded = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

        $t->same(DiffCommandRunner::EXIT_FOUND_CHANGES, $result['exitCode']);
        $t->same('', $result['stderr']);
        $t->same('build/index.asset.php', $decoded['path']);
        $t->same('Text', $decoded['language']);
        $t->same('changed', $decoded['status']);
        $t->true(isset($decoded['chunks']), 'Text override should route file-byte review through text display chunks.');
    },
    'wordpress command env language overrides route into directory review' => static function (TestRunner $t): void {
        $fixtures = dirname(__DIR__) . '/fixtures';
        $result = (new DiffCommandRunner())->runJsonDirectoryDiff(
            $fixtures . '/wordpress-language-override-before',
            $fixtures . '/wordpress-language-override-after',
            [
                'sortPaths' => true,
                'exitCode' => true,
            ],
            [
                'DFT_OVERRIDE' => '*.asset.php:text',
                'DFT_OVERRIDE_1' => '*.blade.php:HTML',
            ],
        );
        $decoded = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        $byPath = [];
        foreach ($decoded as $file) {
            $byPath[$file['path']] = $file;
        }

        $t->same(DiffCommandRunner::EXIT_FOUND_CHANGES, $result['exitCode']);
        $t->same('', $result['stderr']);
        $t->same(true, $result['hasChanges']);
        $t->same($decoded, $result['files']);
        $t->same('Text', $byPath['build/index.asset.php']['language']);
        $t->same('HTML', $byPath['templates/card.blade.php']['language']);
        $t->contains('wp-i18n', $result['stdout']);
        $t->contains('modern', $result['stdout']);
    },
    'wordpress check only command reports block metadata gate status' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-json-before.json');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-json-after.json');
        $runner = new DiffCommandRunner();
        $changed = $runner->runCheckOnly($before, $after, 'wp-content/plugins/acme-card/block.json', 'JSON', [
            'language' => 'json',
            'exitCode' => true,
            'extraInfo' => 'WordPress plugin metadata gate',
        ]);
        $unchanged = $runner->runCheckOnly($before, $before, 'wp-content/plugins/acme-card/block.json', 'JSON', [
            'language' => 'json',
            'exitCode' => true,
            'printUnchanged' => false,
        ]);

        $t->same(DiffCommandRunner::EXIT_FOUND_CHANGES, $changed['exitCode']);
        $t->same(true, $changed['hasChanges']);
        $t->contains('wp-content/plugins/acme-card/block.json --- JSON', $changed['stdout']);
        $t->contains('WordPress plugin metadata gate', $changed['stdout']);
        $t->contains('Has syntactic changes.', $changed['stdout']);
        $t->same(DiffCommandRunner::EXIT_SUCCESS, $unchanged['exitCode']);
        $t->same('', $unchanged['stdout']);
    },
    'wordpress command env display options wrap tabbed block metadata' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-tabbed-block-json-before.json');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-tabbed-block-json-after.json');
        $result = (new DiffCommandRunner())->runTextDiff($before, $after, 'wp-content/plugins/acme-card/block.json', 'JSON', [
            'language' => 'json',
            'exitCode' => true,
        ], [
            'DFT_DISPLAY' => 'side-by-side-show-both',
            'DFT_CONTEXT' => '0',
            'DFT_TAB_WIDTH' => '2',
            'DFT_WIDTH' => '44',
        ]);

        $t->same(DiffCommandRunner::EXIT_FOUND_CHANGES, $result['exitCode']);
        $t->same('', $result['stderr']);
        $t->contains('"title": "Card",', $result['stdout']);
        $t->contains('"title": "Editori', $result['stdout']);
        $t->contains('al Card",', $result['stdout']);
        $t->contains('"viewScriptModule', $result['stdout']);
        $t->contains('file:./view.js', $result['stdout']);
        $t->true(!str_contains($result['stdout'], "\t"), 'Environment-sourced tab width should make tabbed block metadata deterministic.');
        $t->true(!str_contains($result['stdout'], '"apiVersion"'), 'Context zero should keep unchanged block metadata headers out of the command display.');
    },
    'wordpress command env unstable json display emits block metadata review' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-json-before.json');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-json-after.json');
        $result = (new DiffCommandRunner())->runTextDiff($before, $after, 'wp-content/plugins/acme-card/block.json', 'JSON', [
            'language' => 'json',
            'exitCode' => true,
        ], [
            'DFT_DISPLAY' => 'json',
            'DFT_UNSTABLE' => 'yes',
        ]);
        $decoded = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

        $t->same(DiffCommandRunner::EXIT_FOUND_CHANGES, $result['exitCode']);
        $t->same('', $result['stderr']);
        $t->same('wp-content/plugins/acme-card/block.json', $decoded['path']);
        $t->same('JSON', $decoded['language']);
        $t->same('changed', $decoded['status']);
        $t->true(isset($decoded['aligned_lines']), 'Block metadata JSON command output should include upstream-style aligned lines.');
        $t->true(isset($decoded['chunks']), 'Block metadata JSON command output should include review chunks.');
        $t->contains('viewScriptModule', $result['stdout']);
        $t->contains('Card', $result['stdout']);
        $t->contains('Editorial', $result['stdout']);
    },
    'wordpress git backed common path inline display keeps repository suffix' => static function (TestRunner $t): void {
        $before = "{\n  \"apiVersion\": 3,\n  \"name\": \"acme/card\",\n  \"title\": \"Legacy Card\",\n  \"supports\": {\n    \"html\": false\n  }\n}\n";
        $after = "{\n  \"apiVersion\": 3,\n  \"name\": \"acme/card\",\n  \"title\": \"Modern Card\",\n  \"viewScriptModule\": \"file:./view.js\",\n  \"supports\": {\n    \"html\": true\n  }\n}\n";
        $display = (new InlineDiffRenderer())->renderPathArgumentsTextDiff($before, $after, [
            '/srv/releases/old/wp-content/plugins/acme-card/block.json',
            '/srv/releases/new/wp-content/plugins/acme-card/block.json',
        ], [
            'language' => 'json',
            'contextLines' => 1,
        ]);

        $t->contains('wp-content/plugins/acme-card/block.json --- JSON', $display);
        $t->contains('Legacy Card', $display);
        $t->contains('Modern Card', $display);
        $t->contains('viewScriptModule', $display);
        $t->true(!str_contains($display, '/srv/releases/new'), 'Git-backed WordPress review headers should show the stable repository suffix, not checkout-specific release roots.');
    },
    'maps upstream binary changed cli removed status' => static function (TestRunner $t): void {
        $binary = "\x89PNG\r\n\x1a\n" . str_repeat("\0", 2048);
        $display = (new InlineDiffRenderer())->renderBinaryDiff($binary, '', [
            'path' => 'img/logo.png',
        ]);

        $t->contains('img/logo.png --- Binary', $display);
        $t->contains('Binary file removed', $display);
        $t->true(!str_contains($display, 'No syntactic changes'), 'Binary inline output should use the upstream binary status message, not the text no-change message.');
    },
    'maps upstream binary override cli modified status' => static function (TestRunner $t): void {
        $before = "console.log('legacy');\n";
        $after = "console.log('modern');\n";
        $display = (new InlineDiffRenderer())->renderBinaryDiff($before, $after, [
            'path' => 'sample_files/simple_1.js',
        ]);
        $decoded = (new JsonDiffRenderer())->fileBytesDiff(
            $before,
            $after,
            'sample_files/simple_1.js',
            'JavaScript',
            ['forceBinary' => true],
        );

        $t->contains('sample_files/simple_1.js --- Binary', $display);
        $t->contains('Binary file modified (old: 23 B, new: 23 B).', $display);
        $t->same(['language' => 'Binary', 'path' => 'sample_files/simple_1.js', 'status' => 'changed'], $decoded);
    },
    'maps upstream binary override globs before text heuristics' => static function (TestRunner $t): void {
        $before = "console.log('legacy');\n";
        $after = "console.log('modern');\n";
        $decoder = new FileContentDecoder();
        $normal = (new JsonDiffRenderer())->fileBytesDiff(
            $before,
            $after,
            'sample_files/simple_1.js',
            'JavaScript',
            ['language' => 'javascript'],
        );
        $forced = (new JsonDiffRenderer())->fileBytesDiff(
            $before,
            $after,
            'sample_files/simple_1.js',
            'JavaScript',
            [
                'language' => 'javascript',
                'binaryOverrides' => ['*.js'],
            ],
        );

        $t->same("console.log('legacy');\n", $decoder->guessTextContent($before, 'sample_files/simple_1.js'));
        $t->same(null, $decoder->guessTextContent($before, 'sample_files/simple_1.js', ['*.js']));
        $t->same('JavaScript', $normal['language']);
        $t->same('changed', $normal['status']);
        $t->true(isset($normal['chunks']), 'Without an override, valid UTF-8 JavaScript should still be decoded as text.');
        $t->same(['language' => 'Binary', 'path' => 'sample_files/simple_1.js', 'status' => 'changed'], $forced);
    },
    'maps upstream binary override environment aggregation' => static function (TestRunner $t): void {
        $runner = new DiffCommandRunner();
        $parsed = $runner->parseBinaryOverrides(['*.zip'], [
            'DFT_OVERRIDE_BINARY' => '*.gz',
            'DFT_OVERRIDE_BINARY_1' => '*.min.js',
            'DFT_OVERRIDE_BINARY_2' => 'vendor/*.pickle',
            'DFT_OVERRIDE_BINARY_9' => 'legacy/*.dat',
            'DFT_OVERRIDE_BINARY_10' => '*.ignored',
        ]);

        $t->same([
            '*.zip',
            '*.gz',
            '*.min.js',
            'vendor/*.pickle',
            'legacy/*.dat',
        ], $parsed['globs']);
        $t->same([], $parsed['errors']);
    },
    'rejects invalid binary override globs like upstream command parsing' => static function (TestRunner $t): void {
        $result = (new DiffCommandRunner())->runJsonFileBytesDiff(
            "console.log('legacy');\n",
            "console.log('modern');\n",
            'sample_files/simple_1.js',
            'JavaScript',
            [],
            ['DFT_OVERRIDE_BINARY' => '*.js['],
        );

        $t->same(DiffCommandRunner::EXIT_BAD_ARGUMENTS, $result['exitCode']);
        $t->same('', $result['stdout']);
        $t->same(false, $result['hasChanges']);
        $t->same(null, $result['file']);
        $t->contains("Invalid glob syntax '*.js['", $result['stderr']);
        $t->contains('Glob parsing error: unclosed character class', $result['stderr']);
    },
    'wordpress command env binary overrides route into directory byte review' => static function (TestRunner $t): void {
        $fixtures = dirname(__DIR__) . '/fixtures';
        $result = (new DiffCommandRunner())->runJsonDirectoryDiff(
            $fixtures . '/wordpress-binary-override-before',
            $fixtures . '/wordpress-binary-override-after',
            [
                'sortPaths' => true,
                'exitCode' => true,
            ],
            [
                'DFT_OVERRIDE_BINARY' => '*.png',
                'DFT_OVERRIDE_BINARY_1' => '*.min.js',
            ],
        );

        $decoded = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

        $t->same(DiffCommandRunner::EXIT_FOUND_CHANGES, $result['exitCode']);
        $t->same('', $result['stderr']);
        $t->same(true, $result['hasChanges']);
        $t->same($decoded, $result['files']);
        $t->same(1, count($decoded));
        $t->same('wp-content/plugins/acme-card/build/index.min.js', $decoded[0]['path']);
        $t->same('Binary', $decoded[0]['language']);
        $t->same('changed', $decoded[0]['status']);
        $t->true(!isset($decoded[0]['chunks']), 'Environment-sourced binary overrides should reach byte-level directory review.');
    },
    'wordpress binary asset inline display reports modified plugin media' => static function (TestRunner $t): void {
        $pngHeader = "\x89PNG\r\n\x1a\n";
        $before = $pngHeader . str_repeat("\0", 16) . 'legacy-logo-bytes';
        $after = $pngHeader . str_repeat("\0", 16) . 'modern-logo-bytes-with-retina-metadata';
        $display = (new InlineDiffRenderer())->renderBinaryDiff($before, $after, [
            'path' => 'wp-content/plugins/acme-card/assets/logo.png',
            'extraInfo' => 'Binary asset changed during block branding update.',
        ]);

        $t->contains('wp-content/plugins/acme-card/assets/logo.png --- Binary', $display);
        $t->contains('Binary asset changed during block branding update.', $display);
        $t->contains('Binary file modified', $display);
    },
    'wordpress directory diff can force generated assets to binary via override glob' => static function (TestRunner $t): void {
        $fixtures = dirname(__DIR__) . '/fixtures';
        $files = (new DirectoryDiffer())->diffDirectories(
            $fixtures . '/wordpress-binary-override-before',
            $fixtures . '/wordpress-binary-override-after',
            [
                'binaryOverrides' => ['*.min.js'],
            ],
        );

        $t->same(1, count($files));
        $t->same('wp-content/plugins/acme-card/build/index.min.js', $files[0]['path']);
        $t->same('Binary', $files[0]['language']);
        $t->same('changed', $files[0]['status']);
        $t->true(!isset($files[0]['chunks']), 'Generated minified assets forced to binary should render as a status envelope, not text chunks.');
    },
    'wordpress readme inline display keeps path header and compact context' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-footer-before.txt');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-footer-after.txt');
        $display = (new InlineDiffRenderer())->renderTextDiff($before, $after, [
            'path' => 'wp-content/plugins/acme-review-tools/readme.txt',
            'language' => 'text',
            'contextLines' => 1,
        ]);

        $t->contains('wp-content/plugins/acme-review-tools/readme.txt --- Text', $display);
        $t->contains('legacy', $display);
        $t->contains('modern', $display);
        $t->contains('Frequently Asked Questions', $display);
        $t->true(!str_contains($display, 'Stable tag: 1.3.0'), 'Distant stable readme metadata should stay out of compact inline review output.');
    },
    'wordpress git backed plugin rename inline display keeps git metadata' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-git-rename-render-before.php');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-git-rename-render-after.php');
        $display = (new InlineDiffRenderer())->renderGitExternalTextDiff(
            $before,
            $after,
            [
                'wp-content/plugins/acme-card/src/render-card.php',
                '/tmp/git-blob-old/render-card.php',
                'oldhash',
                '100644',
                '/tmp/git-blob-new/render-card.php',
                'newhash',
                '100755',
                'wp-content/plugins/acme-card/includes/render-card.php',
                'similarity 88%',
            ],
            [
                'language' => 'php',
                'contextLines' => 1,
            ],
        );

        $t->contains('wp-content/plugins/acme-card/includes/render-card.php --- PHP', $display);
        $t->contains('Renamed from wp-content/plugins/acme-card/src/render-card.php to wp-content/plugins/acme-card/includes/render-card.php', $display);
        $t->contains('File permissions changed from 100644 to 100755.', $display);
        $t->contains('acme_render_legacy_card', $display);
        $t->contains('acme_render_modern_card', $display);
    },
    'maps upstream side by side created files as single column by default' => static function (TestRunner $t): void {
        $display = (new SideBySideDiffRenderer())->renderTextDiff('', "alpha\tasset\nbeta asset\n", [
            'tabWidth' => 4,
            'columnWidth' => 20,
        ]);

        $t->same("1 alpha    asset\n2 beta asset\n3 \n", $display);
    },
    'maps upstream side by side deleted files as single column by default' => static function (TestRunner $t): void {
        $display = (new SideBySideDiffRenderer())->renderTextDiff("old render.php\nlegacy asset\n", '', [
            'columnWidth' => 20,
        ]);

        $t->same("1 old render.php\n2 legacy asset\n3 \n", $display);
    },
    'maps upstream side by side show both mode for created files' => static function (TestRunner $t): void {
        $display = (new SideBySideDiffRenderer())->renderTextDiff('', "alpha asset\nbeta asset\n", [
            'columnWidth' => 20,
            'showBoth' => true,
        ]);

        $t->contains('.                       1 alpha asset', $display);
        $t->contains('.                       2 beta asset', $display);
    },
    'maps upstream long line sample shape with linear display width wrapping' => static function (TestRunner $t): void {
        $renderer = new SideBySideDiffRenderer();
        $line = str_repeat('abcdefghij', 16000);
        $parts = $renderer->splitLineForDisplay($line, 80, 8, 'left');

        $t->same(2000, count($parts));
        $t->same(str_repeat('abcdefghij', 8), $parts[0]);
        $t->same(str_repeat('abcdefghij', 8), $parts[1999]);
        $t->same(80, max(array_map('strlen', $parts)));
    },
    'wordpress large single-line asset manifest display stays bounded' => static function (TestRunner $t): void {
        $asset = static fn (int $index): string => '{"handle":"acme-card-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT) . '","src":"file:./build/card-' . $index . '.js"}';
        $before = '{"version":"1.0.0","assets":[' . implode(',', array_map($asset, range(0, 64))) . ']}';
        $after = '{"version":"1.1.0","assets":[{"handle":"acme-card-view","src":"file:./build/view.js"},' . implode(',', array_map($asset, range(0, 64))) . ']}';
        $display = (new SideBySideDiffRenderer())->renderTextDiff($before, $after, [
            'tabWidth' => 4,
            'columnWidth' => 72,
        ]);
        $lines = array_values(array_filter(explode("\n", $display), static fn (string $line): bool => $line !== ''));

        $t->true(count($lines) > 40, 'Large single-line asset manifests should wrap over multiple display rows.');
        $t->true(max(array_map('strlen', $lines)) <= 150, 'Wrapped side-by-side rows should stay bounded by the configured display column width.');
        $t->contains('acme-card-view', $display);
        $t->contains('"version":"1.1.0"', $display);
        $t->contains('. ', $display);
    },
    'wordpress minified asset map display wraps multibyte labels at display width' => static function (TestRunner $t): void {
        $asset = static fn (int $index): string => '{"handle":"acme-card-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT) . '","label":"カード📦' . $index . '","src":"file:./build/card-' . $index . '.js"}';
        $before = '{"version":"1.0.0","assets":[' . implode(',', array_map($asset, range(0, 32))) . ']}';
        $after = '{"version":"1.1.0","assets":[{"handle":"acme-card-view","label":"ビュー📦","src":"file:./build/view.js"},' . implode(',', array_map($asset, range(0, 32))) . ']}';
        $renderer = new SideBySideDiffRenderer();
        $display = $renderer->renderTextDiff($before, $after, [
            'tabWidth' => 4,
            'columnWidth' => 40,
        ]);
        $lines = array_values(array_filter(explode("\n", $display), static fn (string $line): bool => $line !== ''));

        $t->true(count($lines) > 40, 'Minified asset maps should wrap over many bounded display rows.');
        $t->true(max(array_map(static fn (string $line): int => $renderer->displayWidth($line, 4), $lines)) <= 86, 'Rows should remain bounded by display width even with CJK and emoji labels.');
        $t->contains('ビュー📦', $display);
        $t->contains('file:./build/view.js', $display);
        $t->contains('. ', $display);
    },
    'wordpress tabbed block metadata display expands tabs for review' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-tabbed-block-json-before.json');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-tabbed-block-json-after.json');
        $display = (new SideBySideDiffRenderer())->renderTextDiff($before, $after, [
            'tabWidth' => 4,
            'columnWidth' => 48,
        ]);

        $t->contains('    "title": "Card",', $display);
        $t->contains('    "title": "Editorial Card",', $display);
        $t->contains('    "viewScriptModule": "file:./view.js",', $display);
        $t->contains('        "html": false', $display);
        $t->contains('        "html": true', $display);
        $t->true(!str_contains($display, "\t"), 'Block metadata review output should not depend on browser or terminal tab stops.');
    },
    'wordpress created import report side by side uses single column' => static function (TestRunner $t): void {
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-created-import-report-after.txt');
        $display = (new SideBySideDiffRenderer())->renderTextDiff('', $after, [
            'tabWidth' => 4,
            'columnWidth' => 64,
        ]);

        $t->contains('1 Post ID,Status,Notes', $display);
        $t->contains('4 44,queued,Needs media sideload retry', $display);
        $t->true(!str_contains($display, '  . '), 'Created WordPress report should not reserve an empty opposite column by default.');
    },
    'wordpress block pattern context display omits distant stable patterns' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-pattern-context-before.php');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-pattern-context-after.php');
        $display = (new SideBySideDiffRenderer())->renderTextDiff($before, $after, [
            'tabWidth' => 4,
            'columnWidth' => 64,
            'contextLines' => 1,
        ]);

        $t->contains('Landing Hero', $display);
        $t->contains("'footer', 'site'", $display);
        $t->contains(' ...', $display);
        $t->true(!str_contains($display, 'Testimonial'), 'Unchanged middle block patterns should stay out of compact side-by-side review output.');
        $t->true(!str_contains($display, 'Gallery'), 'A distant stable pattern should not be displayed when it is outside the context window.');
    },
    'recurses into nested wordpress registration arrays' => static function (TestRunner $t): void {
        $before = "register_block_type('demo/card', ['supports' => ['html' => false, 'align' => ['wide']], 'render_callback' => 'old_card']);";
        $after = "register_block_type('demo/card', ['supports' => ['html' => true, 'align' => ['wide', 'full']], 'render_callback' => 'old_card']);";
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('$[0][1]/[0][0]/[0][0]', $encoded);
        $t->contains('- $[0][1]/[0][0]/[0][0] \'html\'=>false', $encoded);
        $t->contains('+ $[0][1]/[0][0]/[0][0] \'html\'=>true', $encoded);
        $t->contains('+ $[0][1]/[0][0]/[0][1]/[0][1] \'full\'', $encoded);
    },
    'wordpress render callback diff hides comment churn but keeps api changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-render-callback-before.php');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-render-callback-after.php');

        $ops = (new TokenDiffer())->diff($before, $after, ['ignoreComments' => true]);
        $encoded = implode('', array_map(static fn (array $op): string => $op['op'] . $op['text'], $ops));

        $t->contains('-esc_html', $encoded);
        $t->contains('+wp_kses_post', $encoded);
        $t->true(!str_contains($encoded, 'Classic template fallback'), 'Comment-only churn should be filtered.');
    },
    'wordpress render callback diff reports nullable return type changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-render-return-type-before.php');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-render-return-type-after.php');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'php',
            'title' => 'Render callback return type diff',
        ]);

        $t->contains('Render callback return type diff', $html);
        $t->contains('data-path="$php.function.acme_render_card.return_type"', $html);
        $t->contains('<del>string</del><ins>?string</ins>', $html);
        $t->contains('returnnull', $html);
        $t->true(!str_contains($html, 'wp-block-acme-card'), 'Stable markup returned by the callback should stay out of the rendered change stream.');
    },
    'wordpress block style slug diff reports subword changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-style-before.php');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-style-after.php');
        $ops = (new TokenDiffer())->diffWords($before, $after, ['splitNumbers' => true]);
        $encoded = implode('', array_map(static fn (array $op): string => $op['op'] . $op['text'], $ops));

        $t->contains('-legacy', $encoded);
        $t->contains('+modern', $encoded);
        $t->contains('-2', $encoded);
        $t->contains('+3', $encoded);
    },
    'html token renderer escapes source and preserves operation markers' => static function (TestRunner $t): void {
        $html = (new HtmlDiffRenderer())->renderTokenDiff(
            "return '<section>';",
            "return '<script>';",
            ['title' => 'Render callback <change>'],
        );

        $t->contains('class="difftastic-token-diff"', $html);
        $t->contains('Render callback &lt;change&gt;', $html);
        $t->contains('data-op="-"', $html);
        $t->contains('data-op="+"', $html);
        $t->contains('&lt;script&gt;', $html);
    },
    'html word renderer reports wordpress subword additions and deletions' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-style-before.php');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-style-after.php');
        $html = (new HtmlDiffRenderer())->renderWordDiff($before, $after, [
            'splitNumbers' => true,
            'title' => 'Block style subword diff',
        ]);

        $t->contains('class="difftastic-word-diff"', $html);
        $t->contains('<span class="dft-del" data-op="-">legacy</span>', $html);
        $t->contains('<span class="dft-add" data-op="+">modern</span>', $html);
        $t->contains('<span class="dft-del" data-op="-">2</span>', $html);
        $t->contains('<span class="dft-add" data-op="+">3</span>', $html);
    },
    'html syntax-list renderer reports wordpress theme json palette changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-theme-json-before.json');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-theme-json-after.json');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'title' => 'theme.json palette syntax-list diff',
        ]);

        $t->contains('class="difftastic-syntax-list-diff"', $html);
        $t->contains('data-difftastic-display="syntax-list"', $html);
        $t->contains('data-path=', $html);
        $t->contains('&quot;tertiary&quot;', $html);
        $t->contains('&quot;#16a34a&quot;', $html);
    },
    'html syntax-list renderer reports wordpress block markup tag changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-markup-before.html');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-markup-after.html');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'html',
            'title' => 'Block markup <tag> diff',
        ]);

        $t->contains('Block markup &lt;tag&gt; diff', $html);
        $t->contains('divclass=&quot;wp-block-group hero is-style-card&quot;', $html);
        $t->contains('h2id=&quot;featured&quot;', $html);
        $t->contains('&lt;strong&gt;', $html);
        $t->contains('&lt;/strong&gt;', $html);
    },
    'json syntax-list renderer reports wordpress block metadata key changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-json-before.json');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-json-after.json');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'json',
            'title' => 'block.json key-aware diff',
        ]);

        $t->contains('block.json key-aware diff', $html);
        $t->contains('&quot;title&quot;:&quot;Card&quot;', $html);
        $t->contains('&quot;title&quot;:&quot;Editorial Card&quot;', $html);
        $t->contains('&quot;viewScriptModule&quot;:&quot;file:./view.js&quot;', $html);
        $t->contains('&quot;html&quot;:false', $html);
        $t->contains('&quot;html&quot;:true', $html);
        $t->contains('&quot;full&quot;', $html);
    },
    'json syntax-list renderer reports wordpress theme variation deletions' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-theme-variations-before.json');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-theme-variations-after.json');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'json',
            'title' => 'theme.json variation deletion diff',
        ]);

        $t->contains('theme.json variation deletion diff', $html);
        $t->contains('data-op="-"', $html);
        $t->contains('&quot;deprecated-legacy&quot;', $html);
        $t->contains('&quot;deprecated-dark&quot;', $html);
        $t->true(!str_contains($html, '&quot;primary&quot;'), 'Unchanged variations should not be rendered as deleted.');
    },
    'wordpress template wrapper diff reports wrappers without deleting inner block' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-template-wrapper-before.php');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-template-wrapper-after.php');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'title' => 'Template wrapper syntax-list diff',
        ]);

        $t->contains('Template wrapper syntax-list diff', $html);
        $t->contains('data-path="$[0][0]/[0][0]/wrap0"', $html);
        $t->contains('coreGroup(...)', $html);
        $t->true(!str_contains($html, 'coreParagraph(&#039;Hero introduction&#039;)'), 'The retained inner block call should not be rendered as deleted.');
    },
    'wordpress block allow-list array syntax keeps retained items stable' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-array-syntax-before.php');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-array-syntax-after.php');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'title' => 'Block allow-list array syntax diff',
        ]);

        $t->contains('Block allow-list array syntax diff', $html);
        $t->contains('data-path="$[0]/delimiters"', $html);
        $t->contains('<del>()</del><ins>[]</ins>', $html);
        $t->true(!str_contains($html, '&#039;core/paragraph&#039;'), 'Retained block names should not be rendered as changed by array syntax modernization.');
        $t->true(!str_contains($html, '&#039;core/image&#039;'), 'Retained block names should not be rendered as changed by array syntax modernization.');
    },
    'wordpress block style css diff keeps reordered selectors stable' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-style-css-before.css');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-style-css-after.css');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'css',
            'title' => 'Block style CSS selector diff',
        ]);

        $t->contains('Block style CSS selector diff', $html);
        $t->contains('data-path="$css[&quot;.wp-block-acme-card&quot;][0]/(0)[0]"', $html);
        $t->contains('--wp--preset--color--primary', $html);
        $t->contains('--wp--preset--color--accent', $html);
        $t->contains('border-radius:4px;', $html);
        $t->contains('wp-block-query-title', $html);
        $t->true(!str_contains($html, 'wp-block-image'), 'Reordered stable block style selectors should stay out of the rendered change stream.');
    },
    'wordpress block editor scss diff reports mixin header and nested color changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-editor-scss-before.scss');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-editor-scss-after.scss');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'scss',
            'title' => 'Block editor SCSS mixin diff',
        ]);

        $t->contains('Block editor SCSS mixin diff', $html);
        $t->contains('data-path="$css[&quot;@mixinacme-card&quot;]/selector"', $html);
        $t->contains('$radius:4px', $html);
        $t->contains('$radius:6px', $html);
        $t->contains('--wp--preset--color--primary', $html);
        $t->contains('--wp--preset--color--accent', $html);
        $t->true(!str_contains($html, 'data-op="-" data-path="$css[&quot;@mixinacme-card&quot;]"'), 'SCSS mixin changes should not render as a whole-rule deletion.');
    },
    'wordpress nested at-rule css diff keeps reordered inner selectors stable' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-nested-at-rule-css-before.css');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-nested-at-rule-css-after.css');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'css',
            'title' => 'Nested at-rule block style diff',
        ]);

        $t->contains('Nested at-rule block style diff', $html);
        $t->contains('data-path="$css[&quot;@media&quot;][&quot;.wp-block-acme-card&quot;][0]"', $html);
        $t->contains('padding:16px;', $html);
        $t->contains('padding:20px;', $html);
        $t->contains('border-radius:4px;', $html);
        $t->contains('data-path="$css[&quot;@supports&quot;][&quot;.wp-block-acme-card&quot;][1]"', $html);
        $t->contains('grid-template-columns:minmax(0,1fr)auto;', $html);
        $t->true(!str_contains($html, 'wp-block-image'), 'Reordered nested stable selectors inside at-rules should stay out of the rendered change stream.');
    },
    'wordpress plugin readme text diff reports changelog insertions' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-readme-before.txt');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-readme-after.txt');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'text',
            'title' => 'Plugin readme text diff',
        ]);

        $t->contains('Plugin readme text diff', $html);
        $t->contains('data-path="$text.line[3]"', $html);
        $t->contains('<del>Stable tag: 1.2.0</del><ins>Stable tag: 1.3.0</ins>', $html);
        $t->contains('data-path="$text.line[9]"', $html);
        $t->contains('= 1.3.0 =', $html);
        $t->contains('Add Interactivity API view script support.', $html);
        $t->true(!str_contains($html, 'data-op="-" data-path="$text.line[10]"'), 'Retained changelog entries should remain matched after a new release section is inserted.');
    },
    'wordpress plugin readme blank line display is not hidden as unchanged' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-readme-blank-before.txt');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-readme-blank-after.txt');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-events/readme.txt',
            'Text',
            ['language' => 'text'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same('changed', $decoded['status']);
        $t->same('wp-content/plugins/acme-events/readme.txt', $decoded['path']);
        $t->same([7, null], $decoded['aligned_lines'][7]);
        $t->same(7, $decoded['chunks'][0][0]['lhs']['line_number']);
        $t->same([], $decoded['chunks'][0][0]['lhs']['changes']);
    },
    'wordpress inline html style diff reports css sublanguage changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-template-style-before.html');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-template-style-after.html');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'html',
            'title' => 'Inline block style sub-language diff',
        ]);

        $t->contains('Inline block style sub-language diff', $html);
        $t->contains('data-path="$html.style.css[&quot;.wp-block-acme-card&quot;][0]/(0)[0]"', $html);
        $t->contains('--wp--preset--color--primary', $html);
        $t->contains('--wp--preset--color--accent', $html);
        $t->contains('data-path="$html.style.css[&quot;@media&quot;][&quot;.wp-block-acme-card&quot;][0]"', $html);
        $t->contains('gap:1rem;', $html);
        $t->contains('gap:1.5rem;', $html);
        $t->contains('wp-block-query-title', $html);
        $t->true(!str_contains($html, 'data-path="$html.style.css[&quot;@media&quot;][&quot;.wp-block-image&quot;]'), 'Reordered stable CSS inside inline HTML style blocks should stay matched at the CSS sublanguage path.');
    },
    'wordpress inline html script diff reports javascript sublanguage changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-interactivity-script-before.html');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-interactivity-script-after.html');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'html']);
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'html',
            'title' => 'Interactivity script sub-language diff',
        ]);
        $rawScriptChanges = array_values(array_filter($changes, static function (array $change): bool {
            if (!str_starts_with($change['path'], '$[')) {
                return false;
            }

            $text = ($change['text'] ?? '') . ' ' . ($change['old'] ?? '') . ' ' . ($change['new'] ?? '');

            return str_contains($text, "label:'Show details'")
                || str_contains($text, "label:'Read details'")
                || str_contains($text, 'expanded:false')
                || str_contains($text, 'expanded:true');
        }));

        $t->contains('Interactivity script sub-language diff', $html);
        $t->contains('data-path="$html.script.js.call[&quot;wp.interactivity.store&quot;][1]/{0}[0]/{0}[0]"', $html);
        $t->contains('label:&#039;Show details&#039;', $html);
        $t->contains('label:&#039;Read details&#039;', $html);
        $t->contains('expanded:false', $html);
        $t->contains('expanded:true', $html);
        $t->same([], $rawScriptChanges, 'WordPress inline script raw body changes should only appear under the JavaScript sub-language path.');
    },
    'wordpress multi inline asset diff indexes style and script sublanguage blocks' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-multi-asset-html-before.html');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-multi-asset-html-after.html');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'html']);
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'html',
            'title' => 'Multi inline asset sub-language diff',
        ]);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('+ $html.style[0].css[".wp-block-acme-notice"] .wp-block-acme-notice{margin-block-start:1rem;}', $encoded);
        $t->contains('- $html.style[1].css[".wp-block-acme-card"][0]/(0)[0] --wp--preset--color--primary', $encoded);
        $t->contains('+ $html.style[1].css[".wp-block-acme-card"][1] border-radius:6px;', $encoded);
        $t->contains('~ $html.style[2].css[".wp-site-blocks"][0] gap:1rem; gap:1.5rem;', $encoded);
        $t->contains('~ $html.script[0].js.call["wp.interactivity.store"][1]/{0}[0]/{0}[0] label:\'Show details\' label:\'Read details\'', $encoded);
        $t->contains('+ $html.script[1].js.call["wp.interactivity.store"] wp.interactivity.store(\'acme/analytics\'', $encoded);
        $t->true(!str_contains($encoded, '$html.script[2].js.call["wp.interactivity.store"]'), 'Retained gallery interactivity store should remain matched after inserting another script block.');
        $t->contains('data-path="$html.style[1].css[&quot;.wp-block-acme-card&quot;][1]"', $html);
        $t->contains('data-path="$html.script[1].js.call[&quot;wp.interactivity.store&quot;]"', $html);
    },
    'wordpress view script diff reports javascript block wrappers and array insertions' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-view-script-before.js');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-view-script-after.js');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'javascript',
            'title' => 'Block view script JavaScript diff',
        ]);

        $t->contains('Block view script JavaScript diff', $html);
        $t->contains('data-path="$js.block[&quot;if&quot;][0]"', $html);
        $t->contains('if(window.wp)', $html);
        $t->contains('data-path="$js.array[&quot;actions&quot;][1]"', $html);
        $t->contains('&#039;share&#039;', $html);
        $t->contains('expanded:false', $html);
        $t->contains('expanded:true', $html);
        $t->true(!str_contains($html, 'data-path="$js.array[&quot;actions&quot;][2]">dismiss'), 'The retained dismiss action should stay aligned after the share insertion.');
    },
    'wordpress hook registration diff keeps named hook callbacks aligned' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-hook-registration-before.js');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-hook-registration-after.js');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'javascript',
            'title' => 'WordPress hook registration JavaScript diff',
        ]);

        $t->contains('WordPress hook registration JavaScript diff', $html);
        $t->contains('acme.card.analytics', $html);
        $t->contains('bindCard', $html);
        $t->true(!str_contains($html, '<del>&#039;acme.card.init&#039;</del><ins>&#039;acme.card.analytics&#039;</ins>'), 'A newly inserted hook callback should not be paired with the retained init hook by callee name alone.');
        $t->true(!str_contains($html, 'data-op="-" data-path="$js.call[&quot;wp.hooks.addFilter&quot;]"'), 'The stable addFilter registration should remain matched.');
    },
    'wordpress block registration diff keeps repeated global calls scoped to functions' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-registration-functions-before.js');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-registration-functions-after.js');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'javascript',
            'title' => 'WordPress block registration function diff',
        ]);

        $t->contains('WordPress block registration function diff', $html);
        $t->contains('data-path="$js.function[&quot;registerCardBlock&quot;].call[&quot;wp.i18n.setLocaleData&quot;]"', $html);
        $t->contains('data-path="$js.function[&quot;registerQueryBlock&quot;].call[&quot;wp.blocks.registerBlockType&quot;]"', $html);
        $t->contains('wp.blocks.registerBlockType(&quot;acme/query&quot;,querySettings)', $html);
        $t->true(!str_contains($html, '<del>&quot;acme/gallery&quot;</del><ins>&quot;acme/query&quot;</ins>'), 'A new block registration function should not be paired with a retained function only because both call wp.blocks.registerBlockType.');
        $t->true(!str_contains($html, 'data-path="$js.call[&quot;functionregisterQueryBlock&quot;]"'), 'Function declarations should not render as fake calls in WordPress block scripts.');
    },
    'wordpress block editor javascript syntax errors fall back to text diff' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-editor-syntax-error-before.js');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-editor-syntax-error-after.js');
        $differ = new TokenDiffer();
        $changes = $differ->diffSyntaxLists($before, $after, ['language' => 'javascript']);
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'javascript',
            'title' => 'Block editor JavaScript parse fallback diff',
        ]);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->same('6 JavaScript parse errors, exceeded DFT_PARSE_ERROR_LIMIT', $differ->syntaxErrorFallbackReason($before, $after, ['language' => 'javascript']));
        $t->contains('~ $text.fallback Text (6 JavaScript parse errors, exceeded DFT_PARSE_ERROR_LIMIT) line-oriented diff', $encoded);
        $t->contains('~ $text.line[4]', $encoded);
        $t->contains('Legacy panel', $encoded);
        $t->contains('Modern panel', $encoded);
        $t->contains('+ $text.line[6]     scope: \'edit\',', $encoded);
        $t->true(!str_contains($encoded, '$js.call["registerPlugin"]'), 'Parse-error fallback should avoid misleading structured JavaScript call matching.');
        $t->contains('data-path="$text.fallback"', $html);
        $t->contains('Text (6 JavaScript parse errors, exceeded DFT_PARSE_ERROR_LIMIT)', $html);
    },
    'wordpress python migration guard diff keeps stable if header aligned' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-python-migration-if-before.py');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-python-migration-if-after.py');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'python',
            'title' => 'WordPress Python migration guard diff',
        ]);

        $t->contains('WordPress Python migration guard diff', $html);
        $t->contains('data-path="$py.if[&quot;post.get(\&quot;legacy_builder\&quot;)&quot;][1]"', $html);
        $t->contains('data-path="$py.root[0]"', $html);
        $t->contains('purge_builder_shortcodes(post)', $html);
        $t->true(!str_contains($html, 'data-op="-" data-path="$py.if[&quot;post.get(\&quot;legacy_builder\&quot;)&quot;]">if post.get'), 'Stable Python migration guard headers should not be deleted when a cleanup call is unindented.');
    },
    'wordpress python migration loop diff keeps stable for header aligned' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-python-loop-migration-before.py');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-python-loop-migration-after.py');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'python',
            'title' => 'WordPress Python migration loop diff',
        ]);

        $t->contains('WordPress Python migration loop diff', $html);
        $t->contains('data-path="$py.for[&quot;post in posts&quot;][1]"', $html);
        $t->contains('data-path="$py.root[1]"', $html);
        $t->contains('hydrate_featured_media(post)', $html);
        $t->true(!str_contains($html, 'data-op="-" data-path="$py.for[&quot;post in posts&quot;]">for post in posts'), 'Stable Python for headers should not be deleted when a migration helper call is unindented.');
    },
    'wordpress python nested migration diff keeps def and for headers aligned' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-python-nested-migration-before.py');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-python-nested-migration-after.py');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'python',
            'title' => 'WordPress Python nested migration diff',
        ]);

        $t->contains('WordPress Python nested migration diff', $html);
        $t->contains('data-path="$py.def[&quot;migrate_posts&quot;].for[&quot;post in posts&quot;].if[&quot;post.get(\&quot;featured_media\&quot;)&quot;]"', $html);
        $t->contains('hydrate_featured_media(post)', $html);
        $t->true(!str_contains($html, 'data-op="-" data-path="$py.def[&quot;migrate_posts&quot;]"'), 'Stable migration function should not be deleted when a nested guard is inserted.');
        $t->true(!str_contains($html, 'data-op="-" data-path="$py.def[&quot;migrate_posts&quot;].for[&quot;post in posts&quot;]"'), 'Stable migration loop should not be deleted when a nested guard is inserted.');
        $t->true(!str_contains($html, 'normalize_blocks(post)</code>'), 'Retained direct loop statements should stay out of the rendered change stream.');
    },
    'wordpress python compound migration diff reports elif and finally clauses' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-python-compound-migration-before.py');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-python-compound-migration-after.py');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'python',
            'title' => 'WordPress Python compound migration diff',
        ]);

        $t->contains('WordPress Python compound migration diff', $html);
        $t->contains('data-path="$py.def[&quot;migrate_post&quot;].if[&quot;post.get(\&quot;legacy_builder\&quot;)&quot;].elif[&quot;post.get(\&quot;raw_html\&quot;)&quot;]"', $html);
        $t->contains('sanitize_raw_html(post)', $html);
        $t->contains('data-path="$py.def[&quot;migrate_post&quot;].try[&quot;try&quot;].finally[&quot;finally&quot;]"', $html);
        $t->contains('cleanup_temp_media(post)', $html);
        $t->true(!str_contains($html, 'data-op="-" data-path="$py.def[&quot;migrate_post&quot;].if[&quot;post.get(\&quot;legacy_builder\&quot;)&quot;]"'), 'Stable migration if block should not be deleted when an elif branch is inserted.');
        $t->true(!str_contains($html, 'data-op="-" data-path="$py.def[&quot;migrate_post&quot;].try[&quot;try&quot;]"'), 'Stable migration try block should not be deleted when a finally cleanup is inserted.');
        $t->true(!str_contains($html, 'data-op="-" data-path="$py.def[&quot;migrate_post&quot;].try[&quot;try&quot;].except[&quot;ValueError as error&quot;]"'), 'Stable exception handler should remain matched when a finally cleanup is inserted.');
    },
    'wordpress block variation graph limit falls back to text diff' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-graph-limit-fallback-before.js');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-graph-limit-fallback-after.js');
        $differ = new TokenDiffer();
        $changes = $differ->diffSyntaxLists($before, $after, [
            'language' => 'javascript',
            'graphLimit' => 80,
        ]);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes,
        ));
        $structural = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $differ->diffSyntaxLists($before, $after, [
                'language' => 'javascript',
                'graphLimit' => 100000,
            ]),
        ));
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/variations.js',
            'JavaScript',
            ['language' => 'javascript', 'graphLimit' => 80],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same('exceeded DFT_GRAPH_LIMIT', $differ->graphLimitFallbackReason($before, $after, [
            'language' => 'javascript',
            'graphLimit' => 80,
        ]));
        $t->contains('~ $text.fallback Text (exceeded DFT_GRAPH_LIMIT) line-oriented diff', $encoded);
        $t->contains('~ $text.line[2]', $encoded);
        $t->contains('gallery', $encoded);
        $t->true(!str_contains($encoded, '$js.array["variations"]'), 'Graph-limit fallback should avoid structural JavaScript matching after the graph budget is exceeded.');
        $t->contains('$js.array["variations"]', $structural);
        $t->same('Text (exceeded DFT_GRAPH_LIMIT)', $decoded['language']);
        $t->same('changed', $decoded['status']);
    },
    'wordpress block editor typescript props diff keeps retained props aligned' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-edit-props-before.ts');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-edit-props-after.ts');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'typescript',
            'title' => 'Block editor TypeScript props diff',
        ]);

        $t->contains('Block editor TypeScript props diff', $html);
        $t->contains('data-path="$ts.interface[&quot;BlockEditProps&quot;][1]"', $html);
        $t->contains('context:&quot;edit&quot;;', $html);
        $t->contains('data-path="$ts.interface[&quot;BlockEditProps&quot;][1]/{0}[1]"', $html);
        $t->contains('mediaId:number;', $html);
        $t->true(!str_contains($html, 'data-op="-" data-path="$ts.interface[&quot;BlockEditProps&quot;][1]/{0}[1]">ctaText'), 'Retained nested ctaText prop should stay aligned after the mediaId insertion.');
    },
    'wordpress block module imports diff keeps retained imports and exports aligned' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-module-imports-before.ts');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-module-imports-after.ts');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'typescript',
            'title' => 'Block module import/export diff',
        ]);

        $t->contains('Block module import/export diff', $html);
        $t->contains('data-path="$ts.import[&quot;@wordpress/blocks&quot;][1]"', $html);
        $t->contains('typeBlockConfiguration', $html);
        $t->contains('data-path="$ts.import[&quot;@wordpress/i18n&quot;][1]"', $html);
        $t->contains('sprintf', $html);
        $t->contains('data-path="$ts.export.local[2]"', $html);
        $t->contains('deprecatedSave', $html);
        $t->true(!str_contains($html, 'data-op="-" data-path="$ts.import[&quot;@wordpress/i18n&quot;][0]">__'), 'Retained __ import should stay aligned after sprintf is inserted.');
        $t->true(!str_contains($html, 'data-op="-" data-path="$ts.export.local[1]">save'), 'Retained save export should stay aligned after deprecatedSave is inserted.');
    },
    'wordpress block module asset diff keeps default and namespace imports aligned' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-module-assets-before.ts');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-module-assets-after.ts');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'typescript',
            'title' => 'Block module asset import diff',
        ]);

        $t->contains('Block module asset import diff', $html);
        $t->contains('data-path="$ts.import[&quot;./block.json&quot;][0]"', $html);
        $t->contains('supports', $html);
        $t->contains('data-path="$ts.import.namespace[&quot;@wordpress/block-editor&quot;]"', $html);
        $t->contains('<del>blockEditor</del><ins>editor</ins>', $html);
        $t->contains('data-path="$ts.export.source[&quot;save&quot;]"', $html);
        $t->contains('<del>&quot;./save&quot;</del><ins>&quot;./frontend/save&quot;</ins>', $html);
        $t->true(!str_contains($html, 'data-op="-" data-path="$ts.import.default[&quot;./block.json&quot;]"'), 'Retained default metadata import should stay aligned when named block metadata is added.');
        $t->true(!str_contains($html, 'data-op="-" data-path="$ts.export[&quot;./save&quot;]"'), 'Retained save re-export should stay aligned when only its source path changes.');
    },
    'wordpress block import attribute diff keeps metadata imports and export stars aligned' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-import-attributes-before.ts');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-import-attributes-after.ts');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'typescript',
            'title' => 'Block import attribute diff',
        ]);

        $t->contains('Block import attribute diff', $html);
        $t->contains('data-path="$ts.import[&quot;./block.json&quot;][0]"', $html);
        $t->contains('supports', $html);
        $t->contains('data-path="$ts.import.attributes[&quot;./block.json&quot;]/keyword"', $html);
        $t->contains('<del>assert</del><ins>with</ins>', $html);
        $t->contains('data-path="$ts.export.namespace[&quot;./icons&quot;]"', $html);
        $t->contains('<del>icons</del><ins>blockIcons</ins>', $html);
        $t->contains('data-path="$ts.export.type.source[&quot;*&quot;]"', $html);
        $t->contains('<del>&quot;./types&quot;</del><ins>&quot;./frontend/types&quot;</ins>', $html);
        $t->true(!str_contains($html, 'data-op="-" data-path="$ts.import.default[&quot;./block.json&quot;]"'), 'Retained default block metadata import should stay aligned while import attributes change.');
        $t->true(!str_contains($html, 'data-op="-" data-path="$ts.export.type.star[&quot;./types&quot;]"'), 'Export-star source changes should not render as a delete/add pair.');
    },
    'wordpress block editor tsx diff reports jsx tag attribute changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-edit-jsx-before.tsx');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-edit-jsx-after.tsx');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'tsx',
            'title' => 'Block editor TSX control diff',
        ]);

        $t->contains('Block editor TSX control diff', $html);
        $t->contains('PanelBodytitle=&quot;Settings&quot;', $html);
        $t->contains('PanelBodytitle=&quot;Card settings&quot;initialOpen={true}', $html);
        $t->true(!str_contains($html, 'TextControllabel=&quot;Title&quot;'), 'Retained block editor controls should stay out of the rendered TSX tag diff.');
        $t->true(!str_contains($html, '$js.call'), 'TSX JSX tag changes should render as tag-list changes rather than JavaScript call changes.');
    },
    'wordpress block editor tsx whitespace diff hides spacer expression churn' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-editor-whitespace-before.tsx');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-editor-whitespace-after.tsx');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'tsx',
            'title' => 'Block editor TSX whitespace diff',
        ]);

        $t->contains('Block editor TSX whitespace diff', $html);
        $t->contains('No syntactic changes', $html);
        $t->true(!str_contains($html, 'ToolbarButton'), 'Retained editor controls should not render as changed for whitespace-only JSX text movement.');
        $t->true(!str_contains($html, 'screen-reader-text'), 'Retained accessibility text should not render as changed for whitespace-only JSX text movement.');
    },
    'wordpress wxr xml diff reports namespaced postmeta tags safely' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-wxr-postmeta-before.xml');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-wxr-postmeta-after.xml');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'xml',
            'title' => 'WXR postmeta XML tag diff',
        ]);

        $t->contains('WXR postmeta XML tag diff', $html);
        $t->contains('wp:postmetakey=&quot;_old_builder&quot;', $html);
        $t->contains('wp:postmetakey=&quot;_wp_page_template&quot;', $html);
        $t->contains('&lt;wp:postmetakey=&quot;_thumbnail_id&quot;&gt;', $html);
        $t->true(!str_contains($html, '<wp:postmeta key="_thumbnail_id">'), 'Rendered XML tags must be escaped for browser review surfaces.');
    },
    'json display renderer follows upstream file envelope and statuses' => static function (TestRunner $t): void {
        $renderer = new JsonDiffRenderer();

        $t->same(['language' => 'Text', 'path' => 'same.txt', 'status' => 'unchanged'], $renderer->fileDiff('same', 'same', 'same.txt', 'Text'));
        $t->same(['language' => 'Text', 'path' => 'created.txt', 'status' => 'created'], $renderer->fileDiff('', 'created', 'created.txt', 'Text'));
        $t->same(['language' => 'Text', 'path' => 'deleted.txt', 'status' => 'deleted'], $renderer->fileDiff('deleted', '', 'deleted.txt', 'Text'));

        $decoded = json_decode($renderer->renderFileDiff("const title = \"Old\";\n", "const title = \"New\";\n", 'block.js', 'JavaScript'), true, 512, JSON_THROW_ON_ERROR);
        $t->same('JavaScript', $decoded['language']);
        $t->same('block.js', $decoded['path']);
        $t->same('changed', $decoded['status']);
        $t->same([[0, 0], [1, 1]], $decoded['aligned_lines']);
        $t->same(0, $decoded['chunks'][0][0]['lhs']['line_number']);
        $t->same(0, $decoded['chunks'][0][0]['rhs']['line_number']);
        $t->same([['start' => 14, 'end' => 19, 'content' => '"Old"', 'highlight' => 'string']], $decoded['chunks'][0][0]['lhs']['changes']);
        $t->same([['start' => 14, 'end' => 19, 'content' => '"New"', 'highlight' => 'string']], $decoded['chunks'][0][0]['rhs']['changes']);
    },
    'json display renderer maps upstream keyword and type highlight variants' => static function (TestRunner $t): void {
        $before = "export { save } from './save';\n";
        $after = "const supports: Record<string, boolean> = {};\nexport { save } from './save';\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/typescript_highlight.ts',
            'TypeScript',
            ['language' => 'typescript'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->contains('const:keyword', $encoded);
        $t->contains('Record:type', $encoded);
        $t->contains('string:type', $encoded);
        $t->contains('boolean:type', $encoded);
    },
    'json display renderer maps upstream typescript constructor captures as type highlights' => static function (TestRunner $t): void {
        $before = "export { save } from './save';\n";
        $after = "const controller: BlockVariationController = new BlockVariationController();\nexport { save } from './save';\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/typescript_constructor_highlight.ts',
            'TypeScript',
            ['language' => 'typescript'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('TypeScript', $decoded['language']);
        $t->contains('new:keyword', $encoded);
        $t->contains('BlockVariationController:type', $encoded);
        $t->same(2, substr_count($encoded, 'BlockVariationController:type'));
    },
    'json display renderer maps upstream javascript uppercase capture priority' => static function (TestRunner $t): void {
        $before = "export { save } from './save';\n";
        $after = "BlockRegistry.configure(WP_BLOCK_API_VERSION);\nexport { save } from './save';\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/javascript_uppercase_highlight.js',
            'JavaScript',
            ['language' => 'javascript'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('JavaScript', $decoded['language']);
        $t->contains('BlockRegistry:type', $encoded);
        $t->contains('WP_BLOCK_API_VERSION:keyword', $encoded);
    },
    'json display renderer maps upstream javascript builtin variables as keyword highlights' => static function (TestRunner $t): void {
        $before = "export { save } from './save';\n";
        $after = "window.wp.hooks.addAction('acme.card', () => document.body);\nconsole.log(module.hot, arguments.length);\nclass BlockPreview extends HTMLElement { connectedCallback() { this.dataset.ready = document.readyState; super.connectedCallback?.(); } }\nrequire('./view');\nexport { save } from './save';\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/javascript_builtin_variables.js',
            'JavaScript',
            ['language' => 'javascript'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('JavaScript', $decoded['language']);
        $t->contains('window:keyword', $encoded);
        $t->contains('document:keyword', $encoded);
        $t->contains('console:keyword', $encoded);
        $t->contains('module:keyword', $encoded);
        $t->contains('arguments:keyword', $encoded);
        $t->contains('this:keyword', $encoded);
        $t->contains('super:keyword', $encoded);
        $t->contains('wp:normal', $encoded);
        $t->contains('require:normal', $encoded);
    },
    'json display renderer maps upstream php magic constants as keyword highlights' => static function (TestRunner $t): void {
        $before = "<?php\nrequire_once plugin_dir_path(__FILE__) . 'includes/legacy.php';\n";
        $after = "<?php\nrequire_once __DIR__ . '/includes/render.php';\nrequire_once plugin_dir_path(__FILE__) . 'includes/blocks.php';\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/php_magic_constants.php',
            'PHP',
            ['language' => 'php'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('PHP', $decoded['language']);
        $t->contains('__DIR__:keyword', $encoded);
        $t->contains('__FILE__:keyword', $encoded);
        $t->contains('plugin_dir_path:normal', $encoded);
        $t->contains('require_once:keyword', $encoded);
    },
    'json display renderer maps upstream c preprocessor and primitive type highlights' => static function (TestRunner $t): void {
        $before = "int acme_block_flags(void) { return 0; }\n";
        $after = "#include <stdint.h>\n#define ACME_BLOCK_FLAG_DYNAMIC 1\nstatic uint32_t acme_block_flags(uint8_t enabled) { return enabled ? ACME_BLOCK_FLAG_DYNAMIC : 0; }\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/c_preprocessor_highlight.c',
            'C',
            ['language' => 'c'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('C', $decoded['language']);
        $t->contains('include:keyword', $encoded);
        $t->contains('define:keyword', $encoded);
        $t->contains('uint32_t:type', $encoded);
        $t->contains('uint8_t:type', $encoded);
        $t->contains('acme_block_flags:normal', $encoded);
    },
    'json display renderer maps upstream tag captures as type highlights' => static function (TestRunner $t): void {
        $before = "export const Edit = () => null;\n";
        $after = "export const Edit = () => <PanelBody title=\"Modern\" />;\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/tsx_tag_highlight.tsx',
            'TSX',
            ['language' => 'tsx'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('TSX', $decoded['language']);
        $t->contains('PanelBody:type', $encoded);
        $t->contains('"Modern":string', $encoded);
    },
    'json display renderer maps upstream keywordish constants and operators' => static function (TestRunner $t): void {
        $before = "export { save } from './save';\n";
        $after = "const enabled = true && false || null;\nexport { save } from './save';\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/keywordish_highlight.ts',
            'TypeScript',
            ['language' => 'typescript'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->contains('true:keyword', $encoded);
        $t->contains('&&:keyword', $encoded);
        $t->contains('false:keyword', $encoded);
        $t->contains('||:keyword', $encoded);
        $t->contains('null:keyword', $encoded);
    },
    'json display renderer maps upstream rust label captures as type highlights' => static function (TestRunner $t): void {
        $before = "fn render<'a>(title: &'a str) -> &'a str { title }\n";
        $after = "fn render<'block>(title: &'block str) -> &'block str { title }\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/rust_lifetime_label.rs',
            'Rust',
            ['language' => 'rust', 'parseErrorLimit' => 10],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('Rust', $decoded['language']);
        $t->contains('block:type', $encoded);
        $t->same(3, substr_count($encoded, 'block:type'));
    },
    'json display renderer maps upstream rust macro captures as keyword highlights' => static function (TestRunner $t): void {
        $before = "fn register() {\n    let blocks = [\"acme/card\"];\n}\n";
        $after = "fn register() {\n    let blocks = vec![\"acme/card\", \"acme/gallery\"];\n    println!(\"registered {}\", blocks.len());\n}\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/rust_macro_highlight.rs',
            'Rust',
            ['language' => 'rust'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('Rust', $decoded['language']);
        $t->contains('vec:keyword', $encoded);
        $t->contains('println:keyword', $encoded);
        $t->contains('blocks:normal', $encoded);
        $t->contains('len:normal', $encoded);
    },
    'json display renderer maps upstream go keyword builtin and type captures' => static function (TestRunner $t): void {
        $before = "package main\n\nfunc main() {}\n";
        $after = "package main\n\n"
            . "type Block struct {\n"
            . "    Title string\n"
            . "    Enabled bool\n"
            . "}\n\n"
            . "func register(blocks []Block) error {\n"
            . "    for _, block := range blocks {\n"
            . "        if block.Enabled == false || block.Title == \"\" { return nil }\n"
            . "    }\n"
            . "    return nil\n"
            . "}\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/go_highlight.go',
            'Go',
            ['language' => 'go'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('Go', $decoded['language']);
        foreach (['type', 'struct', 'func', 'for', 'range', 'if', 'return', 'nil', 'false'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        foreach ([':', '=', '||', '=='] as $operator) {
            $t->contains("{$operator}:keyword", $encoded);
        }
        foreach (['string', 'bool', 'error'] as $type) {
            $t->contains("{$type}:type", $encoded);
        }
        foreach (['register', 'blocks', 'block', 'Enabled', 'Title'] as $normal) {
            $t->contains("{$normal}:normal", $encoded);
        }
    },
    'json display renderer maps upstream sql keyword operator and type captures' => static function (TestRunner $t): void {
        $before = "-- WordPress migration starts empty\n";
        $after = "CREATE TABLE wp_acme_cards (\n"
            . "    id BIGINT NOT NULL,\n"
            . "    slug VARCHAR(191) DEFAULT '',\n"
            . "    visible BOOLEAN DEFAULT true,\n"
            . "    PRIMARY KEY (id)\n"
            . ");\n"
            . "SELECT slug FROM wp_acme_cards WHERE visible = true;\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/sql_highlight.sql',
            'SQL',
            ['language' => 'sql'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('SQL', $decoded['language']);
        foreach (['CREATE', 'TABLE', 'NOT', 'DEFAULT', 'PRIMARY', 'KEY', 'SELECT', 'FROM', 'WHERE'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        foreach (['BIGINT', 'NULL', 'VARCHAR', 'BOOLEAN'] as $type) {
            $t->contains("{$type}:type", $encoded);
        }
        $t->contains('=:keyword', $encoded);
        $t->contains('true:normal', $encoded);
        foreach (['wp_acme_cards', 'slug', 'visible'] as $normal) {
            $t->contains("{$normal}:normal", $encoded);
        }
    },
    'json display renderer maps upstream lua keyword and builtin constant captures' => static function (TestRunner $t): void {
        $before = "local steps = {}\n\nreturn steps\n";
        $after = "local steps = {}\n\n"
            . "function register_blocks(blocks)\n"
            . "    for _, block in ipairs(blocks) do\n"
            . "        if block.dynamic == false then\n"
            . "            return nil\n"
            . "        end\n"
            . "    end\n\n"
            . "    return steps\n"
            . "end\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/lua_highlight.lua',
            'Lua',
            ['language' => 'lua'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('Lua', $decoded['language']);
        foreach (['function', 'for', 'in', 'do', 'if', 'false', 'then', 'return', 'nil', 'end'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        foreach (['register_blocks', 'blocks', 'block', 'dynamic', 'ipairs', 'steps'] as $normal) {
            $t->contains("{$normal}:normal", $encoded);
        }
    },
    'json display renderer maps upstream swift keyword operator and type captures' => static function (TestRunner $t): void {
        $before = "import Foundation\n\nfunc main() {}\n";
        $after = "import Foundation\n\n"
            . "struct Block {\n"
            . "    let title: String\n"
            . "    let enabled: Bool\n"
            . "}\n\n"
            . "func register(_ blocks: [Block]) -> Bool {\n"
            . "    for block in blocks {\n"
            . "        if block.enabled == false { return false }\n"
            . "    }\n"
            . "    return true\n"
            . "}\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/swift_highlight.swift',
            'Swift',
            ['language' => 'swift'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('Swift', $decoded['language']);
        foreach (['struct', 'let', 'func', 'for', 'in', 'if', 'return', 'false', 'true'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        foreach ([':', '->', '=='] as $operator) {
            $t->contains("{$operator}:keyword", $encoded);
        }
        foreach (['String', 'Bool'] as $type) {
            $t->contains("{$type}:type", $encoded);
        }
        foreach (['register', 'blocks', 'block', 'enabled', 'title'] as $normal) {
            $t->contains("{$normal}:normal", $encoded);
        }
    },
    'json display renderer maps upstream java keyword operator and type captures' => static function (TestRunner $t): void {
        $before = "package tools;\n\npublic class Main {}\n";
        $after = "package tools;\n\n"
            . "public final class BlockRegistry {\n"
            . "    private final String name;\n"
            . "    private final boolean dynamic;\n\n"
            . "    public boolean register(BlockRegistry[] blocks) {\n"
            . "        for (BlockRegistry block : blocks) {\n"
            . "            if (block.dynamic == false) { return false; }\n"
            . "        }\n"
            . "        return true;\n"
            . "    }\n"
            . "}\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/java_highlight.java',
            'Java',
            ['language' => 'java'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('Java', $decoded['language']);
        foreach (['public', 'final', 'private', 'for', 'if', 'return', 'false', 'true'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        foreach (['=='] as $operator) {
            $t->contains("{$operator}:keyword", $encoded);
        }
        foreach (['BlockRegistry', 'String'] as $type) {
            $t->contains("{$type}:type", $encoded);
        }
        $t->contains('boolean:type', $encoded);
        foreach (['register', 'blocks', 'block', 'dynamic', 'name'] as $normal) {
            $t->contains("{$normal}:normal", $encoded);
        }
    },
    'json display renderer maps upstream csharp keyword operator and type captures' => static function (TestRunner $t): void {
        $before = "namespace Tools;\n\npublic class Main {}\n";
        $after = "namespace Tools;\n\n"
            . "public sealed class BlockRegistry {\n"
            . "    private readonly string name;\n"
            . "    private readonly bool enabled;\n\n"
            . "    public bool Register(BlockRegistry[] blocks) {\n"
            . "        foreach (BlockRegistry block in blocks) {\n"
            . "            if (block.enabled == false) { return false; }\n"
            . "        }\n"
            . "        return true;\n"
            . "    }\n"
            . "}\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/csharp_highlight.cs',
            'C#',
            ['language' => 'csharp'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('C#', $decoded['language']);
        foreach (['public', 'sealed', 'private', 'readonly', 'foreach', 'in', 'if', 'return', 'false', 'true'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        foreach (['=='] as $operator) {
            $t->contains("{$operator}:keyword", $encoded);
        }
        foreach (['string', 'bool'] as $type) {
            $t->contains("{$type}:type", $encoded);
        }
        foreach (['BlockRegistry', 'Register', 'blocks', 'block', 'enabled', 'name'] as $normal) {
            $t->contains("{$normal}:normal", $encoded);
        }
    },
    'json display renderer maps upstream python constructor decorators as type highlights' => static function (TestRunner $t): void {
        $before = "def migrate_post(post):\n    return post\n";
        $after = "@CacheWarmup\n"
            . "@staticmethod\n"
            . "def migrate_post(post):\n"
            . "    return MigrationRunner(post)\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/python_decorator_highlight.py',
            'Python',
            ['language' => 'python'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('Python', $decoded['language']);
        $t->contains('CacheWarmup:type', $encoded);
        $t->contains('staticmethod:normal', $encoded);
        $t->contains('MigrationRunner:type', $encoded);
    },
    'json display renderer maps upstream python keyword and builtin function boundary' => static function (TestRunner $t): void {
        $before = "def migrate_post(post):\n    return post\n";
        $after = "global migration_report\n"
            . "nonlocal migrated\n"
            . "self.report = None\n"
            . "cls.enabled = False\n"
            . "match state:\n"
            . "    case True:\n"
            . "        print(len(posts))\n"
            . "        return dict(post)\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/python_keyword_builtin_highlight.py',
            'Python',
            ['language' => 'python'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('Python', $decoded['language']);
        foreach (['global', 'nonlocal', 'self', 'None', 'cls', 'False', 'match', 'case', 'True'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        foreach (['print', 'len', 'dict'] as $builtin) {
            $t->contains("{$builtin}:normal", $encoded);
        }
    },
    'json display renderer maps upstream python builtin type annotations only in annotation context' => static function (TestRunner $t): void {
        $before = "def migrate_post(post):\n    return post\n";
        $after = "def migrate_post(post: dict[str, int]) -> tuple[int, list[str]]:\n"
            . "    list = post.get('blocks', [])\n"
            . "    return (len(list), list)\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/python_type_annotation_highlight.py',
            'Python',
            ['language' => 'python'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('Python', $decoded['language']);
        foreach (['dict', 'str', 'int', 'tuple'] as $type) {
            $t->contains("{$type}:type", $encoded);
        }
        $t->contains('list:type', $encoded);
        $t->contains('list:normal', $encoded);
    },
    'json display renderer maps upstream ruby keyword constant and constructor captures' => static function (TestRunner $t): void {
        $before = "puts 'legacy'\n";
        $after = "module AcmeTools\n"
            . "  class ImportRunner\n"
            . "    DEFAULT_LIMIT = nil\n"
            . "    def self.call(records)\n"
            . "      records.each do |record|\n"
            . "        next unless record[:post_type]\n"
            . "      end\n"
            . "    rescue StandardError\n"
            . "      require 'json'\n"
            . "    end\n"
            . "  end\n"
            . "end\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/ruby_highlight.rb',
            'Ruby',
            ['language' => 'ruby'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('Ruby', $decoded['language']);
        foreach (['module', 'class', 'def', 'do', 'next', 'unless', 'end', 'rescue', 'nil'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        $t->contains('DEFAULT_LIMIT:keyword', $encoded);
        $t->contains('ImportRunner:type', $encoded);
        $t->contains('StandardError:type', $encoded);
        $t->contains('require:normal', $encoded);
        $t->contains('self:normal', $encoded);
    },
    'json display renderer leaves unsupported attribute and property captures normal' => static function (TestRunner $t): void {
        $before = ".old { color: red; }\n";
        $after = "@supports (display: grid) { .card { opacity: 1 !important; } }\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/css_property_highlight.css',
            'CSS',
            ['language' => 'css'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->contains('supports:keyword', $encoded);
        $t->contains('important:keyword', $encoded);
        $t->contains('display:normal', $encoded);
        $t->contains('opacity:normal', $encoded);
    },
    'json display renderer maps upstream yaml boolean and null scalars as keyword highlights' => static function (TestRunner $t): void {
        $before = "enabled: true\nrelease: null\n";
        $after = "enabled: false\nrelease: stable\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/yaml_scalar_highlight.yml',
            'YAML',
            ['language' => 'yaml'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('YAML', $decoded['language']);
        $t->contains('lhs true:keyword', $encoded);
        $t->contains('rhs false:keyword', $encoded);
        $t->contains('lhs null:keyword', $encoded);
        $t->contains('rhs stable:normal', $encoded);
    },
    'json display renderer maps upstream tree sitter error highlight variant' => static function (TestRunner $t): void {
        $before = "const settings = { title: \"Card\" };\n";
        $after = "const settings = { title: \"Card\" }};\n";
        $differ = new TokenDiffer();
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/tree_sitter_error.js',
            'JavaScript',
            ['language' => 'javascript', 'parseErrorLimit' => 1],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same(null, $differ->syntaxErrorFallbackReason($before, $after, ['language' => 'javascript', 'parseErrorLimit' => 1]));
        $t->same([['start' => 34, 'end' => 35, 'text' => '}']], $differ->syntaxErrorSpans($after, ['language' => 'javascript']));
        $t->same('JavaScript', $decoded['language']);
        $t->contains('}:tree_sitter_error', $encoded);
    },
    'ansi highlighter maps upstream tree sitter error style' => static function (TestRunner $t): void {
        $source = "const settings = { title: \"Card\" }};\nconst ok = true;\n";
        $line = "const settings = { title: \"Card\" }};";
        $highlighter = new AnsiSyntaxHighlighter();
        $spansByLine = $highlighter->treeSitterErrorSpansByLine($source, ['language' => 'javascript']);
        $lineSpans = $highlighter->spansForLine($line, [
            'language' => 'javascript',
            'treeSitterErrorSpans' => $spansByLine[0] ?? [],
        ]);
        $rendered = $highlighter->highlightLine($line, 8, [
            'language' => 'javascript',
            'treeSitterErrorSpans' => $spansByLine[0] ?? [],
        ]);

        $t->same([['start' => 34, 'end' => 35, 'style' => '35']], $spansByLine[0] ?? []);
        $t->true(in_array(['start' => 34, 'end' => 35, 'style' => '35'], $lineSpans, true), 'Tree-sitter-error spans should use upstream purple ANSI styling.');
        $t->contains("\033[35m}\033[0m", $rendered);
    },
    'ansi highlighter maps upstream css keywords and html tags' => static function (TestRunner $t): void {
        $highlighter = new AnsiSyntaxHighlighter();
        $css = '@media (min-width: 600px) { .wp-block { color: red !important; } }';
        $html = '<section class="wp-block"><h2>Title</h2></section>';

        $cssSpans = $highlighter->spansForLine($css, ['language' => 'css']);
        $htmlSpans = $highlighter->spansForLine($html, ['language' => 'html']);

        $t->true(in_array(['start' => 1, 'end' => 6, 'style' => '1'], $cssSpans, true), 'CSS at-keywords should follow upstream keyword-style capture handling.');
        $t->true(in_array(['start' => 52, 'end' => 61, 'style' => '1'], $cssSpans, true), 'CSS !important should follow upstream keyword-style capture handling.');
        $t->true(in_array(['start' => 1, 'end' => 8, 'style' => '1'], $htmlSpans, true), 'HTML tag names should follow upstream tag-as-type capture handling.');
        $t->true(in_array(['start' => 27, 'end' => 29, 'style' => '1'], $htmlSpans, true), 'Nested HTML tag names should also be styled as type captures.');
    },
    'ansi highlighter maps upstream html doctype keyword capture' => static function (TestRunner $t): void {
        $line = '<!doctype html><html><body>Block</body></html>';
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'html']);

        $doctypeStart = strpos($line, 'doctype');
        $t->true($doctypeStart !== false, 'Fixture should contain doctype.');
        $t->true(in_array(['start' => $doctypeStart, 'end' => $doctypeStart + strlen('doctype'), 'style' => '1'], $spans, true), 'HTML doctype captures should follow upstream keyword-style handling.');

        $htmlStart = strpos($line, 'html', 2);
        $t->true($htmlStart !== false, 'Fixture should contain the document html tag.');
        $t->true(!in_array(['start' => $htmlStart, 'end' => $htmlStart + strlen('html'), 'style' => '1'], $spans, true), 'The doctype payload should not be promoted as a keyword.');
    },
    'ansi highlighter maps upstream keywordish constants and operators' => static function (TestRunner $t): void {
        $line = 'const enabled = true && false || null;';
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'typescript']);

        $t->true(in_array(['start' => 16, 'end' => 20, 'style' => '1'], $spans, true), 'Boolean captures should follow upstream keyword-style handling.');
        $t->true(in_array(['start' => 21, 'end' => 23, 'style' => '1'], $spans, true), 'Operator captures should follow upstream keyword-style handling.');
        $t->true(in_array(['start' => 24, 'end' => 29, 'style' => '1'], $spans, true), 'Boolean captures should follow upstream keyword-style handling.');
        $t->true(in_array(['start' => 33, 'end' => 37, 'style' => '1'], $spans, true), 'Constant captures should follow upstream keyword-style handling.');
    },
    'ansi highlighter maps upstream typescript constructor captures as type highlights' => static function (TestRunner $t): void {
        $line = 'const controller: BlockVariationController = new BlockVariationController();';
        $rendered = (new AnsiSyntaxHighlighter())->highlightLine($line, 8, ['language' => 'typescript']);

        $t->contains("\033[1mBlockVariationController\033[0m", $rendered);
        $t->contains("\033[1mnew\033[0m", $rendered);
        $t->same(2, substr_count($rendered, "\033[1mBlockVariationController\033[0m"));
    },
    'ansi highlighter maps upstream javascript uppercase captures' => static function (TestRunner $t): void {
        $line = 'BlockRegistry.configure(WP_BLOCK_API_VERSION);';
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'javascript']);

        $t->true(in_array(['start' => 0, 'end' => 13, 'style' => '1'], $spans, true), 'PascalCase identifiers should follow upstream constructor/type capture handling.');
        $t->true(in_array(['start' => 24, 'end' => 44, 'style' => '1'], $spans, true), 'All-caps constants should follow upstream constant-as-keyword capture handling.');
    },
    'ansi highlighter maps upstream javascript builtin variable captures' => static function (TestRunner $t): void {
        $line = "require('./view'); window.wp.hooks.addAction(console, document.body, module.hot, arguments.length);";
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'javascript']);

        foreach (['window', 'console', 'document', 'module', 'arguments'] as $builtin) {
            $start = strpos($line, $builtin);
            $t->true($start !== false, "Fixture should contain {$builtin}.");
            $t->true(in_array(['start' => $start, 'end' => $start + strlen($builtin), 'style' => '1'], $spans, true), "{$builtin} should follow upstream variable.builtin keyword-style handling.");
        }

        $requireStart = strpos($line, 'require');
        $t->true($requireStart !== false, 'Fixture should contain require.');
        $t->true(!in_array(['start' => $requireStart, 'end' => $requireStart + strlen('require'), 'style' => '1'], $spans, true), 'Function-builtin captures should remain normal because upstream only promotes function.macro, not function.builtin.');
    },
    'ansi highlighter maps upstream php magic constants' => static function (TestRunner $t): void {
        $line = "require_once __DIR__ . '/includes/render.php'; plugin_dir_path(__FILE__);";
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'php']);

        foreach (['require_once', '__DIR__', '__FILE__'] as $keyword) {
            $start = strpos($line, $keyword);
            $t->true($start !== false, "Fixture should contain {$keyword}.");
            $t->true(in_array(['start' => $start, 'end' => $start + strlen($keyword), 'style' => '1'], $spans, true), "{$keyword} should follow upstream keyword/constant capture handling.");
        }

        $functionStart = strpos($line, 'plugin_dir_path');
        $t->true($functionStart !== false, 'Fixture should contain plugin_dir_path.');
        $t->true(!in_array(['start' => $functionStart, 'end' => $functionStart + strlen('plugin_dir_path'), 'style' => '1'], $spans, true), 'Ordinary PHP function identifiers should remain normal.');
    },
    'ansi highlighter maps upstream php superglobal builtin variables' => static function (TestRunner $t): void {
        $line = "\$nonce = \$_REQUEST['nonce'] ?? \$_POST['nonce'] ?? \$_SERVER['HTTP_REFERER']; request_handler();";
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'php']);

        foreach (['_REQUEST', '_POST', '_SERVER'] as $superglobal) {
            $start = strpos($line, $superglobal);
            $t->true($start !== false, "Fixture should contain {$superglobal}.");
            $t->true(in_array(['start' => $start, 'end' => $start + strlen($superglobal), 'style' => '1'], $spans, true), "{$superglobal} should follow upstream variable.builtin keyword-style handling.");
        }

        $plainStart = strpos($line, 'request_handler');
        $t->true($plainStart !== false, 'Fixture should contain request_handler.');
        $t->true(!in_array(['start' => $plainStart, 'end' => $plainStart + strlen('request_handler'), 'style' => '1'], $spans, true), 'Ordinary PHP function identifiers should remain normal.');
    },
    'ansi highlighter maps upstream c preprocessor and primitive type captures' => static function (TestRunner $t): void {
        $line = '#include <stdint.h> static uint32_t acme_block_flags(uint8_t enabled) { return enabled ? 1 : 0; }';
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'c']);

        foreach (['include', 'static', 'uint32_t', 'uint8_t', 'return'] as $keyword) {
            $start = strpos($line, $keyword);
            $t->true($start !== false, "Fixture should contain {$keyword}.");
            $t->true(in_array(['start' => $start, 'end' => $start + strlen($keyword), 'style' => '1'], $spans, true), "{$keyword} should follow upstream keyword/type display styling.");
        }

        $functionStart = strpos($line, 'acme_block_flags');
        $t->true($functionStart !== false, 'Fixture should contain acme_block_flags.');
        $t->true(!in_array(['start' => $functionStart, 'end' => $functionStart + strlen('acme_block_flags'), 'style' => '1'], $spans, true), 'Ordinary C function identifiers should remain normal.');
    },
    'ansi highlighter maps upstream rust label captures as type highlights' => static function (TestRunner $t): void {
        $line = "fn render<'block>(title: &'block str) -> &'block str { title }";
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'rust']);

        $t->true(in_array(['start' => 11, 'end' => 17, 'style' => '1'], $spans, true), 'Rust lifetime labels should follow upstream label-as-type capture handling.');
        $t->true(in_array(['start' => 27, 'end' => 32, 'style' => '1'], $spans, true), 'Borrowed lifetime labels should follow upstream label-as-type capture handling.');
        $t->true(in_array(['start' => 43, 'end' => 48, 'style' => '1'], $spans, true), 'Return lifetime labels should follow upstream label-as-type capture handling.');
    },
    'ansi highlighter maps upstream rust macro captures as keyword highlights' => static function (TestRunner $t): void {
        $line = 'let blocks = vec!["acme/card"]; println!("{} blocks", blocks.len());';
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'rust']);

        foreach (['vec', 'println'] as $macro) {
            $start = strpos($line, $macro);
            $t->true($start !== false, "Fixture should contain {$macro}.");
            $t->true(in_array(['start' => $start, 'end' => $start + strlen($macro) + 1, 'style' => '1'], $spans, true), "{$macro}! should follow upstream function.macro keyword-style handling alongside the existing keyword-style ! operator.");
        }

        $lenStart = strpos($line, 'len');
        $t->true($lenStart !== false, 'Fixture should contain len.');
        $t->true(!in_array(['start' => $lenStart, 'end' => $lenStart + strlen('len'), 'style' => '1'], $spans, true), 'Ordinary Rust method identifiers should remain normal.');
    },
    'ansi highlighter maps upstream go keyword builtin and type captures' => static function (TestRunner $t): void {
        $line = 'func register(blocks []Block) error { for _, block := range blocks { if block.Enabled == false { return nil } } }';
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'go']);

        foreach (['func', 'error', 'for', ':=', 'range', 'if', '==', 'false', 'return', 'nil'] as $highlighted) {
            $start = strpos($line, $highlighted);
            $t->true($start !== false, "Fixture should contain {$highlighted}.");
            $t->true(in_array(['start' => $start, 'end' => $start + strlen($highlighted), 'style' => '1'], $spans, true), "{$highlighted} should follow upstream keyword/type display styling.");
        }

        foreach (['register', 'blocks', 'Block', 'Enabled'] as $normal) {
            $start = strpos($line, $normal);
            $t->true($start !== false, "Fixture should contain {$normal}.");
            $t->true(!in_array(['start' => $start, 'end' => $start + strlen($normal), 'style' => '1'], $spans, true), "{$normal} should remain normal without a promoted upstream capture.");
        }
    },
    'ansi highlighter maps upstream sql keyword operator and type captures' => static function (TestRunner $t): void {
        $line = 'CREATE TABLE wp_acme_cards (id BIGINT NOT NULL, visible BOOLEAN DEFAULT true); SELECT slug FROM wp_acme_cards WHERE visible = true;';
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'sql']);

        foreach (['CREATE', 'TABLE', 'BIGINT', 'NOT', 'NULL', 'BOOLEAN', 'DEFAULT', 'SELECT', 'FROM', 'WHERE'] as $highlighted) {
            $start = strpos($line, $highlighted);
            $t->true($start !== false, "Fixture should contain {$highlighted}.");
            $t->true(in_array(['start' => $start, 'end' => $start + strlen($highlighted), 'style' => '1'], $spans, true), "{$highlighted} should follow upstream SQL keyword/type display styling.");
        }

        $equalsStart = strpos($line, '=');
        $t->true($equalsStart !== false, 'Fixture should contain SQL operator.');
        $t->true(in_array(['start' => $equalsStart, 'end' => $equalsStart + 1, 'style' => '1'], $spans, true), 'SQL operators should follow upstream operator-as-keyword display styling.');

        foreach (['wp_acme_cards', 'visible', 'true'] as $normal) {
            $start = strpos($line, $normal);
            $t->true($start !== false, "Fixture should contain {$normal}.");
            $t->true(!in_array(['start' => $start, 'end' => $start + strlen($normal), 'style' => '1'], $spans, true), "{$normal} should remain normal without a promoted upstream SQL capture.");
        }
    },
    'ansi highlighter maps upstream lua keyword and builtin constant captures' => static function (TestRunner $t): void {
        $line = 'function register_blocks(blocks) for _, block in ipairs(blocks) do if block.dynamic == false then return nil end end';
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'lua']);

        foreach (['function', 'for', 'in', 'do', 'if', 'false', 'then', 'return', 'nil', 'end'] as $highlighted) {
            $start = strpos($line, $highlighted);
            $t->true($start !== false, "Fixture should contain {$highlighted}.");
            $t->true(in_array(['start' => $start, 'end' => $start + strlen($highlighted), 'style' => '1'], $spans, true), "{$highlighted} should follow upstream keyword/constant display styling.");
        }

        foreach (['register_blocks', 'blocks', 'block', 'dynamic', 'ipairs'] as $normal) {
            $start = strpos($line, $normal);
            $t->true($start !== false, "Fixture should contain {$normal}.");
            $t->true(!in_array(['start' => $start, 'end' => $start + strlen($normal), 'style' => '1'], $spans, true), "{$normal} should remain normal without a promoted upstream capture.");
        }
    },
    'ansi highlighter maps upstream swift keyword operator and type captures' => static function (TestRunner $t): void {
        $line = 'func register(_ blocks: [Block]) -> Bool { for block in blocks { if block.enabled == false { return false } } }';
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'swift']);

        foreach (['func', ':', '->', 'Bool', 'for', 'in', 'if', '==', 'false', 'return'] as $highlighted) {
            $start = strpos($line, $highlighted);
            $t->true($start !== false, "Fixture should contain {$highlighted}.");
            $t->true(in_array(['start' => $start, 'end' => $start + strlen($highlighted), 'style' => '1'], $spans, true), "{$highlighted} should follow upstream Swift keyword/type/operator styling.");
        }

        foreach (['register', 'blocks', 'Block', 'enabled'] as $normal) {
            $start = strpos($line, $normal);
            $t->true($start !== false, "Fixture should contain {$normal}.");
            $t->true(!in_array(['start' => $start, 'end' => $start + strlen($normal), 'style' => '1'], $spans, true), "{$normal} should remain normal without a promoted upstream capture.");
        }
    },
    'ansi highlighter maps upstream java keyword operator and type captures' => static function (TestRunner $t): void {
        $line = 'public boolean register(BlockRegistry[] blocks) { for (BlockRegistry block : blocks) { if (block.dynamic == false) { return false; } } }';
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'java']);

        foreach (['public', 'boolean', 'BlockRegistry', 'for', ':', 'if', '==', 'false', 'return'] as $highlighted) {
            $start = strpos($line, $highlighted);
            $t->true($start !== false, "Fixture should contain {$highlighted}.");
            $t->true(in_array(['start' => $start, 'end' => $start + strlen($highlighted), 'style' => '1'], $spans, true), "{$highlighted} should follow upstream Java keyword/type/operator styling.");
        }

        foreach (['register', 'blocks', 'block', 'dynamic'] as $normal) {
            $start = strpos($line, $normal);
            $t->true($start !== false, "Fixture should contain {$normal}.");
            $t->true(!in_array(['start' => $start, 'end' => $start + strlen($normal), 'style' => '1'], $spans, true), "{$normal} should remain normal without a promoted upstream capture.");
        }
    },
    'ansi highlighter maps upstream csharp keyword operator and type captures' => static function (TestRunner $t): void {
        $line = 'public bool Register(BlockRegistry[] blocks) { foreach (BlockRegistry block in blocks) { if (block.enabled == false) { return false; } } }';
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'csharp']);

        foreach (['public', 'bool', 'foreach', 'in', 'if', '==', 'false', 'return'] as $highlighted) {
            $start = strpos($line, $highlighted);
            $t->true($start !== false, "Fixture should contain {$highlighted}.");
            $t->true(in_array(['start' => $start, 'end' => $start + strlen($highlighted), 'style' => '1'], $spans, true), "{$highlighted} should follow upstream C# keyword/type/operator styling.");
        }

        foreach (['Register', 'BlockRegistry', 'blocks', 'block', 'enabled'] as $normal) {
            $start = strpos($line, $normal);
            $t->true($start !== false, "Fixture should contain {$normal}.");
            $t->true(!in_array(['start' => $start, 'end' => $start + strlen($normal), 'style' => '1'], $spans, true), "{$normal} should remain normal without a promoted upstream capture.");
        }
    },
    'ansi highlighter maps upstream python constructor decorator captures' => static function (TestRunner $t): void {
        $line = '@CacheWarmup';
        $builtinLine = '@staticmethod';
        $highlighter = new AnsiSyntaxHighlighter();
        $constructorSpans = $highlighter->spansForLine($line, ['language' => 'python']);
        $builtinSpans = $highlighter->spansForLine($builtinLine, ['language' => 'python']);

        $t->true(in_array(['start' => 1, 'end' => 12, 'style' => '1'], $constructorSpans, true), 'Uppercase Python decorator identifiers should follow upstream constructor-as-type handling.');
        $t->same([], $builtinSpans, 'Function/function.builtin decorator captures should remain normal because upstream does not promote them into the display highlight enum.');
    },
    'ansi highlighter maps upstream python keywords but leaves builtin calls normal' => static function (TestRunner $t): void {
        $line = 'self.report = None; cls.enabled = False; match state: case True: print(len(posts)); dict(post)';
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'python']);

        foreach (['self', 'None', 'cls', 'False', 'match', 'case', 'True'] as $keyword) {
            $start = strpos($line, $keyword);
            $t->true($start !== false, "Fixture should contain {$keyword}.");
            $t->true(in_array(['start' => $start, 'end' => $start + strlen($keyword), 'style' => '1'], $spans, true), "{$keyword} should follow upstream keyword/constant/variable.builtin capture handling.");
        }

        foreach (['print', 'len', 'dict'] as $builtin) {
            $start = strpos($line, $builtin);
            $t->true($start !== false, "Fixture should contain {$builtin}.");
            $t->true(!in_array(['start' => $start, 'end' => $start + strlen($builtin), 'style' => '1'], $spans, true), "{$builtin} should remain normal because upstream function.builtin captures are not promoted into display highlights.");
        }
    },
    'ansi highlighter maps upstream python builtin type names only in annotations' => static function (TestRunner $t): void {
        $line = 'def migrate(post: dict[str, int]) -> tuple[int, list[str]]: list = []';
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'python']);

        foreach (['dict', 'str', 'int', 'tuple'] as $type) {
            $start = strpos($line, $type);
            $t->true($start !== false, "Fixture should contain {$type}.");
            $t->true(in_array(['start' => $start, 'end' => $start + strlen($type), 'style' => '1'], $spans, true), "{$type} should follow upstream type capture handling inside Python annotations.");
        }

        $returnListStart = strpos($line, 'list[str]');
        $t->true($returnListStart !== false, 'Fixture should contain return list annotation.');
        $t->true(in_array(['start' => $returnListStart, 'end' => $returnListStart + strlen('list'), 'style' => '1'], $spans, true), 'Return annotation list should be styled as a type.');

        $localListStart = strrpos($line, 'list');
        $t->true($localListStart !== false && $localListStart !== $returnListStart, 'Fixture should contain local list identifier.');
        $t->true(!in_array(['start' => $localListStart, 'end' => $localListStart + strlen('list'), 'style' => '1'], $spans, true), 'Local identifiers named like builtin types should remain normal outside annotation context.');
    },
    'ansi highlighter maps upstream python multiline annotation builtin types' => static function (TestRunner $t): void {
        $source = "from __future__ import annotations\n"
            . "import typing\n"
            . "import typing_extensions\n"
            . "from typing import Optional, TypeAlias\n"
            . "\n"
            . "Payload: TypeAlias = \"dict[str, list[int]]\"\n"
            . "FuturePayload: typing_extensions.TypeAlias = \"typing.Optional[Payload]\"\n"
            . "label = \"list\"\n"
            . "\n"
            . "def migrate(\n"
            . "    post: dict[\n"
            . "        str | bytes,\n"
            . "        int | list[\n"
            . "            str,\n"
            . "        ],\n"
            . "    ],\n"
            . ") -> tuple[\n"
            . "    int,\n"
            . "    list[str],\n"
            . "]:\n"
            . "    parent: Optional[Payload] = None\n"
            . "    future_parent: typing.Optional[Payload] = None\n"
            . "    quoted_parent: list[\"Payload\"] = []\n"
            . "    quoted_future_parent: typing.Optional[\"Payload\"] = None\n"
            . "    encoded: \"dict[str, list[int]]\" = {}\n"
            . "    list = []\n";
        $highlighter = new AnsiSyntaxHighlighter();

        $offset = 0;
        foreach (explode("\n", rtrim($source, "\n")) as $line) {
            $renderedLines[] = $highlighter->highlightLine($line, 8, [
                'language' => 'python',
                'source' => $source,
                'lineStartOffset' => $offset,
            ]);
            $offset += strlen($line) + 1;
        }
        $rendered = implode("\n", $renderedLines ?? []);

        foreach (['dict', 'str', 'bytes', 'int', 'list', 'tuple'] as $type) {
            $t->contains("\033[1m{$type}\033[0m", $rendered);
        }
        $t->contains("\033[1mOptional\033[0m", $rendered);
        $t->contains("future_parent: typing.\033[1mOptional\033[0m[\033[1mPayload\033[0m] \033[1m=\033[0m \033[1mNone\033[0m", $rendered);
        $t->contains("quoted_parent: \033[1mlist\033[0m[\033[1m\"Payload\"\033[0m] \033[1m=\033[0m []", $rendered);
        $t->contains("quoted_future_parent: typing.\033[1mOptional\033[0m[\033[1m\"Payload\"\033[0m] \033[1m=\033[0m \033[1mNone\033[0m", $rendered);
        $t->contains("\033[1mPayload\033[0m: \033[1mTypeAlias\033[0m \033[1m=\033[0m \033[1m\"dict[str, list[int]]\"\033[0m", $rendered);
        $t->contains("\033[1mFuturePayload\033[0m: typing_extensions.\033[1mTypeAlias\033[0m \033[1m=\033[0m \033[1m\"typing.Optional[Payload]\"\033[0m", $rendered);
        $t->contains("    encoded: \033[1m\"dict[str, list[int]]\"\033[0m \033[1m=\033[0m {}", $rendered);
        $t->contains("label \033[1m=\033[0m \033[95m\"list\"\033[0m", $rendered);
        $t->contains("    list \033[1m=\033[0m []", $rendered);
        $t->true(!str_contains($rendered, "    \033[1mlist\033[0m = []"), 'Runtime identifiers named like builtin types should remain normal outside multiline annotations.');
        $t->true(!str_contains($rendered, "label \033[1m=\033[0m \033[1m\"list\"\033[0m"), 'Runtime strings that look like builtin type names should remain string-highlighted.');
        $t->true(!str_contains($rendered, "return (len(posts), \033[1mlist\033[0m)"), 'Runtime identifiers after stringized annotations should not inherit stale annotation context.');
        $t->true(!str_contains($rendered, "import \033[1mtyping\033[0m"), 'Qualified module identifiers should stay normal outside annotation captures.');
    },
    'ansi highlighter maps upstream ruby keywords constants and constructors' => static function (TestRunner $t): void {
        $line = "class ImportRunner; DEFAULT_LIMIT = nil; def call; end; require 'json'";
        $spans = (new AnsiSyntaxHighlighter())->spansForLine($line, ['language' => 'ruby']);

        foreach (['class', 'DEFAULT_LIMIT', '=', 'nil', 'def', 'end'] as $keyword) {
            $start = strpos($line, $keyword);
            $t->true($start !== false, "Fixture should contain {$keyword}.");
            $t->true(in_array(['start' => $start, 'end' => $start + strlen($keyword), 'style' => '1'], $spans, true), "{$keyword} should follow upstream keyword/constant/operator capture handling.");
        }

        $constructorStart = strpos($line, 'ImportRunner');
        $t->true($constructorStart !== false, 'Fixture should contain ImportRunner.');
        $t->true(in_array(['start' => $constructorStart, 'end' => $constructorStart + strlen('ImportRunner'), 'style' => '1'], $spans, true), 'Ruby constants should follow upstream constructor/type capture handling.');

        $requireStart = strpos($line, 'require');
        $t->true($requireStart !== false, 'Fixture should contain require.');
        $t->true(!in_array(['start' => $requireStart, 'end' => $requireStart + strlen('require'), 'style' => '1'], $spans, true), 'Ruby function.method.builtin captures should remain normal because upstream does not promote function captures into display highlights.');
    },
    'syntax list differ maps ruby def and end block delimiters' => static function (TestRunner $t): void {
        $before = "class ImportRunner\n"
            . "  def self.call(records)\n"
            . "    records.each do |record|\n"
            . "      record[:title]\n"
            . "    end\n"
            . "  end\n"
            . "end\n";
        $after = "class ImportRunner\n"
            . "  def self.call(records)\n"
            . "    records.each do |record|\n"
            . "      record[:post_title]\n"
            . "    end\n"
            . "  end\n"
            . "\n"
            . "  def self.count(records)\n"
            . "    records.length\n"
            . "  end\n"
            . "end\n";

        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'ruby']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => ($change['op'] ?? '') . ' ' . ($change['path'] ?? '') . ' ' . ($change['old'] ?? $change['new'] ?? $change['text'] ?? ''),
            $changes,
        ));

        $t->contains('- $[0][0]/def0end[0]/do1end[0]/[0][0] :title', $encoded);
        $t->contains('+ $[0][0]/def0end[0]/do1end[0]/[0][0] :post_title', $encoded);
        $t->contains('+ $[0][0]/def1end defself.count(records)records.lengthend', $encoded);
        $t->true(!str_contains($encoded, 'classImportRunnerdefself.call'), 'Ruby def/end block parsing should avoid replacing the whole class body.');
    },
    'wordpress parser error ansi command honors syntax highlight control' => static function (TestRunner $t): void {
        $before = "wp.blocks.registerBlockType('acme/card', { title: 'Card' });\n";
        $after = "wp.blocks.registerBlockType('acme/card', { title: 'Card' }});\n";
        $runner = new DiffCommandRunner();
        $syntaxOn = $runner->runTextDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/index.js',
            'JavaScript',
            ['language' => 'javascript', 'parseErrorLimit' => 1],
            [
                'DFT_COLOR' => 'always',
                'DFT_DISPLAY' => 'inline',
                'DFT_CONTEXT' => '0',
                'DFT_SYNTAX_HIGHLIGHT' => 'on',
            ],
        );
        $syntaxOff = $runner->runTextDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/index.js',
            'JavaScript',
            ['language' => 'javascript', 'parseErrorLimit' => 1],
            [
                'DFT_COLOR' => 'always',
                'DFT_DISPLAY' => 'inline',
                'DFT_CONTEXT' => '0',
                'DFT_SYNTAX_HIGHLIGHT' => 'off',
            ],
        );

        $t->same('Has syntactic changes.', $syntaxOn['message']);
        $t->same('JavaScript', $syntaxOn['language']);
        $t->contains("\033[35m}\033[0m", $syntaxOn['stdout']);
        $t->true(!str_contains($syntaxOff['stdout'], "\033[35m}\033[0m"), 'DFT_SYNTAX_HIGHLIGHT=off should suppress parser-error syntax styling.');
    },
    'json display renderer maps upstream json sample with line chunks' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-json-1.json');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-json-2.json');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff($before, $after, 'sample_files/json_1.json', 'JSON', ['language' => 'json']), true, 512, JSON_THROW_ON_ERROR);

        $t->same('changed', $decoded['status']);
        $t->same('JSON', $decoded['language']);
        $t->same('sample_files/json_1.json', $decoded['path']);
        $t->true(isset($decoded['aligned_lines']));
        $t->true(isset($decoded['chunks']));

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);
        $t->contains('1:normal', $encoded);
        $t->contains('5:normal', $encoded);
        $t->contains('"bar":string', $encoded);
        $t->contains('"zab":string', $encoded);
        $t->contains('"woo":string', $encoded);
    },
    'json display renderer maps upstream multibyte sample as byte spans' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-multibyte-1.py');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-multibyte-2.py');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff($before, $after, 'sample_files/multibyte_1.py', 'Python', ['language' => 'python']), true, 512, JSON_THROW_ON_ERROR);

        $t->same('Python', $decoded['language']);
        $t->same('changed', $decoded['status']);
        $t->same([
            ['start' => 0, 'end' => 1, 'content' => '"', 'highlight' => 'string'],
            ['start' => 1, 'end' => 4, 'content' => 'foo', 'highlight' => 'string'],
            ['start' => 4, 'end' => 7, 'content' => '€', 'highlight' => 'string'],
            ['start' => 7, 'end' => 8, 'content' => '"', 'highlight' => 'string'],
        ], $decoded['chunks'][0][0]['lhs']['changes']);
        $t->same([
            ['start' => 0, 'end' => 1, 'content' => '"', 'highlight' => 'string'],
            ['start' => 1, 'end' => 4, 'content' => 'bar', 'highlight' => 'string'],
            ['start' => 4, 'end' => 7, 'content' => '€', 'highlight' => 'string'],
            ['start' => 7, 'end' => 8, 'content' => '"', 'highlight' => 'string'],
        ], $decoded['chunks'][0][0]['rhs']['changes']);
    },
    'json display renderer maps upstream string subwords as word spans' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-string-subwords-1.el');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-string-subwords-2.el');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff($before, $after, 'sample_files/string_subwords_1.el', 'Emacs Lisp', ['language' => 'elisp']), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);
        $t->contains('lhs SoloWiki:string', $encoded);
        $t->contains('lhs Viewing:string', $encoded);
        $t->contains('rhs site:normal', $encoded);
        $t->true(!str_contains($encoded, 'lhs "SoloWiki Viewing: %s":string'), 'Changed string atoms should be split into word-level spans when they share enough words.');
    },
    'json display renderer maps upstream comment replacements as word spans' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-comments-1.rs');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-comments-2.rs');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff($before, $after, 'sample_files/comments_1.rs', 'Rust', ['language' => 'rust']), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);
        $t->contains('lhs Changing:comment', $encoded);
        $t->contains('rhs here:comment', $encoded);
        $t->contains('rhs x:comment', $encoded);
        $t->contains('rhs y:comment', $encoded);
        $t->true(!str_contains($encoded, 'rhs // Changing a single word here.:comment'), 'Changed comment atoms should be split into word-level spans when they share enough words.');
    },
    'json display renderer maps upstream multiline string sample as word spans' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-multiline-string-1.ml');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-multiline-string-2.ml');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff($before, $after, 'sample_files/multiline_string_1.ml', 'OCaml'), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' line ' . $line[$side]['line_number'] . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);
        $t->contains('lhs line 4 bar:string', $encoded);
        $t->contains('rhs line 4 novel:string', $encoded);
        $t->true(!str_contains($encoded, 'bar:normal'), 'Words inside multiline strings should keep string highlighting instead of line-level identifier fallback.');
    },
    'wordpress block json display emits machine readable review chunks' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-json-before.json');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-json-after.json');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/block.json',
            'JSON',
            ['language' => 'json'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same('wp-content/plugins/acme-card/block.json', $decoded['path']);
        $t->same('changed', $decoded['status']);
        $t->true(isset($decoded['chunks']));

        $contents = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $contents[] = $change['content'];
                    }
                }
            }
        }
        $joined = implode("\n", $contents);
        $t->contains('"viewScriptModule"', $joined);
        $t->contains('"full"', $joined);
        $t->contains('true', $joined);
    },
    'wordpress dynamic metadata import display emits typescript review chunks' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-dynamic-metadata-before.ts');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-dynamic-metadata-after.ts');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/assets.ts',
            'TypeScript',
            ['language' => 'typescript'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same('wp-content/plugins/acme-card/assets.ts', $decoded['path']);
        $t->same('TypeScript', $decoded['language']);
        $t->same('changed', $decoded['status']);
        $t->true(isset($decoded['chunks']));

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' line ' . $line[$side]['line_number'] . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);
        $t->contains('lhs line 0 assert:keyword', $encoded);
        $t->contains('rhs line 0 with:keyword', $encoded);
        $t->contains('lhs line 1 "javascript":string', $encoded);
        $t->contains('rhs line 1 "module":string', $encoded);
        $t->contains('rhs line 2 "./supports.json":string', $encoded);
    },
    'wordpress typescript metadata display highlights inserted keywords and primitive types' => static function (TestRunner $t): void {
        $before = "export { save } from './save';\n";
        $after = "type BlockAttributes = { title: string; columns: number };\nconst supports: Record<string, boolean> = {};\nexport { save } from './save';\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/index.ts',
            'TypeScript',
            ['language' => 'typescript'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/index.ts', $decoded['path']);
        $t->contains('type:keyword', $encoded);
        $t->contains('const:keyword', $encoded);
        $t->contains('string:type', $encoded);
        $t->contains('number:type', $encoded);
        $t->contains('boolean:type', $encoded);
    },
    'wordpress block controller display highlights custom types and constructors' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-block-controller-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/src/variation-controller.ts', $decoded['path']);
        $t->contains('BlockVariationController:type', $encoded);
        $t->contains('void:type', $encoded);
        $t->contains('new:keyword', $encoded);
    },
    'wordpress block registry display highlights constructor and constant captures' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-block-registry-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/src/block-registry.js', $decoded['path']);
        $t->contains('BlockRegistry:type', $encoded);
        $t->contains('WP_BLOCK_API_VERSION:keyword', $encoded);
    },
    'wordpress browser globals display highlights upstream builtin variables' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-browser-globals-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/src/browser-globals.js', $decoded['path']);
        $t->contains('window:keyword', $encoded);
        $t->contains('document:keyword', $encoded);
        $t->contains('console:keyword', $encoded);
        $t->contains('module:keyword', $encoded);
        $t->contains('arguments:keyword', $encoded);
        $t->contains('wp:normal', $encoded);
    },
    'wordpress php class display highlights this as upstream builtin variable' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-php-this-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/src/BlockRenderer.php', $decoded['path']);
        $t->contains('this:keyword', $encoded);
        $t->contains('render_block:normal', $encoded);
        $t->contains('normalize_attributes:normal', $encoded);
    },
    'wordpress php magic constant display follows upstream constant boundary' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-php-magic-constant-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/acme-card.php', $decoded['path']);
        $t->contains('__DIR__:keyword', $encoded);
        $t->contains('__FILE__:keyword', $encoded);
        $t->contains('plugin_dir_path:normal', $encoded);
        $t->contains('Acme_Card:normal', $encoded);
    },
    'wordpress php request display highlights upstream superglobal builtin variables' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-php-superglobal-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/includes/rest-nonce.php', $decoded['path']);
        $t->contains('_REQUEST:keyword', $encoded);
        $t->contains('_SERVER:keyword', $encoded);
        $t->contains('sanitize_text_field:normal', $encoded);
        $t->contains('wp_unslash:normal', $encoded);
    },
    'wordpress c preprocessor display follows upstream keyword and primitive type boundary' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-c-preprocessor-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/native/block-support.c', $decoded['path']);
        $t->contains('include:keyword', $encoded);
        $t->contains('define:keyword', $encoded);
        $t->contains('uint32_t:type', $encoded);
        $t->contains('uint8_t:type', $encoded);
        $t->contains('acme_block_flags:normal', $encoded);
    },
    'wordpress rust native module display follows upstream macro capture boundary' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-rust-macro-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/native/register_blocks.rs', $decoded['path']);
        $t->contains('vec:keyword', $encoded);
        $t->contains('println:keyword', $encoded);
        $t->contains('blocks:normal', $encoded);
        $t->contains('len:normal', $encoded);
    },
    'wordpress elisp maintenance display follows upstream special form and constant boundary' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-elisp-maintenance-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);
        $spans = (new AnsiSyntaxHighlighter())->spansForLine(
            '(defun acme-card-export () (let ((enabled nil)) (message "export")))',
            ['language' => 'elisp'],
        );

        $t->same('wp-content/plugins/acme-card/tools/export.el', $decoded['path']);
        $t->true(in_array(['start' => 1, 'end' => 6, 'style' => '1'], $spans, true), 'Emacs Lisp defun should map to upstream keyword styling.');
        $t->true(in_array(['start' => 28, 'end' => 31, 'style' => '1'], $spans, true), 'Emacs Lisp let should map to upstream keyword styling.');
        $t->true(in_array(['start' => 42, 'end' => 45, 'style' => '1'], $spans, true), 'Emacs Lisp nil should map to upstream constant/keyword styling.');
        $t->contains('t:keyword', $encoded);
        $t->contains('when:normal', $encoded);
        $t->contains('message:normal', $encoded);
    },
    'wordpress go build helper display follows upstream keyword type boundary' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-go-build-helper-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/tools/register-blocks.go', $decoded['path']);
        foreach (['type', 'struct', 'func', 'for', 'range', 'if', 'return', 'nil', 'false'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        foreach ([':', '=', '||', '=='] as $operator) {
            $t->contains("{$operator}:keyword", $encoded);
        }
        foreach (['string', 'bool', 'error'] as $type) {
            $t->contains("{$type}:type", $encoded);
        }
        $t->contains('register:normal', $encoded);
        $t->contains('Dynamic:normal', $encoded);
    },
    'wordpress lua build helper display follows upstream keyword constant boundary' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-lua-build-script-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/tools/register-blocks.lua', $decoded['path']);
        foreach (['function', 'for', 'in', 'do', 'if', 'false', 'then', 'return', 'nil', 'end'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        $t->contains('register_blocks:normal', $encoded);
        $t->contains('ipairs:normal', $encoded);
    },
    'wordpress swift bridge display follows upstream keyword type boundary' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-swift-bridge-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/tools/BlockBridge.swift', $decoded['path']);
        foreach (['struct', 'let', 'func', 'for', 'in', 'if', 'return', 'false', 'true'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        foreach ([':', '->', '=='] as $operator) {
            $t->contains("{$operator}:keyword", $encoded);
        }
        foreach (['String', 'Bool'] as $type) {
            $t->contains("{$type}:type", $encoded);
        }
        $t->contains('register:normal', $encoded);
        $t->contains('isDynamic:normal', $encoded);
    },
    'wordpress java build helper display follows upstream keyword type boundary' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-java-build-helper-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/tools/BlockRegistry.java', $decoded['path']);
        foreach (['public', 'final', 'private', 'for', 'if', 'return', 'false', 'true', 'this'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        foreach (['=', '=='] as $operator) {
            $t->contains("{$operator}:keyword", $encoded);
        }
        foreach (['BlockRegistry', 'String', 'boolean'] as $type) {
            $t->contains("{$type}:type", $encoded);
        }
        $t->contains('register:normal', $encoded);
        $t->contains('dynamic:normal', $encoded);
    },
    'wordpress csharp build helper display follows upstream keyword type boundary' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-csharp-build-helper-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/tools/BlockRegistry.cs', $decoded['path']);
        foreach (['using', 'public', 'sealed', 'private', 'readonly', 'foreach', 'in', 'if', 'return', 'false', 'true', 'this'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        foreach (['=', '=='] as $operator) {
            $t->contains("{$operator}:keyword", $encoded);
        }
        foreach (['string', 'bool'] as $type) {
            $t->contains("{$type}:type", $encoded);
        }
        $t->contains('Register:normal', $encoded);
        $t->contains('BlockRegistry:normal', $encoded);
        $t->contains('enabled:normal', $encoded);
    },
    'wordpress bash deploy display follows upstream keyword operator boundary' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-bash-deploy-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/bin/deploy.sh', $decoded['path']);
        foreach (['export', 'if', 'then', 'else', 'fi', '--path=wp', '--activate'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        foreach (['&&'] as $operator) {
            $t->contains("{$operator}:keyword", $encoded);
        }
        $t->contains('wp:normal', $encoded);
        $t->contains('plugin:normal', $encoded);
        $t->contains('WP_ENV:normal', $encoded);
    },
    'wordpress python decorator display highlights constructor captures only' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-python-decorator-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-migrator/tools/migrate_posts.py', $decoded['path']);
        $t->contains('CacheWarmup:type', $encoded);
        $t->contains('MigrationRunner:type', $encoded);
        $t->contains('staticmethod:normal', $encoded);
    },
    'wordpress python keyword builtin display follows upstream highlight boundary' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-python-keyword-builtin-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-migrator/tools/migrate_blocks.py', $decoded['path']);
        foreach (['self', 'None', 'cls', 'False', 'nonlocal', 'match', 'case', 'True'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        foreach (['print', 'len', 'dict'] as $builtin) {
            $t->contains("{$builtin}:normal", $encoded);
        }
        foreach (['dict', 'str', 'int', 'list', 'tuple'] as $type) {
            $t->contains("{$type}:type", $encoded);
        }
        $t->contains('list:normal', $encoded);
    },
    'wordpress python multiline annotation display follows upstream type boundary' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-python-multiline-annotation-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        $rendered = implode("\n", $decoded['lines']);

        $t->same('wp-content/plugins/acme-migrator/tools/normalize_posts.py', $decoded['path']);
        foreach (['dict', 'str', 'bytes', 'int', 'list', 'tuple'] as $type) {
            $t->contains("\033[1m{$type}\033[0m", $rendered);
        }
        $t->contains("future_parent: typing.\033[1mOptional\033[0m[\033[1mPayload\033[0m] \033[1m=\033[0m \033[1mNone\033[0m", $rendered);
        $t->contains("quoted_parent: \033[1mlist\033[0m[\033[1m\"Payload\"\033[0m] \033[1m=\033[0m []", $rendered);
        $t->contains("quoted_future_parent: typing.\033[1mOptional\033[0m[\033[1m\"Payload\"\033[0m] \033[1m=\033[0m \033[1mNone\033[0m", $rendered);
        $t->contains("    list \033[1m=\033[0m []", $rendered);
        $t->true(!str_contains($rendered, "    \033[1mlist\033[0m = []"), 'Runtime identifiers named like builtin types should remain normal outside multiline annotations.');
        $t->true(!str_contains($rendered, "    label \033[1m=\033[0m \033[1m\"Payload\"\033[0m"), 'Runtime strings that look like custom type names should remain string-highlighted.');
    },
    'wordpress ruby migration helper display follows upstream keyword boundary' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-ruby-migration-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-migrator/tools/import_posts.rb', $decoded['path']);
        foreach (['class', 'def', 'do', 'next', 'unless', 'rescue', 'nil'] as $keyword) {
            $t->contains("{$keyword}:keyword", $encoded);
        }
        $t->contains('ImportRunner:type', $encoded);
        $t->contains('DEFAULT_LIMIT:keyword', $encoded);
        $t->contains('count:normal', $encoded);
        $t->contains('require:normal', $encoded);
    },
    'wordpress tsx tag highlight display exposes component tags as types' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-tsx-tag-highlight-before.tsx');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-tsx-tag-highlight-after.tsx');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/src/edit.tsx',
            'TSX',
            ['language' => 'tsx'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/src/edit.tsx', $decoded['path']);
        $t->contains('PanelBody:type', $encoded);
        $t->contains('TextControl:type', $encoded);
        $t->contains('&&:keyword', $encoded);
        $t->contains('true:keyword', $encoded);
        $t->contains('false:keyword', $encoded);
        $t->contains('"Modern card":string', $encoded);
    },
    'wordpress html doctype display follows upstream keyword boundary' => static function (TestRunner $t): void {
        ob_start();
        require dirname(__DIR__) . '/examples/wordpress-html-doctype-highlight-display.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/themes/acme/templates/front-page.html', $decoded['path']);
        $t->contains('doctype:keyword', $encoded);
        $t->contains('DOCTYPE:keyword', $encoded);
        $t->contains('block:normal', $encoded);
    },
    'wordpress block editor json display can expose parser error spans when fallback budget allows' => static function (TestRunner $t): void {
        $before = "wp.blocks.registerBlockType('acme/card', { title: 'Card' });\n";
        $after = "wp.blocks.registerBlockType('acme/card', { title: 'Card' }});\n";
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/index.js',
            'JavaScript',
            ['language' => 'javascript', 'parseErrorLimit' => 1],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    $changes[] = $change['content'] . ':' . $change['highlight'];
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('wp-content/plugins/acme-card/index.js', $decoded['path']);
        $t->same('JavaScript', $decoded['language']);
        $t->contains('}:tree_sitter_error', $encoded);
    },
    'json display labels wordpress javascript parse fallback as text' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-editor-syntax-error-before.js');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-editor-syntax-error-after.js');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/sidebar.js',
            'JavaScript',
            ['language' => 'javascript'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same('Text (6 JavaScript parse errors, exceeded DFT_PARSE_ERROR_LIMIT)', $decoded['language']);
        $t->same('wp-content/plugins/acme-card/sidebar.js', $decoded['path']);
        $t->same('changed', $decoded['status']);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);
        $t->contains('lhs Legacy:string', $encoded);
        $t->contains('rhs Modern:string', $encoded);
        $t->contains('rhs scope:normal', $encoded);
    },
    'wordpress oversized php render metadata falls back to text diff' => static function (TestRunner $t): void {
        $before = "<?php\nreturn [\n    'render_callback' => 'acme_render_legacy_card',\n    'supports' => ['html' => false],\n];\n";
        $after = "<?php\nreturn [\n    'render_callback' => 'acme_render_modern_card',\n    'supports' => ['html' => true, 'align' => ['wide']],\n];\n";
        $differ = new TokenDiffer();
        $expectedReason = max(strlen($before), strlen($after)) . ' B exceeded DFT_BYTE_LIMIT';
        $changes = $differ->diffSyntaxLists($before, $after, [
            'language' => 'php',
            'byteLimit' => 80,
        ]);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes,
        ));

        $t->same($expectedReason, $differ->byteLimitFallbackReason($before, $after, [
            'language' => 'php',
            'byteLimit' => 80,
        ]));
        $t->contains('~ $text.fallback Text (' . $expectedReason . ') line-oriented diff', $encoded);
        $t->contains('~ $text.line[2]', $encoded);
        $t->contains('acme_render_legacy_card', $encoded);
        $t->contains('acme_render_modern_card', $encoded);
        $t->true(!str_contains($encoded, '$php.function'), 'Byte-limit fallback should not pretend to have a complete PHP syntax tree.');
    },
    'maps upstream huge cpp byte-limit shape with bounded line fallback' => static function (TestRunner $t): void {
        $line = static fn (int $index): string => 'int block_' . str_pad((string) $index, 4, '0', STR_PAD_LEFT) . '() { return ' . $index . '; }';
        $beforeLines = array_map($line, range(0, 1800));
        $afterLines = $beforeLines;
        $afterLines[400] = 'int block_0400() { return 404; }';
        $afterLines[1500] = 'int block_1500() { return 1504; }';
        array_splice($afterLines, 401, 0, ['int block_view_asset() { return 401; }']);
        $before = implode("\n", $beforeLines) . "\n";
        $after = implode("\n", $afterLines) . "\n";
        $differ = new TokenDiffer();
        $reason = $differ->byteLimitFallbackReason($before, $after, [
            'language' => 'cpp',
            'byteLimit' => 1024,
        ]);
        $changes = $differ->diffSyntaxLists($before, $after, [
            'language' => 'cpp',
            'byteLimit' => 1024,
        ]);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes,
        ));

        $t->true($reason !== null && str_contains($reason, 'exceeded DFT_BYTE_LIMIT'));
        $t->contains('~ $text.fallback Text (' . $reason . ') line-oriented diff', $encoded);
        $t->contains('~ $text.line[400] int block_0400() { return 400; } int block_0400() { return 404; }', $encoded);
        $t->contains('+ $text.line[401] int block_view_asset() { return 401; }', $encoded);
        $t->contains('~ $text.line[1500] int block_1500() { return 1500; } int block_1500() { return 1504; }', $encoded);
        $t->true(!str_contains($encoded, '$text.line[900]'), 'Retained unique lines between separated huge-file edits should stay anchored out of the fallback change list.');
    },
    'wordpress generated cpp build artifact byte-limit json stays line-oriented' => static function (TestRunner $t): void {
        $line = static fn (int $index): string => 'int acme_asset_' . str_pad((string) $index, 4, '0', STR_PAD_LEFT) . '() { return ' . $index . '; }';
        $beforeLines = array_map($line, range(0, 1600));
        $afterLines = $beforeLines;
        $afterLines[256] = 'int acme_asset_0256() { return 512; }';
        $afterLines[1200] = 'int acme_asset_1200() { return 1208; }';
        array_splice($afterLines, 257, 0, ['int acme_generated_view_asset() { return 257; }']);
        $before = implode("\n", $beforeLines) . "\n";
        $after = implode("\n", $afterLines) . "\n";
        $reason = (new TokenDiffer())->byteLimitFallbackReason($before, $after, [
            'language' => 'cpp',
            'byteLimit' => 1024,
        ]);
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/build/generated/asset-index.cpp',
            'C++',
            ['language' => 'cpp', 'byteLimit' => 1024],
        ), true, 512, JSON_THROW_ON_ERROR);
        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $lineChange) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($lineChange[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);

        $t->same('Text (' . $reason . ')', $decoded['language']);
        $t->same('wp-content/plugins/acme-card/build/generated/asset-index.cpp', $decoded['path']);
        $t->same('changed', $decoded['status']);
        $t->contains('rhs acme_generated_view_asset:normal', $encoded);
        $t->contains('lhs 256:normal', $encoded);
        $t->contains('rhs 512:normal', $encoded);
        $t->contains('rhs 1208:normal', $encoded);
        $t->true(!str_contains($encoded, 'acme_asset_0900'), 'Unchanged generated asset rows should not be emitted as JSON fallback changes.');
    },
    'json display labels wordpress byte limit fallback as text' => static function (TestRunner $t): void {
        $before = "<?php\nreturn [\n    'render_callback' => 'acme_render_legacy_card',\n    'supports' => ['html' => false],\n];\n";
        $after = "<?php\nreturn [\n    'render_callback' => 'acme_render_modern_card',\n    'supports' => ['html' => true, 'align' => ['wide']],\n];\n";
        $expectedReason = max(strlen($before), strlen($after)) . ' B exceeded DFT_BYTE_LIMIT';
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/render-metadata.php',
            'PHP',
            ['language' => 'php', 'byteLimit' => 80],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same('Text (' . $expectedReason . ')', $decoded['language']);
        $t->same('wp-content/plugins/acme-card/render-metadata.php', $decoded['path']);
        $t->same('changed', $decoded['status']);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);
        $t->contains("lhs 'acme_render_legacy_card':string", $encoded);
        $t->contains("rhs 'acme_render_modern_card':string", $encoded);
        $t->contains('rhs true:normal', $encoded);
    },
    'wordpress block copy display reports description string word changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-copy-before.json');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-copy-after.json');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/block.json',
            'JSON',
            ['language' => 'json'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);
        $t->contains('lhs legacy:string', $encoded);
        $t->contains('rhs modern:string', $encoded);
        $t->true(!str_contains($encoded, 'Render a card with a legacy call to action'), 'WordPress block descriptions should not be reported as whole-string replacements.');
    },
    'wordpress i18n block copy display keeps multibyte byte offsets valid' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-i18n-block-copy-before.json');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-i18n-block-copy-after.json');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/block.json',
            'JSON',
            ['language' => 'json'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $legacy = null;
        $modern = null;
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (($line['lhs']['changes'] ?? []) as $change) {
                    if ($change['content'] === 'legacy') {
                        $legacy = ['line' => $line['lhs']['line_number']] + $change;
                    }
                }
                foreach (($line['rhs']['changes'] ?? []) as $change) {
                    if ($change['content'] === 'modern') {
                        $modern = ['line' => $line['rhs']['line_number']] + $change;
                    }
                }
            }
        }

        $t->same(['line' => 2, 'start' => 28, 'end' => 34, 'content' => 'legacy', 'highlight' => 'string'], $legacy);
        $t->same(['line' => 2, 'start' => 28, 'end' => 34, 'content' => 'modern', 'highlight' => 'string'], $modern);
    },
    'wordpress multiline render doc comment display keeps comment word spans' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-multiline-copy-before.php');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-multiline-copy-after.php');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-campaign/render.php',
            'PHP',
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);
        $t->contains('lhs legacy:comment', $encoded);
        $t->contains('rhs modern:comment', $encoded);
        $t->true(!str_contains($encoded, 'legacy:normal'), 'Changed WordPress doc-comment copy should not lose comment highlighting.');
    },
    'wordpress crlf only render comments are unchanged by default' => static function (TestRunner $t): void {
        $before = "<?php\r\n/**\r\n * Render the card block.\r\n */\r\nfunction acme_render_card(): string {\r\n    return '<section>Card</section>';\r\n}\r\n";
        $after = "<?php\n/**\n * Render the card block.\n */\nfunction acme_render_card(): string {\n    return '<section>Card</section>';\n}\n";
        $renderer = new JsonDiffRenderer();

        $default = $renderer->fileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/render.php',
            'PHP',
            ['language' => 'php'],
        );
        $preservedCr = $renderer->fileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/render.php',
            'PHP',
            ['language' => 'php', 'stripCr' => false],
        );

        $t->same('unchanged', $default['status']);
        $t->same('changed', $preservedCr['status']);
        $t->true(isset($preservedCr['chunks']), 'Disabling stripCr should preserve CRLF-only changes for callers that need them.');
    },
    'yaml mode tokenizes block scalar bodies as multiline strings' => static function (TestRunner $t): void {
        $tokens = (new TokenDiffer())->tokenize("run: |\n  set -x\n  wp plugin list\nnext: true\n", ['language' => 'yaml']);
        $strings = array_values(array_filter($tokens, static fn ($token): bool => $token->kind === 'string'));

        $t->same(1, count($strings));
        $t->same("  set -x\n  wp plugin list", $strings[0]->text);
        $t->same(7, $strings[0]->start);
        $t->same(32, $strings[0]->end);
    },
    'json display renderer maps upstream yaml block scalar eof sample as string word spans' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-multiline-string-eof-1.yml');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-multiline-string-eof-2.yml');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/multiline_string_eof_1.yml',
            'YAML',
            ['language' => 'yaml'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' line ' . $line[$side]['line_number'] . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);
        $t->contains('lhs line 1 set:string', $encoded);
        $t->contains('lhs line 1 x:string', $encoded);
        $t->true(!str_contains($encoded, 'set:normal'), 'Words removed from YAML block scalars should keep string highlighting.');
    },
    'maps upstream yaml sample as flow list block sequence and scalar changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-yaml-1.yaml');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-yaml-2.yaml');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['language' => 'yaml']);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('+ $[0][0] bar', $encoded);
        $t->contains('+ $yaml.hello[1] \'item\'', $encoded);
        $t->contains('- $yaml.stuff   a', $encoded);
        $t->true(!str_contains($encoded, '- $yaml.hello[0] "world"'), 'Stable YAML block-sequence items should stay matched.');
        $t->true(!str_contains($encoded, '- $yaml.hello[1] other'), 'Block sequence insertion should not delete the retained following item.');
    },
    'json display renderer maps upstream yaml trailling newline sample as string spans' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-trailling-newline-1.yaml');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-trailling-newline-2.yaml');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'sample_files/trailling_newline_1.yaml',
            'YAML',
            ['language' => 'yaml'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);
        $t->contains('lhs ${{ BAR }}:string', $encoded);
        $t->contains('rhs bar:string', $encoded);
        $t->true(!str_contains($encoded, '${{ BAR }}:normal'), 'YAML block scalar bodies should not fall back to normal token highlighting.');
        $t->true(!str_contains($encoded, '{:delimiter'), 'Expression braces inside a YAML block scalar should remain string content.');
    },
    'maps upstream utf16 sample bytes as text content' => static function (TestRunner $t): void {
        $before = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-utf16-1.hex')));
        $after = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-utf16-2.hex')));
        $t->true(is_string($before));
        $t->true(is_string($after));

        $decoder = new FileContentDecoder();
        $decodedBefore = $decoder->guessTextContent($before);
        $t->true($decodedBefore !== null, 'UTF-16 files with a byte order mark should be decoded as text.');
        $t->contains('print("hello ☃ snowman")', (string) $decodedBefore);

        $decoded = json_decode((new JsonDiffRenderer())->renderFileBytesDiff(
            $before,
            $after,
            'sample_files/utf16_1.py',
            'Python',
            ['language' => 'python'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same('Python', $decoded['language']);
        $t->same('changed', $decoded['status']);
        $t->true(isset($decoded['chunks']), 'Decoded UTF-16 text should produce normal changed text chunks, not a binary status envelope.');

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);
        $t->contains('lhs hello:string', $encoded);
        $t->contains('lhs ☃:string', $encoded);
        $t->contains('rhs no:string', $encoded);
        $t->contains('rhs "こんにちは世界":string', $encoded);
    },
    'maps upstream png magic bytes as binary content' => static function (TestRunner $t): void {
        $before = "\x89PNG\r\n\x1a\nIHDRlegacy";
        $after = "\x89PNG\r\n\x1a\nIHDRmodern";
        $decoder = new FileContentDecoder();
        $decoded = (new JsonDiffRenderer())->fileBytesDiff(
            $before,
            $after,
            'img/logo.png',
            'PNG',
        );

        $t->same(null, $decoder->guessTextContent($before));
        $t->same(['language' => 'Binary', 'path' => 'img/logo.png', 'status' => 'changed'], $decoded);
    },
    'maps upstream windows1251 sample bytes as windows 1252 text content' => static function (TestRunner $t): void {
        $before = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-windows1251-1.hex')));
        $after = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-windows1251-2.hex')));
        $t->true(is_string($before));
        $t->true(is_string($after));

        $decoder = new FileContentDecoder();
        $decodedBefore = $decoder->guessTextContent($before);
        $decodedAfter = $decoder->guessTextContent($after);
        $t->contains('Muß können', (string) $decodedBefore);
        $t->contains('ähnlich', (string) $decodedBefore);
        $t->contains('ähmlich', (string) $decodedAfter);

        $decoded = json_decode((new JsonDiffRenderer())->renderFileBytesDiff(
            $before,
            $after,
            'sample_files/windows1251_1.txt',
            'Text',
            ['language' => 'text'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same('Text', $decoded['language']);
        $t->same('changed', $decoded['status']);
        $t->true(isset($decoded['chunks']), 'Windows-1252 text should produce changed text chunks, not a binary status envelope.');

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);
        $t->contains('lhs hnlich:normal', $encoded);
        $t->contains('rhs hmlich:normal', $encoded);
    },
    'maps upstream slightly invalid utf8 cli content as lossy text' => static function (TestRunner $t): void {
        $before = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-slightly-invalid-utf8-before.hex')));
        $after = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-slightly-invalid-utf8-after.hex')));
        $t->true(is_string($before));
        $t->true(is_string($after));

        $decoder = new FileContentDecoder();
        $decodedBefore = $decoder->guessTextContent($before);
        $decodedAfter = $decoder->guessTextContent($after);
        $replacement = "\xef\xbf\xbd";

        $t->contains('using System;', (string) $decodedBefore);
        $t->contains('legacy ' . $replacement . ' copy', (string) $decodedBefore);
        $t->contains('modern ' . $replacement . ' copy', (string) $decodedAfter);
        $t->true(!str_contains((string) $decodedBefore, "legacy \xc2\xa1 copy"), 'A single invalid UTF-8 byte should use replacement text before Windows-1252 fallback.');

        $decoded = json_decode((new JsonDiffRenderer())->renderFileBytesDiff(
            $before,
            $after,
            'sample_files/cli_tests/MainWindowViewModel.cs',
            'C#',
            ['language' => 'text'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same('changed', $decoded['status']);
        $t->true(isset($decoded['chunks']), 'Mostly valid UTF-8 should produce text chunks, not a binary status envelope.');
    },
    'wordpress legacy encoded readme bytes render as text instead of binary' => static function (TestRunner $t): void {
        $before = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-legacy-encoding-before.hex')));
        $after = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-legacy-encoding-after.hex')));
        $t->true(is_string($before));
        $t->true(is_string($after));

        $decoder = new FileContentDecoder();
        $t->contains('müller', (string) $decoder->guessTextContent($before));
        $t->contains('Löst alte Blöcke.', (string) $decoder->guessTextContent($before));

        $decoded = json_decode((new JsonDiffRenderer())->renderFileBytesDiff(
            $before,
            $after,
            'wp-content/plugins/acme-blocks/readme.txt',
            'Text',
            ['language' => 'text'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same('wp-content/plugins/acme-blocks/readme.txt', $decoded['path']);
        $t->same('Text', $decoded['language']);
        $t->same('changed', $decoded['status']);

        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);
        $t->contains('lhs alte:normal', $encoded);
        $t->contains('rhs moderne:normal', $encoded);
    },
    'wordpress utf16 wxr bytes render as xml text instead of binary' => static function (TestRunner $t): void {
        $encodeUtf16Le = static function (string $text): string {
            $bytes = "\xff\xfe";
            foreach (str_split($text) as $byte) {
                $bytes .= $byte . "\0";
            }

            return $bytes;
        };
        $before = $encodeUtf16Le("<?xml version=\"1.0\"?>\n<rss>\n  <wp:postmeta key=\"_old_builder\">legacy</wp:postmeta>\n</rss>\n");
        $after = $encodeUtf16Le("<?xml version=\"1.0\"?>\n<rss>\n  <wp:postmeta key=\"_wp_page_template\">default</wp:postmeta>\n  <wp:postmeta key=\"_thumbnail_id\">42</wp:postmeta>\n</rss>\n");

        $decoded = json_decode((new JsonDiffRenderer())->renderFileBytesDiff(
            $before,
            $after,
            'wp-content/uploads/wordpress-export.xml',
            'XML',
            ['language' => 'xml'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same('XML', $decoded['language']);
        $t->same('changed', $decoded['status']);

        $contents = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $contents[] = $change['content'];
                    }
                }
            }
        }
        $joined = implode("\n", $contents);
        $t->contains('_old_builder', $joined);
        $t->contains('_wp_page_template', $joined);
        $t->contains('_thumbnail_id', $joined);
    },
    'wordpress slightly invalid wxr bytes render as text with replacement characters' => static function (TestRunner $t): void {
        $before = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-slightly-invalid-wxr-before.hex')));
        $after = hex2bin(trim((string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-slightly-invalid-wxr-after.hex')));
        $t->true(is_string($before));
        $t->true(is_string($after));

        $decoder = new FileContentDecoder();
        $replacement = "\xef\xbf\xbd";
        $t->contains('Legacy ' . $replacement . ' block', (string) $decoder->guessTextContent($before));
        $t->contains('Modern ' . $replacement . ' block', (string) $decoder->guessTextContent($after));

        $decoded = json_decode((new JsonDiffRenderer())->renderFileBytesDiff(
            $before,
            $after,
            'wp-content/uploads/wordpress-export.xml',
            'XML',
            ['language' => 'xml'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same('XML', $decoded['language']);
        $t->same('changed', $decoded['status']);
        $t->true(isset($decoded['chunks']), 'Slightly invalid WXR bytes should remain reviewable text.');

        $contents = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $contents[] = $change['content'];
                    }
                }
            }
        }
        $joined = implode("\n", $contents);
        $t->contains('Legacy', $joined);
        $t->contains('Modern', $joined);
        $t->contains('_wp_page_template', $joined);
        $t->true(!str_contains($joined, "\xc2\xa1"), 'WordPress export review should not reinterpret one bad UTF-8 byte as Windows-1252 punctuation.');
    },
    'wordpress plugin workflow yaml display keeps wp cli command changes string highlighted' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-workflow-before.yml');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-workflow-after.yml');
        $decoded = json_decode((new JsonDiffRenderer())->renderFileDiff(
            $before,
            $after,
            'wp-content/plugins/acme-card/.github/workflows/release.yml',
            'YAML',
            ['language' => 'yaml'],
        ), true, 512, JSON_THROW_ON_ERROR);

        $t->same('wp-content/plugins/acme-card/.github/workflows/release.yml', $decoded['path']);
        $changes = [];
        foreach ($decoded['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                foreach (['lhs', 'rhs'] as $side) {
                    foreach (($line[$side]['changes'] ?? []) as $change) {
                        $changes[] = $side . ' ' . $change['content'] . ':' . $change['highlight'];
                    }
                }
            }
        }
        $encoded = implode("\n", $changes);
        $t->contains('lhs json:string', $encoded);
        $t->contains('rhs pot:string', $encoded);
        $t->contains('rhs acme:string', $encoded);
        $t->contains('lhs true:keyword', $encoded);
        $t->contains('rhs false:keyword', $encoded);
        $t->contains('lhs null:keyword', $encoded);
        $t->contains('rhs stable:normal', $encoded);
        $t->true(!str_contains($encoded, 'json:normal'), 'WP-CLI command changes inside YAML run blocks should not fall back to normal highlighting.');
    },
    'wordpress plugin workflow step diff reports yaml block sequence changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-workflow-steps-before.yml');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-workflow-steps-after.yml');
        $html = (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
            'language' => 'yaml',
            'title' => 'Plugin release workflow step diff',
        ]);

        $t->contains('Plugin release workflow step diff', $html);
        $t->contains('data-path="$yaml.jobs.release.steps[1]"', $html);
        $t->contains('name: Install WordPress test env', $html);
        $t->contains('name: Make block metadata', $html);
        $t->contains('name: Make translation template', $html);
        $t->true(!str_contains($html, 'actions/checkout@v4'), 'Stable workflow steps should stay out of the rendered change stream.');
        $t->true(!str_contains($html, 'Build block assets'), 'Stable middle steps should stay matched after an insertion.');
    },
];
