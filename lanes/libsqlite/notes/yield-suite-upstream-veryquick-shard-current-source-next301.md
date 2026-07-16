## Upstream Runner Evidence: veryquick shard current-source next301

Date: 2026-05-28

This isolated upstream-suite micro-slice did not launch a broad SQLite
`testfixture`, `make test`, `mptest`, `all`, or `release` run. It adds
`SQLiteUpstreamSuiteEvidence::upstreamVeryquickShardCurrentSourceNext301()`,
which admits one lane-local zero-error guarded veryquick shard row only when
the launcher Base accepted HEAD, current integration source heads, concrete
`.test` selections, duplicate-runner gate, removed-blocker classification, and
focused PHP PASS-line output all match the next301 evidence record.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from `683` to `684`. The slice records focused current-source veryquick
shard countability only; release/all parity remains gated on a separate
accepted zero-error broad artifact.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext301Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext301Test.php
git diff --check -- lanes/libsqlite
```

Result: focused `SQLiteUpstreamVeryquickShardCurrentSourceNext301Test.php`
passes with one selected test file, `1420` assertions, and `96` PASS lines.
The admitted focused runner row records an exact `96` PASS-line movement, so
`lane-status.json` `phpPass` moves from `137964` to `138060`.

Dependency closure: no new support component is needed. This reuses lane-local
bounded runner metadata, accepted source provenance checks, active-runner
gating, and focused `TestRunner` output parsing.

Non-overlap: next301 avoids accepted batch221 next277/next278/next279
veryquick evidence, earlier accepted next155 through next276 suite evidence,
exact-shard next148, queued runner106/jsonvt104 rebase work, and accepted
B-tree, JSON, VFS/WAL, planner, PRAGMA, ATTACH, window, and VDBE behavior
surfaces.
