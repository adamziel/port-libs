<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\PandocConverter;

$corpus = require __DIR__ . '/fixtures/plainmath-conformance-corpus.php';

$mathDocument = static function (string $tex, bool $display = false): AstNode {
    return new AstNode('document', [], [
        new AstNode('plain', [], [
            new AstNode('math', [
                'text' => $tex,
                'display' => $display,
            ]),
        ]),
    ]);
};

$loadXml = static function (string $xml, string $label): DOMDocument {
    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $ok = $dom->loadXML($xml);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$ok) {
        throw new RuntimeException($label . ' should parse as XML: ' . implode('; ', array_map(static fn (LibXMLError $error): string => trim($error->message), $errors)));
    }

    return $dom;
};

$canonicalXml = static function (DOMDocument $dom): string {
    $canonical = $dom->C14N();
    if (!is_string($canonical)) {
        throw new RuntimeException('Unable to canonicalize MathML XML.');
    }

    return $canonical;
};

$xpathFor = static function (DOMDocument $dom): DOMXPath {
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('m', 'http://www.w3.org/1998/Math/MathML');

    return $xpath;
};

$assertMathMLCase = static function (TestRunner $t, array $case, string $actual) use ($loadXml, $canonicalXml, $xpathFor): void {
    $generated = $loadXml($actual, $case['id'] . ' generated MathML');
    $expected = $loadXml((string) $case['expectedMathML'], $case['id'] . ' expected MathML');

    $t->same(
        $canonicalXml($expected),
        $canonicalXml($generated),
        'PlainMath case ' . $case['id'] . ' should match normalized MathML.'
    );

    $root = $generated->documentElement;
    if (!$root instanceof DOMElement) {
        throw new RuntimeException('PlainMath case ' . $case['id'] . ' generated no document root.');
    }

    $t->same('math', $root->localName, 'PlainMath case ' . $case['id'] . ' should emit a MathML root.');
    $t->same('http://www.w3.org/1998/Math/MathML', $root->namespaceURI, 'PlainMath case ' . $case['id'] . ' should keep the MathML namespace.');
    $t->same(($case['display'] ?? false) ? 'block' : '', $root->getAttribute('display'), 'PlainMath case ' . $case['id'] . ' should keep display mode.');

    $annotations = $xpathFor($generated)->query('/m:math/m:semantics/m:annotation[@encoding="application/x-tex"]');
    if (!$annotations instanceof DOMNodeList) {
        throw new RuntimeException('PlainMath case ' . $case['id'] . ' annotation XPath failed.');
    }

    $t->same(1, $annotations->length, 'PlainMath case ' . $case['id'] . ' should keep one source TeX annotation.');
    $annotation = $annotations->item(0);
    if (!$annotation instanceof DOMNode) {
        throw new RuntimeException('PlainMath case ' . $case['id'] . ' annotation node is missing.');
    }

    $t->same(trim((string) $case['tex']), $annotation->textContent, 'PlainMath case ' . $case['id'] . ' should preserve source TeX.');
};

$mathCasesById = [];
foreach ($corpus['mathml'] as $case) {
    $mathCasesById[$case['id']] = $case;
}

