<?php

declare(strict_types=1);

use PortLibs\Difftastic\HtmlDiffRenderer;
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
    'enables html angle delimiters only in html mode' => static function (TestRunner $t): void {
        $differ = new TokenDiffer();
        $defaultTokens = $differ->tokenize('<h1>');
        $htmlTokens = $differ->tokenize('<h1 id="title">Bar</h1>', ['language' => 'html']);

        $t->same('punctuation', $defaultTokens[0]->kind);
        $t->same('delimiter', $htmlTokens[0]->kind);
        $t->same('open', $htmlTokens[0]->delimiterRole);
        $t->same('close', $htmlTokens[5]->delimiterRole);
        $t->same('open', $htmlTokens[7]->delimiterRole);
        $t->same('close', $htmlTokens[10]->delimiterRole);
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
];
