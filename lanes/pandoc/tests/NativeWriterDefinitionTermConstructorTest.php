<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;

return [
    'renders definition term nodes as textual native definition list terms' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('definition_list', [], [
                new AstNode('definition_item', ['term' => 'Fallback term'], [
                    new AstNode('definition_term', ['text' => 'Review term'], [
                        new AstNode('text', ['text' => 'Review']),
                        new AstNode('space'),
                        new AstNode('code', [
                            'id' => 'term-code',
                            'classes' => ['native'],
                            'attributes' => ['data-kind' => 'definition'],
                            'text' => 'term',
                        ]),
                    ]),
                    new AstNode('definition', [], [
                        new AstNode('paragraph', [], [
                            new AstNode('text', ['text' => 'Body']),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $roundTrip = (new NativeReader())->read($native);
        $term = $roundTrip->children[0]->children[0]->children[0];
        $code = $term->children[2];

        $t->contains('DefinitionList [ ( [ Str "Review" , Space , Code ( "term-code" , [ "native" ] , [ ( "data-kind" , "definition" ) ] ) "term" ]', $native);
        $t->contains('Para [ Str "Body" ]', $native);
        $t->true(!str_contains($native, 'Fallback term'), 'definition_term children must win over item fallback text');
        $t->same('term', $term->type);
        $t->same('Review term', $term->attr('text'));
        $t->same(['text', 'space', 'code'], array_map(static fn (AstNode $node): string => $node->type, $term->children));
        $t->same('term-code', $code->attr('id'));
        $t->same(['native'], $code->attr('classes'));
        $t->same(['data-kind' => 'definition'], $code->attr('attributes'));
        $t->same('term', $code->attr('text'));
    },
    'expands edited definition term text through native json output' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'DefinitionList', 'c' => [
                    [
                        [
                            ['t' => 'Str', 'c' => 'Source'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'Term'],
                        ],
                        [
                            [
                                ['t' => 'Para', 'c' => [
                                    ['t' => 'Str', 'c' => 'Body'],
                                ]],
                            ],
                        ],
                    ],
                ]],
            ],
        ];

        $document = (new PandocJsonReader())->readPacket($packet);
        $item = $document->children[0]->children[0];
        $definition = $item->children[1];
        $edited = new AstNode('document', $document->attrs, [
            new AstNode('definition_list', [], [
                new AstNode('definition_item', [], [
                    new AstNode('definition_term', [], [
                        new AstNode('text', ['text' => 'Edited Term']),
                    ]),
                    $definition,
                ]),
            ]),
        ]);

        $decoded = json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR);
        $term = $decoded['blocks'][0]['c'][0][0];

        $t->same([
            ['t' => 'Str', 'c' => 'Edited'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'Term'],
        ], $term);
        $t->same('Para', $decoded['blocks'][0]['c'][0][1][0][0]['t']);
        $t->same('Body', $decoded['blocks'][0]['c'][0][1][0][0]['c'][0]['c']);
    },
];
