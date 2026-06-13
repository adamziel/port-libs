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
    'renders markdown mark spans as latex highlight commands' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $space = static fn (): AstNode => new AstNode('space');

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Review'),
                $space(),
                new AstNode('span', ['classes' => ['mark']], [
                    $text('highlighted'),
                    $space(),
                    new AstNode('strong', [], [$text('source')]),
                ]),
                $space(),
                new AstNode('span', [
                    'id' => 'annotated-highlight',
                    'classes' => ['mark'],
                ], [$text('with anchor')]),
            ]),
        ]);

        $t->same(
            'Review \hl{highlighted \textbf{source}} \protect\hypertarget{annotated-highlight}{with anchor}',
            (new LatexWriter())->write($document)
        );
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
    'renders pandoc table body head rows in body position' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $cell = static fn (string $value, array $attrs = []): AstNode => new AstNode(
            'table_cell',
            ['text' => $value, ...$attrs],
            [$text($value)]
        );

        $document = new AstNode('document', [], [
            new AstNode('table', [
                'caption' => 'Body head writer audit',
                'alignments' => ['left', 'right', 'center'],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', [], [
                        $cell('Document'),
                        $cell('Items'),
                        $cell('State'),
                    ]),
                ]),
                new AstNode('table_body', [
                    'headRows' => [
                        new AstNode('table_row', [], [
                            $cell('Batch'),
                            $cell('Queue'),
                            $cell('Decision'),
                        ]),
                    ],
                ], [
                    new AstNode('table_row', [], [
                        $cell('Posts'),
                        $cell('42'),
                        $cell('Review'),
                    ]),
                ]),
                new AstNode('table_body', [
                    'headRows' => [
                        new AstNode('table_row', [], [
                            $cell('Media'),
                            $cell('Files'),
                            $cell('Decision'),
                        ]),
                    ],
                ], [
                    new AstNode('table_row', [], [
                        $cell('Images'),
                        $cell('7'),
                        $cell('Import'),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '\begin{longtable}{lrc}',
            '\caption{Body head writer audit}\\\\',
            '\hline',
            'Document & Items & State\\\\',
            '\hline',
            'Batch & Queue & Decision\\\\',
            '\hline',
            'Posts & 42 & Review\\\\',
            '\hline',
            'Media & Files & Decision\\\\',
            '\hline',
            'Images & 7 & Import\\\\',
            '\end{longtable}',
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
    'renders ordered list default metadata without redundant label commands' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $item = static fn (string $value): AstNode => new AstNode('list_item', [], [
            new AstNode('paragraph', [], [$text($value)]),
        ]);

        $document = new AstNode('document', [], [
            new AstNode('ordered_list', ['start' => 4, 'style' => 'decimal', 'delimiter' => 'period'], [
                $item('Decimal checkpoint'),
            ]),
            new AstNode('ordered_list', [], [
                $item('Default list'),
            ]),
        ]);

        $t->same(implode("\n\n", [
            implode("\n", [
                '\begin{enumerate}',
                '\setcounter{enumi}{3}',
                '\item',
                '  Decimal checkpoint',
                '\end{enumerate}',
            ]),
            implode("\n", [
                '\begin{enumerate}',
                '\item',
                '  Default list',
                '\end{enumerate}',
            ]),
        ]), (new LatexWriter())->write($document));
    },
    'renders latex anchor commands for block and inline identifiers' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $space = static fn (): AstNode => new AstNode('space');

        $document = new AstNode('document', [], [
            new AstNode('heading', ['level' => 2, 'id' => 'review-section'], [
                $text('Anchored'),
                $space(),
                $text('Review'),
            ]),
            new AstNode('paragraph', [], [
                $text('Jump'),
                $space(),
                new AstNode('link', ['url' => '#review-section'], [$text('back to heading')]),
                $space(),
                $text('and'),
                $space(),
                new AstNode('span', ['id' => 'source span/1'], [
                    $text('inline'),
                    $space(),
                    new AstNode('strong', [], [$text('anchor')]),
                ]),
            ]),
            new AstNode('div', ['attributes' => ['id' => 'packet:42']], [
                new AstNode('paragraph', [], [
                    $text('Inside anchored div with'),
                    $space(),
                    new AstNode('link', ['url' => '#packet:42'], [$text('self link')]),
                ]),
            ]),
        ]);

        $t->same(implode("\n\n", [
            implode("\n", [
                '\hypertarget{review-section}{%',
                '\subsection{Anchored Review}\label{review-section}',
                '}',
            ]),
            'Jump \hyperlink{review-section}{back to heading} and \protect\hypertarget{source-span-1}{inline \textbf{anchor}}',
            implode("\n", [
                '\hypertarget{packet:42}{%',
                'Inside anchored div with \hyperlink{packet:42}{self link}',
                '}',
            ]),
        ]), (new LatexWriter())->write($document));
    },
    'renders unsupported raw and native commands as latex review text' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $space = static fn (): AstNode => new AstNode('space');

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
                $text('Audit'),
                $space(),
                new AstNode('raw_html_inline', [
                    'format' => 'html',
                    'text' => '<kbd>Ctrl+S</kbd>',
                ]),
                $space(),
                new AstNode('raw_inline', [
                    'format' => 'opml',
                    'text' => '<outline text="Review"/>',
                ]),
            ]),
            new AstNode('native_block', [
                'constructor' => 'TableOfContents',
                'reason' => 'writer extension not mapped',
            ]),
            new AstNode('paragraph', [], [
                $text('Macro'),
                $space(),
                new AstNode('native_inline', [
                    'constructor' => 'UnsupportedInlineCommand',
                    'text' => '\write18{curl example.test}',
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
                '\texttt{[unsupported block command: TableOfContents - writer extension not mapped]}',
                '\end{quote}',
            ]),
            'Macro \texttt{[unsupported inline command: UnsupportedInlineCommand - \textbackslash{}write18\{curl example.test\}]}',
        ]), (new LatexWriter())->write($document));
    },
    'renders unsupported block command inline child payloads as review text' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $space = static fn (): AstNode => new AstNode('space');

        $document = new AstNode('document', [], [
            new AstNode('unsupported_command', [
                'command' => 'UnmappedBlockCommand',
                'reason' => 'block command carries inline arguments',
            ], [
                $text('Review'),
                $space(),
                new AstNode('emph', [], [$text('inline')]),
                $space(),
                new AstNode('raw_inline', [
                    'format' => 'html',
                    'text' => '<span data-review="1">unsafe</span>',
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '\begin{quote}',
            '\texttt{[unsupported block command: UnmappedBlockCommand - block command carries inline arguments]}',
            '',
            'Review \emph{inline} \texttt{[unsupported inline command: raw html - <span data-review="1">unsafe</span>]}',
            '\end{quote}',
        ]), (new LatexWriter())->write($document));
    },
    'renders unsupported command structured metadata as bounded review text' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $space = static fn (): AstNode => new AstNode('space');

        $document = new AstNode('document', [], [
            new AstNode('unsupported_command', [
                'command' => 'RawBlockCommand',
                'arguments' => [
                    'draft.tex',
                    ['command' => '\include{draft}'],
                ],
                'options' => ['format' => 'latex'],
                'attributes' => ['source' => 'review_queue'],
            ]),
            new AstNode('paragraph', [], [
                $text('Inline'),
                $space(),
                new AstNode('unsupported_command', [
                    'command' => 'RawInlineCommand',
                    'message' => 'inline writer extension unavailable',
                    'args' => ['\href{javascript:alert(1)}{x}', true],
                ]),
                $space(),
                $text('fallback'),
            ]),
        ]);

        $t->same(implode("\n\n", [
            implode("\n", [
                '\begin{quote}',
                '\texttt{[unsupported block command: RawBlockCommand - arguments: draft.tex, command=\textbackslash{}include\{draft\}; options: format=latex; attributes: source=review\_queue]}',
                '\end{quote}',
            ]),
            'Inline \texttt{[unsupported inline command: RawInlineCommand - inline writer extension unavailable; arguments: \textbackslash{}href\{javascript:alert(1)\}\{x\}, true]} fallback',
        ]), (new LatexWriter())->write($document));
    },
    'renders inline hard line break commands distinctly from soft breaks' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Reviewer first line'),
                new AstNode('linebreak'),
                $text('forced next line'),
                new AstNode('softbreak'),
                $text('soft wrapped line'),
            ]),
        ]);

        $t->same(
            'Reviewer first line\\\\' . "\n" . 'forced next line' . "\n" . 'soft wrapped line',
            (new LatexWriter())->write($document)
        );
    },
];
