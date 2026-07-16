<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (string $text): AstNode => new AstNode('paragraph', [], [
    new AstNode('text', ['text' => $text]),
]);
$item = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);

$document = new AstNode('document', [], [
    $paragraph('Reviewer checklist before WordPress publish:'),
    new AstNode('ordered_list', [
        'start' => 4,
        'style' => 'upper_alpha',
        'delimiter' => 'one_paren',
    ], [
        $item([$text('Verify block order')]),
        $item([
            $text('Attach source notes'),
            new AstNode('bullet_list', [], [
                $item([$text('Keep imported anchor links')]),
                $item([$text('Confirm migrated media IDs')]),
            ]),
        ]),
    ]),
    new AstNode('bullet_list', ['taskList' => true], [
        $item([$text('Run post-import QA')], ['taskChecked' => false]),
        $item([$text('Publish reviewed content')], ['taskChecked' => true]),
    ]),
]);

echo "HTML preview:\n";
echo (new HtmlWriter())->write($document) . "\n\n";

echo "WordPress blocks:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";
