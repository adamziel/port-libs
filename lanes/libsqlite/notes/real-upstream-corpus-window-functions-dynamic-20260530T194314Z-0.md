# real-upstream-corpus-window-functions-dynamic-20260530T194314Z-0

Status: ready for integration as a real upstream SQLite window-function corpus
batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowD.test`

Behavior covered:

- `windowD.test` `1.1` through `1.4`: view output from
  `cume_dist() OVER (PARTITION BY c0)` must remain visible while a wrapped
  `('500' IS TRUE) IS FALSE` predicate keeps the row.
- `windowD.test` `2.1` through `2.6`: constant TRUE/FALSE view columns and a
  `max(x) OVER ()` view column must not make `500 IS c` true.
- Dynamic expansion: 1,000 source-neutral generated application rows vary
  partition labels, literal storage class (`'500'` text versus `500` integer),
  and TRUE/FALSE target columns while preserving the exact upstream truth
  predicate shape.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowDTruthDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowDTruthDynamicTest.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement:

- `phpPass`: `469812 -> 470821` from 1,009 new focused TestRunner PASS cases.
- Focused behavior assertions: 5,015 in the new test file.
- `benchmarkDenominator.mapped`: unchanged at `1472 / 1589`; this is a real
  upstream behavior expansion within the already mapped window-function corpus
  family rather than a new denominator row.

Non-overlap:

- This slice targets `windowD.test` view/truth-predicate behavior only. It does
  not repeat accepted `window1`, `window2`, `window3`, `window4`, `window5`,
  `window6`, `windowA`, `windowB`, `windowC`, `windowE`, `windowpushd`, JSON
  table/window, row-value/window, compound/window, VDBE window, suite-evidence,
  WAL, VFS, B-tree, PRAGMA, trigger/FK, or source-neutral cleanup surfaces.

Dependency closure:

- No new support component is needed. The slice reuses native
  `SQLiteWindowFunction` and `SQLiteSelectPredicate` behavior.
