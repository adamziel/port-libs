<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\EpubPackageReader;
use PortLibs\Pandoc\HtmlReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$collectMath = static function (AstNode $node) use (&$collectMath): array {
    $nodes = $node->type === 'math' ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($nodes, ...$collectMath($child));
    }

    return $nodes;
};

$writePackageFile = static function (string $root, string $relativePath, string $bytes): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create EPUB fixture directory: ' . $directory);
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Unable to write EPUB fixture file: ' . $path);
    }
};

$removeDirectory = static function (string $directory): void {
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
};

return [
    'renders wordpress math as mathml when requested' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $math = static fn (string $text, bool $display = false): AstNode => new AstNode('math', [
            'text' => $text,
            'display' => $display,
        ]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Inline '),
                $math('x^2'),
                $text(' renders.'),
            ]),
            new AstNode('paragraph', [], [
                $math('E=mc^2', true),
            ]),
        ]);

        $blocks = (new WordPressBlockWriter(['writerHTMLMathMethod' => 'mathml']))->write($document);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="inline">', $blocks);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $blocks);
        $t->true(!str_contains($blocks, '\\(x^2\\)'), 'MathML WordPress output should not leave inline MathJax delimiters');
        $t->true(!str_contains($blocks, '\\[E=mc^2\\]'), 'MathML WordPress output should not leave display MathJax delimiters');
    },
    'reads pandoc rendered html math spans as native math nodes' => static function (TestRunner $t) use ($collectMath): void {
        $html = <<<'HTML'
<!doctype html>
<p>Inline <span class="math inline"><em>x</em><sup>2</sup> + <em>y</em><sup>2</sup></span>.</p>
<p><span class="math display"><em>E</em> = <em>m</em><em>c</em><sup>2</sup></span></p>
HTML;
        $document = (new HtmlReader([
            'htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE,
            'htmlNativeDivs' => true,
        ]))->read($html);
        $math = $collectMath($document);

        $t->same(2, count($math));
        $t->same(false, $math[0]->attr('display'));
        $t->same('x^{2} + y^{2}', $math[0]->attr('text'));
        $t->same(true, $math[1]->attr('display'));
        $t->same('E = mc^{2}', $math[1]->attr('text'));
    },
    'reads nested presentation mathml structures as native math nodes' => static function (TestRunner $t) use ($collectMath): void {
        $html = <<<'HTML'
<!doctype html>
<p>Inline complex <math><mfrac><mrow><msub><mi>a</mi><mn>1</mn></msub><mo>+</mo><msqrt><mi>b</mi></msqrt></mrow><mrow><msubsup><mi>c</mi><mi>i</mi><mn>2</mn></msubsup></mrow></mfrac></math>.</p>
<p><math display="block"><mrow><munderover><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></munderover><mfrac><msup><mi>x</mi><mn>2</mn></msup><mrow><mn>1</mn><mo>+</mo><msqrt><mi>y</mi></msqrt></mrow></mfrac></mrow></math></p>
<p>Matrix <math><mtable><mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd></mtr><mtr><mtd><mn>3</mn></mtd><mtd><mn>4</mn></mtd></mtr></mtable></math>.</p>
HTML;
        $document = (new HtmlReader([
            'htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE,
            'htmlNativeDivs' => true,
        ]))->read($html);
        $math = $collectMath($document);

        $t->same(3, count($math));
        $t->same(false, $math[0]->attr('display'));
        $t->same('\frac{a_{1} + \sqrt{b}}{c_{i}^{2}}', $math[0]->attr('text'));
        $t->same(true, $math[1]->attr('display'));
        $t->contains('\sum\limits_{i = 1}^{n}', $math[1]->attr('text'));
        $t->contains('\frac{x^{2}}{1 + \sqrt{y}}', $math[1]->attr('text'));
        $t->same(false, $math[2]->attr('display'));
        $t->contains('\begin{matrix}', $math[2]->attr('text'));
        $t->contains('1 & 2', $math[2]->attr('text'));
        $t->contains('3 & 4', $math[2]->attr('text'));
    },
    'reads epub package pandoc rendered math spans as native math nodes' => static function (TestRunner $t) use ($collectMath, $writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-rendered-math-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles><rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/></rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:rendered-math</dc:identifier>
    <dc:title>Rendered Math</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest><item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/></manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <p>Inline <span class="math inline"><em>x</em><sup>2</sup> + <em>y</em><sup>2</sup></span>.</p>
    <p><span class="math display"><em>E</em> = <em>m</em><em>c</em><sup>2</sup></span></p>
  </body>
</html>
XML);

            $document = (new EpubPackageReader())->readDirectory($root);
            $math = $collectMath($document);

            $t->same(2, count($math));
            $t->same('x^{2} + y^{2}', $math[0]->attr('text'));
            $t->same(false, $math[0]->attr('display'));
            $t->same('E = mc^{2}', $math[1]->attr('text'));
            $t->same(true, $math[1]->attr('display'));
        } finally {
            $removeDirectory($root);
        }
    },
];
