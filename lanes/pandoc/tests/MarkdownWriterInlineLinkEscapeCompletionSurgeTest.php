<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$softbreak = static fn (): AstNode => new AstNode('softbreak');
$linebreak = static fn (): AstNode => new AstNode('linebreak');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$writeDocument = static fn (array $blocks, array $options = []): string => (new MarkdownWriter($options))->write($document($blocks));
$writeParagraph = static fn (array $children, array $options = []): string => $writeDocument([$paragraph($children)], $options);
$link = static fn (string $url, array $children, array $attrs = []): AstNode => new AstNode('link', ['url' => $url] + $attrs, $children);
$image = static fn (string $url, array $children, array $attrs = []): AstNode => new AstNode('image', ['url' => $url] + $attrs, $children);
$span = static fn (array $children, array $attrs = []): AstNode => new AstNode('span', $attrs, $children);

$tests = [];

$citationTextCases = [
    'space before citation id' => [
        'children' => [$text('see @doe2026 now')],
        'expected' => 'see \\@doe2026 now',
    ],
    'space before braced citation id' => [
        'children' => [$text('see @{doe, 2026} now')],
        'expected' => 'see \\@{doe, 2026} now',
    ],
    'tab before citation id' => [
        'children' => [$text("see\t@doe2026")],
        'expected' => "see\t\\@doe2026",
    ],
    'literal newline before citation id' => [
        'children' => [$text("see\n@doe2026")],
        'expected' => "see\n\\@doe2026",
    ],
    'softbreak before citation id' => [
        'children' => [$text('see'), $softbreak(), $text('@doe2026')],
        'expected' => "see\n\\@doe2026",
    ],
    'hardbreak before citation id' => [
        'children' => [$text('see'), $linebreak(), $text('@doe2026')],
        'expected' => "see\\\n\\@doe2026",
    ],
    'emphasis nested citation literal' => [
        'children' => [new AstNode('emph', [], [$text('see @doe2026')])],
        'expected' => '*see \\@doe2026*',
    ],
    'strong nested braced citation literal' => [
        'children' => [new AstNode('strong', [], [$text('see @{doe, 2026}')])],
        'expected' => '**see \\@{doe, 2026}**',
    ],
    'span nested citation literal' => [
        'children' => [$span([$text('see @doe2026')], ['classes' => ['review']])],
        'expected' => '[see \\@doe2026]{.review}',
    ],
    'link label nested citation literal' => [
        'children' => [$link('/source', [$text('see @doe2026')])],
        'expected' => '[see \\@doe2026](/source)',
    ],
    'image label nested citation literal' => [
        'children' => [$image('media/source.png', [$text('see @doe2026')], ['alt' => 'see @doe2026'])],
        'expected' => '![see \\@doe2026](media/source.png)',
    ],
    'opening paren before citation id' => [
        'children' => [$text('(@doe2026)')],
        'expected' => '(\\@doe2026)',
    ],
    'semicolon before citation id' => [
        'children' => [$text('alpha;@doe2026')],
        'expected' => 'alpha;\\@doe2026',
    ],
    'colon before citation id' => [
        'children' => [$text('source:@doe2026')],
        'expected' => 'source:\\@doe2026',
    ],
    'comma before citation id' => [
        'children' => [$text('source,@doe2026')],
        'expected' => 'source,\\@doe2026',
    ],
    'escaped bracketed citation-looking literal' => [
        'children' => [$text('[@doe2026]')],
        'expected' => '\\[\\@doe2026\\]',
    ],
    'email address remains literal' => [
        'children' => [$text('reviewer@example.test')],
        'expected' => 'reviewer@example.test',
    ],
    'path at sign remains literal' => [
        'children' => [$text('source/@doe2026')],
        'expected' => 'source/@doe2026',
    ],
    'word before braced at sign remains literal' => [
        'children' => [$text('alpha@{not citation}')],
        'expected' => 'alpha@{not citation}',
    ],
    'literal escaped at sign keeps explicit slash' => [
        'children' => [$text('\\@doe2026')],
        'expected' => '\\\\@doe2026',
    ],
    'space before numeric citation id' => [
        'children' => [$text('see @2026-review')],
        'expected' => 'see \\@2026-review',
    ],
    'space before underscore citation id' => [
        'children' => [$text('see @_review')],
        'expected' => 'see \\@\\_review',
    ],
    'space before left brace citation marker' => [
        'children' => [$text('see @{review/source}')],
        'expected' => 'see \\@{review/source}',
    ],
    'plain at sign before punctuation remains literal' => [
        'children' => [$text('status @? unresolved')],
        'expected' => 'status @? unresolved',
    ],
];

