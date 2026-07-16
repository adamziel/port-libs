<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$document = new AstNode('document', [
    'meta' => [
        'title' => 'WordPress Native Citation and Figure Packet',
        'batch' => 'wp-native-cite-figure-42',
        'ready' => true,
    ],
], [
    new AstNode('heading', ['level' => 2, 'id' => 'citation-figure-packet', 'classes' => ['handoff']], [
        $text('Citation and Figure Packet'),
    ]),
    new AstNode('figure', [
        'id' => 'fig-release',
        'classes' => ['review-media'],
        'attributes' => ['data-source' => 'batch-42'],
        'caption' => 'Release archive frame',
        'shortCaption' => 'release frame',
    ], [
        new AstNode('image', [
            'url' => 'https://example.test/uploads/release-frame.jpg',
            'title' => 'Release archive source',
            'alt' => 'Release archive frame',
            'attributes' => ['data-source' => 'batch-42'],
        ], [$text('Release archive frame')]),
    ]),
    new AstNode('paragraph', [], [
        $text('Reviewer citation boundary: '),
        new AstNode('citation', [
            'citations' => [
                [
                    'id' => 'source-audit',
                    'mode' => 'author_in_text',
                    'suffix' => [$text('p. 7')],
                    'noteNum' => 1,
                ],
                [
                    'id' => 'media-review',
                    'mode' => 'suppress_author',
                    'prefix' => [$text('see')],
                    'suffix' => [$text('appendix')],
                    'noteNum' => 1,
                ],
            ],
        ], [$text('@source-audit [p. 7; see -@media-review appendix]')]),
        $text(' before publishing.'),
    ]),
]);

echo (new NativeWriter(['standalone' => true]))->write($document) . "\n";
