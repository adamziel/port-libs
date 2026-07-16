# SQLite Upstream Runner Countability Current-Source Next112

- Slice: `upstream-runner-countability-gap-current-source-next112`
- Base accepted HEAD: `67b9065fe584e293134a85272e27bb677a0554af`
- Behavior: `currentSourceNextArtifactDirectoryRecord()` now keeps `counts_next_source=true` whenever at least one next-source zero-error artifact is countable, even if separate stale, manifest-mismatched, or missing-log artifacts remain blocked.
- Countability blocker removed: mixed current/next artifact directories no longer suppress clean next-source evidence solely because unrelated blocked artifacts are present; blocked labels, manifest mismatch labels, and missing-log labels remain explicit.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php` => `1 test files, 1031 assertions, 0 failures`.
- PASS delta: +1 `TestRunner` PASS case (`counts next-source artifacts while preserving blocked current-source evidence`).
- Dashboard delta: `phpPass` 43574 -> 43575; mapped coverage unchanged at `604 / 1589` because this removes a runner-countability blocker without adding a new upstream behavior inventory row.
- Non-overlap: avoids accepted batch106/107/108 behavior surfaces and does not duplicate runner106 suite-evidence rebase, JSON table rebase, VFS/WAL/B-tree/PRAGMA behavior clusters, or release-blocker closure ledger work.
- Dependency closure: no new support component needed; this composes existing bounded runner audit/log parsing and current/next accepted source provenance gates.
