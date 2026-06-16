<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);
$link = static fn (string $label, string $title): AstNode => new AstNode(
    'link',
    ['url' => '', 'title' => $title],
    [$text($label)]
);
$image = static fn (string $label, string $title): AstNode => new AstNode(
    'image',
    ['url' => '', 'title' => $title, 'alt' => $label],
    [$text($label)]
);

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

$markdownTitle = static function (string $title): string {
    $title = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $title) ?? $title;

    return str_replace(['\\', '"'], ['\\\\', '\\"'], $title);
};

$cases = [
    'double simple title' => ['Double title', 'Double title'],
    'double entity title' => ['AT&T title', 'AT&T title'],
    'double escaped quote title' => ['Escaped "quote" title', 'Escaped "quote" title'],
    'double numeric entity title' => ['Score ) title', 'Score ) title'],
    'double escaped star title' => ['Escaped * title', 'Escaped * title'],
    'double multiline title' => ["First line\nsecond line", 'First line second line'],
    'double tabbed title' => ["First\tsecond", 'First second'],
    'single simple title' => ['Single title', 'Single title'],
    'single double quote title' => ['Double "inside" title', 'Double "inside" title'],
    'single escaped apostrophe title' => ["Escaped ' apostrophe", "Escaped ' apostrophe"],
    'single entity title' => ["Entity \u{00A9} title", "Entity \u{00A9} title"],
    'paren simple title' => ['Paren title', 'Paren title'],
    'paren escaped close title' => ['Paren ) title', 'Paren ) title'],
    'paren escaped open title' => ['Paren ( title', 'Paren ( title'],
    'paren entity title' => ['Paren & title', 'Paren & title'],
];

$tests = [
    'records markdown writer omitted destination title fixture mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(30, count($cases) * 2);
        },
];

foreach ($cases as $name => [$title, $expectedTitle]) {
    $label = 'Link ' . $name;
    $expectedMarkdown = '[' . $label . '](<> "' . $markdownTitle($title) . '")';

    $tests['maps upstream markdown writer omitted destination link ' . $name] =
        static function (TestRunner $t) use ($document, $expectedMarkdown, $expectedTitle, $findFirstNode, $label, $link, $title): void {
            $markdown = (new MarkdownWriter())->write($document([$link($label, $title)]));
            $roundTrip = (new MarkdownReader())->read($markdown);
            $node = $findFirstNode($roundTrip, 'link');

            $t->same($expectedMarkdown, $markdown);
            $t->same('link', $node->type);
            $t->same('', $node->attr('url'));
            $t->same($expectedTitle, $node->attr('title'));
            $t->same($label, $node->children[0]->attr('text') ?? null);
        };

    $imageLabel = 'Image ' . $name;
    $expectedImageMarkdown = '![' . $imageLabel . '](<> "' . $markdownTitle($title) . '")';

    $tests['maps upstream markdown writer omitted destination image ' . $name] =
        static function (TestRunner $t) use ($document, $expectedImageMarkdown, $expectedTitle, $findFirstNode, $image, $imageLabel, $title): void {
            $markdown = (new MarkdownWriter())->write($document([$image($imageLabel, $title)]));
            $roundTrip = (new MarkdownReader())->read($markdown);
            $node = $findFirstNode($roundTrip, 'image');

            $t->same($expectedImageMarkdown, $markdown);
            $t->same('image', $node->type);
            $t->same('', $node->attr('url'));
            $t->same($expectedTitle, $node->attr('title'));
            $t->same($imageLabel, $node->attr('alt'));
        };
}

return $tests;
