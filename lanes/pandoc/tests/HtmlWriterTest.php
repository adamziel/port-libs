<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;

$renderMathHtml = static function (string $tex, bool $display = false): string {
    $document = new AstNode('document', [], [
        new AstNode('paragraph', [], [
            new AstNode('math', [
                'display' => $display,
                'text' => $tex,
            ]),
        ]),
    ]);

    return (new HtmlWriter(['htmlMathMethod' => 'mathml']))->write($document);
};

$assertXml = static function (TestRunner $t, string $html): void {
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $ok = $dom->loadXML('<root>' . $html . '</root>', LIBXML_NONET);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $t->true(
        $ok,
        'Generated MathML HTML should parse as XML: ' . implode('; ', array_map(
            static fn (LibXMLError $error): string => trim($error->message),
            $errors
        ))
    );
};

return [
    'writes html tex macro definitions as mathml' => static function (TestRunner $t) use ($renderMathHtml, $assertXml): void {
        $macro = '\newcommand{\sq}[1]{#1^2}\sq{x}';
        $html = $renderMathHtml($macro);

        $assertXml($t, $html);
        $t->contains('<msup><mi>x</mi><mn>2</mn></msup>', $html);
        $t->contains('<annotation encoding="application/x-tex">\newcommand{\sq}[1]{#1^2}\sq{x}</annotation>', $html);
        $t->true(!str_contains($html, '<mi>sq</mi>'), 'Macro command should not fall back to a literal identifier.');
        $t->true(!str_contains($html, '<mi>newcommand</mi>'), 'Macro definition should not leak into generated MathML.');

        $defaulted = '\newcommand{\norm}[2][2]{\left\lVert #2 \right\rVert_#1}\norm{x}+\norm[1]{y}';
        $defaultedHtml = $renderMathHtml($defaulted);
        $assertXml($t, $defaultedHtml);
        $t->contains('<msub><mrow><mo stretchy="true">‖</mo><mi>x</mi><mo stretchy="true">‖</mo></mrow><mn>2</mn></msub>', $defaultedHtml);
        $t->contains('<msub><mrow><mo stretchy="true">‖</mo><mi>y</mi><mo stretchy="true">‖</mo></mrow><mn>1</mn></msub>', $defaultedHtml);
        $t->true(!str_contains($defaultedHtml, '<mi>norm</mi>'), 'Macro with optional argument should not fall back to a literal identifier.');
    },
    'writes html tex custom environment definitions as mathml' => static function (TestRunner $t) use ($renderMathHtml, $assertXml): void {
        $delimited = '\newenvironment{foo}{\left(}{\right)}\begin{foo}x\end{foo}';
        $delimitedHtml = $renderMathHtml($delimited);
        $assertXml($t, $delimitedHtml);
        $t->contains('<mrow><mo stretchy="true">(</mo><mi>x</mi><mo stretchy="true">)</mo></mrow>', $delimitedHtml);
        $t->true(!str_contains($delimitedHtml, '<mi>foo</mi>'), 'Custom environment name should not leak into generated MathML.');
        $t->true(!str_contains($delimitedHtml, '<mi>newenvironment</mi>'), 'Environment definition should not leak into generated MathML.');

        $renewed = '\newenvironment{foo}{\left(}{\right)}\renewenvironment{foo}{\left[}{\right]}\begin{foo}y\end{foo}';
        $renewedHtml = $renderMathHtml($renewed);
        $assertXml($t, $renewedHtml);
        $t->contains('<mrow><mo stretchy="true">[</mo><mi>y</mi><mo stretchy="true">]</mo></mrow>', $renewedHtml);
        $t->true(!str_contains($renewedHtml, '<mo stretchy="true">(</mo>'), 'Renewed environment should use the latest opener.');

        $defaulted = '\newenvironment{shift}[2][2]{#2_{#1}+}{}\begin{shift}[3]{x}y\end{shift}';
        $defaultedHtml = $renderMathHtml($defaulted);
        $assertXml($t, $defaultedHtml);
        $t->contains('<mrow><msub><mi>x</mi><mn>3</mn></msub><mo>+</mo><mi>y</mi></mrow>', $defaultedHtml);
    },
    'falls back predictably for malformed custom tex environments' => static function (TestRunner $t) use ($renderMathHtml, $assertXml): void {
        $html = $renderMathHtml('\newenvironment{foo}{\left(}{\right)}\begin{foo}x');

        $assertXml($t, $html);
        $t->contains('<span class="math inline">\newenvironment{foo}{\left(}{\right)}\begin{foo}x</span>', $html);
        $t->true(!str_contains($html, '<math'), 'Malformed custom environment should not emit partial MathML.');
    },
    'writes html tex operator declarations and ignorable tokens as mathml' => static function (TestRunner $t) use ($renderMathHtml, $assertXml): void {
        $operator = '\DeclareMathOperator*{\argmax}{arg\,max}\argmax_{x} f(x)';
        $operatorHtml = $renderMathHtml($operator);
        $assertXml($t, $operatorHtml);
        $t->contains('<msub><mi mathvariant="normal">arg max</mi><mi>x</mi></msub>', $operatorHtml);
        $t->true(!str_contains($operatorHtml, '<mi>argmax</mi>'), 'Declared operator should not fall back to a literal identifier.');
        $t->true(!str_contains($operatorHtml, '<mi>DeclareMathOperator</mi>'), 'Operator declaration should not leak into generated MathML.');

        $ignorable = "x% comment\n+ y\\label{eq:a}\\tag*{A}\\nonumber\\allowbreak";
        $ignorableHtml = $renderMathHtml($ignorable);
        $assertXml($t, $ignorableHtml);
        $t->contains('<mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow>', $ignorableHtml);
        foreach (['label', 'tag', 'nonumber', 'allowbreak'] as $fallback) {
            $t->true(!str_contains($ignorableHtml, '<mi>' . $fallback . '</mi>'), "Ignorable TeX command should not leak into generated MathML: {$fallback}.");
        }
    },
    'writes html tex ams environment aliases as mathml' => static function (TestRunner $t) use ($renderMathHtml, $assertXml): void {
        $align = '\begin{align}a&=b+c\\\\d&=e\end{align}';
        $alignHtml = $renderMathHtml($align, true);
        $assertXml($t, $alignHtml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $alignHtml);
        $t->contains('<mtable columnalign="right left"><mtr><mtd><mi>a</mi></mtd><mtd><mrow><mo>=</mo><mi>b</mi><mo>+</mo><mi>c</mi></mrow></mtd></mtr><mtr><mtd><mi>d</mi></mtd><mtd><mrow><mo>=</mo><mi>e</mi></mrow></mtd></mtr></mtable>', $alignHtml);
        foreach (['begin', 'align', 'end'] as $fallback) {
            $t->true(!str_contains($alignHtml, '<mi>' . $fallback . '</mi>'), "AMS align environment should not leak literal identifiers: {$fallback}.");
        }

        $equation = '\begin{equation}x^2+1\end{equation}';
        $equationHtml = $renderMathHtml($equation);
        $assertXml($t, $equationHtml);
        $t->contains('<mrow><msup><mi>x</mi><mn>2</mn></msup><mo>+</mo><mn>1</mn></mrow>', $equationHtml);
        $t->true(!str_contains($equationHtml, '<mtable'), 'Equation environment should render as a grouped formula, not a table.');
    },
    'writes plainmath array column specs row breaks and hline markers to mathml tables' => static function (TestRunner $t) use ($renderMathHtml, $assertXml): void {
        $html = $renderMathHtml(<<<'TEX'
\begin{array}{|l@{\quad}*{2}{cr}|}
a & b & c & d & e\\[1ex]\hline
f & g & h & i & j
\end{array}
TEX, true);

        $assertXml($t, $html);
        $t->contains('<mtable columnalign="left center right center right">', $html);
        $t->same(2, substr_count($html, '<mtr>'));
        $t->same(10, substr_count($html, '<mtd>'));
        $t->true(!str_contains($html, '<mi>hline</mi>'), 'Array row markers should not leak into MathML cells.');
        $t->true(!str_contains($html, '<mo>[</mo><mn>1</mn><mi>ex</mi><mo>]</mo>'), 'Optional row spacing should be consumed with the line break.');
    },
    'writes plainmath aligned and alignedat environments with alignment cells' => static function (TestRunner $t) use ($renderMathHtml, $assertXml): void {
        $aligned = $renderMathHtml(<<<'TEX'
\begin{align*}
x&=1\\
y&=2
\end{align*}
TEX, true);

        $alignedAt = $renderMathHtml(<<<'TEX'
\begin{alignedat}{2}
a&=b & c&=d\\
e&=f & g&=h
\end{alignedat}
TEX, true);

        $assertXml($t, $aligned);
        $assertXml($t, $alignedAt);
        $t->contains('<mtable columnalign="right left">', $aligned);
        $t->same(2, substr_count($aligned, '<mtr>'));
        $t->same(4, substr_count($aligned, '<mtd>'));
        $t->contains('<mtable columnalign="right left right left">', $alignedAt);
        $t->same(2, substr_count($alignedAt, '<mtr>'));
        $t->same(8, substr_count($alignedAt, '<mtd>'));
        $t->true(!str_contains($alignedAt, '<mn>2</mn>'), 'alignedat pair count should not become a MathML cell.');
    },
    'writes nested plainmath cases and matrix environments as nested mathml tables' => static function (TestRunner $t) use ($renderMathHtml, $assertXml): void {
        $html = $renderMathHtml(<<<'TEX'
\begin{cases}
\begin{pmatrix}a&b\\c&d\end{pmatrix} & x>0\\
0 & x\le 0
\end{cases}
TEX, true);

        $assertXml($t, $html);
        $t->same(2, substr_count($html, '<mtable'));
        $t->same(4, substr_count($html, '<mtr>'));
        $t->same(8, substr_count($html, '<mtd>'));
        $t->contains('<mo stretchy="true">{</mo>', $html);
        $t->contains('<mo stretchy="true">(</mo>', $html);
        $t->contains('<mo stretchy="true">)</mo>', $html);
        $t->contains('<mo>≤</mo>', $html);
    },
    'falls back predictably for malformed plainmath matrix environments' => static function (TestRunner $t) use ($renderMathHtml, $assertXml): void {
        $html = $renderMathHtml('\begin{pmatrix}a&b', true);

        $assertXml($t, $html);
        $t->contains('<mtext>\begin{pmatrix}a&amp;b</mtext>', $html);
        $t->same(0, substr_count($html, '<mtable'));
    },
];
