# Expression Operator Corpus Next

Slice: `yield-sqlite-expression-operator-corpus-next`

Base accepted HEAD: `4e7dbe25b0fee516ea96b5e41e63b5b705f0ad61`

## Behavior

Adds parser/executor support for SQLite expression operators that were not
covered by the accepted scalar operator corpus:

- unary plus, unary minus, and bitwise not
- bitwise AND, OR, left shift, and right shift
- numeric-prefix coercion for text and BLOB operands
- SQL NULL propagation for unary/bitwise operands
- parser-level SELECT usage in projection, WHERE, HAVING, and ORDER BY

The Application smoke uses copied `wp_options` flag rows to prove bit-mask style
option filtering without requiring `ext/sqlite`.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteSelectExpression.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSelectExpression.php

php -l lanes/libsqlite/src/SQLiteSelectSql.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSelectSql.php

php -l lanes/libsqlite/src/SQLiteSelectProjection.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSelectProjection.php

php -l lanes/libsqlite/src/SQLiteSelectPredicate.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSelectPredicate.php

php -l lanes/libsqlite/tests/SQLiteExpressionOperatorCorpusTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteExpressionOperatorCorpusTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteExpressionOperatorCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 60 assertions, 0 failures

php lanes/libsqlite/examples/application-select-sql-bitwise-operators.php
[
    {
        "name": "siteurl",
        "active_bits": 1,
        "inactive_bits": 2,
        "doubled_flags": 10
    },
    {
        "name": "blog_public",
        "active_bits": 0,
        "inactive_bits": 6,
        "doubled_flags": 2
    }
]
```

Expected dashboard movement: `phpPass` increases by the verified focused
assertion delta, `933 -> 993`. `benchmarkDenominator.mapped` is unchanged
because no new upstream inventory unit is mapped.

## Non-Overlap

This avoids the accepted scalar arithmetic/concatenation operator corpus,
SELECT expression ORDER BY, SELECT SQL subqueries, grouped SELECT text,
JSON table source/constraint work, Unicode GLOB, rollback/VFS application,
B-tree overflow/root-collapse/page-move work, and VFS sync/lock/writer
clusters. It only covers the previously unhandled unary and bitwise expression
operator family.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded
native PHP SELECT expression, projection, predicate, and SQL parser helpers.
