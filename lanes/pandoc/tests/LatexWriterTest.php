<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;

return [
    'renders bounded latex writer commands for structural and inline ast nodes' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $space = static fn (): AstNode => new AstNode('space');

        $document = new AstNode('document', [], [
            new AstNode('heading', ['level' => 1], [
                $text('Migration'),
                $space(),
                new AstNode('emph', [], [$text('Review')]),
            ]),
            new AstNode('paragraph', [], [
                $text('See'),
                $space(),
                new AstNode('link', ['url' => 'https://example.test/source?a=1&b=2'], [$text('source')]),
                $space(),
                $text('and'),
                $space(),
                new AstNode('note', [], [
                    new AstNode('plain', [], [
                        $text('kept note with'),
                        $space(),
                        new AstNode('code', ['text' => 'raw_$']),
                    ]),
                ]),
                $text('; badge'),
                $space(),
                new AstNode('image', ['url' => 'media/badge.png'], [$text('Review badge')]),
            ]),
            new AstNode('blockquote', [], [
                new AstNode('paragraph', [], [
                    $text('Quoted'),
                    $space(),
                    new AstNode('strong', [], [$text('source')]),
                ]),
            ]),
            new AstNode('code_block', ['text' => "wp media import\nsafe();"]),
            new AstNode('horizontal_rule'),
        ]);

        $t->same(implode("\n\n", [
            '\section{Migration \emph{Review}}',
            'See \href{https://example.test/source?a=1\&b=2}{source} and \footnote{kept note with \texttt{raw\_\$}}; badge \includegraphics[alt={Review badge}]{media/badge.png}',
            implode("\n", [
                '\begin{quote}',
                'Quoted \textbf{source}',
                '\end{quote}',
            ]),
            implode("\n", [
                '\begin{verbatim}',
                'wp media import',
                'safe();',
                '\end{verbatim}',
            ]),
            implode("\n", [
                '\begin{center}',
                '\rule{0.5\linewidth}{0.5pt}',
                '\end{center}',
            ]),
        ]), (new LatexWriter())->write($document));
    },
    'renders line blocks and definition lists without dropping native ast nodes' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);

        $document = new AstNode('document', [], [
            new AstNode('line_block', [], [
                new AstNode('line', [], [$text('First reviewer line')]),
                new AstNode('line', [], [
                    new AstNode('emph', [], [$text('Second')]),
                    new AstNode('space'),
                    $text('line'),
                ]),
                new AstNode('line', ['text' => 'fallback text line']),
            ]),
            new AstNode('definition_list', [], [
                new AstNode('definition_item', [], [
                    new AstNode('definition_term', [], [
                        new AstNode('strong', [], [$text('source')]),
                        new AstNode('space'),
                        $text('packet'),
                    ]),
                    new AstNode('definition', [], [
                        $paragraph('keeps definition text'),
                        new AstNode('code_block', ['text' => 'wp post meta get 42 _source']),
                    ]),
                    new AstNode('definition', [], [
                        $paragraph('keeps alternate definition text'),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n\n", [
            implode("\n", [
                '\begin{flushleft}',
                'First reviewer line\\\\',
                '\emph{Second} line\\\\',
                'fallback text line',
                '\end{flushleft}',
            ]),
            implode("\n", [
                '\begin{description}',
                '\item[{\textbf{source} packet}] keeps definition text',
                '',
                '\begin{verbatim}',
                'wp post meta get 42 _source',
                '\end{verbatim}',
                '',
                'keeps alternate definition text',
                '\end{description}',
            ]),
        ]), (new LatexWriter())->write($document));
    },
];
