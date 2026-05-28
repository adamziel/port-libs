## Suite Upstream Veryquick Shard Current Source Next290

Date: 2026-05-28

This isolated upstream-suite micro-slice does not launch a broad SQLite
`testfixture`, `make test`, `mptest`, `all`, or `release` run. It adds
`SQLiteUpstreamSuiteEvidence::upstreamVeryquickShardCurrentSourceNext290()`,
which admits one lane-local zero-error guarded veryquick shard row only when
the launcher Base accepted HEAD, accepted batch220 source heads, concrete
`.test` selections, duplicate-runner gate, removed-blocker classification, and
focused PHP PASS-line output all match the next290 evidence record.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from `680` to `681`. The slice records focused current-source veryquick
shard countability only; release/all parity remains gated on a separate
accepted zero-error broad artifact.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext290Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext290Test.php
git diff --check -- lanes/libsqlite
```

Result: focused `SQLiteUpstreamVeryquickShardCurrentSourceNext290Test.php`
passed with `1 test files, 1500 assertions, 0 failures`.

Dependency closure: no new support component is needed. This reuses lane-local
bounded runner metadata, accepted source provenance checks, active-runner
gating, and focused `TestRunner` output parsing.

Non-overlap: this avoids accepted next155 through next276 veryquick evidence,
exact-shard next148, queued runner106/jsonvt104 rebase work, accepted
batch109-113 and batch220 behavior surfaces, and live B-tree, JSON, VFS, WAL,
planner, PRAGMA, ATTACH, window, and VDBE work.
