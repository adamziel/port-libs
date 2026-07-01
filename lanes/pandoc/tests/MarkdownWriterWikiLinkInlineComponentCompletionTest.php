<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$code = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);
$softbreak = static fn (): AstNode => new AstNode('softbreak');
$linebreak = static fn (): AstNode => new AstNode('linebreak');
$emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);
$wikiLink = static fn (array $children, string $url): AstNode => new AstNode(
    'link',
    ['url' => $url, 'classes' => ['wikilink']],
    $children
);

$firstLink = static function (AstNode $document): AstNode {
    return $document->children[0]->children[0] ?? new AstNode('missing');
};

$cases = [
    'code-only compact target escapes pipe' => [
        'children' => [$code('run|book')],
        'url' => 'run|book',
        'label' => 'run|book',
        'expected' => '[[run\\|book]]',
    ],
    'code and text labeled target escapes closing bracket and pipe' => [
        'children' => [$text('Run '), $code('book]')],
        'url' => '/docs/run|book]',
        'label' => 'Run book]',
        'expected' => '[[Run book\\]|/docs/run\\|book\\]]]',
    ],
    'softbreak compact target normalizes to one space' => [
        'children' => [$text('Release'), $softbreak(), $text('notes')],
        'url' => 'Release notes',
        'label' => 'Release notes',
        'expected' => '[[Release notes]]',
    ],
    'linebreak labeled target normalizes to one space' => [
        'children' => [$text('Runbook'), $linebreak(), $text('Beta')],
        'url' => '/docs/runbook-beta',
        'label' => 'Runbook Beta',
        'expected' => '[[Runbook Beta|/docs/runbook-beta]]',
    ],
    'mixed softbreak linebreak and code collapses whitespace' => [
        'children' => [$text('Alpha  '), $softbreak(), $text('  Beta'), $linebreak(), $code('Gamma')],
        'url' => 'Alpha Beta Gamma',
        'label' => 'Alpha Beta Gamma',
        'expected' => '[[Alpha Beta Gamma]]',
    ],
    'code html and pipe label escapes entities' => [
        'children' => [$code('AT&T|<review>')],
        'url' => '/docs/a&b',
        'label' => 'AT&T|<review>',
        'expected' => '[[AT&amp;T\\|&lt;review&gt;|/docs/a&amp;b]]',
    ],
];

$tests = [];

$tests['records markdown writer wikilink inline component completion mapped case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(6, count($cases));
    };

foreach ($cases as $name => $case) {
    $tests['maps upstream markdown writer wikilink inline component completion ' . $name] =
        static function (TestRunner $t) use ($case, $document, $firstLink, $wikiLink): void {
            $markdown = (new MarkdownWriter())->write($document([
                $wikiLink($case['children'], $case['url']),
            ]));

            $t->same($case['expected'], $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $link = $firstLink($roundTrip);

            $t->same('link', $link->type, $case['expected']);
            $t->same(['wikilink'], $link->attr('classes'), $case['expected']);
            $t->same($case['url'], $link->attr('url'), $case['expected']);
            $t->same($case['label'], $link->children[0]->attr('text'), $case['expected']);
        };
}

$tests['keeps formatted wikilink labels on explicit link syntax'] =
    static function (TestRunner $t) use ($document, $emph, $text, $wikiLink): void {
        $markdown = (new MarkdownWriter())->write($document([
            $wikiLink([$emph([$text('Runbook')])], '/docs/runbook'),
        ]));

        $t->same('[*Runbook*](/docs/runbook){.wikilink}', $markdown);
    };

return $tests;
