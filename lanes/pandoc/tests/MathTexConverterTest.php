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
    'converts bounded tex binomial commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $binomialMathml = $converter->texToMathMl('\\binom{n}{k} + \\tbinom{p_i}{2} + \\dbinom{a+b}{c}', true);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $binomialMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo fence="true" stretchy="true">)</mo>', $binomialMathml);
        $t->contains('<mstyle displaystyle="false"><mrow><mo fence="true" stretchy="true">(</mo><mfrac linethickness="0"><msub><mi>p</mi><mi>i</mi></msub><mn>2</mn></mfrac><mo fence="true" stretchy="true">)</mo></mrow></mstyle>', $binomialMathml);
        $t->contains('<mstyle displaystyle="true"><mrow><mo fence="true" stretchy="true">(</mo><mfrac linethickness="0"><mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow><mi>c</mi></mfrac><mo fence="true" stretchy="true">)</mo></mrow></mstyle>', $binomialMathml);
        $t->contains('<annotation encoding="application/x-tex">\\binom{n}{k} + \\tbinom{p_i}{2} + \\dbinom{a+b}{c}</annotation>', $binomialMathml);
    },
    'converts bounded tex generalized fractions to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $fractionMathml = $converter->texToMathMl('\\dfrac{a_1}{b_1} + \\tfrac{x}{y} + \\genfrac{[}{]}{0pt}{0}{n}{k} + \\genfrac{\\langle}{\\rangle}{1pt}{2}{p_i}{m_i}', true);
        $plainGenfracMathml = $converter->texToMathMl('\\genfrac{}{}{.5pt}{}{a+b}{c}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $fractionMathml);
        $t->contains('<mstyle displaystyle="true"><mfrac><msub><mi>a</mi><mn>1</mn></msub><msub><mi>b</mi><mn>1</mn></msub></mfrac></mstyle>', $fractionMathml);
        $t->contains('<mstyle displaystyle="false"><mfrac><mi>x</mi><mi>y</mi></mfrac></mstyle>', $fractionMathml);
        $t->contains('<mstyle displaystyle="true"><mrow><mo fence="true" stretchy="true">[</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo fence="true" stretchy="true">]</mo></mrow></mstyle>', $fractionMathml);
        $t->contains('<mstyle scriptlevel="1"><mrow><mo fence="true" stretchy="true">⟨</mo><mfrac linethickness="1pt"><msub><mi>p</mi><mi>i</mi></msub><msub><mi>m</mi><mi>i</mi></msub></mfrac><mo fence="true" stretchy="true">⟩</mo></mrow></mstyle>', $fractionMathml);
        $t->contains('<annotation encoding="application/x-tex">\\dfrac{a_1}{b_1} + \\tfrac{x}{y} + \\genfrac{[}{]}{0pt}{0}{n}{k} + \\genfrac{\\langle}{\\rangle}{1pt}{2}{p_i}{m_i}</annotation>', $fractionMathml);
        $t->contains('<mfrac linethickness=".5pt"><mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow><mi>c</mi></mfrac>', $plainGenfracMathml);
    },
    'converts bounded tex infix fraction commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $infixMathml = $converter->texToMathMl('{a+b \\over c+d} + {n \\atop k} + {n \\choose k}', true);
        $delimitedMathml = $converter->texToMathMl('{p_i \\brack m_i} + {x+y \\brace z}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $infixMathml);
        $t->contains('<mfrac><mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow><mrow><mi>c</mi><mo>+</mo><mi>d</mi></mrow></mfrac>', $infixMathml);
        $t->contains('<mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac>', $infixMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo fence="true" stretchy="true">)</mo>', $infixMathml);
        $t->contains('<annotation encoding="application/x-tex">{a+b \\over c+d} + {n \\atop k} + {n \\choose k}</annotation>', $infixMathml);
        $t->contains('<mo fence="true" stretchy="true">[</mo><mfrac linethickness="0"><msub><mi>p</mi><mi>i</mi></msub><msub><mi>m</mi><mi>i</mi></msub></mfrac><mo fence="true" stretchy="true">]</mo>', $delimitedMathml);
        $t->contains('<mo fence="true" stretchy="true">{</mo><mfrac linethickness="0"><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow><mi>z</mi></mfrac><mo fence="true" stretchy="true">}</mo>', $delimitedMathml);
    },
    'converts bounded tex infix fractions with explicit delimiters to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $withDelimsMathml = $converter->texToMathMl('{a+b \\overwithdelims() c+d} + {n \\atopwithdelims\\langle\\rangle k} + {p_i \\abovewithdelims[]1pt m_i}', true);
        $invisibleDelimsMathml = $converter->texToMathMl('{x \\overwithdelims.\\rbrace y}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $withDelimsMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mfrac><mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow><mrow><mi>c</mi><mo>+</mo><mi>d</mi></mrow></mfrac><mo fence="true" stretchy="true">)</mo>', $withDelimsMathml);
        $t->contains('<mo fence="true" stretchy="true">⟨</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo fence="true" stretchy="true">⟩</mo>', $withDelimsMathml);
        $t->contains('<mo fence="true" stretchy="true">[</mo><mfrac linethickness="1pt"><msub><mi>p</mi><mi>i</mi></msub><msub><mi>m</mi><mi>i</mi></msub></mfrac><mo fence="true" stretchy="true">]</mo>', $withDelimsMathml);
        $t->contains('<annotation encoding="application/x-tex">{a+b \\overwithdelims() c+d} + {n \\atopwithdelims\\langle\\rangle k} + {p_i \\abovewithdelims[]1pt m_i}</annotation>', $withDelimsMathml);
        $t->contains('<mfrac><mi>x</mi><mi>y</mi></mfrac><mo fence="true" stretchy="true">}</mo>', $invisibleDelimsMathml);
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
    'converts bounded tex text mode aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $aliasMathml = $converter->texToMathMl('\\mbox{review mode} + \\textrm{media label} + \\textbf{draft} + \\textit{review} + \\texttt{code_1} + \\textsf{sans group}', true);
        $escapedTextMathml = $converter->texToMathMl('\\text{posts \\& media \\% draft} + \\textnormal{plain} + \\emph{note}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $aliasMathml);
        $t->contains('<mtext>review mode</mtext><mo>+</mo><mstyle mathvariant="normal"><mtext>media label</mtext></mstyle>', $aliasMathml);
        $t->contains('<mstyle mathvariant="bold"><mtext>draft</mtext></mstyle><mo>+</mo><mstyle mathvariant="italic"><mtext>review</mtext></mstyle>', $aliasMathml);
        $t->contains('<mstyle mathvariant="monospace"><mtext>code_1</mtext></mstyle><mo>+</mo><mstyle mathvariant="sans-serif"><mtext>sans group</mtext></mstyle>', $aliasMathml);
        $t->contains('<annotation encoding="application/x-tex">\\mbox{review mode} + \\textrm{media label} + \\textbf{draft} + \\textit{review} + \\texttt{code_1} + \\textsf{sans group}</annotation>', $aliasMathml);
        $t->contains('<mtext>posts &amp; media % draft</mtext><mo>+</mo><mstyle mathvariant="normal"><mtext>plain</mtext></mstyle><mo>+</mo><mstyle mathvariant="italic"><mtext>note</mtext></mstyle>', $escapedTextMathml);
        $t->contains('<annotation encoding="application/x-tex">\\text{posts \\&amp; media \\% draft} + \\textnormal{plain} + \\emph{note}</annotation>', $escapedTextMathml);
    },
    'adds bounded mathml accessibility text and intent annotations' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $accessible = $converter->texToAccessibleMathMl('\\frac{a_1}{\\sqrt{b^2}} + \\alpha', true);
        $nodeMathml = $converter->accessibleMathMlFor(new AstNode('math', [
            'text' => '\\sum_{i=1}^{n} p_i',
            'display' => true,
        ]));

        $t->contains('display="block" alttext="fraction a sub 1 over square root of b superscript 2 plus alpha" intent="row(fraction(subscript(a,1),sqrt(superscript(b,2))),plus,alpha)"', $accessible);
        $t->contains('<annotation encoding="application/x-tex">\\frac{a_1}{\\sqrt{b^2}} + \\alpha</annotation>', $accessible);
        $t->contains('<annotation encoding="application/x-portlibs-math-alttext">fraction a sub 1 over square root of b superscript 2 plus alpha</annotation>', $accessible);
        $t->contains('<annotation encoding="application/x-portlibs-math-intent">row(fraction(subscript(a,1),sqrt(superscript(b,2))),plus,alpha)</annotation>', $accessible);
        $t->contains('alttext="sum sub i equals 1 superscript n p sub i"', $nodeMathml);
        $t->contains('intent="row(subsup(sum,row(i,equals,1),n),subscript(p,i))"', $nodeMathml);
        $t->contains('<annotation encoding="application/x-tex">\\sum_{i=1}^{n} p_i</annotation>', $nodeMathml);
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
    'expands bounded raw tex macros with optional arguments for mathml handoff' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $document = (new MarkdownReader())->read(implode("\n", [
            '\\newcommand{\\reviewpair}[2][draft]{#2 + #1}',
            '\\providecommand{\\withlabel}[3][audit]{#2 + #3 + #1}',
            '',
            '$\\reviewpair{p_i}$',
        ]));
        $macros = $converter->macroDefinitionsFromDocument($document);
        $defaultMathml = $converter->texToMathMl('\\reviewpair{p_i} + \\withlabel{m}{1}', false, $macros);
        $overrideMathml = $converter->texToMathMl('\\reviewpair[final]{p_i} + \\withlabel[source]{m}{2}', false, $macros);

        $t->same([
            'reviewpair' => ['arity' => 2, 'template' => '#2 + #1', 'optionalDefault' => 'draft'],
            'withlabel' => ['arity' => 3, 'template' => '#2 + #3 + #1', 'optionalDefault' => 'audit'],
        ], $macros);
        $t->contains('<msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><mi>d</mi><mi>r</mi><mi>a</mi><mi>f</mi><mi>t</mi><mo>+</mo><mi>m</mi><mo>+</mo><mn>1</mn><mo>+</mo><mi>a</mi><mi>u</mi><mi>d</mi><mi>i</mi><mi>t</mi>', $defaultMathml);
        $t->contains('<annotation encoding="application/x-tex">\\reviewpair{p_i} + \\withlabel{m}{1}</annotation>', $defaultMathml);
        $t->contains('<msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><mi>f</mi><mi>i</mi><mi>n</mi><mi>a</mi><mi>l</mi><mo>+</mo><mi>m</mi><mo>+</mo><mn>2</mn><mo>+</mo><mi>s</mi><mi>o</mi><mi>u</mi><mi>r</mi><mi>c</mi><mi>e</mi>', $overrideMathml);
        $t->contains('<annotation encoding="application/x-tex">\\reviewpair[final]{p_i} + \\withlabel[source]{m}{2}</annotation>', $overrideMathml);
    },
    'rejects unsupported bounded tex macro definitions before mathml conversion' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\bad{x}', false, ['bad-name' => ['arity' => 1, 'template' => '#1']]));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\bad{x}', false, ['bad' => ['arity' => 10, 'template' => '#1']]));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\bad{x}', false, ['bad' => ['arity' => 1]]));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\bad{x}', false, ['bad' => ['arity' => 0, 'template' => '#1', 'optionalDefault' => 'fallback']]));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\bad{x}', false, ['bad' => ['arity' => 1, 'template' => '#1', 'optionalDefault' => ['fallback']]]));
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
    'converts bounded tex ceiling floor bracket and norm delimiters to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $plainMathml = $converter->texToMathMl('\\lceil x/y \\rceil + \\lfloor n/2 \\rfloor + \\lbrack p_i \\rbrack', true);
        $fencedMathml = $converter->texToMathMl('\\left\\lVert p_i + m_i \\right\\rVert + \\left\\lceil \\frac{x}{y} \\right\\rfloor');
        $sizedMathml = $converter->texToMathMl('\\Bigl\\lVert v \\Bigr\\rVert + \\bigl\\lbrack x \\bigr\\rbrack');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\lceil x \\rceil + \\lfloor y \\rfloor');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $plainMathml);
        $t->contains('<mo>⌈</mo><mi>x</mi><mo>/</mo><mi>y</mi><mo>⌉</mo><mo>+</mo><mo>⌊</mo><mi>n</mi><mo>/</mo><mn>2</mn><mo>⌋</mo>', $plainMathml);
        $t->contains('<mo>[</mo><msub><mi>p</mi><mi>i</mi></msub><mo>]</mo>', $plainMathml);
        $t->contains('<annotation encoding="application/x-tex">\\lceil x/y \\rceil + \\lfloor n/2 \\rfloor + \\lbrack p_i \\rbrack</annotation>', $plainMathml);
        $t->contains('<mo fence="true" stretchy="true">‖</mo><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo fence="true" stretchy="true">‖</mo>', $fencedMathml);
        $t->contains('<mo fence="true" stretchy="true">⌈</mo><mfrac><mi>x</mi><mi>y</mi></mfrac><mo fence="true" stretchy="true">⌋</mo>', $fencedMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">‖</mo><mi>v</mi><mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">‖</mo>', $sizedMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">[</mo><mi>x</mi><mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">]</mo>', $sizedMathml);
        $t->contains('alttext="left ceiling x right ceiling plus left floor y right floor"', $accessibleMathml);
    },
    'converts bounded tex arrow group and moustache delimiters to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $arrowFenceMathml = $converter->texToMathMl('\\left\\uparrow x_i \\middle\\Updownarrow y_i \\right\\downarrow + \\Bigl\\Uparrow z \\Bigr\\Downarrow', true);
        $groupMathml = $converter->texToMathMl('\\lgroup p_i \\rgroup + \\left\\lmoustache a \\right\\rmoustache + \\arrowvert q \\Arrowvert r \\bracevert');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\left\\uparrow x \\right\\downarrow');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $arrowFenceMathml);
        $t->contains('<mo fence="true" stretchy="true">↑</mo><msub><mi>x</mi><mi>i</mi></msub><mo fence="true" stretchy="true" separator="true">⇕</mo><msub><mi>y</mi><mi>i</mi></msub><mo fence="true" stretchy="true">↓</mo>', $arrowFenceMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">⇑</mo><mi>z</mi><mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">⇓</mo>', $arrowFenceMathml);
        $t->contains('<annotation encoding="application/x-tex">\\left\\uparrow x_i \\middle\\Updownarrow y_i \\right\\downarrow + \\Bigl\\Uparrow z \\Bigr\\Downarrow</annotation>', $arrowFenceMathml);
        $t->contains('<mo>⟮</mo><msub><mi>p</mi><mi>i</mi></msub><mo>⟯</mo>', $groupMathml);
        $t->contains('<mo fence="true" stretchy="true">⎰</mo><mi>a</mi><mo fence="true" stretchy="true">⎱</mo>', $groupMathml);
        $t->contains('<mo>|</mo><mi>q</mi><mo>‖</mo><mi>r</mi><mo>⎪</mo>', $groupMathml);
        $t->contains('alttext="up arrow x down arrow"', $accessibleMathml);
    },
    'converts bounded tex sized delimiter commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $sizedMathml = $converter->texToMathMl('\\bigl( p_i \\bigr) + \\Bigl\\langle m_i \\Bigr\\rangle + \\big|x\\big|', true);
        $middleMathml = $converter->texToMathMl('\\left\\{x \\bigm| x \\in S\\right\\} + \\Bigg/ y \\Bigg/');
        $invisibleMathml = $converter->texToMathMl('\\big. x \\Biggr]');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $sizedMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">(</mo><msub><mi>p</mi><mi>i</mi></msub><mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">)</mo>', $sizedMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">⟨</mo><msub><mi>m</mi><mi>i</mi></msub><mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">⟩</mo>', $sizedMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">|</mo><mi>x</mi><mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">|</mo>', $sizedMathml);
        $t->contains('<annotation encoding="application/x-tex">\\bigl( p_i \\bigr) + \\Bigl\\langle m_i \\Bigr\\rangle + \\big|x\\big|</annotation>', $sizedMathml);
        $t->contains('<mo fence="true" stretchy="true">{</mo><mi>x</mi><mo fence="true" stretchy="true" separator="true" minsize="1.2em" maxsize="1.2em">|</mo><mi>x</mi><mo>∈</mo><mi>S</mi><mo fence="true" stretchy="true">}</mo>', $middleMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="3em" maxsize="3em">/</mo><mi>y</mi><mo fence="true" stretchy="true" minsize="3em" maxsize="3em">/</mo>', $middleMathml);
        $t->contains('<mi>x</mi><mo fence="true" stretchy="true" minsize="3em" maxsize="3em">]</mo>', $invisibleMathml);
    },
    'converts bounded tex middle delimiters to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $middleMathml = $converter->texToMathMl('\\left\\{p_i \\middle| p_i \\in P\\right\\} + \\left\\langle x \\middle/ y \\right\\rangle', true);
        $invisibleLeftMathml = $converter->texToMathMl('\\left. a \\middle\\vert b \\right]');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $middleMathml);
        $t->contains('<mo fence="true" stretchy="true">{</mo><msub><mi>p</mi><mi>i</mi></msub><mo fence="true" stretchy="true" separator="true">|</mo><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi><mo fence="true" stretchy="true">}</mo>', $middleMathml);
        $t->contains('<mo fence="true" stretchy="true">⟨</mo><mi>x</mi><mo fence="true" stretchy="true" separator="true">/</mo><mi>y</mi><mo fence="true" stretchy="true">⟩</mo>', $middleMathml);
        $t->contains('<annotation encoding="application/x-tex">\\left\\{p_i \\middle| p_i \\in P\\right\\} + \\left\\langle x \\middle/ y \\right\\rangle</annotation>', $middleMathml);
        $t->contains('<mi>a</mi><mo fence="true" stretchy="true" separator="true">|</mo><mi>b</mi><mo fence="true" stretchy="true">]</mo>', $invisibleLeftMathml);
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
    'converts bounded tex explicit operator limits to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $limitsMathml = $converter->texToMathMl('\\sum\\limits_{i=1}^{n} p_i + \\lim\\limits_{x \\to 0} f(x) + \\prod\\limits^{N} q', true);
        $nolimitsMathml = $converter->texToMathMl('\\int\\nolimits_{0}^{1} f(x) dx + \\sum\\nolimits_{j} a_j');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $limitsMathml);
        $t->contains('<munderover><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></munderover><msub><mi>p</mi><mi>i</mi></msub>', $limitsMathml);
        $t->contains('<munder><mo>lim</mo><mrow><mi>x</mi><mo>→</mo><mn>0</mn></mrow></munder><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo>', $limitsMathml);
        $t->contains('<mover><mo>∏</mo><mi>N</mi></mover><mi>q</mi>', $limitsMathml);
        $t->contains('<annotation encoding="application/x-tex">\\sum\\limits_{i=1}^{n} p_i + \\lim\\limits_{x \\to 0} f(x) + \\prod\\limits^{N} q</annotation>', $limitsMathml);
        $t->contains('<msubsup><mo>∫</mo><mn>0</mn><mn>1</mn></msubsup><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo><mi>d</mi><mi>x</mi><mo>+</mo><msub><mo>∑</mo><mi>j</mi></msub><msub><mi>a</mi><mi>j</mi></msub>', $nolimitsMathml);
        $t->contains('<annotation encoding="application/x-tex">\\int\\nolimits_{0}^{1} f(x) dx + \\sum\\nolimits_{j} a_j</annotation>', $nolimitsMathml);
    },
    'converts bounded tex starred operator names and displaylimits to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $starredMathml = $converter->texToMathMl('\\operatorname*{argmax}_{p_i \\in P}^{\\text{draft}} f(p_i) + \\operatorname*{limsup}^{n} a_n', true);
        $displayLimitsMathml = $converter->texToMathMl('\\operatorname{median}\\displaylimits_{i=1}^{n} p_i + \\operatorname*{rank}\\nolimits_{j} q_j');
        $plainStarredMathml = $converter->texToMathMl('\\operatorname*{review} + x');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $starredMathml);
        $t->contains('<munderover><mi>argmax</mi><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mrow><mtext>draft</mtext></munderover><mi>f</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo>', $starredMathml);
        $t->contains('<mover><mi>limsup</mi><mi>n</mi></mover><msub><mi>a</mi><mi>n</mi></msub>', $starredMathml);
        $t->contains('<annotation encoding="application/x-tex">\\operatorname*{argmax}_{p_i \\in P}^{\\text{draft}} f(p_i) + \\operatorname*{limsup}^{n} a_n</annotation>', $starredMathml);
        $t->contains('<munderover><mi>median</mi><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></munderover><msub><mi>p</mi><mi>i</mi></msub>', $displayLimitsMathml);
        $t->contains('<msub><mi>rank</mi><mi>j</mi></msub><msub><mi>q</mi><mi>j</mi></msub>', $displayLimitsMathml);
        $t->contains('<annotation encoding="application/x-tex">\\operatorname{median}\\displaylimits_{i=1}^{n} p_i + \\operatorname*{rank}\\nolimits_{j} q_j</annotation>', $displayLimitsMathml);
        $t->contains('<mi>review</mi><mo>+</mo><mi>x</mi>', $plainStarredMathml);
    },
    'converts bounded tex substack limits to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $substackMathml = $converter->texToMathMl('\\sum_{\\substack{i=1 \\\\ i\\ne j}}^{n} a_i + \\lim_{\\substack{x \\to 0 \\\\ x > 0}} f(x)', true);
        $standaloneMathml = $converter->texToMathMl('\\substack{p_i \\\\ m_i}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $substackMathml);
        $t->contains('<msubsup><mo>∑</mo><mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><mi>i</mi><mo>=</mo><mn>1</mn></mtd></mtr><mtr><mtd><mi>i</mi><mo>≠</mo><mi>j</mi></mtd></mtr></mtable><mi>n</mi></msubsup><msub><mi>a</mi><mi>i</mi></msub>', $substackMathml);
        $t->contains('<msub><mo>lim</mo><mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><mi>x</mi><mo>→</mo><mn>0</mn></mtd></mtr><mtr><mtd><mi>x</mi><mo>&gt;</mo><mn>0</mn></mtd></mtr></mtable></msub><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo>', $substackMathml);
        $t->contains('<annotation encoding="application/x-tex">\\sum_{\\substack{i=1 \\\\ i\\ne j}}^{n} a_i + \\lim_{\\substack{x \\to 0 \\\\ x &gt; 0}} f(x)</annotation>', $substackMathml);
        $t->contains('<mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr></mtable>', $standaloneMathml);
    },
    'converts bounded tex ams align gather and split environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $alignMathml = $converter->texToMathMl('\\begin{align}f(x) &= x^2 \\\\ g(x) &= x + 1\\end{align}', true);
        $gatherMathml = $converter->texToMathMl('\\begin{gathered}a+b \\\\ c+d\\end{gathered}');
        $splitMathml = $converter->texToMathMl('\\begin{split}S &= \\sum_{i=1}^{n} p_i \\\\ &= \\frac{a}{b}\\end{split}', true);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $alignMathml);
        $t->contains('<mtable columnalign="right left"><mtr><mtd><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo></mtd><mtd><mo>=</mo><msup><mi>x</mi><mn>2</mn></msup></mtd></mtr><mtr><mtd><mi>g</mi><mo>(</mo><mi>x</mi><mo>)</mo></mtd><mtd><mo>=</mo><mi>x</mi><mo>+</mo><mn>1</mn></mtd></mtr></mtable>', $alignMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{align}f(x) &amp;= x^2 \\\\ g(x) &amp;= x + 1\\end{align}</annotation>', $alignMathml);
        $t->contains('<mtable columnalign="center"><mtr><mtd><mi>a</mi><mo>+</mo><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi><mo>+</mo><mi>d</mi></mtd></mtr></mtable>', $gatherMathml);
        $t->contains('<mtable columnalign="right left"><mtr><mtd><mi>S</mi></mtd><mtd><mo>=</mo><msubsup><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></msubsup><msub><mi>p</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd></mtd><mtd><mo>=</mo><mfrac><mi>a</mi><mi>b</mi></mfrac></mtd></mtr></mtable>', $splitMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{split}S &amp;= \\sum_{i=1}^{n} p_i \\\\ &amp;= \\frac{a}{b}\\end{split}</annotation>', $splitMathml);
    },
    'converts bounded tex alignedat environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $alignedAtMathml = $converter->texToMathMl('\\begin{alignedat}{2}p_i &= m_i & a_i &= b_i \\\\ x &= y & u &= v\\end{alignedat}', true);
        $alignAtMathml = $converter->texToMathMl('\\begin{alignat*}{2}f(x) &= x^2 & g(x) &= x + 1\\end{alignat*}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $alignedAtMathml);
        $t->contains('<mtable columnalign="right left right left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd><msub><mi>a</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>b</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mi>x</mi></mtd><mtd><mo>=</mo><mi>y</mi></mtd><mtd><mi>u</mi></mtd><mtd><mo>=</mo><mi>v</mi></mtd></mtr></mtable>', $alignedAtMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{alignedat}{2}p_i &amp;= m_i &amp; a_i &amp;= b_i \\\\ x &amp;= y &amp; u &amp;= v\\end{alignedat}</annotation>', $alignedAtMathml);
        $t->contains('<mtable columnalign="right left right left"><mtr><mtd><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo></mtd><mtd><mo>=</mo><msup><mi>x</mi><mn>2</mn></msup></mtd><mtd><mi>g</mi><mo>(</mo><mi>x</mi><mo>)</mo></mtd><mtd><mo>=</mo><mi>x</mi><mo>+</mo><mn>1</mn></mtd></mtr></mtable>', $alignAtMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{alignat*}{2}f(x) &amp;= x^2 &amp; g(x) &amp;= x + 1\\end{alignat*}</annotation>', $alignAtMathml);
    },
    'converts bounded tex flalign environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $flalignMathml = $converter->texToMathMl('\\begin{flalign}\\text{source} && p_i &= m_i && \\text{review} \\\\ \\text{target} && x_i &= y_i \\tag{F-1} && \\text{done}\\end{flalign}', true);
        $starredMathml = $converter->texToMathMl('\\begin{flalign*}a &= b & c &= d\\end{flalign*}');
        $document = new AstNode('document', [], [
            new AstNode('math', [
                'text' => '\\begin{flalign}p_i &= m_i \\label{eq:flush-row} & \\text{review}\\end{flalign}',
                'display' => true,
            ]),
        ]);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $flalignMathml);
        $t->contains('<mtable columnalign="left right left right left right"><mtr><mtd><mtext>source</mtext></mtd><mtd></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd></mtd><mtd><mtext>review</mtext></mtd></mtr><mlabeledtr><mtd><mtext>(F-1)</mtext></mtd><mtd><mtext>target</mtext></mtd><mtd></mtd><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>y</mi><mi>i</mi></msub></mtd><mtd></mtd><mtd><mtext>done</mtext></mtd></mlabeledtr></mtable>', $flalignMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{flalign}\\text{source} &amp;&amp; p_i &amp;= m_i &amp;&amp; \\text{review} \\\\ \\text{target} &amp;&amp; x_i &amp;= y_i \\tag{F-1} &amp;&amp; \\text{done}\\end{flalign}</annotation>', $flalignMathml);
        $t->contains('<mtable columnalign="left right left right"><mtr><mtd><mi>a</mi></mtd><mtd><mo>=</mo><mi>b</mi></mtd><mtd><mi>c</mi></mtd><mtd><mo>=</mo><mi>d</mi></mtd></mtr></mtable>', $starredMathml);
        $t->same([
            'eq:flush-row' => [
                'label' => 'eq:flush-row',
                'id' => 'eq:flush-row',
                'reference' => '1',
                'tag' => null,
                'tagStarred' => false,
            ],
        ], $converter->equationReferenceLabelsFromDocument($document));
    },
    'converts bounded tex multline environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $multlineMathml = $converter->texToMathMl('\\begin{multline}p_i + m_i \\\\[.5em] = a_i + b_i \\\\ + \\frac{x}{y}\\end{multline}', true);
        $multlinedMathml = $converter->texToMathMl('\\left(\\begin{multlined}x+y \\\\ z\\end{multlined}\\right)');
        $taggedMultlineMathml = $converter->texToMathMl('\\begin{multline}p_i + m_i \\label{eq:multi-review} \\\\ q_i \\tag{ML}\\end{multline}', true);
        $document = new AstNode('document', [], [
            new AstNode('math', [
                'text' => '\\begin{multline}p_i \\label{eq:multi-auto} \\\\ q_i\\end{multline}',
                'display' => true,
            ]),
        ]);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $multlineMathml);
        $t->contains('<mtable columnalign="center"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mo>=</mo><msub><mi>a</mi><mi>i</mi></msub><mo>+</mo><msub><mi>b</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mo>+</mo><mfrac><mi>x</mi><mi>y</mi></mfrac></mtd></mtr></mtable>', $multlineMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{multline}p_i + m_i \\\\[.5em] = a_i + b_i \\\\ + \\frac{x}{y}\\end{multline}</annotation>', $multlineMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mtable columnalign="center"><mtr><mtd><mi>x</mi><mo>+</mo><mi>y</mi></mtd></mtr><mtr><mtd><mi>z</mi></mtd></mtr></mtable><mo fence="true" stretchy="true">)</mo>', $multlinedMathml);
        $t->contains('<mtable columnalign="center"><mtr id="eq:multi-review"><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mlabeledtr><mtd><mtext>(ML)</mtext></mtd><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd></mlabeledtr></mtable>', $taggedMultlineMathml);
        $t->same([
            'eq:multi-auto' => [
                'label' => 'eq:multi-auto',
                'id' => 'eq:multi-auto',
                'reference' => '1',
                'tag' => null,
                'tagStarred' => false,
            ],
        ], $converter->equationReferenceLabelsFromDocument($document));
    },
    'converts bounded tex equation wrapper environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $equationMathml = $converter->texToMathMl('\\begin{equation}p_i + m_i \\label{eq:wrapped} \\tag{WP-3}\\end{equation}', true);
        $starredMathml = $converter->texToMathMl('\\begin{equation*}\\operatorname{review}(p_i) + \\eqref{eq:wrapped}\\end{equation*}', false, [], [
            'eq:wrapped' => [
                'label' => 'eq:wrapped',
                'reference' => 'WP-3',
                'tag' => 'WP-3',
            ],
        ]);
        $document = new AstNode('document', [], [
            new AstNode('math', [
                'text' => '\\begin{equation}p_i + m_i \\label{eq:wrapped-auto}\\end{equation}',
                'display' => true,
            ]),
            new AstNode('math', [
                'text' => '\\begin{equation*}x_i + y_i \\label{eq:wrapped-star}\\end{equation*}',
                'display' => true,
            ]),
            new AstNode('math', [
                'text' => '\\begin{equation}q_i \\label{eq:wrapped-tag} \\tag*{audit}\\end{equation}',
                'display' => true,
            ]),
        ]);
        $labels = $converter->equationReferenceLabelsFromDocument($document);
        $resolvedMathml = $converter->texToMathMl('\\eqref{eq:wrapped-auto} + \\eqref{eq:wrapped-star} + \\eqref{eq:wrapped-tag}', false, [], $labels);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $equationMathml);
        $t->contains('<mtable><mlabeledtr><mtd><mtext>(WP-3)</mtext></mtd><mtd id="eq:wrapped"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mtd></mlabeledtr></mtable>', $equationMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{equation}p_i + m_i \\label{eq:wrapped} \\tag{WP-3}\\end{equation}</annotation>', $equationMathml);
        $t->contains('<mi>review</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:wrapped">WP-3</mtext><mo>)</mo></mrow>', $starredMathml);
        $t->same([
            'eq:wrapped-auto' => [
                'label' => 'eq:wrapped-auto',
                'id' => 'eq:wrapped-auto',
                'reference' => '1',
                'tag' => null,
                'tagStarred' => false,
            ],
            'eq:wrapped-star' => [
                'label' => 'eq:wrapped-star',
                'id' => 'eq:wrapped-star',
                'reference' => 'eq:wrapped-star',
                'tag' => null,
                'tagStarred' => false,
            ],
            'eq:wrapped-tag' => [
                'label' => 'eq:wrapped-tag',
                'id' => 'eq:wrapped-tag',
                'reference' => 'audit',
                'tag' => 'audit',
                'tagStarred' => true,
            ],
        ], $labels);
        $t->contains('<mrow><mo>(</mo><mtext href="#eq:wrapped-auto">1</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:wrapped-star">eq:wrapped-star</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:wrapped-tag">audit</mtext><mo>)</mo></mrow>', $resolvedMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{equation}\\end{equation}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{equation}a & b\\end{equation}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{equation}a \\\\ b\\end{equation}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{equation}\\label{eq:empty}\\end{equation}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{equation}a \\tag{}\\end{equation}'));
    },
    'converts bounded tex ams row tags and labels to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $taggedAlignMathml = $converter->texToMathMl('\\begin{align}p_i &= m_i \\tag{WP-1} \\\\ x_i &= y_i \\label{eq:row-review} \\tag*{review}\\end{align}', true);
        $taggedAlignAtMathml = $converter->texToMathMl('\\begin{alignat}{2}a &= b & c &= d \\tag{A}\\\\ u &= v & w &= z\\end{alignat}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $taggedAlignMathml);
        $t->contains('<mtable columnalign="right left"><mlabeledtr><mtd><mtext>(WP-1)</mtext></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mlabeledtr><mlabeledtr id="eq:row-review"><mtd><mtext>review</mtext></mtd><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>y</mi><mi>i</mi></msub></mtd></mlabeledtr></mtable>', $taggedAlignMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{align}p_i &amp;= m_i \\tag{WP-1} \\\\ x_i &amp;= y_i \\label{eq:row-review} \\tag*{review}\\end{align}</annotation>', $taggedAlignMathml);
        $t->contains('<mtable columnalign="right left right left"><mlabeledtr><mtd><mtext>(A)</mtext></mtd><mtd><mi>a</mi></mtd><mtd><mo>=</mo><mi>b</mi></mtd><mtd><mi>c</mi></mtd><mtd><mo>=</mo><mi>d</mi></mtd></mlabeledtr><mtr><mtd><mi>u</mi></mtd><mtd><mo>=</mo><mi>v</mi></mtd><mtd><mi>w</mi></mtd><mtd><mo>=</mo><mi>z</mi></mtd></mtr></mtable>', $taggedAlignAtMathml);
    },
    'converts bounded tex spacing commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $punctuationSpacingMathml = $converter->texToMathMl('p_i\\,m_i\\;n_i\\!q_i + a\\quad b\\qquad c', true);
        $namedSpacingMathml = $converter->texToMathMl('\\operatorname{post}\\thinspace\\operatorname{media}\\negthinspace\\operatorname{review} + x\\medspace y\\thickspace z');
        $mediumSpacingMathml = $converter->texToMathMl('a\\:b\\>c\\enspace d\\negmedspace e\\negthickspace f');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $punctuationSpacingMathml);
        $t->contains('<msub><mi>p</mi><mi>i</mi></msub><mspace width="0.1667em"></mspace><msub><mi>m</mi><mi>i</mi></msub><mspace width="0.2778em"></mspace><msub><mi>n</mi><mi>i</mi></msub><mspace width="-0.1667em"></mspace><msub><mi>q</mi><mi>i</mi></msub>', $punctuationSpacingMathml);
        $t->contains('<mi>a</mi><mspace width="1em"></mspace><mi>b</mi><mspace width="2em"></mspace><mi>c</mi>', $punctuationSpacingMathml);
        $t->contains('<annotation encoding="application/x-tex">p_i\\,m_i\\;n_i\\!q_i + a\\quad b\\qquad c</annotation>', $punctuationSpacingMathml);
        $t->contains('<mi>post</mi><mspace width="0.1667em"></mspace><mi>media</mi><mspace width="-0.1667em"></mspace><mi>review</mi>', $namedSpacingMathml);
        $t->contains('<mi>x</mi><mspace width="0.2222em"></mspace><mi>y</mi><mspace width="0.2778em"></mspace><mi>z</mi>', $namedSpacingMathml);
        $t->contains('<mi>a</mi><mspace width="0.2222em"></mspace><mi>b</mi><mspace width="0.2222em"></mspace><mi>c</mi><mspace width="0.5em"></mspace><mi>d</mi><mspace width="-0.2222em"></mspace><mi>e</mi><mspace width="-0.2778em"></mspace><mi>f</mi>', $mediumSpacingMathml);
    },
    'converts bounded tex explicit hspace and mspace dimensions to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $explicitMathml = $converter->texToMathMl('p_i\\hspace{1.5em}m_i\\mspace{-2mu}q_i + a\\hspace*{.25in}b', true);
        $metricMathml = $converter->texToMathMl('x\\hspace{12pt}y\\mspace{0em}z');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $explicitMathml);
        $t->contains('<msub><mi>p</mi><mi>i</mi></msub><mspace width="1.5em"></mspace><msub><mi>m</mi><mi>i</mi></msub><mspace width="-2mu"></mspace><msub><mi>q</mi><mi>i</mi></msub>', $explicitMathml);
        $t->contains('<mi>a</mi><mspace width=".25in" linebreak="nobreak"></mspace><mi>b</mi>', $explicitMathml);
        $t->contains('<annotation encoding="application/x-tex">p_i\\hspace{1.5em}m_i\\mspace{-2mu}q_i + a\\hspace*{.25in}b</annotation>', $explicitMathml);
        $t->contains('<mi>x</mi><mspace width="12pt"></mspace><mi>y</mi><mspace width="0em"></mspace><mi>z</mi>', $metricMathml);
    },
    'converts bounded tex equation tags and labels to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $taggedMathml = $converter->texToMathMl('p_i + m_i \\label{eq:review-flow} \\tag{WP-2}', true);
        $starredTagMathml = $converter->texToMathMl('p_i + m_i \\tag*{review}');
        $labelOnlyMathml = $converter->texToMathMl('\\label{eq:plain}x_i + y_i');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $taggedMathml);
        $t->contains('<mtable><mlabeledtr><mtd><mtext>(WP-2)</mtext></mtd><mtd id="eq:review-flow"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mtd></mlabeledtr></mtable>', $taggedMathml);
        $t->contains('<annotation encoding="application/x-tex">p_i + m_i \\label{eq:review-flow} \\tag{WP-2}</annotation><annotation encoding="application/x-tex-label">eq:review-flow</annotation>', $taggedMathml);
        $t->contains('<mtext>review</mtext></mtd><mtd><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mtd>', $starredTagMathml);
        $t->contains('<mrow id="eq:plain"><msub><mi>x</mi><mi>i</mi></msub><mo>+</mo><msub><mi>y</mi><mi>i</mi></msub></mrow>', $labelOnlyMathml);
    },
    'converts bounded tex equation references to mathml handoff links' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $referenceMathml = $converter->texToMathMl('\\label{eq:plain}x_i + \\eqref{eq:plain} + \\ref{review row/2}', true);
        $rowReferenceMathml = $converter->texToMathMl('\\begin{align}p_i &= m_i \\label{eq:row-review}\\end{align} + \\eqref{eq:row-review}');

        $t->contains('<mrow id="eq:plain"><msub><mi>x</mi><mi>i</mi></msub><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:plain">eq:plain</mtext><mo>)</mo></mrow><mo>+</mo><mtext href="#review-row-2">review row/2</mtext></mrow>', $referenceMathml);
        $t->contains('<annotation encoding="application/x-tex">\\label{eq:plain}x_i + \\eqref{eq:plain} + \\ref{review row/2}</annotation><annotation encoding="application/x-tex-label">eq:plain</annotation>', $referenceMathml);
        $t->contains('<mtr id="eq:row-review"><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr></mtable><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:row-review">eq:row-review</mtext><mo>)</mo></mrow>', $rowReferenceMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\ref{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\eqref{###}'));
    },
    'resolves bounded tex equation references through a document label map' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('math', [
                    'text' => 'p_i + m_i \\label{eq:review-flow} \\tag{WP-2}',
                    'display' => true,
                ]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('math', [
                    'text' => '\\begin{align}x_i &= y_i \\label{review row/2} \\tag*{review} \\\\ u_i &= v_i\\end{align}',
                    'display' => true,
                ]),
            ]),
        ]);
        $labels = $converter->equationReferenceLabelsFromDocument($document);
        $resolvedMathml = $converter->texToMathMl('\\eqref{eq:review-flow} + \\ref{review row/2} + \\eqref{missing}', false, [], $labels);
        $nodeMathml = $converter->mathMlFor(new AstNode('math', [
            'text' => '\\eqref{eq:review-flow} + \\eqref{review row/2}',
            'display' => false,
        ]), [], $labels);

        $t->same([
            'eq:review-flow' => [
                'label' => 'eq:review-flow',
                'id' => 'eq:review-flow',
                'reference' => 'WP-2',
                'tag' => 'WP-2',
                'tagStarred' => false,
            ],
            'review-row-2' => [
                'label' => 'review row/2',
                'id' => 'review-row-2',
                'reference' => 'review',
                'tag' => 'review',
                'tagStarred' => true,
            ],
        ], $labels);
        $t->contains('<mrow><mo>(</mo><mtext href="#eq:review-flow">WP-2</mtext><mo>)</mo></mrow><mo>+</mo><mtext href="#review-row-2">review</mtext><mo>+</mo><mrow><mo>(</mo><mtext href="#missing">missing</mtext><mo>)</mo></mrow>', $resolvedMathml);
        $t->contains('<annotation encoding="application/x-tex">\\eqref{eq:review-flow} + \\ref{review row/2} + \\eqref{missing}</annotation>', $resolvedMathml);
        $t->contains('<mrow><mo>(</mo><mtext href="#eq:review-flow">WP-2</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#review-row-2">review</mtext><mo>)</mo></mrow>', $nodeMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): array => $converter->equationReferenceLabelsFromDocument(new AstNode('document', [], [
            new AstNode('math', ['text' => 'x \\label{eq:dup}', 'display' => true]),
            new AstNode('math', ['text' => 'y \\label{eq:dup}', 'display' => true]),
        ])));
    },
    'resolves bounded automatic numbers for untagged display equation references' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('math', [
                    'text' => 'p_i + m_i \\label{eq:first}',
                    'display' => true,
                ]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('math', [
                    'text' => 'inline \\label{eq:inline}',
                    'display' => false,
                ]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('math', [
                    'text' => '\\begin{align}x_i &= y_i \\label{eq:row-one} \\\\ u_i &= v_i \\tag{manual}\\end{align}',
                    'display' => true,
                ]),
            ]),
        ]);

        $labels = $converter->equationReferenceLabelsFromDocument($document);
        $resolvedMathml = $converter->texToMathMl('\\eqref{eq:first} + \\eqref{eq:inline} + \\eqref{eq:row-one} + \\eqref{eq:missing}', false, [], $labels);

        $t->same([
            'eq:first' => [
                'label' => 'eq:first',
                'id' => 'eq:first',
                'reference' => '1',
                'tag' => null,
                'tagStarred' => false,
            ],
            'eq:inline' => [
                'label' => 'eq:inline',
                'id' => 'eq:inline',
                'reference' => 'eq:inline',
                'tag' => null,
                'tagStarred' => false,
            ],
            'eq:row-one' => [
                'label' => 'eq:row-one',
                'id' => 'eq:row-one',
                'reference' => '2',
                'tag' => null,
                'tagStarred' => false,
            ],
        ], $labels);
        $t->contains('<mrow><mo>(</mo><mtext href="#eq:first">1</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:inline">eq:inline</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:row-one">2</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:missing">eq:missing</mtext><mo>)</mo></mrow>', $resolvedMathml);
        $t->contains('<annotation encoding="application/x-tex">\\eqref{eq:first} + \\eqref{eq:inline} + \\eqref{eq:row-one} + \\eqref{eq:missing}</annotation>', $resolvedMathml);
    },
    'converts bounded tex notag and nonumber row suppression to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $suppressedMathml = $converter->texToMathMl('\\begin{align}p_i &= m_i \\notag \\\\ x_i &= y_i \\nonumber \\\\ u_i &= v_i \\label{eq:counted}\\end{align}', true);
        $taggedSuppressedMathml = $converter->texToMathMl('\\begin{align}a &= b \\notag \\tag{manual} \\\\ c &= d \\nonumber\\end{align}', true);
        $document = new AstNode('document', [], [
            new AstNode('math', [
                'text' => '\\begin{align}p_i &= m_i \\label{eq:suppressed} \\notag \\\\ x_i &= y_i \\label{eq:counted}\\end{align}',
                'display' => true,
            ]),
        ]);
        $labels = $converter->equationReferenceLabelsFromDocument($document);
        $resolvedMathml = $converter->texToMathMl('\\eqref{eq:suppressed} + \\eqref{eq:counted}', false, [], $labels);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $suppressedMathml);
        $t->contains('<mtable columnalign="right left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>y</mi><mi>i</mi></msub></mtd></mtr><mtr id="eq:counted"><mtd><msub><mi>u</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>v</mi><mi>i</mi></msub></mtd></mtr></mtable>', $suppressedMathml);
        $t->true(!str_contains($suppressedMathml, '<mi>\\notag</mi>'), 'Expected \\notag to be stripped from row MathML');
        $t->true(!str_contains($suppressedMathml, '<mi>\\nonumber</mi>'), 'Expected \\nonumber to be stripped from row MathML');
        $t->contains('<annotation encoding="application/x-tex">\\begin{align}p_i &amp;= m_i \\notag \\\\ x_i &amp;= y_i \\nonumber \\\\ u_i &amp;= v_i \\label{eq:counted}\\end{align}</annotation>', $suppressedMathml);
        $t->contains('<mlabeledtr><mtd><mtext>(manual)</mtext></mtd><mtd><mi>a</mi></mtd><mtd><mo>=</mo><mi>b</mi></mtd></mlabeledtr><mtr><mtd><mi>c</mi></mtd><mtd><mo>=</mo><mi>d</mi></mtd></mtr>', $taggedSuppressedMathml);
        $t->same([
            'eq:suppressed' => [
                'label' => 'eq:suppressed',
                'id' => 'eq:suppressed',
                'reference' => 'eq:suppressed',
                'tag' => null,
                'tagStarred' => false,
            ],
            'eq:counted' => [
                'label' => 'eq:counted',
                'id' => 'eq:counted',
                'reference' => '1',
                'tag' => null,
                'tagStarred' => false,
            ],
        ], $labels);
        $t->contains('<mrow><mo>(</mo><mtext href="#eq:suppressed">eq:suppressed</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:counted">1</mtext><mo>)</mo></mrow>', $resolvedMathml);
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
    'converts bounded tex not relation overlays to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $notRelationMathml = $converter->texToMathMl('p_i \\not\\in P + a \\not= b + x \\not\\leq y + A \\not\\subseteq B + f \\not\\approx g + q \\not\\Rightarrow r', true);
        $notTokenMathml = $converter->texToMathMl('x \\not< y + y \\not> z + A \\not\\leftrightarrow B');
        $fallbackMathml = $converter->texToMathMl('\\not\\alpha_i + \\not{p_i + m_i}');
        $accessibleMathml = $converter->texToAccessibleMathMl('p_i \\not\\in P + a \\not= b', false);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $notRelationMathml);
        $t->contains('<msub><mi>p</mi><mi>i</mi></msub><mo>∉</mo><mi>P</mi><mo>+</mo><mi>a</mi><mo>≠</mo><mi>b</mi><mo>+</mo><mi>x</mi><mo>≰</mo><mi>y</mi>', $notRelationMathml);
        $t->contains('<mi>A</mi><mo>⊈</mo><mi>B</mi><mo>+</mo><mi>f</mi><mo>≉</mo><mi>g</mi><mo>+</mo><mi>q</mi><mo>⇏</mo><mi>r</mi>', $notRelationMathml);
        $t->contains('<annotation encoding="application/x-tex">p_i \\not\\in P + a \\not= b + x \\not\\leq y + A \\not\\subseteq B + f \\not\\approx g + q \\not\\Rightarrow r</annotation>', $notRelationMathml);
        $t->contains('<mi>x</mi><mo>≮</mo><mi>y</mi><mo>+</mo><mi>y</mi><mo>≯</mo><mi>z</mi><mo>+</mo><mi>A</mi><mo>↮</mo><mi>B</mi>', $notTokenMathml);
        $t->contains('<msub><menclose notation="updiagonalstrike"><mi>α</mi></menclose><mi>i</mi></msub><mo>+</mo><menclose notation="updiagonalstrike"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></menclose>', $fallbackMathml);
        $t->contains('alttext="p sub i not in P plus a not equal b"', $accessibleMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\not'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\not_1'));
    },
    'converts bounded tex prime shorthand and prime commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $primeMathml = $converter->texToMathMl("f'(x) + g''_i + h_i''' + x^{2}'", true);
        $commandMathml = $converter->texToMathMl('\\partial^\\prime f + y^\\backprime + z^{\\prime\\prime}');
        $accessibleMathml = $converter->texToAccessibleMathMl("f'(x) + g''_i");

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $primeMathml);
        $t->contains('<msup><mi>f</mi><mo>′</mo></msup><mo>(</mo><mi>x</mi><mo>)</mo>', $primeMathml);
        $t->contains('<msubsup><mi>g</mi><mi>i</mi><mo>″</mo></msubsup>', $primeMathml);
        $t->contains('<msubsup><mi>h</mi><mi>i</mi><mo>‴</mo></msubsup>', $primeMathml);
        $t->contains('<msup><mi>x</mi><mrow><mn>2</mn><mo>′</mo></mrow></msup>', $primeMathml);
        $t->contains('<annotation encoding="application/x-tex">f&#039;(x) + g&#039;&#039;_i + h_i&#039;&#039;&#039; + x^{2}&#039;</annotation>', $primeMathml);
        $t->contains('<msup><mo>∂</mo><mo>′</mo></msup><mi>f</mi><mo>+</mo><msup><mi>y</mi><mo>‵</mo></msup><mo>+</mo><msup><mi>z</mi><mrow><mo>′</mo><mo>′</mo></mrow></msup>', $commandMathml);
        $t->contains('alttext="f superscript prime left parenthesis x right parenthesis plus g sub i superscript double prime"', $accessibleMathml);
        $t->contains('intent="row(superscript(f,prime),left_parenthesis,x,right_parenthesis,plus,subsup(g,i,double_prime))"', $accessibleMathml);
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
    'converts bounded tex extensible arrows to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $xArrowMathml = $converter->texToMathMl('\\xrightarrow[\\text{review}]{\\operatorname{publish}} p_i + \\xleftarrow{draft} m_i', true);
        $mapArrowMathml = $converter->texToMathMl('A \\xleftrightarrow[n+1]{\\text{sync}} B + C \\xmapsto{f} D');
        $accentArrowMathml = $converter->texToMathMl('\\overrightarrow{AB}_i + \\underleftarrow{\\operatorname{media}} + \\overleftrightarrow{x+y}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $xArrowMathml);
        $t->contains('<munderover><mo stretchy="true">→</mo><mtext>review</mtext><mi>publish</mi></munderover><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><mover><mo stretchy="true">←</mo><mrow><mi>d</mi><mi>r</mi><mi>a</mi><mi>f</mi><mi>t</mi></mrow></mover><msub><mi>m</mi><mi>i</mi></msub>', $xArrowMathml);
        $t->contains('<annotation encoding="application/x-tex">\\xrightarrow[\\text{review}]{\\operatorname{publish}} p_i + \\xleftarrow{draft} m_i</annotation>', $xArrowMathml);
        $t->contains('<mi>A</mi><munderover><mo stretchy="true">↔</mo><mrow><mi>n</mi><mo>+</mo><mn>1</mn></mrow><mtext>sync</mtext></munderover><mi>B</mi><mo>+</mo><mi>C</mi><mover><mo stretchy="true">↦</mo><mi>f</mi></mover><mi>D</mi>', $mapArrowMathml);
        $t->contains('<msub><mover accent="true"><mrow><mi>A</mi><mi>B</mi></mrow><mo stretchy="true">→</mo></mover><mi>i</mi></msub>', $accentArrowMathml);
        $t->contains('<munder accentunder="true"><mi>media</mi><mo stretchy="true">←</mo></munder><mo>+</mo><mover accent="true"><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow><mo stretchy="true">↔</mo></mover>', $accentArrowMathml);
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
    'converts bounded tex cases environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $casesMathml = $converter->texToMathMl('\\begin{cases}p_i & p_i \\in P \\\\ 0 & \\text{otherwise}\\end{cases}', true);
        $nestedCasesMathml = $converter->texToMathMl('\\begin{cases}\\frac{a}{b} & a \\ge b \\\\ \\sqrt[n]{x} & a < b\\end{cases}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $casesMathml);
        $t->contains('<mo fence="true" stretchy="true">{</mo><mtable columnalign="left left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mtd></mtr><mtr><mtd><mn>0</mn></mtd><mtd><mtext>otherwise</mtext></mtd></mtr></mtable>', $casesMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{cases}p_i &amp; p_i \\in P \\\\ 0 &amp; \\text{otherwise}\\end{cases}</annotation>', $casesMathml);
        $t->contains('<mo fence="true" stretchy="true">{</mo><mtable columnalign="left left"><mtr><mtd><mfrac><mi>a</mi><mi>b</mi></mfrac></mtd><mtd><mi>a</mi><mo>≥</mo><mi>b</mi></mtd></mtr><mtr><mtd><mroot><mi>x</mi><mi>n</mi></mroot></mtd><mtd><mi>a</mi><mo>&lt;</mo><mi>b</mi></mtd></mtr></mtable>', $nestedCasesMathml);
    },
    'converts bounded tex array environments with column specs to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $arrayMathml = $converter->texToMathMl('\\begin{array}{rl}x_i &= p_i \\\\ y_i &= \\frac{a_i}{b_i}\\end{array}', true);
        $ruledArrayMathml = $converter->texToMathMl('\\begin{array}{l|c|r}\\alpha & \\beta & \\omega \\\\ 1 & 2 & 3\\end{array}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $arrayMathml);
        $t->contains('<mtable columnalign="right left"><mtr><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>p</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>y</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><mfrac><msub><mi>a</mi><mi>i</mi></msub><msub><mi>b</mi><mi>i</mi></msub></mfrac></mtd></mtr></mtable>', $arrayMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{array}{rl}x_i &amp;= p_i \\\\ y_i &amp;= \\frac{a_i}{b_i}\\end{array}</annotation>', $arrayMathml);
        $t->contains('<mtable columnalign="left center right" columnlines="solid solid"><mtr><mtd><mi>α</mi></mtd><mtd><mi>β</mi></mtd><mtd><mi>ω</mi></mtd></mtr><mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd><mtd><mn>3</mn></mtd></mtr></mtable>', $ruledArrayMathml);
    },
    'converts bounded tex array rule commands to mathml metadata' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $ruleMathml = $converter->texToMathMl('\\begin{array}{l|c|r}\\hline p_i & m_i & 1 \\\\ \\hline q_i & n_i & 2 \\\\ \\hline\\end{array}', true);
        $sparseRulesMathml = $converter->texToMathMl('\\begin{array}{lc|r}a & b & c \\\\ x & y & z\\end{array}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $ruleMathml);
        $t->contains('<mtable columnalign="left center right" columnlines="solid solid" data-tex-topline="solid" rowlines="solid" data-tex-bottomline="solid"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd><mn>1</mn></mtd></mtr><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><msub><mi>n</mi><mi>i</mi></msub></mtd><mtd><mn>2</mn></mtd></mtr></mtable>', $ruleMathml);
        $t->true(!str_contains($ruleMathml, '<mi>\\hline</mi>'), 'Expected TeX \\hline to become array rule metadata');
        $t->contains('<annotation encoding="application/x-tex">\\begin{array}{l|c|r}\\hline p_i &amp; m_i &amp; 1 \\\\ \\hline q_i &amp; n_i &amp; 2 \\\\ \\hline\\end{array}</annotation>', $ruleMathml);
        $t->contains('<mtable columnalign="left center right" columnlines="none solid"><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd><mtd><mi>c</mi></mtd></mtr><mtr><mtd><mi>x</mi></mtd><mtd><mi>y</mi></mtd><mtd><mi>z</mi></mtd></mtr></mtable>', $sparseRulesMathml);
    },
    'converts bounded tex array cline commands to partial rule metadata' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $clineMathml = $converter->texToMathMl('\\begin{array}{l|c|r}p_i & m_i & 1 \\\\ \\cline{2-3} q_i & n_i & 2 \\\\ \\cline{1-1}\\cline{3-3} r_i & s_i & 3\\end{array}', true);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $clineMathml);
        $t->contains('<mtable columnalign="left center right" columnlines="solid solid" data-tex-clines="after-row-1:2-3 after-row-2:1-1,3-3"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd><mn>1</mn></mtd></mtr><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><msub><mi>n</mi><mi>i</mi></msub></mtd><mtd><mn>2</mn></mtd></mtr><mtr><mtd><msub><mi>r</mi><mi>i</mi></msub></mtd><mtd><msub><mi>s</mi><mi>i</mi></msub></mtd><mtd><mn>3</mn></mtd></mtr></mtable>', $clineMathml);
        $t->true(!str_contains($clineMathml, '<mi>\\cline</mi>'), 'Expected TeX \\cline to become array partial-rule metadata');
        $t->contains('<annotation encoding="application/x-tex">\\begin{array}{l|c|r}p_i &amp; m_i &amp; 1 \\\\ \\cline{2-3} q_i &amp; n_i &amp; 2 \\\\ \\cline{1-1}\\cline{3-3} r_i &amp; s_i &amp; 3\\end{array}</annotation>', $clineMathml);
    },
    'converts bounded tex compact matrix and subarray environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $compactMathml = $converter->texToMathMl('\\left(\\begin{smallmatrix}p_1 & m_1 \\\\ p_2 & m_2\\end{smallmatrix}\\right) + \\sum_{\\begin{subarray}{c}i=1 \\\\ i\\ne j\\end{subarray}}^{n} a_i', true);
        $alignedSubarrayMathml = $converter->texToMathMl('\\prod_{\\begin{subarray}{r} k \\to \\infty \\\\ k > 0 \\end{subarray}} k');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $compactMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mstyle scriptlevel="1"><mtable rowspacing="0.1em" columnspacing="0.2778em"><mtr><mtd><msub><mi>p</mi><mn>1</mn></msub></mtd><mtd><msub><mi>m</mi><mn>1</mn></msub></mtd></mtr><mtr><mtd><msub><mi>p</mi><mn>2</mn></msub></mtd><mtd><msub><mi>m</mi><mn>2</mn></msub></mtd></mtr></mtable></mstyle><mo fence="true" stretchy="true">)</mo>', $compactMathml);
        $t->contains('<msubsup><mo>∑</mo><mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><mi>i</mi><mo>=</mo><mn>1</mn></mtd></mtr><mtr><mtd><mi>i</mi><mo>≠</mo><mi>j</mi></mtd></mtr></mtable><mi>n</mi></msubsup><msub><mi>a</mi><mi>i</mi></msub>', $compactMathml);
        $t->contains('<annotation encoding="application/x-tex">\\left(\\begin{smallmatrix}p_1 &amp; m_1 \\\\ p_2 &amp; m_2\\end{smallmatrix}\\right) + \\sum_{\\begin{subarray}{c}i=1 \\\\ i\\ne j\\end{subarray}}^{n} a_i</annotation>', $compactMathml);
        $t->contains('<msub><mo>∏</mo><mtable columnalign="right" rowspacing="0.1em"><mtr><mtd><mi>k</mi><mo>→</mo><mi>∞</mi></mtd></mtr><mtr><mtd><mi>k</mi><mo>&gt;</mo><mn>0</mn></mtd></mtr></mtable></msub><mi>k</mi>', $alignedSubarrayMathml);
    },
    'converts bounded tex above below and style wrappers to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $aboveBelowMathml = $converter->texToMathMl('\\overset{\\text{new}}{p_i} + \\underset{0}{\\lim}_{n \\to \\infty} a_n', true);
        $braceMathml = $converter->texToMathMl('\\overbrace{x + y}^{\\text{sum}} + \\underbrace{m_i}_{\\text{media}}');
        $styleMathml = $converter->texToMathMl('\\displaystyle \\frac{a}{b} + \\textstyle c + \\scriptstyle d_i + \\scriptscriptstyle e');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $aboveBelowMathml);
        $t->contains('<mover><msub><mi>p</mi><mi>i</mi></msub><mtext>new</mtext></mover>', $aboveBelowMathml);
        $t->contains('<msub><munder><mo>lim</mo><mn>0</mn></munder><mrow><mi>n</mi><mo>→</mo><mi>∞</mi></mrow></msub><msub><mi>a</mi><mi>n</mi></msub>', $aboveBelowMathml);
        $t->contains('<annotation encoding="application/x-tex">\\overset{\\text{new}}{p_i} + \\underset{0}{\\lim}_{n \\to \\infty} a_n</annotation>', $aboveBelowMathml);
        $t->contains('<msup><mover><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow><mo>⏞</mo></mover><mtext>sum</mtext></msup>', $braceMathml);
        $t->contains('<msub><munder><msub><mi>m</mi><mi>i</mi></msub><mo>⏟</mo></munder><mtext>media</mtext></msub>', $braceMathml);
        $t->contains('<mstyle displaystyle="true"><mfrac><mi>a</mi><mi>b</mi></mfrac></mstyle><mo>+</mo><mstyle displaystyle="false"><mi>c</mi></mstyle>', $styleMathml);
        $t->contains('<mstyle scriptlevel="1"><msub><mi>d</mi><mi>i</mi></msub></mstyle><mo>+</mo><mstyle scriptlevel="2"><mi>e</mi></mstyle>', $styleMathml);
    },
    'converts bounded tex color phantom and cancel commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $colorMathml = $converter->texToMathMl('\\color{red}{p_i} + \\textcolor{#336699}{\\operatorname{media}} + \\color{review-blue}{x+y}', true);
        $phantomMathml = $converter->texToMathMl('\\phantom{p_i + m_i} + \\hphantom{draft} + \\vphantom{\\frac{a}{b}}');
        $cancelMathml = $converter->texToMathMl('\\cancel{x_i} + \\bcancel{y_i} + \\xcancel{z_i}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $colorMathml);
        $t->contains('<mstyle mathcolor="red"><msub><mi>p</mi><mi>i</mi></msub></mstyle>', $colorMathml);
        $t->contains('<mstyle mathcolor="#336699"><mi>media</mi></mstyle>', $colorMathml);
        $t->contains('<mstyle mathcolor="review-blue"><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow></mstyle>', $colorMathml);
        $t->contains('<annotation encoding="application/x-tex">\\color{red}{p_i} + \\textcolor{#336699}{\\operatorname{media}} + \\color{review-blue}{x+y}</annotation>', $colorMathml);
        $t->contains('<mphantom><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mphantom>', $phantomMathml);
        $t->contains('<mpadded height="0" depth="0"><mphantom><mrow><mi>d</mi><mi>r</mi><mi>a</mi><mi>f</mi><mi>t</mi></mrow></mphantom></mpadded>', $phantomMathml);
        $t->contains('<mpadded width="0"><mphantom><mfrac><mi>a</mi><mi>b</mi></mfrac></mphantom></mpadded>', $phantomMathml);
        $t->contains('<menclose notation="updiagonalstrike"><msub><mi>x</mi><mi>i</mi></msub></menclose>', $cancelMathml);
        $t->contains('<menclose notation="downdiagonalstrike"><msub><mi>y</mi><mi>i</mi></msub></menclose>', $cancelMathml);
        $t->contains('<menclose notation="updiagonalstrike downdiagonalstrike"><msub><mi>z</mi><mi>i</mi></msub></menclose>', $cancelMathml);
    },
    'converts bounded tex smash and overlap boxes to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $smashMathml = $converter->texToMathMl('\\smash{\\frac{a}{b}} + \\smash[t]{p_i} + \\smash[b]{m_i}', true);
        $overlapMathml = $converter->texToMathMl('\\mathllap{p_i} + \\mathrlap{m_i} + \\mathclap{x+y} + \\llap{L} + \\rlap{R} + \\clap{C}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $smashMathml);
        $t->contains('<mpadded height="0" depth="0"><mfrac><mi>a</mi><mi>b</mi></mfrac></mpadded>', $smashMathml);
        $t->contains('<mpadded height="0"><msub><mi>p</mi><mi>i</mi></msub></mpadded>', $smashMathml);
        $t->contains('<mpadded depth="0"><msub><mi>m</mi><mi>i</mi></msub></mpadded>', $smashMathml);
        $t->contains('<annotation encoding="application/x-tex">\\smash{\\frac{a}{b}} + \\smash[t]{p_i} + \\smash[b]{m_i}</annotation>', $smashMathml);
        $t->contains('<mpadded width="0" lspace="-1width"><msub><mi>p</mi><mi>i</mi></msub></mpadded>', $overlapMathml);
        $t->contains('<mpadded width="0"><msub><mi>m</mi><mi>i</mi></msub></mpadded>', $overlapMathml);
        $t->contains('<mpadded width="0" lspace="-0.5width"><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow></mpadded>', $overlapMathml);
        $t->contains('<mpadded width="0" lspace="-1width"><mi>L</mi></mpadded><mo>+</mo><mpadded width="0"><mi>R</mi></mpadded><mo>+</mo><mpadded width="0" lspace="-0.5width"><mi>C</mi></mpadded>', $overlapMathml);
        $t->contains('<annotation encoding="application/x-tex">\\mathllap{p_i} + \\mathrlap{m_i} + \\mathclap{x+y} + \\llap{L} + \\rlap{R} + \\clap{C}</annotation>', $overlapMathml);
    },
    'converts bounded tex cancelto target annotations to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $cancelToMathml = $converter->texToMathMl('\\cancelto{0}{x_i} + \\cancelto{\\text{draft}}{\\frac{a}{b}}', true);
        $scriptedMathml = $converter->texToMathMl('\\cancelto{n+1}{\\operatorname{media}_i}_j');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $cancelToMathml);
        $t->contains('<mover><menclose notation="updiagonalstrike"><msub><mi>x</mi><mi>i</mi></msub></menclose><mn>0</mn></mover>', $cancelToMathml);
        $t->contains('<mover><menclose notation="updiagonalstrike"><mfrac><mi>a</mi><mi>b</mi></mfrac></menclose><mtext>draft</mtext></mover>', $cancelToMathml);
        $t->contains('<annotation encoding="application/x-tex">\\cancelto{0}{x_i} + \\cancelto{\\text{draft}}{\\frac{a}{b}}</annotation>', $cancelToMathml);
        $t->contains('<msub><mover><menclose notation="updiagonalstrike"><msub><mi>media</mi><mi>i</mi></msub></menclose><mrow><mi>n</mi><mo>+</mo><mn>1</mn></mrow></mover><mi>j</mi></msub>', $scriptedMathml);
    },
    'converts bounded tex math alphabet variants to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $u = static fn (int $codepoint): string => html_entity_decode('&#x' . strtoupper(dechex($codepoint)) . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $variantMathml = $converter->texToMathMl('\\mathrm{d}x + \\mathbf{v_i} + \\mathit{n} + \\mathsf{S} + \\mathtt{code}', true);
        $scriptVariantMathml = $converter->texToMathMl('\\mathcal{F}_n + \\mathbb{R} + \\mathfrak{g} + \\mathscr{L} + \\boldsymbol{\\alpha}_i');
        $singleTokenMathml = $converter->texToMathMl('\\mathbf x + \\mathbb R');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $variantMathml);
        $t->contains('<mstyle mathvariant="normal"><mi>d</mi></mstyle><mi>x</mi>', $variantMathml);
        $t->contains('<mstyle mathvariant="bold"><msub><mi>' . $u(0x1D42F) . '</mi><mi>' . $u(0x1D422) . '</mi></msub></mstyle>', $variantMathml);
        $t->contains('<mstyle mathvariant="italic"><mi>' . $u(0x1D45B) . '</mi></mstyle><mo>+</mo><mstyle mathvariant="sans-serif"><mi>' . $u(0x1D5B2) . '</mi></mstyle>', $variantMathml);
        $t->contains('<mstyle mathvariant="monospace"><mrow><mi>' . $u(0x1D68C) . '</mi><mi>' . $u(0x1D698) . '</mi><mi>' . $u(0x1D68D) . '</mi><mi>' . $u(0x1D68E) . '</mi></mrow></mstyle>', $variantMathml);
        $t->contains('<annotation encoding="application/x-tex">\\mathrm{d}x + \\mathbf{v_i} + \\mathit{n} + \\mathsf{S} + \\mathtt{code}</annotation>', $variantMathml);
        $t->contains('<msub><mstyle mathvariant="script"><mi>' . $u(0x2131) . '</mi></mstyle><mi>n</mi></msub><mo>+</mo><mstyle mathvariant="double-struck"><mi>' . $u(0x211D) . '</mi></mstyle>', $scriptVariantMathml);
        $t->contains('<mstyle mathvariant="fraktur"><mi>' . $u(0x1D524) . '</mi></mstyle><mo>+</mo><mstyle mathvariant="script"><mi>' . $u(0x2112) . '</mi></mstyle>', $scriptVariantMathml);
        $t->contains('<msub><mstyle mathvariant="bold"><mi>α</mi></mstyle><mi>i</mi></msub>', $scriptVariantMathml);
        $t->contains('<mstyle mathvariant="bold"><mi>' . $u(0x1D431) . '</mi></mstyle><mo>+</mo><mstyle mathvariant="double-struck"><mi>' . $u(0x211D) . '</mi></mstyle>', $singleTokenMathml);
    },
    'rewrites bounded tex math alphabet ascii runs to unicode alphanumeric mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $u = static fn (int $codepoint): string => html_entity_decode('&#x' . strtoupper(dechex($codepoint)) . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $alphabetMathml = $converter->texToMathMl('\\mathbb{AZ09} + \\mathcal{FLO} + \\mathfrak{gR} + \\mathtt{code42}', true);
        $safeFallbackMathml = $converter->texToMathMl('\\mathit{h} + \\boldsymbol{\\alpha}_i + \\mathrm{d}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $alphabetMathml);
        $t->contains('<mstyle mathvariant="double-struck"><mrow><mi>' . $u(0x1D538) . '</mi><mi>' . $u(0x2124) . '</mi><mn>' . $u(0x1D7D8) . $u(0x1D7E1) . '</mn></mrow></mstyle>', $alphabetMathml);
        $t->contains('<mstyle mathvariant="script"><mrow><mi>' . $u(0x2131) . '</mi><mi>' . $u(0x2112) . '</mi><mi>' . $u(0x1D4AA) . '</mi></mrow></mstyle>', $alphabetMathml);
        $t->contains('<mstyle mathvariant="fraktur"><mrow><mi>' . $u(0x1D524) . '</mi><mi>' . $u(0x211C) . '</mi></mrow></mstyle>', $alphabetMathml);
        $t->contains('<mstyle mathvariant="monospace"><mrow><mi>' . $u(0x1D68C) . '</mi><mi>' . $u(0x1D698) . '</mi><mi>' . $u(0x1D68D) . '</mi><mi>' . $u(0x1D68E) . '</mi><mn>' . $u(0x1D7FA) . $u(0x1D7F8) . '</mn></mrow></mstyle>', $alphabetMathml);
        $t->contains('<annotation encoding="application/x-tex">\\mathbb{AZ09} + \\mathcal{FLO} + \\mathfrak{gR} + \\mathtt{code42}</annotation>', $alphabetMathml);
        $t->contains('<mstyle mathvariant="italic"><mi>' . $u(0x210E) . '</mi></mstyle><mo>+</mo><msub><mstyle mathvariant="bold"><mi>α</mi></mstyle><mi>i</mi></msub><mo>+</mo><mstyle mathvariant="normal"><mi>d</mi></mstyle>', $safeFallbackMathml);
    },
    'rejects malformed bounded tex color phantom cancel and variant commands without invoking a tex engine' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\color{}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\color{url(javascript:bad)}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\color{red}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\textcolor{red}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\phantom'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\phantom{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hphantom_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\smash'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\smash{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\smash[]{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\smash[x]{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathllap'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathrlap{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathclap_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\cancel'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\xcancel{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\cancelto'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\cancelto{}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\cancelto{0}{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathbf'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathbb{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\boldsymbol_1'));
    },
    'rejects malformed bounded tex above below commands without invoking a tex engine' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overset{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underset{}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overbrace'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underbrace_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\displaystyle'));
    },
    'rejects malformed bounded tex matrix environments without invoking a tex engine' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}a & b\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{}a & b\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{p{2cm}}a & b\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{matrix}a & b'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{matrix}\\frac{a}{b & c\\end{matrix}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{matrix}\\end{matrix}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{cases}x & y'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{cases}\\end{cases}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\end{matrix}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{align}a & b & c\\end{align}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{gather}a & b\\end{gather}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{split}S &= x \\\\ \\end{split}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{gathered}\\end{gathered}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{alignedat}a &= b\\end{alignedat}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{alignedat}{0}a &= b\\end{alignedat}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{alignedat}{5}a &= b\\end{alignedat}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{alignedat}{2}a &= b & c\\end{alignedat}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{alignat}{1}a &= b \\\\ \\end{alignat}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{flalign}x\\end{flalign}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{flalign}a &= b & c &= d & e &= f & g &= h & i &= j\\end{flalign}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{flalign}a &= b \\\\ \\end{flalign}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{multline}\\end{multline}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{multline}a & b\\end{multline}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{multlined}a \\\\\\end{multlined}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{multline}a \\\\[.5em \\end{multline}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{cc}a & b \\\\ \\cline{} c & d\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{cc}a & b \\\\ \\cline{0-1} c & d\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{cc}a & b \\\\ \\cline{2-1} c & d\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{cc}a & b \\\\ \\cline{2-3} c & d\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{align}a &= b \\tag{}\\end{align}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{align}a &= b \\tag{A} \\tag{B}\\end{align}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{align}\\tag{A}\\end{align}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{align}a &= b \\label{}\\end{align}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{smallmatrix}\\end{smallmatrix}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{subarray}a \\\\ b\\end{subarray}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{subarray}{}a \\\\ b\\end{subarray}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{subarray}{p{2cm}}a \\\\ b\\end{subarray}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{subarray}{cc}a & b \\\\ c\\end{subarray}'));
    },
    'rejects malformed bounded tex math groups without invoking a tex engine' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\frac{a}{'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sqrt'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sqrt[]{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sqrt[3{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\binom{n}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\binom{}{k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\tbinom{n}{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\dfrac{a}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\genfrac{[}{]}{bad}{0}{n}{k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\genfrac{\\unknown}{]}{0pt}{0}{n}{k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\genfrac{[}{]}{0pt}{4}{n}{k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\genfrac{[}{]}{0pt}{0}{}{k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\genfrac{[}{]}{0pt}{0}{n}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\choose k'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('n \\choose'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('{a \\over}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('{\\atop b}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overwithdelims() k'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('{n \\overwithdelims\\unknown) k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('{n \\atopwithdelims( k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('{n \\abovewithdelims()bad k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('{n \\abovewithdelims()1pt }'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\text{unterminated'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mbox'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\textbf'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatorname'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatorname{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\limits_{i=1}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sum\\limits'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\int\\nolimits'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\displaylimits_{i=1}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatorname{median}\\displaylimits'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatorname*{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\substack'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\substack{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\substack{a & b}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\substack{a \\\\ }'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\left'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\left\\unknown{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\middle|'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\left( x \\right) \\middle| y'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\left( x \\middle\\unknown y \\right)'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\big'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\big\\unknown{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\Bigg a'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hspace'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hspace{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hspace{1}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hspace{calc(1em)}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mspace{bad}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mspace*{1em}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\tag{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('x \\tag{A} \\tag{B}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\tag*'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\label{}x'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\ref'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\eqref{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hat'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\vec_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underline^2'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\xrightarrow'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\xrightarrow{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\xleftarrow[]{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overrightarrow'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underrightarrow_1'));
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
