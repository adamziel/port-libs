# Upstream veryquick shard current-source next232

Date: 2026-05-28

This isolated upstream-suite micro-slice does not launch a broad SQLite
`testfixture`, `make test`, `mptest`, `all`, or `release` run. It adds
`SQLiteUpstreamSuiteEvidence::upstreamVeryquickShardCurrentSourceNext232()`,
which admits one lane-local zero-error guarded veryquick shard row only when
the launcher Base accepted HEAD, current integration source heads, concrete
`.test` selections, duplicate-runner gate, removed-blocker classification, and
focused PHP PASS-line output all match the next232 evidence record.

Focused upstream denominator impact: `UPSTREAM_TEST_MANIFEST.json` mapped count
moves from `631` to `632`. The slice records focused current-source veryquick
shard countability only; release/all parity remains gated on a separate
accepted zero-error broad artifact.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext232Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext232Test.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Result: the focused upstream-suite evidence test passes with one test file,
`1582` assertions, `0` failures, and exact `100` TestRunner PASS lines. The
admitted focused runner-output delta is exactly `100` PASS lines.

Dependency closure: no new support component is needed. This reuses lane-local
bounded runner metadata, launcher Base accepted HEAD provenance, integration
source provenance checks, active-runner gating, and focused `TestRunner` output
parsing.

Non-overlap: this avoids accepted next155/157/159/161/164/166/167/169/171/
172/173/174/175/176/177/178/181/184/187/190/194/200/202/209/212/213/219/
220/222/224/225/226/227 veryquick evidence, exact-shard next148, queued
suite217 stale evidence, runner106/jsonvt104 rebase work, accepted batch107/108
and batch109-113 behavior surfaces, and live B-tree/JSON/VFS/WAL/planner/
PRAGMA/ATTACH/window/VDBE work.
