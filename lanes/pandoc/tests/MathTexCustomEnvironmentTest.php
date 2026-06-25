<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MathTexConverter;

return [
    'expands bounded raw tex custom environments for mathml handoff' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $delimited = $converter->texToMathMl('\newenvironment{foo}{\left(}{\right)}\begin{foo}x\end{foo}', true);
        $renewed = $converter->texToMathMl('\newenvironment{foo}{\left(}{\right)}\renewenvironment{foo}{\left[}{\right]}\begin{foo}y\end{foo}');
        $defaulted = $converter->texToMathMl('\newenvironment{shift}[2][2]{#2_{#1}+}{}\begin{shift}[3]{x}y\end{shift}');
        $arrayWrapper = $converter->texToMathMl('\newenvironment{ary}{\begin{array}{cc}}{\end{array}}\begin{ary}2 & 3\\\\4 & 5\end{ary}', true);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $delimited);
        $t->contains('<mrow><mo fence="true" stretchy="true">(</mo><mi>x</mi><mo fence="true" stretchy="true">)</mo></mrow>', $delimited);
        $t->contains('<annotation encoding="application/x-tex">\newenvironment{foo}{\left(}{\right)}\begin{foo}x\end{foo}</annotation>', $delimited);
        $t->true(!str_contains($delimited, '<mi>foo</mi>') && !str_contains($delimited, '<mi>newenvironment</mi>'));

        $t->contains('<mrow><mo fence="true" stretchy="true">[</mo><mi>y</mi><mo fence="true" stretchy="true">]</mo></mrow>', $renewed);
        $t->true(!str_contains($renewed, '<mo fence="true" stretchy="true">(</mo>'), 'Renewed environment should use the latest opener.');

        $t->contains('<mrow><msub><mi>x</mi><mn>3</mn></msub><mo>+</mo><mi>y</mi></mrow>', $defaulted);

        $t->contains('<mtable columnalign="center center"><mtr><mtd><mn>2</mn></mtd><mtd><mn>3</mn></mtd></mtr><mtr><mtd><mn>4</mn></mtd><mtd><mn>5</mn></mtd></mtr></mtable>', $arrayWrapper);
        $t->contains('<annotation encoding="application/x-tex">\newenvironment{ary}{\begin{array}{cc}}{\end{array}}\begin{ary}2 &amp; 3\\\\4 &amp; 5\end{ary}</annotation>', $arrayWrapper);
    },
    'collects raw tex custom environment definitions for mathml handoff' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $document = new AstNode('document', [], [
            new AstNode('raw_tex', [
                'tex' => '\newenvironment{foo}{\left(}{\right)}',
                'command' => 'newenvironment',
            ]),
            new AstNode('raw_tex', [
                'tex' => '\newenvironment{shift}[2][2]{#2_{#1}+}{}',
                'command' => 'newenvironment',
            ]),
        ]);

        $macros = $converter->macroDefinitionsFromDocument($document);
        $mathml = $converter->texToMathMl('\begin{foo}z\end{foo} + \begin{shift}{q}r\end{shift}', false, $macros);

        $t->same([
            'foo' => ['environment' => true, 'arity' => 0, 'opener' => '\left(', 'closer' => '\right)'],
            'shift' => ['environment' => true, 'arity' => 2, 'opener' => '#2_{#1}+', 'closer' => '', 'optionalDefault' => '2'],
        ], $macros);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mi>z</mi><mo fence="true" stretchy="true">)</mo><mo>+</mo><msub><mi>q</mi><mn>2</mn></msub><mo>+</mo><mi>r</mi>', $mathml);
        $t->contains('<annotation encoding="application/x-tex">\begin{foo}z\end{foo} + \begin{shift}{q}r\end{shift}</annotation>', $mathml);
    },
    'rejects malformed bounded raw tex custom environments before mathml conversion' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\newenvironment{foo}{\left(}{\right)}\begin{foo}x'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\begin{foo}x\end{foo}', false, [
            'foo' => ['environment' => true, 'arity' => 1, 'opener' => '#1+', 'closer' => ''],
        ]));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\begin{foo}x\end{foo}', false, [
            'foo' => ['environment' => true, 'arity' => 0, 'opener' => '\left(', 'closer' => '\right)', 'optionalDefault' => 'bad'],
        ]));
    },
];
