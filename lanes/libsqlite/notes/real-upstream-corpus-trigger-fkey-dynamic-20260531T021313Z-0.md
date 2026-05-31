real-upstream-corpus-trigger-fkey-dynamic-20260531T021313Z-0

Base accepted HEAD: b8677cf94d5b050eacc055d83ba1f29b3739b6f1

Upstream source:
- /home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test, tests fkey2-4.1 through fkey2-4.4: recursive foreign-key CASCADE actions run even when ordinary recursive triggers are disabled.
- /home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test, tests fkey6-3.1 through fkey6-4.2: PRAGMA defer_foreign_keys defers RESTRICT enforcement until commit, and commit still rejects unresolved violations.

Patch:
- Added SQLiteSelfReferentialForeignKeyActionPlan for generic self-referential delete actions over row arrays.
- Added SQLiteRealUpstreamTriggerFkeySelfReferenceDynamicCorpusTest with 69 focused assertions over self-referential CASCADE, ordinary trigger recursion on/off, deferred RESTRICT, trigger reinsert repair, SET NULL/SET DEFAULT, and malformed dynamic trigger/FK inputs.

Non-overlap:
- Does not repeat the parked broad trigger/FK handoff that broke trigger/upsert gates.
- Does not add domain-specific APIs, examples, or fixture surfaces.
- Does not touch dashboard/root publication files.

Dependency closure:
- No new external support component is required. The slice uses existing lane PHP test runner support and a new generic native row-array helper under lanes/libsqlite/src.

Verification:
- php -l lanes/libsqlite/src/SQLiteSelfReferentialForeignKeyActionPlan.php
- php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeySelfReferenceDynamicCorpusTest.php
- php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeySelfReferenceDynamicCorpusTest.php
