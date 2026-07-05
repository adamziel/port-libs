<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$findFirstNode = null;
$findFirstNode = static function (AstNode $node, string $type) use (&$findFirstNode): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findFirstNode($child, $type);
        if ($match->type === $type) {
            return $match;
        }
    }

    return new AstNode('missing');
};

$collectNodes = null;
$collectNodes = static function (AstNode $node, string $type) use (&$collectNodes): array {
    $nodes = $node->type === $type ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($nodes, ...$collectNodes($child, $type));
    }

    return $nodes;
};

$cases = [
    'full link destination-line double title' => [
        'markdown' => "[visible][alpha]\n\n[alpha]: /alpha \"First title line\nsecond title line\"",
        'type' => 'link',
        'label' => 'alpha',
        'url' => '/alpha',
        'title' => 'First title line second title line',
        'text' => 'visible',
    ],
    'shortcut link continuation-line single title' => [
        'markdown' => "[beta]\n\n[beta]: /beta\n'Shortcut title line\nsecond shortcut line'",
        'type' => 'link',
        'label' => 'beta',
        'url' => '/beta',
        'title' => 'Shortcut title line second shortcut line',
        'text' => 'beta',
    ],
    'collapsed link continuation-line paren title' => [
        'markdown' => "[gamma][]\n\n[gamma]: /gamma\n(Paren title line\nsecond paren line)",
        'type' => 'link',
        'label' => 'gamma',
        'url' => '/gamma',
        'title' => 'Paren title line second paren line',
        'text' => 'gamma',
    ],
    'reference image destination-line paren title' => [
        'markdown' => "![diagram][delta]\n\n[delta]: media/diagram.png (Figure title line\nsecond figure line)",
        'type' => 'image',
        'label' => 'delta',
        'url' => 'media/diagram.png',
        'title' => 'Figure title line second figure line',
        'alt' => 'diagram',
    ],
];

$tests = [];

foreach ($cases as $name => $case) {
    $tests["maps upstream markdown reference multiline title {$name}"] =
        static function (TestRunner $t) use ($case, $findFirstNode, $name): void {
            $document = (new MarkdownReader())->read($case['markdown']);
            $node = $findFirstNode($document, $case['type']);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same($case['type'], $node->type, $name);
            $t->same($case['url'], $node->attr('url'), $name . ' url');
            $t->same($case['title'], $node->attr('title'), $name . ' title');
            $t->same(false, str_contains($blocks, '[' . $case['label'] . ']:'), $name . ' reference definition hidden');
            if ($case['type'] === 'image') {
                $t->same($case['alt'], $node->attr('alt'), $name . ' alt');
                return;
            }

            $t->same($case['text'], $node->children[0]->attr('text'), $name . ' text');
        };
}

$tests['maps checked-in markdown reference multiline title fixture'] =
    static function (TestRunner $t) use ($collectNodes): void {
        $fixture = (string) file_get_contents(
            dirname(__DIR__) . '/fixtures/upstream-markdown-reference-multiline-title.md'
        );
        $document = (new MarkdownReader())->read($fixture);
        $links = $collectNodes($document, 'link');
        $images = $collectNodes($document, 'image');

        $t->same(3, count($document->children));
        $t->same(2, count($links));
        $t->same('visible', $links[0]->children[0]->attr('text'));
        $t->same('/alpha', $links[0]->attr('url'));
        $t->same('First title line second title line', $links[0]->attr('title'));
        $t->same('beta', $links[1]->children[0]->attr('text'));
        $t->same('/beta', $links[1]->attr('url'));
        $t->same('Shortcut title line second shortcut line', $links[1]->attr('title'));
        $t->same(1, count($images));
        $t->same('media/diagram.png', $images[0]->attr('url'));
        $t->same('Figure title line second figure line', $images[0]->attr('title'));
    };

$tests['records markdown reference multiline title mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(4, count($cases));
    };

return $tests;
