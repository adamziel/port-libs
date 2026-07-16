### 2026-05-27 release suite parity current-next18

Added a focused upstream-style PHP corpus file for SQLite release-suite scalar
parity around core math, planner-hint, compile-option, and deterministic
date/time dispatch:

- math domain/null behavior for `sqrt`, `ln`, `log`, `log2`, `pow`, `mod`,
  trigonometric functions, `ceil`, `floor`, `trunc`, `round`, `sign`, and
  `abs`;
- date/time modifier behavior for unixepoch parsing, `strftime()`,
  `start of` modifiers, `weekday N`, relative day/hour modifiers, and NULL
  propagation;
- compile-option introspection and planner likelihood wrappers.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseSuiteParityCurrentNext18Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 68 assertions, 0 failures
```

PASS-line delta: +68 focused TestRunner PASS cases. `phpPass` moves from 6121
to 6189 in this clean worktree status file. `benchmarkDenominator.mapped` is
unchanged because this slice adds focused PHP parity cases but does not admit a
new upstream inventory unit.

Non-overlap: this avoids accepted SELECT SQL text/JOIN/GROUP/subquery/ORDER
expression clusters, JSON table source/cursor/constraint work, WAL/VFS
transaction and rollback clusters, B-tree page/root/overflow work, Unicode
GLOB range behavior, and the batch16 SQL/JSON/storage handoff surfaces. The
slice is limited to release-suite scalar parity assertions not already covered
by `SQLiteUpstreamCorpusTest.php`'s small core scalar seed.

Dependency closure: no new support component is needed. The tests reuse the
existing native PHP `SQLiteCoreScalarFunction` dispatcher and require no
external SQLite extension, upstream binary, or live service.
