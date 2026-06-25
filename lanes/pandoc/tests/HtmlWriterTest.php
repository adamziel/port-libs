<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;

$renderMathHtml = static function (string $tex, bool $display = false): string {
    $document = new AstNode('document', [], [
        new AstNode('paragraph', [], [
            new AstNode('math', [
                'display' => $display,
                'text' => $tex,
            ]),
        ]),
    ]);

    return PandocConverter::write($document, 'html', [
        'htmlMathMethod' => 'mathml',
    ]);
};

return [
    'writes html tex macro definitions as mathml' => static function (TestRunner $t) use ($renderMathHtml): void {
        $macro = '\newcommand{\sq}[1]{#1^2}\sq{x}';
        $html = $renderMathHtml($macro);

        $t->contains('<msup><mi>x</mi><mn>2</mn></msup>', $html);
        $t->contains('<annotation encoding="application/x-tex">\newcommand{\sq}[1]{#1^2}\sq{x}</annotation>', $html);
        $t->true(!str_contains($html, '<mi>sq</mi>'), 'Macro command should not fall back to a literal identifier.');
        $t->true(!str_contains($html, '<mi>newcommand</mi>'), 'Macro definition should not leak into generated MathML.');

        $defaulted = '\newcommand{\norm}[2][2]{\left\lVert #2 \right\rVert_#1}\norm{x}+\norm[1]{y}';
        $defaultedHtml = $renderMathHtml($defaulted);
        $t->contains('<msub><mrow><mo stretchy="true">‖</mo><mi>x</mi><mo stretchy="true">‖</mo></mrow><mn>2</mn></msub>', $defaultedHtml);
        $t->contains('<msub><mrow><mo stretchy="true">‖</mo><mi>y</mi><mo stretchy="true">‖</mo></mrow><mn>1</mn></msub>', $defaultedHtml);
        $t->true(!str_contains($defaultedHtml, '<mi>norm</mi>'), 'Macro with optional argument should not fall back to a literal identifier.');
    },
    'writes html tex operator declarations and ignorable tokens as mathml' => static function (TestRunner $t) use ($renderMathHtml): void {
        $operator = '\DeclareMathOperator*{\argmax}{arg\,max}\argmax_{x} f(x)';
        $operatorHtml = $renderMathHtml($operator);
        $t->contains('<msub><mi mathvariant="normal">arg max</mi><mi>x</mi></msub>', $operatorHtml);
        $t->true(!str_contains($operatorHtml, '<mi>argmax</mi>'), 'Declared operator should not fall back to a literal identifier.');
        $t->true(!str_contains($operatorHtml, '<mi>DeclareMathOperator</mi>'), 'Operator declaration should not leak into generated MathML.');

        $ignorable = "x% comment\n+ y\\label{eq:a}\\tag*{A}\\nonumber\\allowbreak";
        $ignorableHtml = $renderMathHtml($ignorable);
        $t->contains('<mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow>', $ignorableHtml);
        foreach (['label', 'tag', 'nonumber', 'allowbreak'] as $fallback) {
            $t->true(!str_contains($ignorableHtml, '<mi>' . $fallback . '</mi>'), "Ignorable TeX command should not leak into generated MathML: {$fallback}.");
        }
    },
    'writes html tex ams environment aliases as mathml' => static function (TestRunner $t) use ($renderMathHtml): void {
        $align = '\begin{align}a&=b+c\\\\d&=e\end{align}';
        $alignHtml = $renderMathHtml($align, true);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $alignHtml);
        $t->contains('<mtable columnalign="right left"><mtr><mtd><mi>a</mi></mtd><mtd><mrow><mo>=</mo><mi>b</mi><mo>+</mo><mi>c</mi></mrow></mtd></mtr><mtr><mtd><mi>d</mi></mtd><mtd><mrow><mo>=</mo><mi>e</mi></mrow></mtd></mtr></mtable>', $alignHtml);
        foreach (['begin', 'align', 'end'] as $fallback) {
            $t->true(!str_contains($alignHtml, '<mi>' . $fallback . '</mi>'), "AMS align environment should not leak literal identifiers: {$fallback}.");
        }

        $equation = '\begin{equation}x^2+1\end{equation}';
        $equationHtml = $renderMathHtml($equation);
        $t->contains('<mrow><msup><mi>x</mi><mn>2</mn></msup><mo>+</mo><mn>1</mn></mrow>', $equationHtml);
        $t->true(!str_contains($equationHtml, '<mtable'), 'Equation environment should render as a grouped formula, not a table.');
    },
];
