# Real Upstream Corpus PRAGMA Schema Dynamic Journal State

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T010649Z-0`

Base accepted HEAD: `714d8628d70df34f443545659c4afed0ff8c4b1b`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- `pragma-1.1`
- `pragma-1.10` through `pragma-1.14.4`
- `pragma-2.2` through `pragma-2.4`
- `pragma-5.0` through `pragma-5.2`

Implemented behavior:

- `SQLitePragmaJournalState` now tracks a bounded transaction state.
- `PRAGMA synchronous = ...` inside an active transaction throws the upstream safety-level error and preserves the previous synchronous value.
- `commit()` and `rollback()` clear the bounded transaction state.
- The focused test batch also verifies synchronous keyword/numeric normalization, attached-schema synchronous isolation, and journal-mode schema rules for temporary, memory, WAL-capable, and non-WAL-capable schemas.

Focused evidence:

- First red run: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicJournalStateTest.php` produced `1 test files, 6924 assertions, 167 failures` due to an incorrect expectation for unqualified `journal_mode` propagation to attached schemas.
- Final focused run: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicJournalStateTest.php` passed with `1 test files, 7258 assertions, 0 failures`.
- New focused PASS lines: `1001`.
- Mapped denominator movement: none; mapped inventory remains `1589 / 1589`.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded `SQLitePragmaJournalState` component and adds the missing transaction guard required by upstream `pragma.test`.

Non-overlap:

- This does not repeat accepted PRAGMA5 virtual rows, PRAGMA schema catalog/table-valued introspection, cache/default-cache dynamic state, schema/user/data-version runtime state, WAL/VFS apply, or page-move/storage clusters.
