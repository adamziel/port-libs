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
    'renders figure and table commands without dropping native ast nodes' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $space = static fn (): AstNode => new AstNode('space');
        $cell = static fn (array $children = [], array $attrs = []): AstNode => new AstNode('table_cell', $attrs, $children);

        $document = new AstNode('document', [], [
            new AstNode('figure', [
                'caption' => 'Reviewer gallery',
                'attributes' => ['latex-placement' => 'htbp'],
            ], [
                new AstNode('image', ['url' => 'media/review_1.png'], [$text('Gallery alt')]),
            ]),
            new AstNode('table', [
                'captionInlines' => [
                    $text('Migration'),
                    $space(),
                    new AstNode('emph', [], [$text('queue')]),
                ],
                'alignments' => ['left', 'right', 'center'],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', [], [
                        $cell([$text('Metric')]),
                        $cell([$text('State & owner')]),
                        $cell([$text('Notes')]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        $cell([new AstNode('strong', [], [$text('Posts')])]),
                        $cell([
                            $text('Ready % complete'),
                        ], ['colspan' => 2, 'align' => 'center']),
                    ]),
                ]),
                new AstNode('table_foot', [], [
                    new AstNode('table_row', [], [
                        $cell([$text('Totals')]),
                        $cell([$text('42')]),
                        $cell([$text('Done')]),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n\n", [
            implode("\n", [
                '\begin{figure}[htbp]',
                '\centering',
                '\includegraphics[alt={Gallery alt}]{media/review\_1.png}',
                '\caption{Reviewer gallery}',
                '\end{figure}',
            ]),
            implode("\n", [
                '\begin{longtable}{lrc}',
                '\caption{Migration \emph{queue}}\\\\',
                '\hline',
                'Metric & State \& owner & Notes\\\\',
                '\hline',
                '\textbf{Posts} & \multicolumn{2}{c}{Ready \% complete}\\\\',
                '\hline',
                'Totals & 42 & Done\\\\',
                '\end{longtable}',
            ]),
        ]), (new LatexWriter())->write($document));
    },
    'renders ordered list labels counters and task item commands' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);

        $document = new AstNode('document', [], [
            new AstNode('ordered_list', ['start' => 3, 'style' => 'upper_alpha', 'delimiter' => 'one_paren'], [
                new AstNode('list_item', [], [
                    new AstNode('paragraph', [], [$text('Review source')]),
                ]),
                new AstNode('list_item', [], [
                    new AstNode('paragraph', [], [$text('Approve packet')]),
                    new AstNode('ordered_list', ['start' => 2, 'style' => 'lower_roman', 'delimiter' => 'two_parens'], [
                        new AstNode('list_item', [], [
                            new AstNode('plain', [], [$text('Nested check')]),
                        ]),
                    ]),
                ]),
            ]),
            new AstNode('bullet_list', [], [
                new AstNode('list_item', ['taskChecked' => false], [
                    new AstNode('plain', [], [$text('Missing alt text')]),
                ]),
                new AstNode('list_item', ['taskChecked' => true], [
                    new AstNode('plain', [], [$text('Images reconciled')]),
                ]),
            ]),
        ]);

        $t->same(implode("\n\n", [
            implode("\n", [
                '\begin{enumerate}',
                '\renewcommand{\labelenumi}{\Alph{enumi})}',
                '\setcounter{enumi}{2}',
                '\item',
                '  Review source',
                '\item',
                '  Approve packet',
                '\begin{enumerate}',
                '\renewcommand{\labelenumii}{(\roman{enumii})}',
                '\setcounter{enumii}{1}',
                '\item',
                '  Nested check',
                '\end{enumerate}',
                '\end{enumerate}',
            ]),
            implode("\n", [
                '\begin{itemize}',
                '\item[$\square$]',
                '  Missing alt text',
                '\item[$\boxtimes$]',
                '  Images reconciled',
                '\end{itemize}',
            ]),
        ]), (new LatexWriter())->write($document));
    },
];
