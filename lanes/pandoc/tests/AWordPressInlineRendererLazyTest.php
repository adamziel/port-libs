<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'wordpress inline renderer loads only when inline content is rendered' => static function (TestRunner $t): void {
        $rendererClass = 'PortLibs\\Pandoc\\WordPressInlineRenderer';
        $t->same(false, class_exists($rendererClass, false));

        $writer = new WordPressBlockWriter();
        $t->same('', $writer->write(new AstNode('document')));
        $t->same(false, class_exists($rendererClass, false));

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Preserve ']),
                new AstNode('code', ['text' => 'code_example();']),
                new AstNode('text', ['text' => ' exactly.']),
            ]),
        ]);
        $t->same(
            '<!-- wp:paragraph -->' . "\n"
                . '<p>Preserve <code>code_example();</code> exactly.</p>' . "\n"
                . '<!-- /wp:paragraph -->',
            $writer->write($document)
        );
        $t->same(true, class_exists($rendererClass, false));
    },
];