foreach ($citationTextCases as $label => $case) {
    $tests["maps upstream markdown writer citation escape completion {$label}"] =
        static function (TestRunner $t) use ($case, $writeParagraph): void {
            $t->same($case['expected'], $writeParagraph($case['children']));
        };
}

$longScheme = 'abcdefghijklmnopqrstuvwxyzabcdefg';
$autolinkCases = [
    'uppercase uri scheme remains compact' => [
        'children' => [$link('HTTPS://example.test/source', [$text('HTTPS://example.test/source')])],
        'expected' => '<HTTPS://example.test/source>',
    ],
    'ftp uri scheme remains compact' => [
        'children' => [$link('ftp://example.test/source', [$text('ftp://example.test/source')])],
        'expected' => '<ftp://example.test/source>',
    ],
    'urn uri scheme remains compact' => [
        'children' => [$link('urn:isbn:9780000000000', [$text('urn:isbn:9780000000000')])],
        'expected' => '<urn:isbn:9780000000000>',
    ],
    'doi uri scheme remains compact' => [
        'children' => [$link('doi:10.1000/source', [$text('doi:10.1000/source')])],
        'expected' => '<doi:10.1000/source>',
    ],
    'one letter uri scheme falls back to explicit link' => [
        'children' => [$link('x:source', [$text('x:source')], ['classes' => ['uri']])],
        'expected' => '[x:source](x:source){.uri}',
    ],
    'overlong uri scheme falls back to explicit link' => [
        'children' => [$link($longScheme . ':source', [$text($longScheme . ':source')], ['classes' => ['uri']])],
        'expected' => '[' . $longScheme . ':source](' . $longScheme . ':source){.uri}',
    ],
    'one letter uri scheme without class falls back to explicit link' => [
        'children' => [$link('x:source', [$text('x:source')])],
        'expected' => '[x:source](x:source)',
    ],
    'mailto without at sign falls back to explicit link' => [
        'children' => [$link('mailto:not-an-email', [$text('not-an-email')], ['classes' => ['email']])],
        'expected' => '[not-an-email](mailto:not-an-email){.email}',
    ],
    'mailto missing local part falls back to explicit link' => [
        'children' => [$link('mailto:@example.test', [$text('@example.test')], ['classes' => ['email']])],
        'expected' => '[\\@example.test](mailto:@example.test){.email}',
    ],
    'mailto missing domain falls back to explicit link' => [
        'children' => [$link('mailto:editor@', [$text('editor@')], ['classes' => ['email']])],
        'expected' => '[editor@](mailto:editor@){.email}',
    ],
    'mailto double at sign falls back to explicit link' => [
        'children' => [$link('mailto:a@@example.test', [$text('a@@example.test')], ['classes' => ['email']])],
        'expected' => '[a@@example.test](mailto:a@@example.test){.email}',
    ],
    'mailto domain without dot falls back to explicit link' => [
        'children' => [$link('mailto:editor@example', [$text('editor@example')], ['classes' => ['email']])],
        'expected' => '[editor@example](mailto:editor@example){.email}',
    ],
    'uppercase mailto address remains compact' => [
        'children' => [$link('mailto:Editor@Example.Test', [$text('Editor@Example.Test')], ['classes' => ['email']])],
        'expected' => '<Editor@Example.Test>',
    ],
    'uppercase mailto scheme remains compact' => [
        'children' => [$link('MAILTO:editor@example.test', [$text('editor@example.test')], ['classes' => ['email']])],
        'expected' => '<editor@example.test>',
    ],
    'apostrophe email local remains compact' => [
        'children' => [$link("mailto:o'connor@example.test", [$text("o'connor@example.test")], ['classes' => ['email']])],
        'expected' => "<o'connor@example.test>",
    ],
    'mailto with uri class keeps explicit class' => [
        'children' => [$link('mailto:editor@example.test', [$text('editor@example.test')], ['classes' => ['uri']])],
        'expected' => '[editor@example.test](mailto:editor@example.test){.uri}',
    ],
    'uri with email class keeps explicit class' => [
        'children' => [$link('https://example.test/source', [$text('https://example.test/source')], ['classes' => ['email']])],
        'expected' => '[https://example.test/source](https://example.test/source){.email}',
    ],
    'valid tel uri with uri class remains compact' => [
        'children' => [$link('tel:+15551234567', [$text('tel:+15551234567')], ['classes' => ['uri']])],
        'expected' => '<tel:+15551234567>',
    ],
];

