<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'preserves safe html global attributes across wordpress html writer output' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [
                'id' => 'global-attrs',
                'classes' => ['review-copy'],
                'attributes' => [
                    'part' => 'review-lead',
                    'exportparts' => 'review-lead:wp-lead',
                    'slot' => 'summary',
                    'inputmode' => 'latin',
                    'enterkeyhint' => 'send',
                    'spellcheck' => 'false',
                    'autocapitalize' => 'sentences',
                    'autocorrect' => 'off',
                    'writingsuggestions' => 'false',
                    'virtualkeyboardpolicy' => 'manual',
                    'onclick' => 'alert(1)',
                    'style' => 'color:red',
                ],
            ], [
                new AstNode('span', [
                    'attributes' => [
                        'part' => 'label',
                        'slot' => 'badge',
                        'spellcheck' => 'false',
                        'onfocus' => 'alert(1)',
                        'style' => 'display:none',
                    ],
                ], [new AstNode('text', ['text' => 'Reviewer label'])]),
                new AstNode('space'),
                new AstNode('link', [
                    'url' => '/review',
                    'attributes' => [
                        'exportparts' => 'link:review-link',
                        'enterkeyhint' => 'done',
                        'onmouseover' => 'alert(1)',
                    ],
                ], [new AstNode('text', ['text' => 'review'])]),
                new AstNode('space'),
                new AstNode('image', [
                    'url' => 'media/review.png',
                    'alt' => 'Review badge',
                    'width' => '80',
                    'height' => '40',
                    'attributes' => [
                        'part' => 'badge-image',
                        'slot' => 'media',
                        'onerror' => 'alert(1)',
                    ],
                ]),
            ]),
            new AstNode('table', [
                'attributes' => [
                    'spellcheck' => 'false',
                    'inputmode' => 'text',
                    'onclick' => 'alert(1)',
                ],
            ], [
                new AstNode('table_body', [
                    'attributes' => [
                        'spellcheck' => 'true',
                    ],
                ], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [
                            'attributes' => [
                                'enterkeyhint' => 'next',
                            ],
                        ], [new AstNode('text', ['text' => 'Ready'])]),
                    ]),
                ]),
            ]),
        ]);

        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<p id="global-attrs" class="review-copy" part="review-lead" exportparts="review-lead:wp-lead" slot="summary" inputmode="latin" enterkeyhint="send" spellcheck="false" autocapitalize="sentences" autocorrect="off" writingsuggestions="false" virtualkeyboardpolicy="manual">', $blocks);
        $t->contains('<span part="label" slot="badge" spellcheck="false">Reviewer label</span>', $blocks);
        $t->contains('<a href="/review" exportparts="link:review-link" enterkeyhint="done">review</a>', $blocks);
        $t->contains('<img src="media/review.png" alt="Review badge" width="80" height="40" part="badge-image" slot="media"/>', $blocks);
        $t->contains('<table spellcheck="false" inputmode="text"><tbody spellcheck="true"><tr><td enterkeyhint="next">Ready</td></tr></tbody></table>', $blocks);
        $t->true(!str_contains($blocks, 'onclick'), 'Unsafe block/table event handlers should not survive HTML writer global-attribute handoff');
        $t->true(!str_contains($blocks, 'onfocus') && !str_contains($blocks, 'onmouseover') && !str_contains($blocks, 'onerror'), 'Unsafe inline event handlers should not survive HTML writer global-attribute handoff');
        $t->true(!str_contains($blocks, 'display:none'), 'Unsafe inline style declarations should not survive HTML writer global-attribute handoff');
        $t->true(!str_contains($blocks, 'position:absolute'), 'Unsafe layout style declarations should not survive HTML writer global-attribute handoff');
    },

    'preserves safe inline colors while dropping unsafe style declarations' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('span', [
                    'attributes' => [
                        'style' => 'color:#b42318; position:absolute; background-color: rgb(255, 0, 0); text-decoration: underline wavy blue',
                    ],
                ], [new AstNode('text', ['text' => 'red'])]),
            ]),
        ]);

        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<span style="color:#b42318; background-color:rgb(255, 0, 0); text-decoration:underline wavy blue">red</span>', $blocks);
        $t->true(!str_contains($blocks, 'position:absolute'), 'Unsafe positioning should be removed from inline styles');
    },
];
