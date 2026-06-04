<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\MathTexConverter;

return [
    'converts bounded tex fractions scripts roots symbols and text to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $mathml = $converter->texToMathMl('\\frac{a_1}{\\sqrt{b^2}} + \\alpha \\times \\omega', true);
        $textMathml = $converter->texToMathMl('\\text{posts & media} \\in S');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $mathml);
        $t->contains('<mfrac><msub><mi>a</mi><mn>1</mn></msub><msqrt><msup><mi>b</mi><mn>2</mn></msup></msqrt></mfrac>', $mathml);
        $t->contains('<mo>+</mo><mi>α</mi><mo>×</mo><mi>ω</mi>', $mathml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="inline">', $textMathml);
        $t->contains('<mtext>posts &amp; media</mtext><mo>∈</mo><mi>S</mi>', $textMathml);
    },
    'converts bounded tex delimiter commands and stretch fences to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $angleMathml = $converter->texToMathMl('\\langle x,y \\rangle');
        $fencedMathml = $converter->texToMathMl('\\left(\\frac{x}{y}\\right) + \\left\\{a\\right\\}', true);
        $invisibleFenceMathml = $converter->texToMathMl('\\left. x \\right|_{0}^{1}');

        $t->contains('<mo>⟨</mo><mi>x</mi><mo>,</mo><mi>y</mi><mo>⟩</mo>', $angleMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mfrac><mi>x</mi><mi>y</mi></mfrac><mo fence="true" stretchy="true">)</mo>', $fencedMathml);
        $t->contains('<mo fence="true" stretchy="true">{</mo><mi>a</mi><mo fence="true" stretchy="true">}</mo>', $fencedMathml);
        $t->contains('<mi>x</mi><msubsup><mo fence="true" stretchy="true">|</mo><mn>0</mn><mn>1</mn></msubsup>', $invisibleFenceMathml);
    },
    'converts bounded tex large operators functions and operator names to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $operatorMathml = $converter->texToMathMl('\\sum_{i=1}^{n} \\operatorname{migrate}(p_i) + \\int_{0}^{1} f(x) dx', true);
        $functionMathml = $converter->texToMathMl('\\sin^2 \\theta + \\log_{10} x + \\prod_{k=1}^{3} k');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $operatorMathml);
        $t->contains('<msubsup><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></msubsup>', $operatorMathml);
        $t->contains('<mi>migrate</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo>', $operatorMathml);
        $t->contains('<msubsup><mo>∫</mo><mn>0</mn><mn>1</mn></msubsup><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo><mi>d</mi><mi>x</mi>', $operatorMathml);
        $t->contains('<msup><mi>sin</mi><mn>2</mn></msup><mi>θ</mi>', $functionMathml);
        $t->contains('<msub><mi>log</mi><mn>10</mn></msub><mi>x</mi><mo>+</mo><msubsup><mo>∏</mo><mrow><mi>k</mi><mo>=</mo><mn>1</mn></mrow><mn>3</mn></msubsup><mi>k</mi>', $functionMathml);
    },
    'rejects malformed bounded tex math groups without invoking a tex engine' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\frac{a}{'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sqrt'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\text{unterminated'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatorname'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatorname{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\left'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\left\\unknown{x}'));
    },
    'renders latex writer math and raw tex without dropping source content' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Posts & media formula: '),
                new AstNode('math', ['text' => 'E = mc^2', 'display' => false]),
                $text(' with raw cite '),
                new AstNode('raw_tex', ['tex' => '\\cite{source}']),
                $text('.'),
            ]),
            new AstNode('paragraph', [], [
                $text('Display: '),
                new AstNode('math', ['text' => '\\alpha + \\omega', 'display' => true]),
            ]),
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [
                    new AstNode('math', ['text' => 'p', 'display' => false]),
                    $text('-Tree keeps '),
                    new AstNode('raw_tex', ['tex' => '\\cite{tree}']),
                ]),
            ]),
        ]);

        $t->same(implode("\n\n", [
            'Posts \\& media formula: $E = mc^2$ with raw cite \\cite{source}.',
            'Display: \\[\\alpha + \\omega\\]',
            implode("\n", [
                '\\begin{itemize}',
                '\\item',
                '  $p$-Tree keeps \\cite{tree}',
                '\\end{itemize}',
            ]),
        ]), (new LatexWriter())->write($document));
    },
    'renders raw tex blocks through latex writer for source handoff' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('raw_tex', ['tex' => '\\newcommand{\\wptuple}[1]{\\langle #1 \\rangle}']),
            new AstNode('raw_block', ['format' => 'latex', 'text' => '\\begin{migration-review}' . "\n" . '\\item keep TeX source' . "\n" . '\\end{migration-review}']),
        ]);

        $t->same(implode("\n\n", [
            '\\newcommand{\\wptuple}[1]{\\langle #1 \\rangle}',
            implode("\n", [
                '\\begin{migration-review}',
                '\\item keep TeX source',
                '\\end{migration-review}',
            ]),
        ]), (new LatexWriter())->write($document));
    },
];
