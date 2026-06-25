<?php

declare(strict_types=1);

use PortLibs\Pandoc\PlainMath\Expression as E;
use PortLibs\Pandoc\PlainMath\MathMlWriter;

$corpus = require __DIR__ . '/fixtures/plainmath-conformance-corpus.php';

$loadXml = static function (string $xml, string $label): DOMDocument {
    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument('1.0', 'UTF-8');
    $ok = $document->loadXML($xml, LIBXML_NONET);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$ok) {
        throw new RuntimeException($label . ' is not XML: ' . implode('; ', array_map(
            static fn (LibXMLError $error): string => trim($error->message),
            $errors
        )));
    }

    return $document;
};

$canonicalXml = static function (DOMDocument $document): string {
    $canonical = $document->C14N();
    if (!is_string($canonical)) {
        throw new RuntimeException('Unable to canonicalize MathML XML.');
    }

    return $canonical;
};

$assertMathML = static function (TestRunner $t, string $expected, string $actual) use ($loadXml, $canonicalXml): void {
    $t->same(
        $canonicalXml($loadXml($expected, 'expected PlainMath writer MathML')),
        $canonicalXml($loadXml($actual, 'actual PlainMath writer MathML'))
    );
};

$expressionFromFixture = static function (array $fixture) use (&$expressionFromFixture): E {
    $kind = (string) ($fixture['kind'] ?? '');
    $value = array_key_exists('value', $fixture) ? (string) $fixture['value'] : null;
    $attributes = isset($fixture['attributes']) && is_array($fixture['attributes']) ? $fixture['attributes'] : [];
    $children = array_map(
        static fn (array $child): E => $expressionFromFixture($child),
        isset($fixture['children']) && is_array($fixture['children']) ? $fixture['children'] : []
    );

    return match ($kind) {
        'number' => E::number((string) $value, $attributes),
        'identifier' => E::identifier((string) $value, $attributes),
        'operator' => E::operator((string) $value, $attributes),
        'mathOperator' => E::mathOperator((string) $value, $attributes),
        'row' => E::row($children, $attributes),
        'super' => E::super($children[0] ?? throw new RuntimeException('Missing super base.'), $children[1] ?? throw new RuntimeException('Missing super script.'), $attributes),
        'sub' => E::sub($children[0] ?? throw new RuntimeException('Missing sub base.'), $children[1] ?? throw new RuntimeException('Missing sub script.'), $attributes),
        'subsup' => E::subSup($children[0] ?? throw new RuntimeException('Missing subsup base.'), $children[1] ?? throw new RuntimeException('Missing subsup subscript.'), $children[2] ?? throw new RuntimeException('Missing subsup superscript.'), $attributes),
        'fraction' => E::fraction($children[0] ?? throw new RuntimeException('Missing fraction numerator.'), $children[1] ?? throw new RuntimeException('Missing fraction denominator.'), $attributes),
        'sqrt' => E::sqrt($children[0] ?? throw new RuntimeException('Missing sqrt body.'), $attributes),
        'delimited' => E::delimited(isset($attributes['left']) ? (string) $attributes['left'] : null, $children[0] ?? throw new RuntimeException('Missing delimited body.'), isset($attributes['right']) ? (string) $attributes['right'] : null, $attributes),
        'table' => E::table(array_map(
            static fn (array $row): array => array_map(
                static fn (array $cell): E => $expressionFromFixture($cell),
                $row
            ),
            isset($fixture['rows']) && is_array($fixture['rows']) ? $fixture['rows'] : []
        ), $attributes),
        default => throw new RuntimeException('Unsupported expectedExpression kind: ' . $kind),
    };
};

