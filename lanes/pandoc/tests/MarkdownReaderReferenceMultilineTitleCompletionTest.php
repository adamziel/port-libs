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

$cases = [
    'full link destination-line double title' => [
        'markdown' => "[visible][alpha]\n\n[alpha]: /alpha \"First title line\nsecond title line\"",
        'type' => 'link',
        'label' => 'alpha',
        'url' => '/alpha',
        'title' => "First title line\nsecond title line",
        'text' => 'visible',
    ],
    'shortcut link continuation-line single title' => [
        'markdown' => "[beta]\n\n[beta]: /beta\n'Shortcut title line\nsecond shortcut line'",
        'type' => 'link',
        'label' => 'beta',
        'url' => '/beta',
        'title' => "Shortcut title line\nsecond shortcut line",
        'text' => 'beta',
    ],
    'collapsed link continuation-line paren title' => [
        'markdown' => "[gamma][]\n\n[gamma]: /gamma\n(Paren title line\nsecond paren line)",
        'type' => 'link',
        'label' => 'gamma',
        'url' => '/gamma',
        'title' => "Paren title line\nsecond paren line",
        'text' => 'gamma',
    ],
    'reference image destination-line paren title' => [
        'markdown' => "![diagram][delta]\n\n[delta]: media/diagram.png (Figure title line\nsecond figure line)",
        'type' => 'image',
        'label' => 'delta',
        'url' => 'media/diagram.png',
        'title' => "Figure title line\nsecond figure line",
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

$tests['records markdown reference multiline title mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(4, count($cases));
    };

return $tests;
