<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;

return [
    'renders unsupported latex writer commands as bounded review text' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('raw_html', [
                'format' => 'html',
                'text' => '<aside data-review="1">needs TeX handoff</aside>',
            ]),
            new AstNode('raw_block', [
                'format' => 'typst',
                'text' => '#show heading: it => it',
            ]),
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Audit ']),
                new AstNode('raw_html_inline', [
                    'format' => 'html',
                    'text' => '<kbd>Ctrl+S</kbd>',
                ]),
                new AstNode('text', ['text' => ' ']),
                new AstNode('raw_inline', [
                    'format' => 'opml',
                    'text' => '<outline text="Review"/>',
                ]),
            ]),
            new AstNode('unsupported_command', [
                'command' => 'ReviewBlock',
                'reason' => 'block command carries inline arguments',
            ], [
                new AstNode('text', ['text' => 'Payload ']),
                new AstNode('emph', [], [
                    new AstNode('text', ['text' => 'inline']),
                ]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Native ']),
                new AstNode('native_inline', [
                    'constructor' => 'UnsupportedInlineCommand',
                    'text' => '\write18{curl example.test}',
                ]),
                new AstNode('text', ['text' => ' ']),
                new AstNode('unsupported_command', [
                    'command' => 'RawInlineCommand',
                    'message' => 'inline writer extension unavailable',
                    'args' => ['\href{javascript:alert(1)}{x}', true],
                ]),
            ]),
        ]);

        $t->same(implode("\n\n", [
            implode("\n", [
                '\begin{quote}',
                '\texttt{[unsupported block command: raw html - <aside data-review="1">needs TeX handoff</aside>]}',
                '\end{quote}',
            ]),
            implode("\n", [
                '\begin{quote}',
                '\texttt{[unsupported block command: raw typst - \#show heading: it => it]}',
                '\end{quote}',
            ]),
            'Audit \texttt{[unsupported inline command: raw html - <kbd>Ctrl+S</kbd>]} \texttt{[unsupported inline command: raw opml - <outline text="Review"/>]}',
            implode("\n", [
                '\begin{quote}',
                '\texttt{[unsupported block command: ReviewBlock - block command carries inline arguments]}',
                '',
                'Payload \emph{inline}',
                '\end{quote}',
            ]),
            'Native \texttt{[unsupported inline command: UnsupportedInlineCommand - \textbackslash{}write18\{curl example.test\}]} \texttt{[unsupported inline command: RawInlineCommand - inline writer extension unavailable; arguments: \textbackslash{}href\{javascript:alert(1)\}\{x\}, true]}',
        ]), (new LatexWriter())->write($document));
    },
];