return [
    'writes corpus expression metadata through the shadow mathml writer' => static function (TestRunner $t) use ($corpus, $assertMathML, $expressionFromFixture): void {
        $writer = new MathMlWriter();
        $coveredIds = [];

        foreach ($corpus['mathml'] as $case) {
            if (!isset($case['expectedExpression']) || !is_array($case['expectedExpression'])) {
                continue;
            }

            $coveredIds[] = $case['id'];
            $assertMathML(
                $t,
                (string) $case['expectedMathML'],
                $writer->writeDocument(
                    $expressionFromFixture($case['expectedExpression']),
                    (bool) $case['display'],
                    (string) $case['tex']
                )
            );
        }

        $t->same([
            'script-super',
            'implicit-product-identifiers',
            'display-fraction-root',
            'pmatrix-two-by-two',
            'infix-choose',
        ], $coveredIds, 'PlainMath expression metadata should cover the first parser extraction families.');
    },
    'writes typed plainmath script and row expressions as mathml documents' => static function (TestRunner $t) use ($assertMathML): void {
        $writer = new MathMlWriter();

        $assertMathML(
            $t,
            '<math xmlns="http://www.w3.org/1998/Math/MathML"><semantics><msup><mi>x</mi><mn>2</mn></msup><annotation encoding="application/x-tex">x^2</annotation></semantics></math>',
            $writer->writeDocument(
                E::super(E::identifier('x'), E::number('2')),
                false,
                'x^2'
            )
        );

        $assertMathML(
            $t,
            '<math xmlns="http://www.w3.org/1998/Math/MathML"><semantics><mrow><mi>a</mi><msup><mi>x</mi><mn>2</mn></msup><mo>+</mo><mi>b</mi><mi>x</mi><mo>+</mo><mi>c</mi><mo>=</mo><mn>0</mn></mrow><annotation encoding="application/x-tex">ax^2 + bx + c = 0</annotation></semantics></math>',
            $writer->writeDocument(
                E::row([
                    E::identifier('a'),
                    E::super(E::identifier('x'), E::number('2')),
                    E::operator('+'),
                    E::identifier('b'),
                    E::identifier('x'),
                    E::operator('+'),
                    E::identifier('c'),
                    E::operator('='),
                    E::number('0'),
                ]),
                false,
                'ax^2 + bx + c = 0'
            )
        );
    },
    'writes typed plainmath fractions roots and display annotations' => static function (TestRunner $t) use ($assertMathML): void {
        $writer = new MathMlWriter();

        $assertMathML(
            $t,
            '<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><semantics><mfrac><msup><mi>x</mi><mn>2</mn></msup><msqrt><msub><mi>y</mi><mn>1</mn></msub></msqrt></mfrac><annotation encoding="application/x-tex">\frac{x^2}{\sqrt{y_1}}</annotation></semantics></math>',
            $writer->writeDocument(
                E::fraction(
                    E::super(E::identifier('x'), E::number('2')),
                    E::sqrt(E::sub(E::identifier('y'), E::number('1')))
                ),
                true,
                '\frac{x^2}{\sqrt{y_1}}'
            )
        );

        $assertMathML(
            $t,
            '<math xmlns="http://www.w3.org/1998/Math/MathML"><semantics><mrow><mo stretchy="true">(</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo stretchy="true">)</mo></mrow><annotation encoding="application/x-tex">n \choose k</annotation></semantics></math>',
            $writer->writeDocument(
                E::delimited(
                    '(',
                    E::fraction(E::identifier('n'), E::identifier('k'), ['linethickness' => '0']),
                    ')'
                ),
                false,
                'n \choose k'
            )
        );
    },
    'writes typed plainmath table expressions inside delimiters' => static function (TestRunner $t) use ($assertMathML): void {
        $writer = new MathMlWriter();

        $assertMathML(
            $t,
            '<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><semantics><mrow><mo stretchy="true">(</mo><mtable><mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd></mtr><mtr><mtd><mn>3</mn></mtd><mtd><mn>4</mn></mtd></mtr></mtable><mo stretchy="true">)</mo></mrow><annotation encoding="application/x-tex">\begin{pmatrix}1 &amp; 2 \\\\ 3 &amp; 4\end{pmatrix}</annotation></semantics></math>',
            $writer->writeDocument(
                E::delimited(
                    '(',
                    E::table([
                        [E::number('1'), E::number('2')],
                        [E::number('3'), E::number('4')],
                    ]),
                    ')'
                ),
                true,
                '\begin{pmatrix}1 & 2 \\\\ 3 & 4\end{pmatrix}'
            )
        );
    },
];
