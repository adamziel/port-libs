# suite-upstream-veryquick-shard-current-source-next293

Status: focused upstream-runner countability blocker removal for the current-source next293 veryquick shard.

Behavior:
- Adds a lane-local zero-error guarded-runner admission row for `veryquick-current-source-next293-*.test`.
- Ties the row to launcher Base accepted HEAD `483323e72c0dc81d1e479309afb9cdc0cf8f649e` and current integration source `8a447f445e5d2fd32fc9fd463117f585d1416551`.
- Preserves the accepted current-integration anchor without recounting next155 through next279 veryquick shards, exact-shard next148, runner106/jsonvt104 rebase work, or release/all parity.
- Records exact focused TestRunner admission of 96 PASS lines and maps one additional upstream denominator row, moving `683 / 1589` to `684 / 1589` when accepted.

Verification:
- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext293Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext293Test.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this composes lane-local artifact rows, authoritative source provenance, guarded zero-error veryquick metadata, duplicate-runner gates, and focused PHP PASS-line output only.

Non-overlap: avoids accepted suite next155 through next279 veryquick evidence, exact-shard next148, runner106/jsonvt104 rebase items, accepted batch221 behavior surfaces, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work.
