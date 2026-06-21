<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$heading = static fn (int $level, string $textValue): AstNode => new AstNode('heading', ['level' => $level], [
    $text($textValue),
]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$term = static fn (array $children, string $fallback): AstNode => new AstNode('term', ['text' => $fallback], $children);
$definition = static fn (array $blocks): AstNode => new AstNode('definition', [], $blocks);
$item = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    ['term' => $term->attr('text', '')],
    array_merge([$term], $definitions)
);

$document = new AstNode('document', [], [
    new AstNode('definition_list', [], [
        $item($term([
            $text('Source review'),
        ], 'Source review'), [
            $definition([
                $heading(2, 'Block Audit'),
                $paragraph([
                    $text('Confirm '),
                    new AstNode('link', ['url' => '#media-checks'], [$text('media checks')]),
                    $text(' before publish.'),
                ]),
            ]),
        ]),
        $item($term([
            new AstNode('link', ['url' => '#publish-checklist'], [$text('Publish checklist')]),
        ], 'Publish checklist'), [
            $definition([
                new AstNode('plain', [], [$text('Reviewer sign-off stays linked to the checklist anchor.')]),
            ]),
        ]),
    ]),
]);

echo "LaTeX reviewer export:\n";
echo (new LatexWriter())->write($document) . "\n\n";

echo "WordPress block handoff:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";
