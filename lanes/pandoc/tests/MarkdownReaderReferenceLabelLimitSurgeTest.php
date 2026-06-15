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

$overlongReferenceLabelCases = [];
for ($index = 0; $index < 10; $index++) {
    $label = str_repeat(chr(ord('a') + $index), 1000 + $index);
    $url = '/overlong-reference-label-' . $index;

    $overlongReferenceLabelCases["shortcut link length {$index}"] = [
        'markdown' => "[{$label}]\n\n[{$label}]: {$url}",
    ];
    $overlongReferenceLabelCases["collapsed link length {$index}"] = [
        'markdown' => "[{$label}][]\n\n[{$label}]: {$url}",
    ];
    $overlongReferenceLabelCases["full link length {$index}"] = [
        'markdown' => "[visible {$index}][{$label}]\n\n[{$label}]: {$url}",
    ];
    $overlongReferenceLabelCases["shortcut image length {$index}"] = [
        'markdown' => "![{$label}]\n\n[{$label}]: {$url}.png",
    ];
    $overlongReferenceLabelCases["full image length {$index}"] = [
        'markdown' => "![visible {$index}][{$label}]\n\n[{$label}]: {$url}.png",
    ];
}

return [
    'maps upstream commonmark overlong reference labels as literal text' =>
        static function (TestRunner $t) use ($findFirstNode, $overlongReferenceLabelCases): void {
            $reader = new MarkdownReader();
            $mapped = 0;

            foreach ($overlongReferenceLabelCases as $name => $case) {
                $document = $reader->read($case['markdown']);
                $firstBlock = $document->children[0] ?? new AstNode('missing');

                $t->same('paragraph', $firstBlock->type, $name);
                $t->same('missing', $findFirstNode($document, 'link')->type, $name);
                $t->same('missing', $findFirstNode($document, 'image')->type, $name);
                $mapped++;
            }

            $t->same(50, $mapped);
        },
    'keeps commonmark maximum length reference labels linkable at 999 characters' =>
        static function (TestRunner $t) use ($findFirstNode): void {
            $label = str_repeat('z', 999);
            $imageLabel = str_repeat('y', 999);
            $document = (new MarkdownReader())->read("[{$label}] and ![{$imageLabel}]\n\n[{$label}]: /max-label\n[{$imageLabel}]: /max-label.png");
            $link = $findFirstNode($document, 'link');
            $image = $findFirstNode($document, 'image');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('link', $link->type);
            $t->same('/max-label', $link->attr('url'));
            $t->same('image', $image->type);
            $t->same('/max-label.png', $image->attr('url'));
            $t->contains('<a href="/max-label">', $blocks);
            $t->contains('<img src="/max-label.png"', $blocks);
        },
    'keeps overlong implicit heading reference labels literal' =>
        static function (TestRunner $t) use ($findFirstNode): void {
            $label = str_repeat('h', 1000);
            $document = (new MarkdownReader())->read("# {$label}\n\n[{$label}]");
            $paragraph = $document->children[1] ?? new AstNode('missing');

            $t->same('paragraph', $paragraph->type);
            $t->same('missing', $findFirstNode($paragraph, 'link')->type);
            $t->same($label, trim((string) $paragraph->attr('text', ''), '[]'));
        },
];
