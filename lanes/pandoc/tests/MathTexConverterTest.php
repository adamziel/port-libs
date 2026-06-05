<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\MarkdownReader;
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
    'converts bounded tex indexed roots to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $rootMathml = $converter->texToMathMl('\\sqrt[3]{x} + \\sqrt[n+1]{\\frac{a}{b}}', true);
        $docxRootMathml = $converter->mathMlFor(new AstNode('math', [
            'text' => '\\sqrt[k]{x_i + y_i}',
            'display' => false,
            'sourceFormat' => 'docx-omml',
        ]));

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $rootMathml);
        $t->contains('<mroot><mi>x</mi><mn>3</mn></mroot><mo>+</mo><mroot><mfrac><mi>a</mi><mi>b</mi></mfrac><mrow><mi>n</mi><mo>+</mo><mn>1</mn></mrow></mroot>', $rootMathml);
        $t->contains('<annotation encoding="application/x-tex">\\sqrt[3]{x} + \\sqrt[n+1]{\\frac{a}{b}}</annotation>', $rootMathml);
        $t->contains('<mroot><mrow><msub><mi>x</mi><mi>i</mi></msub><mo>+</mo><msub><mi>y</mi><mi>i</mi></msub></mrow><mi>k</mi></mroot>', $docxRootMathml);
        $t->contains('<annotation encoding="application/x-tex">\\sqrt[k]{x_i + y_i}</annotation>', $docxRootMathml);
    },
    'adds source tex semantics annotations to bounded mathml handoff' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $annotated = $converter->texToMathMl('\\text{posts & media} \\in S');
        $nodeMathml = $converter->mathMlFor(new AstNode('math', [
            'text' => '\\frac{x}{y}',
            'display' => true,
        ]));

        $t->contains('<semantics><mrow><mtext>posts &amp; media</mtext><mo>∈</mo><mi>S</mi></mrow><annotation encoding="application/x-tex">\\text{posts &amp; media} \\in S</annotation></semantics>', $annotated);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><semantics><mfrac><mi>x</mi><mi>y</mi></mfrac>', $nodeMathml);
        $t->contains('<annotation encoding="application/x-tex">\\frac{x}{y}</annotation></semantics></math>', $nodeMathml);
    },
    'expands bounded raw tex macros for mathml handoff while preserving source annotations' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $document = (new MarkdownReader())->read(implode("\n", [
            '\\newcommand{\\tuple}[1]{\\langle #1 \\rangle}',
            '\\providecommand{\\pair}[2]{\\langle #1,#2 \\rangle}',
            '',
            '$\\tuple{x,y}$',
        ]));
        $macros = $converter->macroDefinitionsFromDocument($document);
        $mathml = $converter->texToMathMl('\\tuple{x,y} + \\pair{p_i}{m_i}', false, $macros);
        $nodeMathml = $converter->mathMlFor(new AstNode('math', [
            'text' => '\\pair{a}{b}',
            'display' => true,
        ]), $macros);

        $t->same([
            'tuple' => ['arity' => 1, 'template' => '\\langle #1 \\rangle'],
            'pair' => ['arity' => 2, 'template' => '\\langle #1,#2 \\rangle'],
        ], $macros);
        $t->contains('<mo>⟨</mo><mi>x</mi><mo>,</mo><mi>y</mi><mo>⟩</mo><mo>+</mo><mo>⟨</mo><msub><mi>p</mi><mi>i</mi></msub><mo>,</mo><msub><mi>m</mi><mi>i</mi></msub><mo>⟩</mo>', $mathml);
        $t->contains('<annotation encoding="application/x-tex">\\tuple{x,y} + \\pair{p_i}{m_i}</annotation>', $mathml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><semantics><mrow><mo>⟨</mo><mi>a</mi><mo>,</mo><mi>b</mi><mo>⟩</mo></mrow>', $nodeMathml);
        $t->contains('<annotation encoding="application/x-tex">\\pair{a}{b}</annotation></semantics></math>', $nodeMathml);
    },
    'rejects unsupported bounded tex macro definitions before mathml conversion' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\bad{x}', false, ['bad-name' => ['arity' => 1, 'template' => '#1']]));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\bad{x}', false, ['bad' => ['arity' => 10, 'template' => '#1']]));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\bad{x}', false, ['bad' => ['arity' => 1]]));
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
    'converts bounded tex relation set logic and arrow commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $setMathml = $converter->texToMathMl('\\forall p_i \\in P \\land m_i \\notin M \\Rightarrow p_i \\subseteq Q \\cup R', true);
        $logicMathml = $converter->texToMathMl('\\exists x \\in S \\colon x \\approx y \\lor \\neg (y \\equiv z)');
        $emptyMathml = $converter->texToMathMl('A \\cap B = \\emptyset \\iff A \\subset B \\setminus C');
        $arrowMathml = $converter->texToMathMl('a \\leftarrow b \\leftrightarrow c \\mapsto d \\partial f');
        $aliasMathml = $converter->texToMathMl('U \\supseteq V \\supset W \\vee a \\le b \\ge c \\ne d');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $setMathml);
        $t->contains('<mo>∀</mo><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi>', $setMathml);
        $t->contains('<mo>∧</mo><msub><mi>m</mi><mi>i</mi></msub><mo>∉</mo><mi>M</mi><mo>⇒</mo><msub><mi>p</mi><mi>i</mi></msub><mo>⊆</mo><mi>Q</mi><mo>∪</mo><mi>R</mi>', $setMathml);
        $t->contains('<mo>∃</mo><mi>x</mi><mo>∈</mo><mi>S</mi><mo>:</mo><mi>x</mi><mo>≈</mo><mi>y</mi><mo>∨</mo><mo>¬</mo><mo>(</mo><mi>y</mi><mo>≡</mo><mi>z</mi><mo>)</mo>', $logicMathml);
        $t->contains('<mi>A</mi><mo>∩</mo><mi>B</mi><mo>=</mo><mo>∅</mo><mo>⇔</mo><mi>A</mi><mo>⊂</mo><mi>B</mi><mo>∖</mo><mi>C</mi>', $emptyMathml);
        $t->contains('<mi>a</mi><mo>←</mo><mi>b</mi><mo>↔</mo><mi>c</mi><mo>↦</mo><mi>d</mi><mo>∂</mo><mi>f</mi>', $arrowMathml);
        $t->contains('<mi>U</mi><mo>⊇</mo><mi>V</mi><mo>⊃</mo><mi>W</mi><mo>∨</mo><mi>a</mi><mo>≤</mo><mi>b</mi><mo>≥</mo><mi>c</mi><mo>≠</mo><mi>d</mi>', $aliasMathml);
    },
    'converts bounded tex accents overlines and underlines to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $accentMathml = $converter->texToMathMl('\\hat{x} + \\widehat{ab} + \\bar y + \\overline{AB} + \\vec{v}_i');
        $dotMathml = $converter->texToMathMl('\\dot{x} + \\ddot{y} + \\tilde n');
        $underMathml = $converter->texToMathMl('\\underline{\\operatorname{draft}}');

        $t->contains('<mover accent="true"><mi>x</mi><mo>^</mo></mover>', $accentMathml);
        $t->contains('<mover accent="true"><mrow><mi>a</mi><mi>b</mi></mrow><mo>^</mo></mover>', $accentMathml);
        $t->contains('<mover accent="true"><mi>y</mi><mo>¯</mo></mover><mo>+</mo><mover accent="true"><mrow><mi>A</mi><mi>B</mi></mrow><mo>‾</mo></mover>', $accentMathml);
        $t->contains('<msub><mover accent="true"><mi>v</mi><mo>→</mo></mover><mi>i</mi></msub>', $accentMathml);
        $t->contains('<mover accent="true"><mi>x</mi><mo>˙</mo></mover><mo>+</mo><mover accent="true"><mi>y</mi><mo>¨</mo></mover><mo>+</mo><mover accent="true"><mi>n</mi><mo>~</mo></mover>', $dotMathml);
        $t->contains('<munder accentunder="true"><mi>draft</mi><mo>_</mo></munder>', $underMathml);
    },
    'converts bounded tex matrix and aligned environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $matrixMathml = $converter->texToMathMl('\\begin{matrix}a & b \\\\ c & d\\end{matrix}');
        $fencedMathml = $converter->texToMathMl('\\begin{pmatrix}p_1 & m_1 \\\\ p_2 & m_2\\end{pmatrix}', true);
        $bracketMathml = $converter->texToMathMl('\\begin{bmatrix}\\frac{a}{b} & \\sqrt{x} \\\\ \\alpha & \\omega\\end{bmatrix}');
        $alignedMathml = $converter->texToMathMl('\\begin{aligned}x_i &= \\operatorname{score}(p_i) \\\\ y_i &= \\frac{a_i}{b_i}\\end{aligned}');

        $t->contains('<mtable><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mi>d</mi></mtd></mtr></mtable>', $matrixMathml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $fencedMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mtable><mtr><mtd><msub><mi>p</mi><mn>1</mn></msub></mtd><mtd><msub><mi>m</mi><mn>1</mn></msub></mtd></mtr><mtr><mtd><msub><mi>p</mi><mn>2</mn></msub></mtd><mtd><msub><mi>m</mi><mn>2</mn></msub></mtd></mtr></mtable><mo fence="true" stretchy="true">)</mo>', $fencedMathml);
        $t->contains('<mo fence="true" stretchy="true">[</mo><mtable><mtr><mtd><mfrac><mi>a</mi><mi>b</mi></mfrac></mtd><mtd><msqrt><mi>x</mi></msqrt></mtd></mtr><mtr><mtd><mi>α</mi></mtd><mtd><mi>ω</mi></mtd></mtr></mtable><mo fence="true" stretchy="true">]</mo>', $bracketMathml);
        $t->contains('<mtable columnalign="right left"><mtr><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><mi>score</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo></mtd></mtr>', $alignedMathml);
        $t->contains('<mtr><mtd><msub><mi>y</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><mfrac><msub><mi>a</mi><mi>i</mi></msub><msub><mi>b</mi><mi>i</mi></msub></mfrac></mtd></mtr></mtable>', $alignedMathml);
    },
    'rejects malformed bounded tex matrix environments without invoking a tex engine' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}a & b\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{matrix}a & b'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{matrix}\\frac{a}{b & c\\end{matrix}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{matrix}\\end{matrix}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\end{matrix}'));
    },
    'rejects malformed bounded tex math groups without invoking a tex engine' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\frac{a}{'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sqrt'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sqrt[]{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sqrt[3{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\text{unterminated'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatorname'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatorname{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\left'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\left\\unknown{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hat'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\vec_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underline^2'));
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
