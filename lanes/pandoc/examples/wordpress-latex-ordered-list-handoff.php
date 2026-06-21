<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$plain = static fn (string $textValue): AstNode => new AstNode('plain', [], [$text($textValue)]);
$item = static fn (string $textValue): AstNode => new AstNode('list_item', [], [$plain($textValue)]);

$document = new AstNode('document', [], [
    $paragraph([
        $text('Reviewer checklist for the imported archive:'),
    ]),
    new AstNode('ordered_list', ['start' => 4, 'style' => 'lower_roman', 'delimiter' => 'period'], [
        $item('Queue editorial review'),
        new AstNode('list_item', [], [
            $plain('Publish reviewed batch'),
            new AstNode('ordered_list', ['start' => 2, 'style' => 'upper_alpha', 'delimiter' => 'one_paren'], [
                $item('Confirm media captions'),
                $item('Record source URL audit'),
            ]),
        ]),
    ]),
]);

echo "LaTeX reviewer export:\n";
echo (new LatexWriter())->write($document) . "\n\n";

echo "WordPress block handoff:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";