foreach ($autolinkCases as $label => $case) {
    $tests["maps upstream markdown writer autolink completion {$label}"] =
        static function (TestRunner $t) use ($case, $writeParagraph): void {
            $t->same($case['expected'], $writeParagraph($case['children']));
        };
}

$attributeCases = [
    'link id with space escapes token' => [
        'children' => [$link('/source', [$text('source')], ['id' => 'review link'])],
        'expected' => '[source](/source){#review\\ link}',
    ],
    'link id with newline escapes token on one line' => [
        'children' => [$link('/source', [$text('source')], ['id' => "review\nlink"])],
        'expected' => '[source](/source){#review\\ link}',
    ],
    'link class with space escapes token' => [
        'children' => [$link('/source', [$text('source')], ['classes' => ['needs review']])],
        'expected' => '[source](/source){.needs\\ review}',
    ],
    'link class with tab escapes token on one line' => [
        'children' => [$link('/source', [$text('source')], ['classes' => ["needs\treview"]])],
        'expected' => '[source](/source){.needs\\ review}',
    ],
    'link class with braces escapes token' => [
        'children' => [$link('/source', [$text('source')], ['classes' => ['needs{review}']])],
        'expected' => '[source](/source){.needs\\{review\\}}',
    ],
    'link class with parens escapes token' => [
        'children' => [$link('/source', [$text('source')], ['classes' => ['needs(review)']])],
        'expected' => '[source](/source){.needs\\(review\\)}',
    ],
    'attribute name with space escapes token' => [
        'children' => [$link('/source', [$text('source')], ['attributes' => ['data review' => 'yes']])],
        'expected' => '[source](/source){data\\ review="yes"}',
    ],
    'attribute name with equals escapes token' => [
        'children' => [$link('/source', [$text('source')], ['attributes' => ['data=review' => 'yes']])],
        'expected' => '[source](/source){data\\=review="yes"}',
    ],
    'attribute value with newline stays on one line' => [
        'children' => [$link('/source', [$text('source')], ['attributes' => ['data-review' => "Line\nTwo"]])],
        'expected' => '[source](/source){data-review="Line Two"}',
    ],
    'attribute value with controls stays on one line' => [
        'children' => [$link('/source', [$text('source')], ['attributes' => ['data-review' => "A\x00B\x7FC"]])],
        'expected' => '[source](/source){data-review="A B C"}',
    ],
    'attribute value with quote and slash escapes quoted value' => [
        'children' => [$link('/source', [$text('source')], ['attributes' => ['data-review' => 'A "quote" \\ path']])],
        'expected' => '[source](/source){data-review="A \\"quote\\" \\\\ path"}',
    ],
    'span combined escaped attribute tuple' => [
        'children' => [$span([$text('marked')], ['id' => 'review span', 'classes' => ['needs review'], 'attributes' => ['data key' => "A\nB"]])],
        'expected' => '[marked]{#review\\ span .needs\\ review data\\ key="A B"}',
    ],
    'code id with space escapes token' => [
        'children' => [new AstNode('code', ['text' => 'source', 'id' => 'code key', 'classes' => ['php']])],
        'expected' => '`source`{#code\\ key .php}',
    ],
    'math id with newline escapes token' => [
        'children' => [new AstNode('math', ['text' => 'x = y', 'display' => true, 'id' => "eq\nreview"])],
        'expected' => '$$x = y$${#eq\\ review}',
    ],
    'reference definition id with space escapes token' => [
        'children' => [$link('/source', [$text('source')], ['id' => 'review link'])],
        'expected' => "[source]\n\n  [source]: /source {#review\\ link}",
        'options' => ['referenceLinks' => true],
    ],
    'reference definition attribute value with newline stays on one line' => [
        'children' => [$link('/source', [$text('source')], ['attributes' => ['data-review' => "Line\nTwo"]])],
        'expected' => "[source]\n\n  [source]: /source {data-review=\"Line Two\"}",
        'options' => ['referenceLinks' => true],
    ],
    'image id with space escapes token' => [
        'children' => [$image('media/source.png', [$text('alt')], ['id' => 'image id', 'alt' => 'alt'])],
        'expected' => '![alt](media/source.png){#image\\ id}',
    ],
    'attribute value with closing brace remains quoted' => [
        'children' => [$link('/source', [$text('source')], ['attributes' => ['data-review' => 'a}b']])],
        'expected' => '[source](/source){data-review="a}b"}',
    ],
];

