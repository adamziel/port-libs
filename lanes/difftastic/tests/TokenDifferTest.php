<?php

declare(strict_types=1);

use PortLibs\Difftastic\HtmlDiffRenderer;
use PortLibs\Difftastic\FileContentDecoder;
use PortLibs\Difftastic\JsonDiffRenderer;
use PortLibs\Difftastic\TokenDiffer;

return [
    'tokenizes identifiers numbers strings and punctuation separately' => static function (TestRunner $t): void {
        $tokens = (new TokenDiffer())->tokenize('fn add(x, 2) { return "ok"; }');
        $t->same('identifier', $tokens[0]->kind);
        $t->same('fn', $tokens[0]->text);
        $t->same('number', $tokens[5]->kind);
        $t->same('string', $tokens[9]->kind);
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
