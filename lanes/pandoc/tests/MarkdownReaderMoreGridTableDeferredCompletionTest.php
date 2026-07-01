<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$plainText = null;
$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }

    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return "\n";
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

return [
    'maps upstream markdown reader more grid table deferred block cell completion' =>
        static function (TestRunner $t) use ($plainText): void {
            $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-reader-more-grid-table-deferred.md');
            $document = (new MarkdownReader())->read($fixture);
            $table = $document->children[0] ?? new AstNode('missing');
            $headCell = $table->children[0]->children[0]->children[1] ?? new AstNode('missing');
            $bodyLabel = $table->children[1]->children[0]->children[0] ?? new AstNode('missing');
            $deferredCell = $table->children[1]->children[0]->children[1] ?? new AstNode('missing');
            $markdown = (new MarkdownWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('table', $table->type);
            $t->same('Deferred grid cell block content', $table->attr('caption'));
            $t->same(['default', 'default'], $table->attr('alignments'));
            $t->same('Notes', $plainText($headCell));
            $t->same('Alpha', $plainText($bodyLabel));
            $t->same('First paragraph.' . "\n" . '- queued' . "\n" . '- reviewed', $deferredCell->attr('text'));
            $t->same(['paragraph', 'bullet_list'], array_map(
                static fn (AstNode $node): string => $node->type,
                $deferredCell->children
            ));
            $t->same('First paragraph.', $deferredCell->children[0]->attr('text'));
            $t->same('queued', $plainText($deferredCell->children[1]->children[0] ?? new AstNode('missing')));
            $t->same('reviewed', $plainText($deferredCell->children[1]->children[1] ?? new AstNode('missing')));
            $t->contains('- queued', $markdown);
            $t->contains('<td><p>First paragraph.</p><ul><li>queued</li><li>reviewed</li></ul></td>', $blocks);
        },

    'records upstream markdown reader more grid table deferred mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(1, 1);
        },
];
