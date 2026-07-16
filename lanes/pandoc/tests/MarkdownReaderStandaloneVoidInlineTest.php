<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps upstream html reader standalone void inline fragments' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-void-inline.html');
        $document = (new MarkdownReader())->read($fixture);
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $expected = [
            [
                'raw' => '<area shape="rect" coords="0,0,80,40" href="/wp-admin/upload.php" alt="Media library">',
                'text' => ' keeps imported hotspots visible.',
            ],
            [
                'raw' => '<source src="/wp-content/uploads/imported-tour.mp4" type="video/mp4">',
                'text' => ' keeps imported media source visible.',
            ],
            [
                'raw' => '<track kind="captions" src="/wp-content/uploads/imported-tour.vtt" srclang="en" label="English captions">',
                'text' => ' keeps imported captions visible.',
            ],
            [
                'raw' => '<embed src="/wp-content/uploads/imported-map-fallback.swf" type="application/x-shockwave-flash">',
                'text' => ' keeps legacy fallback visible.',
            ],
        ];

        $t->same(4, count($document->children));
        foreach ($expected as $index => $case) {
            $paragraph = $document->children[$index] ?? new AstNode('missing');

            $t->same('paragraph', $paragraph->type, 'case ' . $index . ' should stay a paragraph');
            $t->same(['raw_html_inline', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), 'case ' . $index . ' inline node shape');
            $t->same($case['raw'], $paragraph->children[0]->attr('html'), 'case ' . $index . ' raw inline html');
            $t->same($case['text'], $paragraph->children[1]->attr('text'), 'case ' . $index . ' trailing text');
            $t->contains('<p>' . $case['raw'] . $case['text'] . '</p>', $blocks, 'case ' . $index . ' WordPress paragraph handoff');
        }

        $t->contains('RawInline (Format "html") "<track', $native);
        $t->true(!str_contains($native, 'RawBlock (Format "html") "<track'), 'standalone track should not fall through to a raw HTML block');
        $t->true(!str_contains($blocks, '<!-- wp:html -->'), 'void inline fragments should not become WordPress HTML blocks');
    },
];
