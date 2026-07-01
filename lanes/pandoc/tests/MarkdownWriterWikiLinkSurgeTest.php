<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);
$wikiLink = static fn (string $label, string $url, array $attrs = []): AstNode => new AstNode(
    'link',
    array_replace(['url' => $url, 'classes' => ['wikilink']], $attrs),
    [$text($label)]
);

$cases = [
    'same plain page' => ['Migration runbook', 'Migration runbook', '[[Migration runbook]]'],
    'same uri page' => ['https://example.org', 'https://example.org', '[[https://example.org]]'],
    'same random string page' => ['random string', 'random string', '[[random string]]'],
    'same closing bracket page' => ['Name of ]page', 'Name of ]page', '[[Name of \]page]]'],
    'same double closing bracket page' => ['Name ]] page', 'Name ]] page', '[[Name \]\] page]]'],
    'same pipe page' => ['Name | pipe', 'Name | pipe', '[[Name \| pipe]]'],
    'same backslash page' => ['C:\docs\page', 'C:\docs\page', '[[C:\\\\docs\\\\page]]'],
    'same ampersand page' => ['AT&T page', 'AT&T page', '[[AT&amp;T page]]'],
    'same angle page' => ['<review> page', '<review> page', '[[&lt;review&gt; page]]'],
    'same quoted page' => ['quoted "page"', 'quoted "page"', '[[quoted &quot;page&quot;]]'],
    'same star punctuation page' => ['page with *stars*', 'page with *stars*', '[[page with *stars*]]'],
    'same underscore page' => ['page_with_underscore', 'page_with_underscore', '[[page_with_underscore]]'],
    'same colon page' => ['page:section', 'page:section', '[[page:section]]'],
    'same slash path page' => ['page/sub/path', 'page/sub/path', '[[page/sub/path]]'],
    'same query page' => ['page?query=1&two=2', 'page?query=1&two=2', '[[page?query=1&amp;two=2]]'],
    'same anchor page' => ['#section', '#section', '[[#section]]'],
    'same hyphen page' => ['release-notes-2026', 'release-notes-2026', '[[release-notes-2026]]'],
    'same parenthesized page' => ['page (draft)', 'page (draft)', '[[page (draft)]]'],
    'same bracketed page' => ['page [draft]', 'page [draft]', '[[page [draft\]]]'],
    'same braced page' => ['page {draft}', 'page {draft}', '[[page {draft}]]'],
    'same semicolon page' => ['page;semi', 'page;semi', '[[page;semi]]'],
    'same comma page' => ['page,comma', 'page,comma', '[[page,comma]]'],
    'same dotted page' => ['page.with.dots', 'page.with.dots', '[[page.with.dots]]'],
    'same plus page' => ['page+plus', 'page+plus', '[[page+plus]]'],
    'same tilde page' => ['page~tilde', 'page~tilde', '[[page~tilde]]'],
    'same equals page' => ['page=equals', 'page=equals', '[[page=equals]]'],
    'same percent encoded page' => ['page%20encoded', 'page%20encoded', '[[page%20encoded]]'],
    'same numeric suffix page' => ['Page_123', 'Page_123', '[[Page_123]]'],
    'labeled docs target' => ['Runbook', '/docs/runbook', '[[Runbook|/docs/runbook]]'],
    'labeled uri target' => ['Title', 'https://example.org', '[[Title|https://example.org]]'],
    'labeled closing bracket label' => ['Label ] bracket', '/target', '[[Label \] bracket|/target]]'],
    'labeled pipe label' => ['Label | pipe', '/target', '[[Label \| pipe|/target]]'],
    'labeled closing bracket target' => ['Label', '/target/]bracket', '[[Label|/target/\]bracket]]'],
    'labeled pipe target' => ['Label', '/target/|pipe', '[[Label|/target/\|pipe]]'],
    'labeled pipes on both sides' => ['A | B', 'C | D', '[[A \| B|C \| D]]'],
    'labeled ampersand label' => ['AT&T', '/target', '[[AT&amp;T|/target]]'],
    'labeled ampersand target' => ['Label', '/a&b', '[[Label|/a&amp;b]]'],
    'labeled angle label' => ['<Label>', '/target', '[[&lt;Label&gt;|/target]]'],
    'labeled quoted label' => ['Quote "label"', '/target', '[[Quote &quot;label&quot;|/target]]'],
    'labeled quoted target' => ['Label', '/quote"target"', '[[Label|/quote&quot;target&quot;]]'],
    'labeled star punctuation label' => ['*literal* label', '/target', '[[*literal* label|/target]]'],
    'labeled underscore label' => ['literal_label', '/target', '[[literal_label|/target]]'],
    'labeled spaced target' => ['Spaced target', 'random string', '[[Spaced target|random string]]'],
    'labeled parenthesized target' => ['Parenthesized', '/source(packet)', '[[Parenthesized|/source(packet)]]'],
    'labeled bracketed target' => ['Bracket target', '/source[one]', '[[Bracket target|/source[one\]]]'],
    'labeled double close label' => ['Label ]] close', '/target', '[[Label \]\] close|/target]]'],
    'labeled double close target' => ['Label', '/target]]close', '[[Label|/target\]\]close]]'],
    'labeled heading target' => ['Section', '#heading', '[[Section|#heading]]'],
    'labeled query target' => ['Query', '/search?q=one&page=2', '[[Query|/search?q=one&amp;page=2]]'],
    'labeled colon label' => ['Term: definition', '/target', '[[Term: definition|/target]]'],
    'labeled slash label' => ['Docs/source', '/target', '[[Docs/source|/target]]'],
    'labeled scheme target' => ['Scheme', 'wiki:Page', '[[Scheme|wiki:Page]]'],
    'labeled code punctuation' => ['t`i*t_le', 'https://example.org', '[[t`i*t_le|https://example.org]]'],
    'labeled plus label' => ['C++', '/cpp', '[[C++|/cpp]]'],
    'labeled percent label' => ['100% ready', '/ready', '[[100% ready|/ready]]'],
    'labeled percent target' => ['Encoded', '/a%20b', '[[Encoded|/a%20b]]'],
];

