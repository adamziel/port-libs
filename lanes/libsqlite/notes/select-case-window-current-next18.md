# SELECT CASE Window Current Next18

Adds parser-level `CASE` expression support to the shared SELECT expression
path, not only projection-only helpers. The focused slice covers:

- simple and searched `CASE` projection expressions;
- `CASE` in WHERE predicate operands;
- `CASE` in final `ORDER BY`;
- `CASE` in window `PARTITION BY` and window `ORDER BY`;
- `CASE` as `lag`, `lead`, `first_value`, `last_value`, `nth_value`, and
  `ntile` arguments;
- nested CASE, NULL non-match behavior, BLOB branch comparisons, COLLATE
  wrapping, scalar function arguments, and malformed CASE rejection.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/src/SQLiteSelectExpression.php
php -l lanes/libsqlite/src/SQLiteSelectPredicate.php
php -l lanes/libsqlite/tests/SQLiteSelectCaseWindowCurrentNext18Test.php
php -l lanes/libsqlite/examples/application-select-case-window-current-next18.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectCaseWindowCurrentNext18Test.php
php lanes/libsqlite/examples/application-select-case-window-current-next18.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane json ok\n";'
git diff --check -- lanes/libsqlite
```

Result: focused `SQLiteSelectCaseWindowCurrentNext18Test.php` passed with
1 test file, 40 assertions, 0 failures. The Application smoke reported copied
`wp_options` rows bucketed through parser-level CASE expressions and ranked by
native window execution without requiring ext/sqlite.

Non-overlap note: this slice does not repeat accepted standalone SELECT CASE
projection coverage, accepted parser-level window text dispatch, accepted
SELECT SQL expression ORDER BY, subqueries, GROUP BY/HAVING, JSON table
source/cursor work, or storage VFS/B-tree/WAL clusters. It narrows the
previous expression CASE conflict to shared SELECT expression parsing and
window/predicate reuse.

Dependency closure: no new shared support component is needed; this reuses the
lane-local SELECT SQL parser, expression evaluator, predicate evaluator, and
window executor.
