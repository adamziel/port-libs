<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$citation = static fn (string $id): AstNode => new AstNode('citation', ['id' => $id]);
$link = static fn (string $url, string $label): AstNode => new AstNode('link', ['url' => $url], [$text($label)]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);

$collectNodes = null;
$collectNodes = static function (AstNode $node, string $type) use (&$collectNodes): array {
    $matches = [];
    if ($node->type === $type) {
        $matches[] = $node;
    }

    foreach ($node->children as $child) {
        array_push($matches, ...$collectNodes($child, $type));
    }

    return $matches;
};

$cases = [
    'citation immediately after reference link' => [
        'children' => [$link('/url', 'link'), $citation('author')],
        'expected' => "[link][][@author]\n\n  [link]: /url",
    ],
    'space and citation after reference link' => [
        'children' => [$link('/url', 'link'), $space(), $citation('author')],
        'expected' => "[link][] [@author]\n\n  [link]: /url",
    ],
];

$tests = [
    'records markdown writer shortcut citation reference completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(2, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer shortcut citation reference completion ' . $label] =
        static function (TestRunner $t) use ($case, $collectNodes, $document): void {
            $markdown = (new MarkdownWriter(['referenceLinks' => true]))->write($document($case['children']));

            $t->same($case['expected'], $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $links = $collectNodes($roundTrip, 'link');
            $citations = $collectNodes($roundTrip, 'citation');

            $t->same('link', ($links[0] ?? new AstNode('missing'))->type);
            $t->same('/url', $links[0]->attr('url'));
            $t->same('citation', ($citations[0] ?? new AstNode('missing'))->type);
            $t->same('author', $citations[0]->attr('id'));
        };
}

return $tests;
