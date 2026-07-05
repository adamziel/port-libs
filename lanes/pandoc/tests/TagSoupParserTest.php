<?php

declare(strict_types=1);

use PortLibs\Pandoc\HtmlReader;
use PortLibs\Pandoc\HtmlNativeAstComparisonHarness;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\TagSoupParseOptions;
use PortLibs\Pandoc\TagSoupParser;
use PortLibs\Pandoc\TagSoupRenderer;
use PortLibs\Pandoc\TagSoupTag;

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

    'opt-in tagsoup backend matches initial html native fixture slice' => static function (TestRunner $t): void {
        $root = dirname(__DIR__) . '/fixtures';
        $harness = new HtmlNativeAstComparisonHarness();
        foreach ([
            'upstream-html-address-block',
            'upstream-html-anchor-image-attrs',
            'upstream-html-base-relative-image',
            'upstream-html-blockquote',
            'upstream-html-button-inline-fallback',
            'upstream-html-definition-list',
            'upstream-html-fallback-content-containers',
            'upstream-html-figure-caption',
            'upstream-html-form-controls',
            'upstream-html-generic-raw-inline',
            'upstream-html-header-native-divs',
            'upstream-html-inline-code-aliases',
            'upstream-html-inline-fallback-content-containers',
            'upstream-html-line-block',
            'upstream-html-list-item-id',
            'upstream-html-main-native-divs',
            'upstream-html-main-inline-plain',
            'upstream-html-multi-term-definition-list',
            'upstream-html-optional-definition-list-tree-construction',
            'upstream-html-ordered-list-type-start',
            'upstream-html-paragraph-blockquote-tree-construction',
            'upstream-html-paragraph-heading-tree-construction',
            'upstream-html-pre-code-attributes',
            'upstream-html-pre-code-br',
            'upstream-html-rawtext-fallback-containers',
            'upstream-html-script-raw-block',
            'upstream-html-section-aside-native-divs',
            'upstream-html-smallcaps-class',
            'upstream-html-standalone-button-inline',
            'upstream-html-style-raw-block',
            'upstream-html-template-raw-boundary',
            'upstream-html-textarea-raw-block',
            'upstream-html-thematic-break',
            'upstream-html-xmp-rawtext-fallback',
        ] as $basename) {
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
