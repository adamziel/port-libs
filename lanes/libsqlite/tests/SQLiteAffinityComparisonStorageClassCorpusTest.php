<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityComparison;
use PortLibs\LibSqlite\SQLiteBlobValue;

$tests = [];

$storageCases = [
    'null storage class' => [null, 'null'],
    'bool storage class is integer' => [true, 'integer'],
    'integer storage class' => [7, 'integer'],
    'real storage class' => [7.5, 'real'],
    'text storage class' => ['7', 'text'],
    'blob storage class' => [new SQLiteBlobValue('7'), 'blob'],
];

foreach ($storageCases as $name => [$value, $expected]) {
    $tests['affinity comparison storage class ' . $name] = static function (TestRunner $t) use ($value, $expected): void {
        $t->same($expected, SQLiteAffinityComparison::storageClass($value));
    };
}

$coercionCases = [
    'integer affinity converts text integer rhs' => [5, '5', 'INTEGER', 'NONE', 5, 5, 'integer', 'integer'],
    'integer affinity converts text integer lhs' => ['5', 5, 'NONE', 'INTEGER', 5, 5, 'integer', 'integer'],
    'numeric affinity converts text real rhs' => [5, '5.25', 'NUMERIC', 'NONE', 5, 5.25, 'integer', 'real'],
    'numeric affinity converts exponent rhs to integer when exact' => [90, '9e1', 'NUMERIC', 'NONE', 90, 90, 'integer', 'integer'],
    'numeric affinity converts exponent rhs to real when fractional' => [95.5, '9.55e1', 'NUMERIC', 'NONE', 95.5, 95.5, 'real', 'real'],
    'numeric affinity leaves partial numeric text rhs unchanged' => [5, '5x', 'NUMERIC', 'NONE', 5, '5x', 'integer', 'text'],
    'numeric affinity leaves decimal prefix text rhs unchanged' => [5, '5.0x', 'NUMERIC', 'NONE', 5, '5.0x', 'integer', 'text'],
    'numeric affinity leaves exponent prefix text rhs unchanged' => [5, '5e2x', 'NUMERIC', 'NONE', 5, '5e2x', 'integer', 'text'],
    'numeric affinity leaves non numeric text rhs unchanged' => [0, 'plugin', 'NUMERIC', 'NONE', 0, 'plugin', 'integer', 'text'],
    'numeric affinity converts blob bytes rhs' => [11, new SQLiteBlobValue('11'), 'NUMERIC', 'NONE', 11, 11, 'integer', 'integer'],
    'numeric affinity leaves partial blob rhs unchanged' => [11, new SQLiteBlobValue('11x'), 'NUMERIC', 'NONE', 11, new SQLiteBlobValue('11x'), 'integer', 'blob'],
    'text affinity converts integer rhs with none affinity' => ['5', 5, 'TEXT', 'NONE', '5', '5', 'text', 'text'],
    'text affinity converts real rhs with none affinity' => ['5.5', 5.5, 'TEXT', 'NONE', '5.5', '5.5', 'text', 'text'],
    'text affinity converts bool rhs with none affinity' => ['1', true, 'TEXT', 'NONE', '1', '1', 'text', 'text'],
    'text affinity does not convert blob rhs' => ['5', new SQLiteBlobValue('5'), 'TEXT', 'NONE', '5', new SQLiteBlobValue('5'), 'text', 'blob'],
    'text affinity does not override numeric lhs affinity' => [5, '5', 'INTEGER', 'TEXT', 5, 5, 'integer', 'integer'],
    'no affinity preserves text numeric storage classes' => [5, '5', 'NONE', 'NONE', 5, '5', 'integer', 'text'],
    'blob affinity behaves like no affinity for numeric text' => [5, '5', 'BLOB', 'NONE', 5, '5', 'integer', 'text'],
    'real affinity converts trimmed text rhs' => [12.0, ' 12.0 ', 'REAL', 'NONE', 12.0, 12, 'real', 'integer'],
    'numeric affinity converts signed text rhs' => [-12, '-12', 'NUMERIC', 'NONE', -12, -12, 'integer', 'integer'],
    'numeric affinity converts leading plus text rhs' => [12, '+12', 'NUMERIC', 'NONE', 12, 12, 'integer', 'integer'],
    'numeric affinity leaves hex-like text rhs unchanged' => [16, '0x10', 'NUMERIC', 'NONE', 16, '0x10', 'integer', 'text'],
];

