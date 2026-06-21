<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$heading = static fn (int $level, string $textValue, array $attrs = []): AstNode => new AstNode(
    'heading',
    $attrs + ['level' => $level],
    [$text($textValue)]
);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$document = new AstNode('document', [], [
    $heading(1, 'Legacy Handbook', ['id' => 'legacy-handbook']),
    $heading(2, 'Import Checklist'),
    $paragraph([
        $text('Keep the reviewer export aligned with the source book hierarchy.'),
    ]),
]);

echo "LaTeX reviewer export:\n";
echo (new LatexWriter(['writerTopLevelDivision' => 'chapter']))->write($document) . "\n\n";

echo "WordPress block handoff:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";
