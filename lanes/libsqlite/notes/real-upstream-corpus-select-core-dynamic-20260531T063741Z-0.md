# real-upstream-corpus-select-core-dynamic-20260531T063741Z-0

Base accepted HEAD: `e80280ab3ef4a3dc0e83a28a18647e19ca0381e1`.

Added `SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T063741ZTest.php`, a real upstream SELECT core dynamic corpus slice backed by hydrated SQLite upstream files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`
  - cited sections: `select1-2.*` core WHERE/ORDER/LIMIT row selection and result expressions, `select1-4.*` DISTINCT/ORDER/LIMIT behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test`
  - cited sections: `select2-1.*` and `select2-4.*` comma-join and WHERE expression behavior.

Focused movement:

- `1,002` distinct TestRunner PASS cases.
- `5,009` behavior assertions.
- No mapped denominator movement; mapped coverage is already complete at `1589 / 1589`.
- Expected dashboard movement: PASS-line growth only if accepted into the selected libsqlite corpus batch.

Non-overlap:

- Avoids accepted `selectC` alias resolution, `select5`/`select6` aggregate dynamic batch2, `select8` limit-offset, `select9` set operations, JSON table SELECT sources, expression ORDER BY, grouped SELECT SQL text, and subquery text executor slices.
- Uses generic `items` and `lookup` source tables only; no WordPress-specific source API or scenario names are introduced.

Dependency closure:

- No new support component is needed.
- Reuses the existing native `SQLiteSelectSql` parser/executor and the hydrated upstream SQLite test corpus.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T063741ZTest.php`
  - Result: `1 test files, 5009 assertions, 0 failures`.

Root harness: not run - isolated micro-slice.
