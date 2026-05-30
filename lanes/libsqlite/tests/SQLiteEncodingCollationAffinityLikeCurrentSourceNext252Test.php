<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUtf16LikeGlobAffinityCurrentSourceCursor;

$tests = [];

$rows252 = [
    ['key' => 1.0, 'rowid' => 1, 'textEncoding' => 'UTF-8', 'payload' => ['option_name' => 'upload_path', 'option_value' => 1.0]],
    ['key' => 10.0, 'rowid' => 2, 'textEncoding' => 'UTF-16LE', 'payload' => ['option_name' => 'posts_per_page', 'option_value' => 10.0]],
    ['key' => 100.0, 'rowid' => 3, 'textEncoding' => 'UTF-16BE', 'payload' => ['option_name' => 'thumbnail_size_w', 'option_value' => 100.0]],
    ['key' => 100.5, 'rowid' => 4, 'textEncoding' => 'UTF-16LE', 'payload' => ['option_name' => 'image_quality', 'option_value' => 100.5]],
    ['key' => 1000.0, 'rowid' => 5, 'textEncoding' => 'UTF-8', 'payload' => ['option_name' => 'large_size_w', 'option_value' => 1000.0]],
    ['key' => 1200.0, 'rowid' => 6, 'textEncoding' => 'UTF-16BE', 'payload' => ['option_name' => 'large_size_h', 'option_value' => 1200.0]],
    ['key' => 100.25, 'rowid' => 7, 'textEncoding' => 'UTF-16LE', 'payload' => ['option_name' => 'jpeg_quality', 'option_value' => 100.25]],
    ['key' => 0.0, 'rowid' => 8, 'textEncoding' => 'UTF-8', 'payload' => ['option_name' => 'blog_public', 'option_value' => 0.0]],
    ['key' => true, 'rowid' => 9, 'textEncoding' => 'UTF-16LE', 'payload' => ['option_name' => 'autoload_flag', 'option_value' => true]],
    ['key' => null, 'rowid' => 10, 'textEncoding' => 'UTF-16BE', 'payload' => ['option_name' => 'empty_option', 'option_value' => null]],
];

$cursor252 = static fn (string $pattern = '100%', string $operator = 'LIKE', string $collation = 'NOCASE'): SQLiteUtf16LikeGlobAffinityCurrentSourceCursor =>
    new SQLiteUtf16LikeGlobAffinityCurrentSourceCursor($rows252, $pattern, $operator, $collation);

$valueAt252 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$matched252 = static fn (string $pattern = '100%', string $operator = 'LIKE', string $collation = 'NOCASE'): array =>
    $cursor252($pattern, $operator, $collation)->matchedRows();

$matchedRowids252 = static fn (string $pattern = '100%', string $operator = 'LIKE', string $collation = 'NOCASE'): array =>
    array_map(static fn (array $row): int => $row['rowid'], $matched252($pattern, $operator, $collation));

$matchedTexts252 = static fn (string $pattern = '100%', string $operator = 'LIKE', string $collation = 'NOCASE'): array =>
    array_map(static fn (array $row): string => $row['text'], $matched252($pattern, $operator, $collation));

$matchedBytes252 = static fn (string $pattern = '100%', string $operator = 'LIKE', string $collation = 'NOCASE'): array =>
    array_column($matched252($pattern, $operator, $collation), 'bytesHex', 'rowid');

$plan252 = static fn (): array => $cursor252()->currentNextPlan();

