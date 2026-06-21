<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$heading = static fn (int $level, string $textValue, array $attrs = []): AstNode => new AstNode('heading', [
    'level' => $level,
    ...$attrs,
], [$text($textValue)]);
$paragraph = static fn (string $textValue): AstNode => new AstNode('paragraph', [], [$text($textValue)]);

$document = new AstNode('document', [], [
    $heading(1, 'Migration Review', ['id' => 'migration-review']),
    $paragraph('Summarize block conversion decisions before publish.'),
    $heading(2, 'Media Checks'),
    $paragraph('Confirm captions and source URLs.'),
    $heading(3, 'Reviewer Notes'),
    $paragraph('Keep this outline aligned with the editor review packet.'),
]);

echo "LaTeX reviewer export:\n";
echo (new LatexWriter())->write($document) . "\n\n";

echo "WordPress block handoff:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";
