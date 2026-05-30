### 2026-05-27 compound EXCEPT/INTERSECT affinity next13

Micro-slice: `yield-sqlite-compound-except-intersect-affinity-next13`.

This isolated SQL execution slice adds focused upstream-style coverage for
compound SELECT comparison rules in `INTERSECT` and `EXCEPT`. SQLite compares
compound result rows without applying column affinity, while still treating
NULLs as equal for duplicate removal and treating integer/real numeric values
as equal. The new focused test file covers direct `SQLiteSelectCompound`
combining and parser-level no-FROM `SQLiteSelectSql` execution for text versus
numeric, text versus BLOB, NULL, integer/real, duplicate representatives, CTE
arms, and chained compound operations.

Focused evidence:

```sh
php -l lanes/libsqlite/tests/SQLiteCompoundExceptIntersectAffinityTest.php
php -l lanes/libsqlite/examples/application-select-sql-compound-affinity.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundExceptIntersectAffinityTest.php
php -r 'var_export(require "lanes/libsqlite/examples/application-select-sql-compound-affinity.php"); echo PHP_EOL;'
git diff --check -- lanes/libsqlite
```

Result: the focused test command reported `1 test files, 25 assertions, 0
failures` with 25 PASS lines. The Application smoke reported text `1` surviving
`EXCEPT` against numeric `1`, numeric `1` matching real `1.0`, BLOB `X'31'`
surviving `EXCEPT` against text `1`, and NULL matching through `INTERSECT`.
`lane-status.json` moves `phpPass` from 3796 to 3821 for this clean worktree by
that exact verified PASS-line delta. No mapped upstream denominator change is
claimed.

Non-overlap: this avoids accepted compound row composition, grouped SELECT SQL
text, scalar operators, subqueries, expression ORDER BY, JSON table source and
constraint work, VFS sync/write/lock/rollback clusters, WAL savepoint byte
truncation/checkpoint transaction work, B-tree page relocation/root collapse/
overflow release, and Unicode GLOB behavior. This slice is only the narrower
compound comparison-affinity edge for `INTERSECT` and `EXCEPT`.

Dependency closure: no new support component is needed. The tests reuse the
lane-local SELECT SQL parser/executor, compound row combiner, BLOB value model,
and pure PHP test harness.
