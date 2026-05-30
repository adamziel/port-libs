# Real Upstream Trigger/FK Dynamic Check Corpus

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T224905Z-0`

Base accepted HEAD: `dc9a740fd34e07dba61e9143b3604d183ad170bf`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test`
- Ported sections: `fkey5-5.0..13.12`, focused on `PRAGMA foreign_key_check`, parent collation, composite key order, NULL child key suppression, schema-qualified checks, and `WITHOUT ROWID` child rowid reporting.

Local changes:

- Added `SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCheckCollationPlan()` to model upstream `fkey5.test` behavior for result rows and parent-key comparison semantics.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicCheckCorpusTest.php` with 180 dynamic datasets over binary, nocase/rtrim composite, and `WITHOUT ROWID` foreign-key-check cases.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`: pass.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCheckCorpusTest.php`: pass.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCheckCorpusTest.php`: `1 test files, 5589 assertions, 0 failures`.

Expected movement:

- New focused PASS cases: `5583`.
- Previous selected `phpPass`: `991889`.
- Expected selected `phpPass` after acceptance: `997472`.
- Mapped denominator remains `1589 / 1589`; this is selected PASS-line growth, not new mapped inventory.

Dependency closure:

- No new support component is needed. The slice reuses existing generic libsqlite trigger/FK planning support and the hydrated upstream SQLite test cache.

Non-overlap:

- Avoids accepted trigger/FK action matrix, fkey6 defer-pragma/restrict repair, fkey2 nocase trigger repair, trigger5 undo, and application WAL rollback JSON parity surfaces. This slice is specifically `fkey5.test` foreign-key-check row production and parent-collation/composite comparison behavior.
