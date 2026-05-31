# real-upstream-corpus-select-core-dynamic-20260531T044300Z-0

Base accepted HEAD: `ea98db4ecded4356aee592549997cc44a35fab5b`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`
- `select1-2.2` through `select1-2.17.1`: aggregate `count`, `min`, `max`,
  `sum`, scalar min/max over row values, aggregate arithmetic, `coalesce()`
  around aggregate results, NULL-skipping aggregate behavior, and long text
  min propagation.

Implemented focused coverage:

- Extended `SQLiteRealUpstreamSelectCoreYieldDynamicTest.php`.
- Added 1,020 distinct TestRunner PASS cases over 60 dynamic generic
  application-table seeds.
- Added 5,100 behavior assertions. Focused file moved from `7,503`
  assertions before this slice to `12,603` assertions after this slice.

Non-overlap:

- This slice owns the next `select1.test` aggregate result block for the
  existing SELECT-core yield file.
- It does not repeat accepted `selectD` parenthesized/USING join work,
  `selectC` alias-resolution coverage, `selectE`/`selectF` compound coverage,
  grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/
  constraint work, storage/VFS/B-tree behavior, metadata-only runner rows, or
  generated fake upstream script IDs.
- It adds no new domain-specific API or source text.

Red-first evidence:

- Initial widened attempt failed because bare `Count()` and three multi-count
  projections expose missing executor support:
  `SQLite SELECT SQL aggregate count needs one column or scalar expression
  argument` and `SQLite SELECT SQL GROUP BY supports one aggregate value
  column`.
- Those unsupported upstream cases are left as explicit follow-up blockers;
  the passing batch preserves only supported upstream aggregate semantics.

Dependency closure:

- No new support component is needed. This reuses the native PHP
  `SQLiteSelectSql` parser/executor and the hydrated upstream SQLite checkout
  as source truth.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreYieldDynamicTest.php`
  - Before: `1 test files, 7503 assertions, 0 failures`
  - After: `1 test files, 12603 assertions, 0 failures`
  - PASS-line delta: `+1020`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreYieldDynamicTest.php`
- `git diff --check -- lanes/libsqlite`

Root harness:

- Not run - isolated micro-slice.
