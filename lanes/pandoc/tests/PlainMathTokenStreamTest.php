<?php

declare(strict_types=1);

use PortLibs\Pandoc\PlainMath\TexTokenStream;

return [
    'reads command names with immutable cursor spans' => static function (TestRunner $t): void {
        $stream = new TexTokenStream('\frac {x}');
        $read = $stream->readCommand();

        $t->true(is_array($read));
        $t->same('frac', $read['command']);
        $t->same(['start' => 0, 'end' => 5, 'text' => '\frac'], $read['span']);
        $t->same(0, $stream->offset(), 'Original stream cursor should not move.');
        $t->same(5, $read['stream']->offset());

        $afterWhitespace = $read['stream']->skipWhitespace();
        $t->same(6, $afterWhitespace->offset());
        $group = $afterWhitespace->readRawGroup();

        $t->true(is_array($group));
        $t->same('x', $group['value']);
        $t->same(['start' => 6, 'end' => 9, 'text' => '{x}'], $group['span']);
    },
    'reads control symbol commands as single tokens' => static function (TestRunner $t): void {
        $slash = chr(92);
        $source = $slash . ',' . $slash . '%' . $slash . $slash . $slash . '{';
        $stream = new TexTokenStream($source);

        $comma = $stream->readCommand();
        $t->true(is_array($comma));
        $t->same(',', $comma['command']);
        $t->same(['start' => 0, 'end' => 2, 'text' => $slash . ','], $comma['span']);

        $percent = $comma['stream']->readCommand();
        $t->true(is_array($percent));
        $t->same('%', $percent['command']);
        $t->same(['start' => 2, 'end' => 4, 'text' => $slash . '%'], $percent['span']);

        $lineBreak = $percent['stream']->readCommand();
        $t->true(is_array($lineBreak));
        $t->same($slash, $lineBreak['command']);
        $t->same(['start' => 4, 'end' => 6, 'text' => $slash . $slash], $lineBreak['span']);

        $brace = $lineBreak['stream']->readCommand();
        $t->true(is_array($brace));
        $t->same('{', $brace['command']);
        $t->same(['start' => 6, 'end' => 8, 'text' => $slash . '{'], $brace['span']);
        $t->same(8, $brace['stream']->offset());
    },
    'reads nested raw groups without treating escaped braces as structure' => static function (TestRunner $t): void {
        $source = '{a{b\{c\}}d}z';
        $stream = new TexTokenStream($source);
        $group = $stream->readRawGroup();

        $t->true(is_array($group));
        $t->same('a{b\{c\}}d', $group['value']);
        $t->same(['start' => 0, 'end' => strlen('{a{b\{c\}}d}'), 'text' => '{a{b\{c\}}d}'], $group['span']);
        $t->same(['start' => 1, 'end' => strlen('{a{b\{c\}}d}') - 1, 'text' => 'a{b\{c\}}d'], $group['inner_span']);
        $t->same('z', $group['stream']->readUtf8Char()['char']);
        $t->same(null, (new TexTokenStream('{unterminated'))->readRawGroup());
    },
    'reads nested optional brackets without consuming absent brackets' => static function (TestRunner $t): void {
        $source = '[a[b\]c]d]x';
        $stream = new TexTokenStream($source);
        $optional = $stream->readOptionalBracket();

        $t->true(is_array($optional));
        $t->same('a[b\]c]d', $optional['value']);
        $t->same(['start' => 0, 'end' => strlen('[a[b\]c]d]'), 'text' => '[a[b\]c]d]'], $optional['span']);
        $t->same('x', $optional['stream']->readUtf8Char()['char']);

        $notOptional = $optional['stream']->readOptionalBracket();
        $t->same(null, $notOptional);
        $t->same(strlen('[a[b\]c]d]'), $optional['stream']->offset());
    },
    'skips whitespace and unescaped tex comments' => static function (TestRunner $t): void {
        $slash = chr(92);
        $source = " \t% first comment\n  " . $slash . 'alpha' . $slash . '% not a comment';
        $stream = new TexTokenStream($source);
        $skipped = $stream->skipWhitespace();

        $t->same(strpos($source, $slash . 'alpha'), $skipped->offset());

        $alpha = $skipped->readCommand();
        $t->true(is_array($alpha));
        $t->same('alpha', $alpha['command']);

        $escapedPercent = $alpha['stream']->skipWhitespace()->readCommand();
        $t->true(is_array($escapedPercent));
        $t->same('%', $escapedPercent['command']);
    },
    'reads utf-8 characters with byte offsets' => static function (TestRunner $t): void {
        $alpha = "\u{03B1}";
        $pi = "\u{1D6D1}";
        $source = $alpha . '+' . $pi;
        $stream = new TexTokenStream($source);

        $first = $stream->readUtf8Char();
        $t->true(is_array($first));
        $t->same($alpha, $first['char']);
        $t->same(['start' => 0, 'end' => strlen($alpha), 'text' => $alpha], $first['span']);

        $second = $first['stream']->readUtf8Char();
        $t->true(is_array($second));
        $t->same('+', $second['char']);
        $t->same(['start' => strlen($alpha), 'end' => strlen($alpha) + 1, 'text' => '+'], $second['span']);

        $third = $second['stream']->readUtf8Char();
        $t->true(is_array($third));
        $t->same($pi, $third['char']);
        $t->same(['start' => strlen($alpha) + 1, 'end' => strlen($source), 'text' => $pi], $third['span']);
        $t->same(null, $third['stream']->readUtf8Char());
    },
];
