# Upstream veryquick shard current-source next213-228

Date: 2026-05-29

This isolated upstream-suite preparation slice does not launch a broad SQLite
`testfixture`, `make test`, `mptest`, `all`, or `release` run. It adds
`SQLiteUpstreamSuiteEvidence::upstreamVeryquickShardPreparedWindowMiddle()`,
which prepares current-source next213 through next228 suite evidence only when
every numeric slice has lane-local note provenance, guarded veryquick runner
metadata, concrete `.test` selections, current launcher/source heads, zero-error
artifact status, duplicate-runner clearance, and focused PHP PASS-line output.

Focused upstream denominator impact: no mapped count increase. The record keeps
`current_mapped` and `next_mapped` equal at `629`, and explicitly keeps release
parity and aggregate next213-228 countability unclaimed. Individual zero-error
shard rows remain the only mapped-count path until accepted by the integrator.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardPreparedWindowMiddleTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardPreparedWindowMiddleTest.php
git diff --check
```

Expected result: the focused upstream-suite evidence preparation test passes
with one test file and exact `128` TestRunner assertions. The admitted focused
runner-output delta is exactly `128` PASS lines.

Dependency closure: no new support component is needed. This reuses lane-local
bounded runner metadata, note paths, launcher Base accepted HEAD provenance,
integration source provenance checks, active-runner gating, and focused
`TestRunner` PASS-line output.

Non-overlap: this avoids accepted next197-212 suite evidence, individual
accepted next213/219/220/222/224/228 shard countability, exact-shard next148,
runner106/jsonvt104 rebase work, queued manifest-conflict work, and live
B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE behavior surfaces.