foreach ($attributeCases as $label => $case) {
    $tests["maps upstream markdown writer attribute escape completion {$label}"] =
        static function (TestRunner $t) use ($case, $writeParagraph): void {
            $t->same($case['expected'], $writeParagraph($case['children'], $case['options'] ?? []));
        };
}

$htmlSidecarCases = [
    'span html id class data tuple' => [
        'children' => [$span([$text('source')], ['htmlAttributes' => ['id' => 'span-a', 'class' => 'review primary', 'data-kind' => 'span']])],
        'expected' => '[source]{#span-a .review .primary data-kind="span"}',
    ],
    'span html id with space escapes token' => [
        'children' => [$span([$text('source')], ['htmlAttributes' => ['id' => 'span a']])],
        'expected' => '[source]{#span\\ a}',
    ],
    'span html class with braces escapes token' => [
        'children' => [$span([$text('source')], ['htmlAttributes' => ['class' => 'needs{review}']])],
        'expected' => '[source]{.needs\\{review\\}}',
    ],
    'span html data value newline stays one line' => [
        'children' => [$span([$text('source')], ['htmlAttributes' => ['data-review' => "Line\nTwo"]])],
        'expected' => '[source]{data-review="Line Two"}',
    ],
    'span html data value controls stay one line' => [
        'children' => [$span([$text('source')], ['htmlAttributes' => ['data-review' => "A\x00B\x7FC"]])],
        'expected' => '[source]{data-review="A B C"}',
    ],
    'span html data value quote and slash escapes quoted value' => [
        'children' => [$span([$text('source')], ['htmlAttributes' => ['data-review' => 'A "quote" \\ path']])],
        'expected' => '[source]{data-review="A \\"quote\\" \\\\ path"}',
    ],
    'span html attribute name with equals escapes token' => [
        'children' => [$span([$text('source')], ['htmlAttributes' => ['data=review' => 'yes']])],
        'expected' => '[source]{data\\=review="yes"}',
    ],
    'span html class mark renders mark shorthand' => [
        'children' => [$span([$text('marked')], ['htmlAttributes' => ['class' => 'mark']])],
        'expected' => '==marked==',
    ],
    'span html class mark with data falls back to attributed span' => [
        'children' => [$span([$text('marked')], ['htmlAttributes' => ['class' => 'mark', 'data-kind' => 'mark']])],
        'expected' => '[marked]{.mark data-kind="mark"}',
    ],
    'span html emoji sidecar renders alias shorthand' => [
        'children' => [$span([$text("\u{1F44D}")], ['htmlAttributes' => ['class' => 'emoji', 'data-emoji' => 'thumbsup']])],
        'expected' => ':thumbsup:',
    ],
    'span html emoji mismatch falls back to attributes' => [
        'children' => [$span([$text('not emoji')], ['htmlAttributes' => ['class' => 'emoji', 'data-emoji' => 'thumbsup']])],
        'expected' => '[not emoji]{.emoji data-emoji="thumbsup"}',
    ],
    'span html abbreviation sidecar emits definition' => [
        'children' => [$span([$text('HTML')], ['htmlAttributes' => ['class' => 'abbr', 'title' => 'Hypertext Markup Language']])],
        'expected' => "HTML\n\n*[HTML]: Hypertext Markup Language",
    ],
    'span html abbreviation extra data falls back to attributes' => [
        'children' => [$span([$text('HTML')], ['htmlAttributes' => ['class' => 'abbr', 'title' => 'Hypertext', 'data-kind' => 'abbr']])],
        'expected' => '[HTML]{.abbr title="Hypertext" data-kind="abbr"}',
    ],
    'span html data and pandoc attribute both survive' => [
        'children' => [$span([$text('source')], ['htmlAttributes' => ['data-kind' => 'html'], 'attributes' => ['kind' => 'pandoc']])],
        'expected' => '[source]{data-kind="html" kind="pandoc"}',
    ],
    'span html compressed classes normalize tuple classes' => [
        'children' => [$span([$text('source')], ['htmlAttributes' => ['class' => 'one   two']])],
        'expected' => '[source]{.one .two}',
    ],
    'span html xml lang aria tuple' => [
        'children' => [$span([$text('texto')], ['htmlAttributes' => ['xml:lang' => 'es', 'aria-label' => 'Source label']])],
        'expected' => '[texto]{xml:lang="es" aria-label="Source label"}',
    ],
    'link html id class data tuple' => [
        'children' => [$link('/source', [$text('source')], ['htmlAttributes' => ['id' => 'link-a', 'class' => 'review', 'data-kind' => 'link']])],
        'expected' => '[source](/source){#link-a .review data-kind="link"}',
    ],
    'link html href sidecar is not duplicated' => [
        'children' => [$link('/source', [$text('source')], ['htmlAttributes' => ['href' => '/source', 'data-kind' => 'link']])],
        'expected' => '[source](/source){data-kind="link"}',
    ],
    'link html title sidecar without node title stays attribute' => [
        'children' => [$link('/source', [$text('source')], ['htmlAttributes' => ['title' => 'Source title']])],
        'expected' => '[source](/source){title="Source title"}',
    ],
    'link html title sidecar with node title is not duplicated' => [
        'children' => [$link('/source', [$text('source')], ['title' => 'Source title', 'htmlAttributes' => ['title' => 'Source title', 'data-kind' => 'link']])],
        'expected' => '[source](/source "Source title"){data-kind="link"}',
    ],
    'uri autolink html uri class remains compact' => [
        'children' => [$link('https://example.test/source', [$text('https://example.test/source')], ['htmlAttributes' => ['class' => 'uri']])],
        'expected' => '<https://example.test/source>',
    ],
    'uri autolink html data disables compact shorthand' => [
        'children' => [$link('https://example.test/source', [$text('https://example.test/source')], ['htmlAttributes' => ['class' => 'uri', 'data-kind' => 'source']])],
        'expected' => '[https://example.test/source](https://example.test/source){.uri data-kind="source"}',
    ],
    'email autolink html email class remains compact' => [
        'children' => [$link('mailto:editor@example.test', [$text('editor@example.test')], ['htmlAttributes' => ['class' => 'email']])],
        'expected' => '<editor@example.test>',
    ],
    'email autolink html data disables compact shorthand' => [
        'children' => [$link('mailto:editor@example.test', [$text('editor@example.test')], ['htmlAttributes' => ['class' => 'email', 'data-kind' => 'mail']])],
        'expected' => '[editor@example.test](mailto:editor@example.test){.email data-kind="mail"}',
    ],
    'reference definition html id class data tuple' => [
        'children' => [$link('/source', [$text('source')], ['htmlAttributes' => ['id' => 'link-a', 'class' => 'review', 'data-kind' => 'link']])],
        'expected' => "[source]\n\n  [source]: /source {#link-a .review data-kind=\"link\"}",
        'options' => ['referenceLinks' => true],
    ],
    'reference definition html target attrs are reused' => [
        'children' => [
            $link('/one', [$text('Source')], ['htmlAttributes' => ['data-kind' => 'source']]),
            $text(' and '),
            $link('/one', [$text('Again')], ['htmlAttributes' => ['data-kind' => 'source']]),
        ],
        'expected' => "[Source] and [Again][Source]\n\n  [Source]: /one {data-kind=\"source\"}",
        'options' => ['referenceLinks' => true],
    ],
    'reference definition html target attrs disambiguate' => [
        'children' => [
            $link('/one', [$text('Source')], ['htmlAttributes' => ['data-kind' => 'one']]),
            $text(' and '),
            $link('/one', [$text('Source')], ['htmlAttributes' => ['data-kind' => 'two']]),
        ],
        'expected' => "[Source] and [Source][1]\n\n  [Source]: /one {data-kind=\"one\"}\n  [1]: /one {data-kind=\"two\"}",
        'options' => ['referenceLinks' => true],
    ],
    'image html id class data tuple' => [
        'children' => [$image('media/source.png', [$text('alt')], ['htmlAttributes' => ['id' => 'image-a', 'class' => 'thumb', 'data-kind' => 'image']])],
        'expected' => '![alt](media/source.png){#image-a .thumb data-kind="image"}',
    ],
    'image html src sidecar is not duplicated' => [
        'children' => [$image('media/source.png', [$text('alt')], ['htmlAttributes' => ['src' => 'media/source.png', 'data-kind' => 'image']])],
        'expected' => '![alt](media/source.png){data-kind="image"}',
    ],
    'image html alt sidecar with empty visible label survives' => [
        'children' => [$image('media/source.png', [], ['htmlAttributes' => ['alt' => 'Alt text']])],
        'expected' => '![](media/source.png){alt="Alt text"}',
    ],
    'image html alt sidecar with caption survives' => [
        'children' => [$image('media/source.png', [$text('Caption')], ['htmlAttributes' => ['alt' => 'Alt text']])],
        'expected' => '![Caption](media/source.png){alt="Alt text"}',
    ],
    'image html title sidecar without node title stays attribute' => [
        'children' => [$image('media/source.png', [$text('Caption')], ['htmlAttributes' => ['title' => 'Image title']])],
        'expected' => '![Caption](media/source.png){title="Image title"}',
    ],
    'image html title sidecar with node title is not duplicated' => [
        'children' => [$image('media/source.png', [$text('Caption')], ['title' => 'Image title', 'htmlAttributes' => ['title' => 'Image title', 'alt' => 'Alt text']])],
        'expected' => '![Caption](media/source.png "Image title"){alt="Alt text"}',
    ],
    'code html id class data tuple' => [
        'children' => [new AstNode('code', ['text' => 'source', 'htmlAttributes' => ['id' => 'code-a', 'class' => 'php', 'data-kind' => 'code']])],
        'expected' => '`source`{#code-a .php data-kind="code"}',
    ],
    'code html class with backticks expands delimiter' => [
        'children' => [new AstNode('code', ['text' => 'wp `code`', 'htmlAttributes' => ['class' => 'php']])],
        'expected' => '`` wp `code` ``{.php}',
    ],
    'math html id class data tuple' => [
        'children' => [new AstNode('math', ['text' => 'x + y', 'htmlAttributes' => ['id' => 'math-a', 'class' => 'math', 'data-kind' => 'math']])],
        'expected' => '$x + y${#math-a .math data-kind="math"}',
    ],
    'display math html id newline escapes token' => [
        'children' => [new AstNode('math', ['text' => 'x = y', 'display' => true, 'htmlAttributes' => ['id' => "eq\nreview"]])],
        'expected' => '$$x = y$${#eq\\ review}',
    ],
    'small caps html sidecar renders semantic span' => [
        'children' => [new AstNode('small_caps', ['htmlAttributes' => ['id' => 'small-a', 'class' => 'review', 'data-kind' => 'small']], [$text('Small Caps')])],
        'expected' => '[Small Caps]{#small-a .smallcaps .review data-kind="small"}',
    ],
    'underline html sidecar renders semantic span' => [
        'children' => [new AstNode('underline', ['htmlAttributes' => ['class' => 'review', 'data-kind' => 'under']], [$text('under')])],
        'expected' => '[under]{.underline .review data-kind="under"}',
    ],
    'strikeout html sidecar renders semantic span' => [
        'children' => [new AstNode('strikeout', ['htmlAttributes' => ['class' => 'review', 'data-kind' => 'delete']], [$text('gone')])],
        'expected' => '[gone]{.strikeout .review data-kind="delete"}',
    ],
    'superscript html sidecar renders semantic span' => [
        'children' => [new AstNode('superscript', ['htmlAttributes' => ['data-kind' => 'sup']], [$text('2')])],
        'expected' => '[2]{.superscript data-kind="sup"}',
    ],
    'subscript html sidecar renders semantic span' => [
        'children' => [new AstNode('subscript', ['htmlAttributes' => ['data-kind' => 'sub']], [$text('n')])],
        'expected' => '[n]{.subscript data-kind="sub"}',
    ],
    'link html class merges explicit class without duplicate' => [
        'children' => [$link('/source', [$text('source')], ['classes' => ['review'], 'htmlAttributes' => ['class' => 'review tracked']])],
        'expected' => '[source](/source){.review .tracked}',
    ],
    'link html id overridden by explicit id preserves old id precedence' => [
        'children' => [$link('/source', [$text('source')], ['id' => 'explicit-id', 'htmlAttributes' => ['id' => 'html-id', 'data-kind' => 'link']])],
        'expected' => '[source](/source){#explicit-id data-kind="link"}',
    ],
    'span html empty values are skipped' => [
        'children' => [$span([$text('source')], ['htmlAttributes' => ['id' => '', 'class' => '', 'data-empty' => '', 'data-kind' => 'kept']])],
        'expected' => '[source]{data-kind="kept"}',
    ],
    'span html uppercase attribute names normalize' => [
        'children' => [$span([$text('source')], ['htmlAttributes' => ['DATA-KIND' => 'upper', 'ARIA-LABEL' => 'Upper label']])],
        'expected' => '[source]{data-kind="upper" aria-label="Upper label"}',
    ],
    'span html attributes preserve insertion order before pandoc attrs' => [
        'children' => [$span([$text('source')], ['htmlAttributes' => ['data-a' => 'A', 'data-b' => 'B'], 'attributes' => ['data-c' => 'C']])],
        'expected' => '[source]{data-a="A" data-b="B" data-c="C"}',
    ],
    'reference definition html title sidecar stays attribute' => [
        'children' => [$link('/source', [$text('source')], ['htmlAttributes' => ['title' => 'Source title', 'data-kind' => 'link']])],
        'expected' => "[source]\n\n  [source]: /source {title=\"Source title\" data-kind=\"link\"}",
        'options' => ['referenceLinks' => true],
    ],
    'image html class with explicit class merges without duplicate' => [
        'children' => [$image('media/source.png', [$text('alt')], ['classes' => ['thumb'], 'htmlAttributes' => ['class' => 'thumb review']])],
        'expected' => '![alt](media/source.png){.thumb .review}',
    ],
    'image html data and pandoc attribute both survive' => [
        'children' => [$image('media/source.png', [$text('alt')], ['htmlAttributes' => ['data-kind' => 'html'], 'attributes' => ['kind' => 'pandoc']])],
        'expected' => '![alt](media/source.png){data-kind="html" kind="pandoc"}',
    ],
];

$tests['records markdown writer html sidecar completion mapped case count'] =
    static function (TestRunner $t) use ($htmlSidecarCases): void {
        $t->same(50, count($htmlSidecarCases));
    };

foreach ($htmlSidecarCases as $label => $case) {
    $tests["maps upstream markdown writer html sidecar completion {$label}"] =
        static function (TestRunner $t) use ($case, $writeParagraph): void {
            $t->same($case['expected'], $writeParagraph($case['children'], $case['options'] ?? []));
        };
}

return $tests;
