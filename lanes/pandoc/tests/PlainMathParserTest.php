<?php

declare(strict_types=1);

use PortLibs\Pandoc\PlainMath\Expression;
use PortLibs\Pandoc\PlainMath\MathMlWriter;
use PortLibs\Pandoc\PlainMath\TexParser;

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
        $canonicalXml($loadXml($expected, 'expected parsed PlainMath MathML')),
        $canonicalXml($loadXml($actual, 'actual parsed PlainMath MathML'))
    );
};

$expressionToFixture = static function (Expression $expression) use (&$expressionToFixture): array {
    if ($expression->kind === 'table') {
        $rows = [];
        foreach ($expression->children as $row) {
            $cells = [];
            foreach ($row->children as $cell) {
                $cells[] = $expressionToFixture($cell->children[0]);
            }
            $rows[] = $cells;
        }

        $fixture = ['kind' => 'table', 'rows' => $rows];
        if ($expression->attributes !== []) {
            $fixture['attributes'] = $expression->attributes;
        }

        return $fixture;
    }

    $fixture = ['kind' => $expression->kind];
    if ($expression->value !== null) {
        $fixture['value'] = $expression->value;
    }
    if ($expression->attributes !== []) {
        $fixture['attributes'] = array_filter(
            $expression->attributes,
            static fn (mixed $value): bool => $value !== null
        );
    }
    if ($expression->children !== []) {
        $fixture['children'] = array_map(
            static fn (Expression $child): array => $expressionToFixture($child),
            $expression->children
        );
    }

    return $fixture;
};

return [
    'parses expected expression corpus with the typed tex reader' => static function (TestRunner $t) use ($corpus, $expressionToFixture, $assertMathML): void {
        $parser = new TexParser();
        $writer = new MathMlWriter();
        $coveredIds = [];

        foreach ($corpus['mathml'] as $case) {
            if (!isset($case['expectedExpression']) || !is_array($case['expectedExpression'])) {
                continue;
            }

            $coveredIds[] = $case['id'];
            $result = $parser->parse((string) $case['tex']);
            $t->true($result->ok(), 'PlainMath parser should accept expression fixture ' . $case['id'] . ': ' . var_export($result->diagnostics, true));
            $expression = $result->expression();
            $t->true($expression instanceof Expression, 'PlainMath parser should return an expression for ' . $case['id'] . '.');
            $t->same($case['expectedExpression'], $expressionToFixture($expression), 'PlainMath parser expression should match fixture shape for ' . $case['id'] . '.');

            $assertMathML(
                $t,
                (string) $case['expectedMathML'],
                $writer->writeDocument($expression, (bool) $case['display'], (string) $case['tex'])
            );
        }

        $t->same([
            'script-super',
            'implicit-product-identifiers',
            'display-fraction-root',
            'pmatrix-two-by-two',
            'infix-choose',
        ], $coveredIds, 'PlainMath parser should cover the first reader chunk fixtures.');
    },
    'parses all upstream-derived tex reader mathml fixtures through typed expressions' => static function (TestRunner $t) use ($corpus, $assertMathML): void {
        $parser = new TexParser();
        $writer = new MathMlWriter();
        $coveredIds = [];

        foreach ($corpus['mathml'] as $case) {
            $id = (string) $case['id'];
            $coveredIds[] = $id;
            $result = $parser->parse((string) $case['tex']);
            $t->true($result->ok(), 'PlainMath parser should accept typed expression fixture ' . $id . ': ' . var_export($result->diagnostics, true));
            $expression = $result->expression();
            $t->true($expression instanceof Expression, 'PlainMath parser should return an expression for typed fixture ' . $id . '.');
            if (!$expression instanceof Expression) {
                continue;
            }

            $assertMathML(
                $t,
                (string) $case['expectedMathML'],
                $writer->writeDocument($expression, (bool) $case['display'], (string) $case['tex'])
            );
        }

        $t->same(count($corpus['mathml']), count($coveredIds), 'PlainMath typed parser should cover every MathML corpus case.');
    },
    'reports diagnostics for unsupported core reader constructs' => static function (TestRunner $t): void {
        $result = (new TexParser())->parse('\begin{tikzpicture}a&=b\end{tikzpicture}');

        $t->true(!$result->ok(), 'Unsupported environments should produce parser diagnostics.');
        $t->same('unsupported-environment', $result->diagnostics[0]['code'] ?? null);
    },
];
