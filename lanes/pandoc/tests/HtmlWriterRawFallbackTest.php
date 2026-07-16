<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;

return [
    'preserves non-html raw inline and block content as escaped labeled HTML' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Inline ']),
                new AstNode('raw_tex_inline', ['format' => 'latex', 'tex' => '\\LaTeX{}']),
                new AstNode('text', ['text' => ' source.']),
            ]),
            new AstNode('raw_tex', ['tex' => '\\section{Review}']),
            new AstNode('raw_block', ['format' => 'opml', 'text' => '<outline text="Review"/>']),
        ]);

        $html = (new HtmlWriter())->write($document);

        $t->contains('<span class="pandoc-raw-latex" data-pandoc-raw-format="latex">\\LaTeX{}</span>', $html);
        $t->contains('<pre class="pandoc-raw-tex" data-pandoc-raw-format="tex"><code class="language-tex">\\section{Review}</code></pre>', $html);
        $t->contains('<pre class="pandoc-raw-opml" data-pandoc-raw-format="opml"><code class="language-opml">&lt;outline text=&quot;Review&quot;/&gt;</code></pre>', $html);
        $t->true(!str_contains($html, '<outline text="Review"/>'), 'Non-HTML raw content must stay escaped.');
    },
];
