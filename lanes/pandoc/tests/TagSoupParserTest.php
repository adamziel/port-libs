<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlReader;
use PortLibs\Pandoc\HtmlNativeAstComparisonHarness;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\TagSoupParseOptions;
use PortLibs\Pandoc\TagSoupParser;
use PortLibs\Pandoc\TagSoupRenderer;
use PortLibs\Pandoc\TagSoupTag;
use PortLibs\Pandoc\TagSoupTokenStream;

return [
    'parses malformed formatting tags as source-order flat tokens' => static function (TestRunner $t): void {
        $tokens = TagSoupParser::canonicalizeTags((new TagSoupParser())->parse('<p><b>one<i>two</b>three</i>'));

        $t->same([
            ['open', 'p', '', []],
            ['open', 'b', '', []],
            ['text', '', 'one', []],
            ['open', 'i', '', []],
            ['text', '', 'two', []],
            ['close', 'b', '', []],
            ['text', '', 'three', []],
            ['close', 'i', '', []],
        ], tokenSummary($tokens));
    },

    'parses attributes entities and self-closing syntax' => static function (TestRunner $t): void {
        $tokens = TagSoupParser::canonicalizeTags((new TagSoupParser())->parse('<BR class=x disabled data-v="A&amp;B"/>'));

        $t->same([
            ['open', 'br', '', [
                ['name' => 'class', 'value' => 'x'],
                ['name' => 'disabled', 'value' => ''],
                ['name' => 'data-v', 'value' => 'A&B'],
            ]],
            ['close', 'br', '', []],
        ], tokenSummary($tokens));
    },

    'strips namespace prefixes from tag names like pandoc html reader' => static function (TestRunner $t): void {
        $tokens = TagSoupParser::canonicalizeTags((new TagSoupParser())->parse('<xhtml:p xml:lang="en">Hi <m:strong>there</m:strong></xhtml:p>'));

        $t->same([
            ['open', 'p', '', [['name' => 'xml:lang', 'value' => 'en']]],
            ['text', '', 'Hi ', []],
            ['open', 'strong', '', []],
            ['text', '', 'there', []],
            ['close', 'strong', '', []],
            ['close', 'p', '', []],
        ], tokenSummary($tokens));

        $document = (new HtmlReader(['htmlReaderBackend' => 'tagsoup-pandoc-port']))->read('<xhtml:p>Hi <m:strong>there</m:strong></xhtml:p>');
        $t->same('paragraph', $document->children[0]->type);
        $t->same(['text', 'strong'], array_map(static fn (AstNode $node): string => $node->type, $document->children[0]->children));
        $t->same('there', $document->children[0]->children[1]->children[0]->attr('text'));
    },

    'parses comments declarations cdata and raw text source-order content' => static function (TestRunner $t): void {
        $tokens = TagSoupParser::canonicalizeTags((new TagSoupParser())->parse(
            '<!doctype html><!--c--><![CDATA[x<y]]><script>a < b && c</script><style>x < y</style>'
        ));

        $t->same([
            ['open', '!DOCTYPE', '', [['name' => 'html', 'value' => '']]],
            ['comment', '', 'c', []],
            ['text', '', 'x<y', []],
            ['open', 'script', '', []],
            ['text', '', 'a < b && c', []],
            ['close', 'script', '', []],
            ['open', 'style', '', []],
            ['text', '', 'x < y', []],
            ['close', 'style', '', []],
        ], tokenSummary($tokens));
    },

    'parses declaration attributes without names like tagsoup' => static function (TestRunner $t): void {
        $tokens = (new TagSoupParser())->parse('<!review "loose" value=yes>');

        $t->same([
            ['open', '!review', '', [
                ['name' => '', 'value' => 'loose'],
                ['name' => 'value', 'value' => 'yes'],
            ]],
        ], tokenSummary($tokens));
    },

    'emits optional position and warning tokens' => static function (TestRunner $t): void {
        $options = (new TagSoupParseOptions(includePositions: true, includeWarnings: true));
        $tokens = (new TagSoupParser())->parse("x\n<>", $options);

        $t->same([
            ['position', '', '', []],
            ['text', '', "x\n", []],
            ['position', '', '', []],
            ['warning', '', 'Unexpected "<>"', []],
            ['position', '', '', []],
            ['text', '', '<>', []],
        ], tokenSummary($tokens));
        $t->same([1, 1], [$tokens[0]->row, $tokens[0]->column]);
        $t->same([2, 1], [$tokens[2]->row, $tokens[2]->column]);
    },

    'compact canonical token stream preserves tokens while releasing consumed payloads' => static function (TestRunner $t): void {
        $html = '<DIV class="note"><P>One <STRONG>two</STRONG></P></DIV>';
        $expected = (new TagSoupParser())->parseCanonical($html);
        $stream = (new TagSoupParser())->parseCanonicalStream($html);

        $t->same(tokenSummary($expected), tokenSummary($stream->slice(0)));
        $stream->releaseBefore(3);
        $t->same(null, $stream->tokenAt(0));
        $t->same(null, $stream->tokenAt(2));
        $t->same('open', $stream->tokenAt(3)?->type);
        $t->same('strong', $stream->tokenAt(3)?->name);
        $t->same('two', $stream->tokenAt(4)?->text);
        $t->same(tokenSummary(array_slice($expected, 3)), tokenSummary($stream->slice(3)));

        $chunked = new TagSoupTokenStream();
        for ($index = 0; $index < 1026; ++$index) {
            $chunked->append(TagSoupTag::text('token-' . $index));
        }
        $chunked->releaseBefore(1024);
        $t->same(null, $chunked->tokenAt(1023));
        $t->same('token-1024', $chunked->tokenAt(1024)?->text);
        $t->same('token-1025', $chunked->tokenAt(1025)?->text);
    },

    'compact token stream preserves wide name and attribute encodings' => static function (TestRunner $t): void {
        $stream = new TagSoupTokenStream();
        for ($index = 0; $index < 255; ++$index) {
            $stream->append(TagSoupTag::open('tag' . $index));
        }
        $shortValue = str_repeat('s', 255);
        $wideValue = str_repeat('w', 65535);
        $stream->append(TagSoupTag::open('root', [
            ['name' => 'short', 'value' => $shortValue],
            ['name' => 'wide', 'value' => $wideValue],
        ]));
        $stream->append(TagSoupTag::close('tag254'));

        $t->same('tag254', $stream->nameAt(254));
        $t->same('root', $stream->tokenAt(255)?->name);
        $t->same($shortValue, $stream->attributeAt(255, 'short'));
        $t->same($wideValue, $stream->tokenAt(255)?->attributes[1]['value'] ?? null);
        $t->same('tag254', $stream->tokenAt(256)?->name);

        $replacement = new TagSoupTokenStream();
        $replacement->append(TagSoupTag::open('before', [
            ['name' => 'data-one', 'value' => 'one'],
            ['name' => 'data-two', 'value' => 'two'],
        ]));
        $t->same('before', $replacement->tokenAt(0)?->name);
        $replacement->replaceAt(0, TagSoupTag::open('after', [
            ['name' => 'data-one', 'value' => 'three'],
            ['name' => 'data-two', 'value' => 'four'],
        ]));
        $t->same('after', $replacement->tokenAt(0)?->name);
        $t->same('four', $replacement->attributeAt(0, 'data-two'));
    },

    'renders tags with tagsoup-style escaping and br minimization' => static function (TestRunner $t): void {
        $renderer = new TagSoupRenderer();

        $t->same(
            '<p title="A&amp;B">A&lt;B<br /></p>',
            $renderer->render([
                TagSoupTag::open('p', [['name' => 'title', 'value' => 'A&B']]),
                TagSoupTag::text('A<B'),
                TagSoupTag::open('br'),
                TagSoupTag::close('br'),
                TagSoupTag::close('p'),
            ])
        );
    },

    'opt-in html reader backend reads simple blocks through tagsoup token stream' => static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => 'tagsoup-pandoc-port']))->read(
            '<h1>Hi <em>there</em></h1><p>A <strong>B</strong> <a href="/x">x</a></p>'
        );

        $t->same('PortLibs\Pandoc\PandocHtmlTagSoupReader', $document->attr('meta')['reader'] ?? null);
        $t->same(2, count($document->children));
        $t->same('heading', $document->children[0]->type);
        $t->same(1, $document->children[0]->attr('level'));
        $t->same('Hi there', $document->children[0]->attr('text'));
        $t->same('paragraph', $document->children[1]->type);
        $t->same('A B x', $document->children[1]->attr('text'));
        $t->same('strong', $document->children[1]->children[1]->type);
        $t->same('link', $document->children[1]->children[3]->type);
        $t->same('/x', $document->children[1]->children[3]->attr('url'));
    },

    'opt-in html reader backend imports upstream document metadata through tagsoup token stream' => static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => 'tagsoup-pandoc-port']))->read(<<<'HTML'
<html lang="es"><head>
<title> HTML   Metadata </title>
<meta name="keywords" content="one">
<meta name="keywords" content="two">
<meta name="Empty" content="">
<meta name="spaced" content="  keep  ">
</head><body xml:lang="pt-BR"><p>ola</p></body></html>
HTML);
        $meta = $document->attr('meta');

        $t->same('pt-BR', $meta['lang'] ?? null);
        $t->same('HTML Metadata', $meta['title'] ?? null);
        $t->same(['one', 'two'], $meta['keywords'] ?? null);
        $t->same('', $meta['Empty'] ?? null);
        $t->same('  keep  ', $meta['spaced'] ?? null);
        $t->true(!array_key_exists('empty', $meta));
        $t->same('ola', $document->children[0]->attr('text'));
    },

    'opt-in html reader backend imports ordered list style sources through tagsoup token stream' => static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => 'tagsoup-pandoc-port']))->read(
            '<ol></ol><ol type="i"></ol><ol type="A"></ol><ol type="1"></ol>'
            . '<ol class="lower-roman"></ol><ol style="lower-roman"></ol>'
            . '<ol style="list-style: upper-alpha;"></ol><ol style="list-style-type: upper-roman;"></ol>'
        );

        $t->same([
            'default',
            'lower_roman',
            'upper_alpha',
            'decimal',
            'lower_roman',
            'default',
            'upper_alpha',
            'upper_roman',
        ], array_map(static fn (AstNode $node): mixed => $node->attr('style'), $document->children));
    },

    'opt-in html reader backend preserves present empty href anchors as links' => static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => 'tagsoup-pandoc-port']))->read(
            '<p><a href="">Empty</a>.</p><table><tbody><tr><td><a href="">Cell</a></td></tr></tbody></table>'
        );

        $paragraphLink = $document->children[0]->children[0];
        $tableLink = $document->children[1]->children[1]->children[0]->children[0]->children[0];

        $t->same('link', $paragraphLink->type);
        $t->same('', $paragraphLink->attr('url'));
        $t->same('Empty', $paragraphLink->children[0]->attr('text'));
        $t->same('link', $tableLink->type);
        $t->same('', $tableLink->attr('url'));
        $t->same('Cell', $tableLink->children[0]->attr('text'));
    },

    'opt-in html reader backend moves inline wrapper boundary whitespace like upstream' => static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => 'tagsoup-pandoc-port']))->read(
            '<p>text<em> Leading space</em></p>'
            . '<p><em>Trailing space </em>text</p>'
            . '<p>Empty <strong></strong> and <em></em>.</p>'
        );

        $t->same('text ', $document->children[0]->children[0]->attr('text'));
        $t->same('emph', $document->children[0]->children[1]->type);
        $t->same('Leading space', $document->children[0]->children[1]->children[0]->attr('text'));
        $t->same('Trailing space', $document->children[1]->children[0]->children[0]->attr('text'));
        $t->same(' text', $document->children[1]->children[1]->attr('text'));
        $t->same('strong', $document->children[2]->children[1]->type);
        $t->same([], $document->children[2]->children[1]->children);
        $t->same('emph', $document->children[2]->children[3]->type);
        $t->same([], $document->children[2]->children[3]->children);
    },

    'opt-in tagsoup backend matches upstream html reader full golden fixture' => static function (TestRunner $t): void {
        $root = dirname(__DIR__) . '/fixtures';
        $harness = new HtmlNativeAstComparisonHarness();
        $basename = 'upstream-html-reader-full-golden';
        $htmlFixture = 'upstream-html-reader-full-golden.html';
        $nativeFixture = 'upstream-html-reader-full-golden.native';
        $html = file_get_contents($root . '/' . $htmlFixture);
        $native = file_get_contents($root . '/' . $nativeFixture);
        if (!is_string($html) || !is_string($native)) {
            throw new RuntimeException('Missing fixture pair for ' . $basename);
        }

        $local = $harness->normalizedDocument(
            (new HtmlReader(['htmlReaderBackend' => 'tagsoup-pandoc-port']))->read($html)
        );
        $expected = $harness->normalizedDocument((new NativeReader())->read($native));

        $t->same($expected, $local, $basename . ' should match through the TagSoup backend');
    },

    'opt-in tagsoup backend matches every checked-in html native fixture pair' => static function (TestRunner $t): void {
        $root = dirname(__DIR__) . '/fixtures';
        $harness = new HtmlNativeAstComparisonHarness();
        $basenames = [];
        foreach (glob($root . '/*.html') ?: [] as $htmlPath) {
            $basename = basename($htmlPath, '.html');
            if (is_file($root . '/' . $basename . '.native')) {
                $basenames[] = $basename;
            }
        }
        sort($basenames, SORT_STRING);
        $t->same(133, count($basenames), 'checked-in HTML/native fixture pair count');

        foreach ($basenames as $basename) {
            $options = HtmlNativeAstComparisonHarness::readerOptionsForFixtureBasename($basename);
            $options['htmlReaderBackend'] = 'tagsoup-pandoc-port';
            $html = file_get_contents($root . '/' . $basename . '.html');
            $native = file_get_contents($root . '/' . $basename . '.native');
            if (!is_string($html) || !is_string($native)) {
                throw new RuntimeException('Missing fixture pair for ' . $basename);
            }

            $local = $harness->normalizedDocument((new HtmlReader($options))->read($html));
            $expected = $harness->normalizedDocument((new NativeReader())->read($native));

            $t->same($expected, $local, $basename . ' should match through the TagSoup backend');
        }
    },
];

/**
 * @param list<TagSoupTag> $tokens
 * @return list<array{0:string,1:string,2:string,3:list<array{name:string,value:string}>}>
 */
function tokenSummary(array $tokens): array
{
    return array_map(
        static fn (TagSoupTag $token): array => [
            $token->type,
            $token->name,
            $token->text,
            $token->attributes,
        ],
        $tokens
    );
}
