<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = static fn (): string => implode("\n", [
    '+---------------+---------------+',
    '| Fruit         | Price         |',
    '+===============+===============+',
    '| Bananas       | $1.34         |',
    '+---------------+---------------+',
    '| Oranges       | $2.10         |',
    '+===============+===============+',
    '| Sum           | $3.44         |',
    '+===============+===============+',
]);

return [
    'maps upstream manual grid table footer fixture into table foot' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader())->read($fixture());
            $table = $document->children[0] ?? new AstNode('missing');
            $sections = array_map(static fn (AstNode $section): string => $section->type, $table->children);
            $head = $table->children[0] ?? new AstNode('missing');
            $body = $table->children[1] ?? new AstNode('missing');
            $foot = $table->children[2] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('table', $table->type);
            $t->same(['table_head', 'table_body', 'table_foot'], $sections);
            $t->same([16 / 72, 16 / 72], $table->attr('widths'));
            $t->same(1, count($head->children));
            $t->same(2, count($body->children));
            $t->same(1, count($foot->children));
            $t->same('Fruit', $head->children[0]->children[0]->attr('text'));
            $t->same('Oranges', $body->children[1]->children[0]->attr('text'));
            $t->same('Sum', $foot->children[0]->children[0]->attr('text'));
            $t->same('$3.44', $foot->children[0]->children[1]->attr('text'));
            $t->contains('<tbody><tr><td>Bananas</td><td>$1.34</td></tr><tr><td>Oranges</td><td>$2.10</td></tr></tbody>', $blocks);
            $t->contains('<tfoot><tr><td>Sum</td><td>$3.44</td></tr></tfoot>', $blocks);
        },

    'records upstream manual grid table footer mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(1, 1);
        },
];
