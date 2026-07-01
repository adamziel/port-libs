<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;

return [
    'writes citation groups as native inline metadata constructors' => static function (TestRunner $t): void {
        $citationGroup = new AstNode('citation_group', [
            'citationSourceInlines' => [
                new AstNode('text', ['text' => '[see']),
                new AstNode('space'),
                new AstNode('text', ['text' => '@doe2026,']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'p.']),
                new AstNode('space'),
                new AstNode('text', ['text' => '4;']),
                new AstNode('space'),
                new AstNode('text', ['text' => '-@roe2025]']),
            ],
        ], [
            new AstNode('citation', [
                'id' => 'doe2026',
                'prefix' => [new AstNode('text', ['text' => 'see'])],
                'suffix' => [
                    new AstNode('text', ['text' => 'p.']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => '4']),
                ],
                'mode' => 'normal',
                'noteNum' => 1,
                'hash' => 2026,
            ]),
            new AstNode('citation', [
                'id' => 'roe2025',
                'mode' => 'suppress_author',
                'noteNum' => 2,
                'hash' => 2025,
            ]),
        ]);
        $document = new AstNode('document', [
            'meta' => [
                'reviewInline' => [$citationGroup],
            ],
        ], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Review']),
                new AstNode('space'),
                $citationGroup,
            ]),
        ]);

        $native = (new NativeWriter(['standalone' => true]))->write($document);
        $roundTrip = (new NativeReader())->read($native);
        $meta = $roundTrip->attr('meta');
        $paragraph = $roundTrip->children[0];

        $t->contains('MetaInlines [ Cite', $native);
        $t->contains('citationId = "doe2026"', $native);
        $t->contains('citationMode = SuppressAuthor', $native);
        $t->contains('[ Str "[see" , Space , Str "@doe2026," , Space , Str "p." , Space , Str "4;" , Space , Str "-@roe2025]" ]', $native);
        $t->same('MetaInlines', $meta['reviewInline']['type']);
        $t->same('citation_group', $meta['reviewInline']['value'][0]->type);
        $t->same(['doe2026', 'roe2025'], array_map(static fn (AstNode $node): string => $node->attr('id'), $meta['reviewInline']['value'][0]->children));
        $t->same('suppress_author', $meta['reviewInline']['value'][0]->children[1]->attr('mode'));
        $t->same('paragraph', $paragraph->type);
        $t->same(['text', 'text', 'citation_group'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same(['doe2026', 'roe2025'], array_map(static fn (AstNode $node): string => $node->attr('id'), $paragraph->children[2]->children));
    },
];