$cases252 = [
    'like rowids keep real trailing zero digits' => [$matchedRowids252, ['100%'], [3, 7, 4, 5]],
    'like texts keep real trailing zero digits' => [$matchedTexts252, ['100%'], ['100', '100.25', '100.5', '1000']],
    'like range skips one and ten' => [$matchedRowids252, ['10%'], [2, 3, 7, 4, 5]],
    'like zero matches zero real' => [$matchedRowids252, ['0%'], [8]],
    'like one matches bool one and real one' => [$matchedRowids252, ['1%'], [1, 9, 2, 3, 7, 4, 5, 6]],
    'glob rowids keep real trailing zero digits' => [$matchedRowids252, ['100*', 'GLOB', 'BINARY'], [3, 7, 4, 5]],
    'glob exact hundred matches real hundred' => [$matchedRowids252, ['100', 'GLOB', 'BINARY'], [3]],
    'glob exact thousand matches real thousand' => [$matchedRowids252, ['1000', 'GLOB', 'BINARY'], [5]],
    'bytes row three utf16be numeric text' => [static fn (): string => $matchedBytes252()[3], [], '003100300030'],
    'bytes row five utf8 numeric text' => [static fn (): string => $matchedBytes252()[5], [], '31303030'],
    'bytes row seven utf16le fractional text' => [static fn (): string => $matchedBytes252()[7], [], '3100300030002e0032003500'],
    'plan current rowid' => [static fn (): mixed => $plan252()['currentRowid'], [], 3],
    'plan current text' => [static fn (): mixed => $plan252()['currentText'], [], '100'],
    'plan current storage' => [static fn (): mixed => $plan252()['currentOriginalStorage'], [], 'real'],
    'plan current encoding' => [static fn (): mixed => $plan252()['currentEncoding'], [], 'UTF-16BE'],
    'plan current in range' => [static fn (): mixed => $plan252()['currentInRange'], [], true],
    'plan current residual' => [static fn (): mixed => $plan252()['currentResidualMatch'], [], true],
    'plan current lower compare' => [static fn (): mixed => $plan252()['currentLowerComparison'], [], 0],
    'plan current upper compare' => [static fn (): mixed => $plan252()['currentUpperComparison'], [], -1],
    'plan next rowid' => [static fn (): mixed => $plan252()['nextRowid'], [], 7],
    'plan next text' => [static fn (): mixed => $plan252()['nextText'], [], '100.25'],
    'plan next encoding' => [static fn (): mixed => $plan252()['nextEncoding'], [], 'UTF-16LE'],
    'plan next residual' => [static fn (): mixed => $plan252()['nextResidualMatch'], [], true],
    'plan lower bound' => [static fn (): mixed => $plan252()['range']['lowerInclusive'], [], '100'],
    'plan upper bound' => [static fn (): mixed => $plan252()['range']['upperBound'], [], '101'],
    'plan operator' => [static fn (): mixed => $plan252()['operator'], [], 'LIKE'],
    'plan collation' => [static fn (): mixed => $plan252()['collation'], [], 'NOCASE'],
    'dependency text affinity' => [static fn (): mixed => $plan252()['dependencies'][0], [], 'sqlite-text-affinity'],
    'dependency utf16' => [static fn (): mixed => $plan252()['dependencies'][1], [], 'sqlite-utf16-encoding'],
    'dependency collation' => [static fn (): mixed => $plan252()['dependencies'][2], [], 'sqlite-like-glob-collation'],
    'application payload preserved row three' => [static fn (): mixed => $matched252()[0]['payload']['option_name'], [], 'thumbnail_size_w'],
    'application payload preserved row five' => [static fn (): mixed => $matched252()[3]['payload']['option_name'], [], 'large_size_w'],
    'position row three sorted before fractional' => [static fn (): mixed => $matched252()[0]['position'], [], 5],
    'position row five sorted after fractional' => [static fn (): mixed => $matched252()[3]['position'], [], 8],
    'text affinity excludes null' => [static fn (): bool => !in_array(10, $matchedRowids252('1%'), true), [], true],
    'float row six still keeps trailing zeros' => [static fn (): array => $matchedTexts252('120%'), [], ['1200']],
    'float row six bytes utf16be' => [static fn (): string => $matchedBytes252('120%')[6], [], '0031003200300030'],
    'fractional does not trim meaningful trailing nonzero' => [static fn (): array => $matchedTexts252('100.2%'), [], ['100.25']],
    'fractional direct glob' => [$matchedRowids252, ['100.?', 'GLOB', 'BINARY'], [4]],
    'integer-like real exact like' => [$matchedRowids252, ['100', 'LIKE', 'NOCASE'], [3]],
    'integer-like real exact glob' => [$matchedRowids252, ['1000', 'GLOB', 'BINARY'], [5]],
    'binary like has no default nocase range' => [$matchedRowids252, ['100%', 'LIKE', 'BINARY'], []],
    'nocase numeric sort is stable by rowid for equal text' => [static function () use ($rows252): array {
        $rows = $rows252;
        $rows[] = ['key' => '100', 'rowid' => 11, 'textEncoding' => 'UTF-8', 'payload' => []];
        $cursor = new SQLiteUtf16LikeGlobAffinityCurrentSourceCursor($rows, '100%', 'LIKE', 'NOCASE');

        return array_map(static fn (array $row): int => $row['rowid'], $cursor->matchedRows());
    }, [], [3, 11, 7, 4, 5]],
    'bool one text is one' => [static fn (): array => $matchedTexts252('1'), [], ['1', '1']],
    'real ten text is ten not one' => [static fn (): array => $matchedTexts252('10'), [], ['10']],
    'real hundred does not match one exact' => [static fn (): bool => !in_array(3, $matchedRowids252('1'), true), [], true],
    'real thousand does not match hundred exact' => [static fn (): bool => !in_array(5, $matchedRowids252('100'), true), [], true],
    'path helper reaches rowid' => [$valueAt252, [$plan252(), 'currentRowid'], 3],
    'path helper missing returns null' => [$valueAt252, [$plan252(), 'range.missing'], null],
    'range eof for no prefix' => [static fn (): mixed => $cursor252('%100')->currentNextPlan()['eof'], [], true],
    'range null for no prefix' => [static fn (): mixed => $cursor252('%100')->currentNextPlan()['range'], [], null],
    'no prefix matched rows remain empty because cursor has no usable range' => [$matchedRowids252, ['%100'], []],
    'non numeric string still participates' => [static function () use ($rows252): array {
        $rows = $rows252;
        $rows[] = ['key' => '100-option', 'rowid' => 12, 'textEncoding' => 'UTF-8', 'payload' => []];

        return array_map(
            static fn (array $row): int => $row['rowid'],
            (new SQLiteUtf16LikeGlobAffinityCurrentSourceCursor($rows, '100%', 'LIKE', 'NOCASE'))->matchedRows(),
        );
    }, [], [3, 12, 7, 4, 5]],
    'blob-like null rows stay outside plan current after range' => [static fn (): mixed => $cursor252('zz%')->currentNextPlan()['currentRowid'], [], null],
];

foreach ($cases252 as $name => [$callback, $arguments, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFiveTwo ' . $name] = static function (TestRunner $t) use ($callback, $arguments, $expected): void {
        $t->same($expected, $callback(...$arguments));
    };
}

$tests['encoding collation affinity like current source nextTwoFiveTwo rejects unsupported array operand'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobAffinityCurrentSourceCursor([
        ['key' => ['100'], 'rowid' => 1, 'textEncoding' => 'UTF-8'],
    ], '100%', 'LIKE', 'NOCASE'));
};

return $tests;
