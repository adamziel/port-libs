<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode(
    'paragraph',
    [],
    [new AstNode('text', ['text' => $value])]
);

return [
    'core-only wordpress nodes do not load the extended renderer' => static function (
        TestRunner $t
    ) use ($text, $paragraph): void {
        $rendererClass = 'PortLibs\\Pandoc\\WordPressExtendedNodeRenderer';
        $t->same(false, class_exists($rendererClass, false));

        $document = new AstNode('document', [], [
            new AstNode('heading', ['level' => 2], [$text('Core heading')]),
            new AstNode('paragraph', [], [
                $text('Core '),
                new AstNode('emph', [], [$text('emphasis')]),
                $text(' and '),
                new AstNode('strong', [], [$text('strength')]),
                $text(' with '),
                new AstNode('link', ['url' => 'https://example.test'], [$text('link')]),
                $text('.'),
            ]),
            new AstNode('plain', [], [$text('Plain core text.')]),
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [$paragraph('Bullet item')]),
            ]),
            new AstNode('ordered_list', [], [
                new AstNode('list_item', [], [$paragraph('Ordered item')]),
            ]),
            new AstNode('line_block', [], [
                new AstNode('line', [], [$text('First line')]),
                new AstNode('line', [], [$text('Second line')]),
            ]),
            new AstNode('code_block', ['text' => 'let value = 1;']),
            new AstNode('table', [], [
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['text' => 'Cell']),
                    ]),
                ]),
            ]),
        ]);

        $expected = implode("\n\n", [
            '<!-- wp:heading {"level":2} -->' . "\n"
                . '<h2>Core heading</h2>' . "\n"
                . '<!-- /wp:heading -->',
            '<!-- wp:paragraph -->' . "\n"
                . '<p>Core <em>emphasis</em> and <strong>strength</strong> with '
                . '<a href="https://example.test">link</a>.</p>' . "\n"
                . '<!-- /wp:paragraph -->',
            '<!-- wp:paragraph -->' . "\n"
                . '<p>Plain core text.</p>' . "\n"
                . '<!-- /wp:paragraph -->',
            '<!-- wp:list -->' . "\n"
                . '<ul><li>Bullet item</li></ul>' . "\n"
                . '<!-- /wp:list -->',
            '<!-- wp:list {"ordered":true} -->' . "\n"
                . '<ol><li>Ordered item</li></ol>' . "\n"
                . '<!-- /wp:list -->',
            '<!-- wp:verse -->' . "\n"
                . '<pre class="wp-block-verse">First line' . "\n"
                . 'Second line</pre>' . "\n"
                . '<!-- /wp:verse -->',
            '<!-- wp:code -->' . "\n"
                . '<pre class="wp-block-code"><code>let value = 1;</code></pre>' . "\n"
                . '<!-- /wp:code -->',
            '<!-- wp:table -->' . "\n"
                . '<figure class="wp-block-table"><table><tbody><tr><td>Cell</td></tr></tbody></table></figure>' . "\n"
                . '<!-- /wp:table -->',
        ]);

        $t->same($expected, (new WordPressBlockWriter())->write($document));
        $t->same(false, class_exists($rendererClass, false));
    },
];
