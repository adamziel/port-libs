# libsqlite-release-admission-current

Adds a current accepted-head release admission gate for release/all suite evidence.

- New behavior: `SQLiteUpstreamSuiteEvidence::releaseAdmissionCurrentRecord()` composes current release-runner countability, duplicate broad-runner detection, the release admission ledger, accepted-head provenance, and focused PHP TestRunner admission.
- Countable path: admits a zero-error release/all artifact only when it is tied to the current accepted repository head and matching SQLite manifest, has no active broad-runner conflict, and the focused PHP evidence is clean.
- Blocked path: keeps stale release artifacts, duplicate broad runners, and failed focused PHP output out of release parity and exposes the concrete blocker list.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php` passed with `1 test files, 3757 assertions, 0 failures`.
- Direct delta: 69 new focused assertions and 2 new TestRunner PASS cases in `SQLiteUpstreamSuiteEvidenceTest.php`.
- Dashboard expectation: `phpPass` may increase by 2 PASS lines from the new focused test cases; mapped coverage is unchanged because no new upstream inventory row is claimed.
- Dependency closure: no new support component needed; this reuses lane-local upstream-suite evidence parsing and admission gates.
- Non-overlap: avoids suite399-459 shard countability rows, release gap burnup next117, release countability next121, veryquick shard current-source rows, numbered-source consolidation, ordinary SQL/JSON/WAL/B-tree/VFS behavior helpers, and stale release ledger-only movement.