foreach ($coercionCases as $name => [$left, $right, $leftAffinity, $rightAffinity, $expectedLeft, $expectedRight, $expectedLeftClass, $expectedRightClass]) {
    $tests['affinity comparison coerced pair ' . $name] = static function (TestRunner $t) use ($left, $right, $leftAffinity, $rightAffinity, $expectedLeft, $expectedRight, $expectedLeftClass, $expectedRightClass): void {
        $pair = SQLiteAffinityComparison::coercedPair($left, $right, $leftAffinity, $rightAffinity);
        if ($expectedLeft instanceof SQLiteBlobValue) {
            $t->true($pair['left'] instanceof SQLiteBlobValue);
            $t->same($expectedLeft->bytes, $pair['left']->bytes);
        } else {
            $t->same($expectedLeft, $pair['left']);
        }
        if ($expectedRight instanceof SQLiteBlobValue) {
            $t->true($pair['right'] instanceof SQLiteBlobValue);
            $t->same($expectedRight->bytes, $pair['right']->bytes);
        } else {
            $t->same($expectedRight, $pair['right']);
        }
        $t->same($expectedLeftClass, $pair['leftStorageClass']);
        $t->same($expectedRightClass, $pair['rightStorageClass']);
    };
}

$comparisonCases = [
    'numeric affinity equal text integer' => [5, '5', 'NUMERIC', 'NONE', 'BINARY', 0],
    'numeric affinity orders converted integer' => [5, '6', 'NUMERIC', 'NONE', 'BINARY', -1],
    'numeric affinity orders converted real' => [5.5, '5.25', 'NUMERIC', 'NONE', 'BINARY', 1],
    'numeric affinity leaves partial text above numeric by storage rank' => [5, '5x', 'NUMERIC', 'NONE', 'BINARY', -1],
    'numeric affinity leaves text numeric prefix above numeric by storage rank' => [500, '5e2x', 'NUMERIC', 'NONE', 'BINARY', -1],
    'no affinity keeps numeric below text' => [100, '2', 'NONE', 'NONE', 'BINARY', -1],
    'text affinity makes lexical order' => ['100', 2, 'TEXT', 'NONE', 'BINARY', -1],
    'text affinity compares converted integer equal' => ['2', 2, 'TEXT', 'NONE', 'BINARY', 0],
    'text affinity compares converted bool equal' => ['1', true, 'TEXT', 'NONE', 'BINARY', 0],
    'blob storage sorts after text' => ['z', new SQLiteBlobValue('a'), 'NONE', 'NONE', 'BINARY', -1],
    'blob byte comparison equal' => [new SQLiteBlobValue('abc'), new SQLiteBlobValue('abc'), 'NONE', 'NONE', 'BINARY', 0],
    'blob byte comparison orders unsigned bytes' => [new SQLiteBlobValue("ab\x00"), new SQLiteBlobValue("ab\x01"), 'NONE', 'NONE', 'BINARY', -1],
    'null comparison is unknown' => [null, 0, 'NUMERIC', 'NONE', 'BINARY', null],
    'nocase collation folds ascii text' => ['Plugin', 'plugin', 'NONE', 'NONE', 'NOCASE', 0],
    'binary collation keeps ascii case distinct' => ['Plugin', 'plugin', 'NONE', 'NONE', 'BINARY', -1],
    'rtrim collation ignores trailing spaces' => ['autoload', "autoload  ", 'NONE', 'NONE', 'RTRIM', 0],
    'numeric affinity converts lhs from text' => ['42', 42, 'NONE', 'NUMERIC', 'BINARY', 0],
    'numeric affinity converts lhs from blob' => [new SQLiteBlobValue('42'), 42, 'NONE', 'NUMERIC', 'BINARY', 0],
    'numeric affinity leaves lhs text when malformed' => ['42x', 42, 'NONE', 'NUMERIC', 'BINARY', 1],
    'integer and real compare numeric equal' => [7, 7.0, 'NONE', 'NONE', 'BINARY', 0],
    'integer and real compare numeric order' => [7, 7.5, 'NONE', 'NONE', 'BINARY', -1],
    'text numeric rhs with text affinity orders lexically' => ['10', 2, 'TEXT', 'NONE', 'BINARY', -1],
    'text rhs affinity converts lhs to text' => [2, '10', 'NONE', 'TEXT', 'BINARY', 1],
    'numeric affinity exact decimal integer equals integer' => [12, '12.0', 'NUMERIC', 'NONE', 'BINARY', 0],
    'numeric affinity fractional decimal remains real' => [12, '12.5', 'NUMERIC', 'NONE', 'BINARY', -1],
    'numeric affinity trims whitespace before conversion' => [12, " \t12\n", 'NUMERIC', 'NONE', 'BINARY', 0],
    'numeric affinity leaves empty text above numeric' => [0, '', 'NUMERIC', 'NONE', 'BINARY', -1],
    'numeric affinity leaves whitespace text above numeric' => [0, '   ', 'NUMERIC', 'NONE', 'BINARY', -1],
    'text storage sorts below blob after no conversion' => ['10', new SQLiteBlobValue('10'), 'NONE', 'NONE', 'BINARY', -1],
    'null stays unknown against text prefix' => [null, '5x', 'NUMERIC', 'NONE', 'BINARY', null],
    'numeric storage sorts below text after failed conversion' => [10.5, '10.5x', 'REAL', 'NONE', 'BINARY', -1],
    'numeric affinity converts signed exponent equal' => [-900, '-9e2', 'NUMERIC', 'NONE', 'BINARY', 0],
    'numeric affinity converts plus decimal order' => [1.25, '+1.5', 'NUMERIC', 'NONE', 'BINARY', -1],
    'nocase collation only applies after text storage' => [5, '5', 'NONE', 'NONE', 'NOCASE', -1],
    'rtrim collation does not trim blobs' => [new SQLiteBlobValue('a '), new SQLiteBlobValue('a'), 'NONE', 'NONE', 'RTRIM', 1],
    'text affinity preserves null unknown' => ['0', null, 'TEXT', 'NONE', 'BINARY', null],
];

