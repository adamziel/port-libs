<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexReader;
use PortLibs\Pandoc\PandocConverter;

$root = dirname(__DIR__, 3);
$fixtureRoot = $root . '/lanes/pandoc/fixtures/latex-reader';

/**
 * @return list<AstNode>
 */
$descendants = static function (AstNode $node) use (&$descendants): array {
    $nodes = [$node];
    foreach ($node->children as $child) {
        $nodes = array_merge($nodes, $descendants($child));
    }

    return $nodes;
};

/**
 * @return list<AstNode>
 */
$nodesOfType = static function (AstNode $document, string $type) use ($descendants): array {
    return array_values(array_filter(
        $descendants($document),
        static fn (AstNode $node): bool => $node->type === $type
    ));
};

$plainText = static function (AstNode $node) use (&$plainText): string {
    $text = (string) $node->attr('text', '');
    if ($text !== '') {
        return $text;
    }
    $parts = [];
    foreach ($node->children as $child) {
        $parts[] = $plainText($child);
    }

    return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
};

$cleanupDirectory = static function (string $path) use (&$cleanupDirectory): void {
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . '/' . $entry;
        is_dir($child) ? $cleanupDirectory($child) : unlink($child);
    }
    rmdir($path);
};

return [
    'reads a realistic academic LaTeX article into editable WordPress structure' => static function (TestRunner $t) use ($fixtureRoot, $nodesOfType, $plainText): void {
        $document = PandocConverter::readFile($fixtureRoot . '/academic-article.tex', 'latex');
        $meta = $document->attr('meta', []);
        $latex = $document->attr('latex', []);

        $t->same('latex', $document->attr('sourceFormat'));
        $t->same(LatexReader::class, $meta['reader'] ?? null);
        $t->same('A Native LaTeX Import Study', $meta['title'] ?? null);
        $t->same(['Ada Lovelace', 'Grace Hopper'], $meta['author'] ?? null);
        $t->same('June 2026', $meta['date'] ?? null);
        $t->same('article', $meta['documentClass'] ?? null);
        $t->same(['amsmath', 'graphicx', 'hyperref', 'booktabs', 'longtable'], $meta['packages'] ?? null);
        $t->contains('Project Harbor', (string) ($meta['abstract'] ?? ''));
        $t->same(['appendix.tex'], $latex['includedFiles'] ?? null);
        $t->same(['references.bib'], $latex['bibliographyFiles'] ?? null);
        $t->same('parse-only-no-tex-engine-package-loading-shell-escape-or-arbitrary-macro-execution', $latex['executionPolicy'] ?? null);
        $t->same(['latex-unsupported-command:custompublisher'], $latex['diagnostics'] ?? null);
        $diagnosticDetails = array_values(array_filter(
            $latex['diagnosticDetails'] ?? [],
            static fn (mixed $detail): bool => is_array($detail) && ($detail['code'] ?? '') === 'latex-unsupported-command:custompublisher'
        ));
        $t->same([[
            'code' => 'latex-unsupported-command:custompublisher',
            'source' => 'appendix.tex',
            'line' => 3,
            'column' => 18,
            'command' => 'custompublisher',
        ]], $diagnosticDetails);
        $t->same(true, $latex['bibliographyResolved'] ?? null);
        $t->same(2, $latex['bibliographyItemCount'] ?? null);

        $t->true(count($nodesOfType($document, 'heading')) >= 5);
        $t->same(1, count($nodesOfType($document, 'figure')));
        $t->same(1, count($nodesOfType($document, 'table')));
        $t->same(3, count($nodesOfType($document, 'math')));
        $t->true(count($nodesOfType($document, 'bullet_list')) >= 1);
        $t->same(1, count($nodesOfType($document, 'ordered_list')));
        $t->same(1, count($nodesOfType($document, 'blockquote')));
        $t->same(1, count($nodesOfType($document, 'note')));
        $t->same(2, count($nodesOfType($document, 'citation')));
        $t->same(1, count($nodesOfType($document, 'raw_tex_inline')));
        $t->contains('The local include is parsed through a bounded PHP file resolver.', $plainText($document));

        $blocks = PandocConverter::write($document, 'wordpress', [
            'writerHTMLMathMethod' => 'mathml',
        ]);
        $t->contains('<h1 id="title">A Native LaTeX Import Study</h1>', $blocks);
        $t->contains('id="table-of-contents"', $blocks);
        $t->contains('href="#latex-sec:methods"', $blocks);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML"', $blocks);
        $t->contains('<!-- wp:list -->', $blocks);
        $t->contains('<!-- wp:quote -->', $blocks);
        $t->contains('<!-- wp:image -->', $blocks);
        $t->contains('src="figures/architecture.svg"', $blocks);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('<th style="text-align:left">Gate</th>', $blocks);
        $t->contains('href="#latex-fig:architecture">Figure 1</a>', $blocks);
        $t->contains('href="#latex-tab:gates">1</a>', $blocks);
        $t->contains('href="#latex-eq:identity">1</a>', $blocks);
        $t->contains('Knuth 1984', $blocks);
        $t->contains('<span class="pandoc-raw-tex" data-pandoc-latex-source="appendix.tex"', $blocks);
        $t->contains('>\\custompublisher{review}</span>', $blocks);
        $t->contains('data-pandoc-latex-source="appendix.tex"', $blocks);
        $t->contains('data-pandoc-latex-line="3"', $blocks);

        $html = PandocConverter::write($document, 'html', [
            'writerHTMLMathMethod' => 'mathml',
        ]);
        $t->contains('data-cites="knuth1984 lamport1994"', $html);
        $t->contains('(Knuth 1984; Lamport 1994, pp. 12--14)', $html);
        $t->true(!str_contains($html, '\\citep'));
    },
    'extracts LaTeX figure media and rewrites only the rendered image reference' => static function (TestRunner $t) use ($fixtureRoot, $cleanupDirectory): void {
        $outputDirectory = sys_get_temp_dir() . '/pandoc-latex-media-' . bin2hex(random_bytes(6));
        try {
            $result = PandocConverter::convertFileWithMedia($fixtureRoot . '/academic-article.tex', 'latex', 'wordpress', [
                'extractMedia' => [
                    'destination' => 'media',
                    'outputDirectory' => $outputDirectory,
                ],
                'writerOptions' => [
                    'writerHTMLMathMethod' => 'mathml',
                ],
            ]);

            $t->same(1, count($result['media']));
            $t->same('media/figures/architecture.svg', $result['media'][0]['path'] ?? null);
            $t->same('image/svg+xml', $result['media'][0]['mimeType'] ?? null);
            $t->true(is_file($outputDirectory . '/figures/architecture.svg'));
            $t->contains('src="media/figures/architecture.svg"', $result['output']);
        } finally {
            $cleanupDirectory($outputDirectory);
        }
    },
    'keeps report book and lecture-note content as structured blocks' => static function (TestRunner $t) use ($fixtureRoot, $nodesOfType): void {
        $report = PandocConverter::readFile($fixtureRoot . '/technical-report.tex', 'latex');
        $reportBlocks = PandocConverter::write($report, 'wordpress');
        $t->true(count($nodesOfType($report, 'heading')) >= 2);
        $t->same(1, count($nodesOfType($report, 'definition_list')));
        $t->same(1, count($nodesOfType($report, 'code_block')));
        $t->contains('pandoc-definition-list latex-description', $reportBlocks);
        $t->contains('<pre class="wp-block-code"><code class="language-latex-verbatim">', $reportBlocks);

        $book = PandocConverter::readFile($fixtureRoot . '/book-extract.tex', 'latex');
        $bookBlocks = PandocConverter::write($book, 'wordpress');
        $t->same('The First Chapter', $book->children[0]->attr('text'));
        $t->same(1, count($nodesOfType($book, 'blockquote')));
        $t->same(1, count($nodesOfType($book, 'table')));
        $t->contains('<span style="font-variant:small-caps">small caps</span>', $bookBlocks);
        $t->contains('<!-- wp:table -->', $bookBlocks);

        $lecture = PandocConverter::readFile($fixtureRoot . '/lecture-notes.tex', 'latex');
        $lectureBlocks = PandocConverter::write($lecture, 'wordpress', [
            'writerHTMLMathMethod' => 'mathml',
        ]);
        $lectureMath = $nodesOfType($lecture, 'math');
        $t->same(2, count($lectureMath));
        $t->same([true, true], array_map(static fn (AstNode $node): bool => (bool) $node->attr('display'), $lectureMath));
        $t->same(1, count($nodesOfType($lecture, 'code_block')));
        $t->same(1, count($nodesOfType($lecture, 'table')));
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML"', $lectureBlocks);
        $t->contains('<!-- wp:code {"language":"latex-lstlisting"} -->', $lectureBlocks);
        $t->contains('<pre class="wp-block-code php">', $lectureBlocks);
        $t->contains('<thead>', $lectureBlocks);
    },
    'preserves AMS math contexts and equation references as MathML' => static function (TestRunner $t): void {
        $source = "\\documentclass{article}\n"
            . "\\usepackage{amsmath}\n"
            . "\\begin{document}\n"
            . "\\begin{equation}\\label{eq:identity}\\begin{aligned}a &= b \\\\ c &= d\\end{aligned}\\tag{A}\\end{equation}\n"
            . "See \\eqref{eq:identity}.\n"
            . "\\[\n"
            . "\\begin{cases}x & x > 0 \\\\ 0 & \\text{otherwise}\\end{cases}\n"
            . "\\]\n"
            . "\\begin{align}f(x) &= x^2 \\\\ g(x) &= \\operatorname{rank}(x)\\end{align}\n"
            . "\\end{document}\n";
        $document = PandocConverter::read($source, 'latex');
        $blocks = PandocConverter::write($document, 'wordpress', [
            'writerHTMLMathMethod' => 'mathml',
        ]);

        $t->same([], $document->attr('latex')['diagnostics'] ?? null);
        $t->contains('href="#latex-eq:identity">(1)</a>', $blocks);
        $t->contains('<mtext>(A)</mtext>', $blocks);
        $t->contains('<mtable columnalign="right left">', $blocks);
        $t->contains('<mtext>otherwise</mtext>', $blocks);
        $t->contains('<mi>rank</mi>', $blocks);
        $t->true(!str_contains($blocks, '<span class="pandoc-raw-tex">\\['));
    },
    'places BibLaTeX and Natbib bibliography sections at their source positions' => static function (TestRunner $t) use ($fixtureRoot): void {
        $source = "\\documentclass{article}\n"
            . "\\begin{document}\n"
            . "Text \\citep[see][pp. 1--2]{knuth1984,lamport1994}. Author \\citeauthor{knuth1984}. Bare \\citealp{lamport1994}.\\footcite{knuth1984}\n"
            . "\\addbibresource{references.bib}\n"
            . "\\printbibliography[title={Sources}]\n"
            . "\\begin{refsection}\n"
            . "More \\textcite{lamport1994}.\n"
            . "\\printbibliography[title={Local sources}]\n"
            . "\\end{refsection}\n"
            . "\\end{document}\n";
        $document = PandocConverter::read($source, 'latex', [
            'sourceDirectory' => $fixtureRoot,
            'sourcePath' => $fixtureRoot . '/citation-sections.tex',
        ]);
        $blocks = PandocConverter::write($document, 'wordpress');
        $html = PandocConverter::write($document, 'html');

        $t->same(true, $document->attr('latex')['bibliographyResolved'] ?? null);
        $t->same(2, $document->attr('latex')['bibliographyItemCount'] ?? null);
        $t->contains('(see Knuth 1984; Lamport 1994, pp. 1--2)', $html);
        $t->contains('Author <span class="citation" data-cites="knuth1984">Knuth</span>.', $html);
        $t->contains('Bare <span class="citation" data-cites="lamport1994">Lamport 1994</span>.', $html);
        $t->contains('<h2 id="sources">Sources</h2>', $blocks);
        $t->contains('<h2 id="local-sources">Local sources</h2>', $blocks);
        $t->contains('class="wp-block-group pandoc-csl-bibliography"', $blocks);
        $t->contains('class="wp-block-group latex-refsection"', $blocks);
        $t->contains('<sup id="fnref-1">', $blocks);
    },
    'keeps longtable spans captions labels and foot rows editable' => static function (TestRunner $t) use ($nodesOfType): void {
        $source = "\\documentclass{article}\n"
            . "\\begin{document}\n"
            . "\\begin{longtable}{>{\\bfseries}l *{2}{c} p{2cm}}\n"
            . "\\caption{A durable table}\\label{tab:durable}\\\\\n"
            . "\\toprule\nName & Left & Right & Notes \\\\\n\\midrule\n"
            . "\\endfirsthead\nName & Left & Right & Notes \\\\\n\\endhead\n"
            . "Continued & & & \\\\\n\\endfoot\nFinal note & & & \\\\\n\\endlastfoot\n"
            . "\\multirow{2}{*}{\\textbf{Merged}} & A & B & first \\\\\n"
            . "& \\multicolumn{2}{>{\\itshape}c}{C and D} & second \\\\\n"
            . "\\bottomrule\n\\end{longtable}\n"
            . "\\begin{tabular*}{\\textwidth}{lcr}\n"
            . "\\toprule\n"
            . "Name & Center & Value \\\\\n"
            . "\\midrule\n"
            . "alpha & beta & 1 \\\\\n"
            . "\\bottomrule\n"
            . "\\end{tabular*}\n"
            . "\\begin{array}{lr}\n"
            . "Label & Value \\\\\n"
            . "gamma & 2 \\\\\n"
            . "\\end{array}\n"
            . "See \\autoref{tab:durable}.\n"
            . "\\end{document}\n";
        $document = PandocConverter::read($source, 'latex');
        $tables = $nodesOfType($document, 'table');
        $table = $tables[0] ?? null;
        $blocks = PandocConverter::write($document, 'wordpress');

        $t->true($table instanceof AstNode);
        $t->same(3, count($tables));
        $t->same('A durable table', $table?->attr('caption'));
        $t->same('latex-tab:durable', $table?->attr('id'));
        $t->same(1, count($nodesOfType($table, 'table_foot')));
        $t->contains('rowspan="2"', $blocks);
        $t->contains('colspan="2"', $blocks);
        $t->contains('<tfoot>', $blocks);
        $t->contains('<th style="text-align:left">Name</th>', $blocks);
        $t->contains('<th style="text-align:center">Center</th>', $blocks);
        $t->contains('<th style="text-align:right">Value</th>', $blocks);
        $t->contains('>gamma</td>', $blocks);
        $t->contains('href="#latex-tab:durable">Table 1</a>', $blocks);
    },
    'maps scholarly environments and title metadata to editable WordPress groups' => static function (TestRunner $t) use ($nodesOfType): void {
        $source = "\\documentclass{article}\n"
            . "\\title{Semantic Structures}\n"
            . "\\author{Ada Lovelace\\thanks{Supported by the Open Research Fund} \\and Grace Hopper}\n"
            . "\\affiliation{Open Documentation Lab}\n"
            . "\\keywords{LaTeX, WordPress, multilingual text}\n"
            . "\\newtheorem{claim}{Claim}\n"
            . "\\begin{document}\n\\maketitle\n"
            . "\\begin{claim}[Import safety]\\label{claim:safe}No TeX engine is executed.\\end{claim}\n"
            . "See \\autoref{claim:safe}.\n"
            . "\\begin{proof}[Sketch]The parser preserves source semantics. \\qed\\end{proof}\n"
            . "\\begin{acknowledgements}Thanks to the maintainers.\\end{acknowledgements}\n"
            . "\\end{document}\n";
        $document = PandocConverter::read($source, 'latex');
        $meta = $document->attr('meta', []);
        $blocks = PandocConverter::write($document, 'wordpress');

        $t->same(['Open Documentation Lab'], $meta['affiliations'] ?? null);
        $t->same(['Supported by the Open Research Fund'], $meta['authorNotes'] ?? null);
        $t->same(['LaTeX', 'WordPress', 'multilingual text'], $meta['keywords'] ?? null);
        $t->true(count($nodesOfType($document, 'div')) >= 4);
        $t->contains('latex-affiliation', $blocks);
        $t->contains('latex-author-note', $blocks);
        $t->contains('latex-keywords', $blocks);
        $t->contains('latex-scholarly latex-claim', $blocks);
        $t->contains('href="#latex-claim:safe">Claim 1</a>', $blocks);
        $t->contains('latex-scholarly latex-proof', $blocks);
        $t->contains('□', $blocks);
        $t->contains('latex-scholarly latex-acknowledgments', $blocks);
    },
    'maps the LaTeX section hierarchy through subparagraph without flattening it' => static function (TestRunner $t) use ($nodesOfType): void {
        $source = "\\documentclass{book}\n"
            . "\\begin{document}\n"
            . "\\chapter{Chapter}\\label{chap:one}\n"
            . "\\section{Section}\n"
            . "\\subsection{Subsection}\n"
            . "\\subsubsection{Subsubsection}\n"
            . "\\paragraph{Paragraph}\n"
            . "\\subparagraph{Subparagraph}\n"
            . "See \\ref{chap:one}.\n"
            . "\\end{document}\n";
        $document = PandocConverter::read($source, 'latex');
        $headings = $nodesOfType($document, 'heading');
        $blocks = PandocConverter::write($document, 'wordpress');

        $t->same([1, 2, 3, 4, 5, 6], array_map(static fn (AstNode $node): int => (int) $node->attr('level'), $headings));
        $t->same(['Chapter', 'Section', 'Subsection', 'Subsubsection', 'Paragraph', 'Subparagraph'], array_map(static fn (AstNode $node): string => (string) $node->attr('text'), $headings));
        $t->contains('id="latex-chap:one"', $blocks);
        $t->contains('href="#latex-chap:one">Chapter</a>', $blocks);
    },
    'imports attributed open-license article book lecture and multilingual LaTeX extracts' => static function (TestRunner $t) use ($fixtureRoot, $nodesOfType): void {
        $corpus = $fixtureRoot . '/corpus';
        $t->true(is_file($corpus . '/SOURCES.md'));
        $t->contains('CC BY 4.0', (string) file_get_contents($corpus . '/SOURCES.md'));
        $t->contains('CC BY-SA 4.0', (string) file_get_contents($corpus . '/SOURCES.md'));

        $article = PandocConverter::readFile($corpus . '/article-bos-mccurley.tex', 'latex');
        $articleMeta = $article->attr('meta', []);
        $articleBlocks = PandocConverter::write($article, 'wordpress');
        $t->same('LaTeX, metadata, and publishing workflows', $articleMeta['title'] ?? null);
        $t->same(['Joppe W. Bos', 'Kevin S. McCurley'], $articleMeta['author'] ?? null);
        $t->same(['NXP Semiconductors', 'Self'], $articleMeta['affiliations'] ?? null);
        $t->contains('streamlining and automating', (string) ($articleMeta['abstract'] ?? ''));
        $t->same([], $article->attr('latex')['diagnostics'] ?? null);
        $t->contains('latex-keywords', $articleBlocks);
        $t->contains('<em>curation of metadata about publications</em>', $articleBlocks);
        $t->true(!str_contains($articleBlocks, 'pandoc-raw-tex'));

        $book = PandocConverter::readFile($corpus . '/book-lebl.tex', 'latex');
        $bookBlocks = PandocConverter::write($book, 'wordpress', ['writerHTMLMathMethod' => 'mathml']);
        $t->same([], $book->attr('latex')['diagnostics'] ?? null);
        $t->true(count($nodesOfType($book, 'math')) >= 4);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML"', $bookBlocks);
        $t->contains('Holomorphic Functions in Several Variables', $bookBlocks);

        $lecture = PandocConverter::readFile($corpus . '/lecture-holsinger.tex', 'latex');
        $lectureBlocks = PandocConverter::write($lecture, 'wordpress', ['writerHTMLMathMethod' => 'mathml']);
        $t->same([], $lecture->attr('latex')['diagnostics'] ?? null);
        $t->same(2, count($nodesOfType($lecture, 'table')));
        $t->same(1, count($nodesOfType($lecture, 'ordered_list')));
        $t->same(1, count($nodesOfType($lecture, 'note')));
        $t->contains('colspan="3"', $lectureBlocks);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML"', $lectureBlocks);

        $multilingual = PandocConverter::readFile($corpus . '/multilingual-goulet.tex', 'latex');
        $multilingualBlocks = PandocConverter::write($multilingual, 'wordpress');
        $t->same(['booktabs', 'tabularx'], $multilingual->attr('meta')['packages'] ?? null);
        $t->same([], $multilingual->attr('latex')['diagnostics'] ?? null);
        $t->same(1, count($nodesOfType($multilingual, 'table')));
        $t->contains('éléments de texte', $multilingualBlocks);
        $t->contains('Un exemple de tableau aéré', $multilingualBlocks);
        $t->contains('<th style="text-align:left">Description</th>', $multilingualBlocks);
        $t->contains('<!-- wp:table -->', $multilingualBlocks);
    },
    'reports UTF-8 source columns for unsupported LaTeX commands' => static function (TestRunner $t): void {
        $source = "\\begin{document}\nŻółć \\custommacro{value}.\n\\end{document}\n";
        $document = PandocConverter::read($source, 'latex');
        $details = $document->attr('latex')['diagnosticDetails'] ?? [];
        $blocks = PandocConverter::write($document, 'wordpress');

        $t->same([[
            'code' => 'latex-unsupported-command:custommacro',
            'source' => '<input>',
            'line' => 2,
            'column' => 6,
            'command' => 'custommacro',
        ]], $details);
        $t->contains('data-pandoc-latex-line="2"', $blocks);
        $t->contains('data-pandoc-latex-column="6"', $blocks);
    },
    'never executes unsafe macro bodies or out-of-root local includes' => static function (TestRunner $t) use ($fixtureRoot, $nodesOfType): void {
        $source = "\\newcommand{\\unsafe}[1]{\\input{#1}}\n"
            . "\\begin{document}\n"
            . "\\unsafe{secret}\n"
            . "\\input{../secret}\n"
            . "\\end{document}\n";
        $document = PandocConverter::read($source, 'latex', [
            'sourceDirectory' => $fixtureRoot,
        ]);
        $latex = $document->attr('latex', []);
        $blocks = PandocConverter::write($document, 'wordpress');

        $t->same(2, count($nodesOfType($document, 'raw_tex')));
        $t->same(1, count($nodesOfType($document, 'raw_tex_inline')));
        $t->same([
            'latex-macro-unsafe-body:unsafe',
            'latex-unsupported-command:unsafe',
            'latex-local-source-outside-root:..-secret',
        ], $latex['diagnostics'] ?? null);
        $t->contains('\\newcommand{\\unsafe}[1]{\\input{#1}}', $blocks);
        $t->contains('<span class="pandoc-raw-tex" data-pandoc-latex-source="&lt;input&gt;"', $blocks);
        $t->contains('>\\unsafe{secret}</span>', $blocks);
        $t->contains('\\input{../secret}', $blocks);
    },
];
