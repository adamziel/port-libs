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
        $t->contains('<span class="pandoc-raw-tex">\\custompublisher{review}</span>', $blocks);

        $html = PandocConverter::write($document, 'html', [
            'writerHTMLMathMethod' => 'mathml',
        ]);
        $t->contains('data-cites="knuth1984 lamport1994"', $html);
        $t->contains('(Knuth 1984, pp. 12--14; Lamport 1994, pp. 12--14)', $html);
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
        $t->contains('<span class="pandoc-raw-tex">\\unsafe{secret}</span>', $blocks);
        $t->contains('\\input{../secret}', $blocks);
    },
];
