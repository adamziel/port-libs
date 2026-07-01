<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$note = static fn (array $blocks): AstNode => new AstNode('note', [], $blocks);
$citation = static fn (array $attrs): AstNode => new AstNode('citation', $attrs);
$citationGroup = static fn (array $citations): AstNode => new AstNode('citation_group', [], $citations);
$link = static fn (string $url, string $title, string $label): AstNode => new AstNode(
    'link',
    ['url' => $url, 'title' => $title],
    [$text($label)]
);
$code = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);

$fixture = static fn (): string =>
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-inline-note-citations.md');

$collectNodes = null;
$collectNodes = static function (AstNode $node, string $type) use (&$collectNodes): array {
    $matches = $node->type === $type ? [$node] : [];

    foreach ($node->children as $child) {
        array_push($matches, ...$collectNodes($child, $type));
    }

    return $matches;
};

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$cases = [
    'normal citation inside note' => [
        'source' => 'foo^[bar [@doe]]',
        'document' => $document([
            $paragraph([
                $text('foo'),
                $note([
                    $paragraph([
                        $text('bar '),
                        $citation(['id' => 'doe']),
                    ]),
                ]),
            ]),
        ]),
        'expected' => "foo[^1]\n\n[^1]: bar [@doe]",
        'inlineTypes' => ['text', 'citation'],
        'citationIds' => ['doe'],
    ],
    'link and author citation inside note' => [
        'source' => 'alpha^[note [packet](https://example.test/source "source title") and @roe [p. 9]]',
        'document' => $document([
            $paragraph([
                $text('alpha'),
                $note([
                    $paragraph([
                        $text('note '),
                        $link('https://example.test/source', 'source title', 'packet'),
                        $text(' and '),
                        $citation(['id' => 'roe', 'mode' => 'author_in_text', 'suffix' => [$text('p. 9')]]),
                    ]),
                ]),
            ]),
        ]),
        'expected' => "alpha[^1]\n\n[^1]: note [packet](https://example.test/source \"source title\") and @roe [p. 9]",
        'inlineTypes' => ['text', 'link', 'text', 'citation'],
        'citationIds' => ['roe'],
        'linkUrls' => ['https://example.test/source'],
        'linkTitles' => ['source title'],
    ],
    'code and citation group inside note' => [
        'source' => 'trail^[code `]` and [@smith; see -@doe]]',
        'document' => $document([
            $paragraph([
                $text('trail'),
                $note([
                    $paragraph([
                        $text('code '),
                        $code(']'),
                        $text(' and '),
                        $citationGroup([
                            $citation(['id' => 'smith']),
                            $citation(['id' => 'doe', 'mode' => 'suppress_author', 'prefix' => [$text('see')]]),
                        ]),
                    ]),
                ]),
            ]),
        ]),
        'expected' => "trail[^1]\n\n[^1]: code `]` and [@smith; see -@doe]",
        'inlineTypes' => ['text', 'code', 'text', 'citation_group'],
        'citationIds' => ['smith', 'doe'],
        'codeTexts' => [']'],
    ],
];

$tests = [
    'records markdown writer inline-note citation fixture mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(3, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer inline-note citation fixture ' . $label] =
        static function (TestRunner $t) use ($case, $collectNodes, $fixture, $inlineTypes, $label): void {
            $markdown = (new MarkdownWriter())->write($case['document']);

            $t->contains($case['source'], $fixture(), $label . ' source fixture line');
            $t->same($case['expected'], $markdown, $label . ' markdown');

            $roundTrip = (new MarkdownReader())->read($markdown);
            $note = $collectNodes($roundTrip, 'note')[0] ?? new AstNode('missing');
            $noteParagraph = $note->children[0] ?? new AstNode('missing');

            $t->same($case['expected'], (new MarkdownWriter())->write($roundTrip), $label . ' stable regeneration');
            $t->same('note', $note->type, $label . ' note node');
            $t->same('paragraph', $noteParagraph->type, $label . ' note paragraph');
            $t->same($case['inlineTypes'], $inlineTypes($noteParagraph), $label . ' note inline types');
            $t->same($case['citationIds'], array_map(
                static fn (AstNode $node): string => (string) $node->attr('id', ''),
                $collectNodes($noteParagraph, 'citation')
            ), $label . ' citation ids');

            if (isset($case['linkUrls'])) {
                $links = $collectNodes($noteParagraph, 'link');
                $t->same($case['linkUrls'], array_map(
                    static fn (AstNode $node): string => (string) $node->attr('url', ''),
                    $links
                ), $label . ' link urls');
                $t->same($case['linkTitles'], array_map(
                    static fn (AstNode $node): string => (string) $node->attr('title', ''),
                    $links
                ), $label . ' link titles');
            }

            if (isset($case['codeTexts'])) {
                $t->same($case['codeTexts'], array_map(
                    static fn (AstNode $node): string => (string) $node->attr('text', ''),
                    $collectNodes($noteParagraph, 'code')
                ), $label . ' code texts');
            }
        };
}

return $tests;