$tests = [];

$tests['records markdown writer wikilink inline surge mapped case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(56, count($cases));
    };

foreach ($cases as $name => [$label, $url, $expected]) {
    $tests['maps upstream markdown writer wikilink inline surge ' . $name] =
        static function (TestRunner $t) use ($document, $wikiLink, $label, $url, $expected): void {
            $markdown = (new MarkdownWriter())->write($document([$wikiLink($label, $url)]));

            $t->same($expected, $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $link = $roundTrip->children[0]->children[0];
            $t->same('link', $link->type);
            $t->same(['wikilink'], $link->attr('classes'));
            $t->same($url, $link->attr('url'));
            $t->same($label, $link->children[0]->attr('text'));
        };
}

$tests['keeps titled wikilink nodes on explicit link syntax to preserve title'] =
    static function (TestRunner $t) use ($document, $wikiLink): void {
        $markdown = (new MarkdownWriter())->write($document([
            $wikiLink('Runbook', '/docs/runbook', ['title' => 'Review source']),
        ]));

        $t->same('[Runbook](/docs/runbook "Review source"){.wikilink}', $markdown);
    };

$tests['keeps attributed wikilink nodes on explicit link syntax to preserve attributes'] =
    static function (TestRunner $t) use ($document, $wikiLink): void {
        $markdown = (new MarkdownWriter())->write($document([
            $wikiLink('Runbook', '/docs/runbook', [
                'id' => 'runbook',
                'attributes' => ['data-source' => 'batch-56'],
            ]),
        ]));

        $t->same('[Runbook](/docs/runbook){#runbook .wikilink data-source="batch-56"}', $markdown);
    };

return $tests;
