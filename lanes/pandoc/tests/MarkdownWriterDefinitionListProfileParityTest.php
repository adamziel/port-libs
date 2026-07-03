<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$plain = static fn (string $value): AstNode => new AstNode('plain', [], [$text($value)]);
$definition = static fn (array $children): AstNode => new AstNode('definition', [], $children);
$term = static fn (array $children, string $fallback): AstNode => new AstNode('definition_term', ['text' => $fallback], $children);
$item = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    ['term' => $term->attr('text', '')],
    array_merge([$term], $definitions)
);
$document = static fn (AstNode $item): AstNode => new AstNode('document', [], [
    new AstNode('definition_list', [], [$item]),
]);
$simpleParagraphDocument = static fn (): AstNode => $document($item(
    $term([$text('Term')], 'Term'),
    [$definition([$paragraph('Definition')])]
));
$simplePlainDocument = static fn (): AstNode => $document($item(
    $term([$text('Term')], 'Term'),
    [$definition([$plain('Definition')])]
));

return [
    'maps markdown definition-list profile defaults to pandoc loose paragraph output' =>
        static function (TestRunner $t) use ($simpleParagraphDocument): void {
            $t->same("Term\n\n:   Definition", (new MarkdownWriter(['format' => 'markdown']))->write($simpleParagraphDocument()));
        },
    'maps markdown definition-list profile defaults to pandoc tight plain output' =>
        static function (TestRunner $t) use ($simplePlainDocument): void {
            $t->same("Term\n:   Definition", (new MarkdownWriter(['format' => 'markdown']))->write($simplePlainDocument()));
        },
    'maps commonmark gfm and strict definition lists to pandoc no-marker fallback by default' =>
        static function (TestRunner $t) use ($simpleParagraphDocument): void {
            foreach (['commonmark', 'gfm', 'markdown_github', 'markdown_strict'] as $format) {
                $t->same("Term  \nDefinition", (new MarkdownWriter(['format' => $format]))->write($simpleParagraphDocument()), $format);
            }
        },
    'honors definition list extension enablement for commonmark-family writers' =>
        static function (TestRunner $t) use ($simpleParagraphDocument): void {
            foreach ([
                ['format' => 'commonmark+definition_lists'],
                ['format' => 'gfm+definition_lists'],
                ['format' => 'markdown_strict+definition_lists'],
                ['format' => 'commonmark', 'extensions' => ['+definition_lists']],
                ['format' => 'gfm', 'extensions' => ['definition_list' => true]],
            ] as $options) {
                $t->same("Term\n\n:   Definition", (new MarkdownWriter($options))->write($simpleParagraphDocument()));
            }
        },
    'honors explicit definition list disablement as pandoc no-marker fallback' =>
        static function (TestRunner $t) use ($simpleParagraphDocument): void {
            foreach ([
                ['format' => 'markdown-definition_lists'],
                ['format' => 'markdown', 'extensions' => ['-definition_lists']],
                ['format' => 'markdown', 'definitionLists' => false],
            ] as $options) {
                $t->same("Term  \nDefinition", (new MarkdownWriter($options))->write($simpleParagraphDocument()));
            }
        },
];
