# Upstream veryquick shard current-source next374

Date: 2026-05-28

This isolated upstream-suite micro-slice does not launch a broad SQLite
`testfixture`, `make test`, `mptest`, `all`, or `release` run. It adds
`SQLiteUpstreamSuiteEvidence::upstreamVeryquickShardCurrentSourceNext374()`,
which admits one lane-local zero-error guarded veryquick shard row only when
the launcher Base accepted HEAD, clean-integrated batch225 source provenance,
concrete `.test` selections, duplicate-runner gate, removed-blocker
classification, and focused PHP PASS-line output all match the next374
evidence record.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from `740` to `741`. The slice records focused current-source veryquick
shard countability only; release/all parity remains gated on a separate
accepted zero-error broad artifact.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext374Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext374Test.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Expected result: the focused upstream-suite evidence test passes with one test
file, exact `96` TestRunner PASS lines, `1341` assertions, and no failures.
The admitted focused runner-output delta is exactly `96` PASS lines.

Dependency closure: no new support component is needed. This reuses lane-local
bounded runner metadata, launcher Base accepted HEAD provenance,
clean-integrated batch225 source provenance checks, active-runner gating, and
focused `TestRunner` PASS-line output.

Non-overlap: this avoids accepted next155 through next339 veryquick evidence,
exact-shard next148, queued runner106/jsonvt104 rebase work, accepted batch225
behavior surfaces, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/
VDBE work. It does not claim release/all parity.