foreach ($comparisonCases as $name => [$left, $right, $leftAffinity, $rightAffinity, $collation, $expected]) {
    $tests['affinity comparison storage-class order ' . $name] = static function (TestRunner $t) use ($left, $right, $leftAffinity, $rightAffinity, $collation, $expected): void {
        $comparison = SQLiteAffinityComparison::compare($left, $right, $leftAffinity, $rightAffinity, $collation);
        $normalized = $comparison === null ? null : $comparison <=> 0;
        $t->same($expected, $normalized);
    };
}

$tests['affinity comparison rejects unsupported affinity'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAffinityComparison::compare(1, '1', 'GEOMETRY'));
};

$tests['affinity comparison equals centralizes collation equality'] = static function (TestRunner $t): void {
    $t->same(false, SQLiteAffinityComparison::equals('Plugin ', 'plugin', 'TEXT', 'NONE', 'NOCASE'));
    $t->true(SQLiteAffinityComparison::equals('Plugin', 'plugin', 'TEXT', 'NONE', 'NOCASE'));
    $t->true(SQLiteAffinityComparison::equals('cache  ', 'cache', 'TEXT', 'NONE', 'RTRIM'));
    $t->true(SQLiteAffinityComparison::equals('9223372036854775808', 9.223372036854776E+18, 'NONE', 'NUMERIC'));
    $t->same(false, SQLiteAffinityComparison::equals(new SQLiteBlobValue('7'), 7, 'NUMERIC', 'NONE'));
    $t->true(!SQLiteAffinityComparison::equals(null, null, 'NONE', 'NONE'));
};

return $tests;