return [
    'matches static upstream-derived plainmath mathml corpus' => static function (TestRunner $t) use ($corpus, $mathDocument, $assertMathMLCase): void {
        $writer = new HtmlWriter(['writerHTMLMathMethod' => 'mathml']);

        foreach ($corpus['mathml'] as $case) {
            $actual = $writer->write($mathDocument((string) $case['tex'], (bool) ($case['display'] ?? false)));
            $assertMathMLCase($t, $case, $actual);
        }
    },
    'prototypes texmath-style plainmath atom categories without mathml annotations' => static function (TestRunner $t) use ($corpus, $mathDocument): void {
        $writer = new HtmlWriter(['writerHTMLMathMethod' => 'mathml']);
        $checked = 0;

        foreach ($corpus['mathml'] as $case) {
            if (!isset($case['atomCategories'])) {
                continue;
            }

            $checked++;
            $t->same(
                $case['atomCategories'],
                $writer->plainMathAtomCategoryPrototype((string) $case['tex']),
                'PlainMath case ' . $case['id'] . ' should expose TexMath-style atom category diagnostics.'
            );

            $actual = $writer->write($mathDocument((string) $case['tex'], (bool) ($case['display'] ?? false)));
            $t->true(!str_contains($actual, 'data-plainmath-atom'), 'PlainMath case ' . $case['id'] . ' should not annotate runtime MathML atoms.');
            $t->true(!str_contains($actual, 'atomCategory'), 'PlainMath case ' . $case['id'] . ' should keep atom categories out of runtime MathML.');
        }

        $t->true($checked >= 1, 'PlainMath fixture should include atom category prototype cases.');
    },
    'records predictable plainmath fallback without malformed mathml' => static function (TestRunner $t) use ($corpus, $mathDocument): void {
        $writer = new HtmlWriter(['writerHTMLMathMethod' => 'mathml']);

        foreach ($corpus['fallback'] as $case) {
            $actual = $writer->write($mathDocument((string) $case['tex'], (bool) ($case['display'] ?? false)));
            $t->same($case['expectedHtml'], $actual, 'PlainMath fallback case ' . $case['id'] . ' should remain stable.');
            $t->true(!str_contains($actual, '<math'), 'PlainMath fallback case ' . $case['id'] . ' should not emit partial MathML.');
        }
    },
    'keeps malformed plainmath fallback valid through epub3 writer integration' => static function (TestRunner $t) use ($loadXml): void {
        $document = new AstNode('document', [
            'meta' => [
                'title' => 'PlainMath Fallback EPUB',
            ],
        ], [
            new AstNode('heading', ['level' => 1, 'text' => 'PlainMath Fallback EPUB'], [
                new AstNode('text', ['text' => 'PlainMath Fallback EPUB']),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Inline ']),
                new AstNode('math', ['text' => '\frac{a}{', 'display' => false]),
                new AstNode('text', ['text' => '.']),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('math', ['text' => '\left( x + y', 'display' => true]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('math', ['text' => '\begin{pmatrix}a&b', 'display' => true]),
            ]),
        ]);

        $epub = PandocConverter::write($document, 'epub3', [
            'modified' => '2026-06-25T14:20:00Z',
        ]);
        $path = tempnam(sys_get_temp_dir(), 'pandoc-plainmath-fallback-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary PlainMath fallback EPUB path.');
        }
        file_put_contents($path, $epub);

        $zip = new ZipArchive();
        try {
            if ($zip->open($path) !== true) {
                throw new RuntimeException('Unable to open generated PlainMath fallback EPUB package.');
            }

            $chapter = $zip->getFromName('OEBPS/text/chapter.xhtml');
            if (!is_string($chapter)) {
                throw new RuntimeException('Generated PlainMath fallback EPUB is missing package or chapter files.');
            }

            $loadXml($chapter, 'PlainMath fallback EPUB chapter XHTML');
            $t->contains('<span class="math inline">\frac{a}{</span>', $chapter);
            $t->contains('<span class="math display">\left( x + y</span>', $chapter);
            $t->contains('<span class="math display">\begin{pmatrix}a&amp;b</span>', $chapter);
            $t->true(!str_contains($chapter, '<math'), 'Malformed PlainMath fallback XHTML should not contain MathML nodes.');
        } finally {
            $zip->close();
            @unlink($path);
        }
    },
    'keeps plainmath mathml valid through epub3 writer integration' => static function (TestRunner $t) use ($mathCasesById, $loadXml, $assertMathMLCase): void {
        $inline = $mathCasesById['script-super'];
        $display = $mathCasesById['display-fraction-root'];
        $document = new AstNode('document', [
            'meta' => [
                'title' => 'PlainMath Conformance EPUB',
            ],
        ], [
            new AstNode('heading', ['level' => 1, 'text' => 'PlainMath Conformance EPUB'], [
                new AstNode('text', ['text' => 'PlainMath Conformance EPUB']),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Inline ']),
                new AstNode('math', ['text' => $inline['tex'], 'display' => false]),
                new AstNode('text', ['text' => '.']),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('math', ['text' => $display['tex'], 'display' => true]),
            ]),
        ]);

        $epub = PandocConverter::write($document, 'epub3', [
            'modified' => '2026-06-25T13:40:00Z',
        ]);
        $path = tempnam(sys_get_temp_dir(), 'pandoc-plainmath-conformance-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary PlainMath conformance EPUB path.');
        }
        file_put_contents($path, $epub);

        $zip = new ZipArchive();
        try {
            if ($zip->open($path) !== true) {
                throw new RuntimeException('Unable to open generated PlainMath conformance EPUB package.');
            }

            $package = $zip->getFromName('OEBPS/package.opf');
            $chapter = $zip->getFromName('OEBPS/text/chapter.xhtml');
            if (!is_string($package) || !is_string($chapter)) {
                throw new RuntimeException('Generated PlainMath conformance EPUB is missing package or chapter files.');
            }

            $t->contains('<item id="chapter-1" href="text/chapter.xhtml" media-type="application/xhtml+xml" properties="mathml"/>', $package);
            $loadXml($chapter, 'PlainMath conformance EPUB chapter XHTML');

            $chapterDom = $loadXml($chapter, 'PlainMath conformance EPUB chapter XHTML for extraction');
            $xpath = new DOMXPath($chapterDom);
            $xpath->registerNamespace('m', 'http://www.w3.org/1998/Math/MathML');
            $mathNodes = $xpath->query('//m:math');
            if (!$mathNodes instanceof DOMNodeList) {
                throw new RuntimeException('PlainMath conformance EPUB MathML XPath failed.');
            }

            $t->same(2, $mathNodes->length, 'PlainMath conformance EPUB should contain inline and display MathML.');
            foreach ([[$inline, 0], [$display, 1]] as [$case, $index]) {
                $node = $mathNodes->item($index);
                if (!$node instanceof DOMNode) {
                    throw new RuntimeException('PlainMath conformance EPUB is missing MathML node ' . $index . '.');
                }

                $fragment = $chapterDom->saveXML($node);
                if (!is_string($fragment)) {
                    throw new RuntimeException('Unable to serialize PlainMath conformance EPUB MathML node ' . $index . '.');
                }
                $assertMathMLCase($t, $case, $fragment);
            }
        } finally {
            $zip->close();
            @unlink($path);
        }
    },
    'documents non-runtime plainmath conformance gaps in fixture metadata' => static function (TestRunner $t) use ($corpus): void {
        $t->true(count($corpus['knownGaps']) >= 1, 'PlainMath fixture metadata should record representative blocked upstream cases.');
        $t->true(in_array('macro-environments', array_column($corpus['knownGaps'], 'id'), true), 'PlainMath gap metadata should retain the custom environment macro blocker.');

        foreach ($corpus['knownGaps'] as $gap) {
            foreach (['id', 'upstream', 'tex', 'gap'] as $field) {
                $t->true(isset($gap[$field]) && trim((string) $gap[$field]) !== '', 'PlainMath gap metadata should include ' . $field . '.');
            }
        }
    },
];
