<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);

$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [new AstNode('text', ['text' => $value])]);

return [
    'renders metadata review as native group and list blocks' => static function (TestRunner $t) use ($text): void {
        $document = new AstNode('document', [
            'meta' => [
                'keywords' => [
                    'type' => 'MetaList',
                    'value' => [
                        ['type' => 'MetaInlines', 'value' => [$text('migration')]],
                        ['type' => 'MetaInlines', 'value' => [$text('blocks')]],
                    ],
                ],
            ],
        ]);

        $blocks = (new WordPressBlockWriter(['includeMetadata' => true]))->write($document);

        $t->contains('<!-- wp:group -->', $blocks);
        $t->contains('class="wp-block-group pandoc-document-metadata"', $blocks);
        $t->contains('<!-- wp:list -->', $blocks);
        $t->same(false, str_contains($blocks, '<!-- wp:html -->'), 'metadata review should not be serialized as a Custom HTML block');
    },

    'renders div line definition and footnote content as native blocks' => static function (TestRunner $t) use ($paragraph, $text): void {
        $document = new AstNode('document', [], [
            new AstNode('div', ['classes' => ['callout']], [
                new AstNode('heading', ['level' => 3], [$text('Grouped')]),
                $paragraph('Editable child paragraph.'),
            ]),
            new AstNode('line_block', [], [
                new AstNode('line', [], [$text('First line')]),
                new AstNode('line', [], [$text('Second line')]),
            ]),
            new AstNode('definition_list', [], [
                new AstNode('definition_item', [], [
                    new AstNode('term', [], [$text('Term')]),
                    new AstNode('definition', [], [$paragraph('Definition body.')]),
                ]),
            ]),
            new AstNode('paragraph', [], [
                $text('With note'),
                new AstNode('note', [], [$paragraph('Footnote body.')]),
            ]),
        ]);

        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('class="wp-block-group callout"', $blocks);
        $t->contains('<!-- wp:heading {"level":3} -->', $blocks);
        $t->contains('<!-- wp:verse -->', $blocks);
        $t->contains('<pre class="wp-block-verse">First line' . "\n" . 'Second line</pre>', $blocks);
        $t->contains('class="wp-block-group pandoc-definition-list"', $blocks);
        $t->contains('<p class="pandoc-definition-term"><strong>Term</strong></p>', $blocks);
        $t->contains('class="wp-block-group footnotes"', $blocks);
        $t->contains('<!-- wp:list {"ordered":true} -->', $blocks);
        $t->same(false, str_contains($blocks, '<!-- wp:html -->'), 'structured non-raw blocks should avoid Custom HTML blocks');
    },

    'keeps raw html as an explicit custom html block' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['html' => '<aside>Source HTML</aside>']),
        ]);

        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:html -->' . "\n" . '<aside>Source HTML</aside>' . "\n" . '<!-- /wp:html -->', $blocks);
    },
];
