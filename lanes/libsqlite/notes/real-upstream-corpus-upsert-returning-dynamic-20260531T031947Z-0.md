# real-upstream-corpus-upsert-returning-dynamic-20260531T031947Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsertfault.test`
  - Section `1`: `CREATE TABLE t1(a PRIMARY KEY, b, c, d, UNIQUE(b, c))`
    followed by `INSERT INTO t1 VALUES(3, 2, 2, NULL) ON CONFLICT(b, c) DO
    UPDATE SET d=d+1`.

## Ported Behavior

- Added `SQLiteRealUpstreamUpsertReturningCompositeFaultDynamicTest.php`.
- Ports the real upstream composite `UNIQUE(b,c)` UPSERT update behavior into
  generic `app_settings`-style row arrays with `setting_id`, `tenant_id`,
  `key_name`, and `revision`.
- Adds `RETURNING` row-image checks around the upstream update behavior:
  the existing row matched by `(tenant_id,key_name)` is updated, the incoming
  primary key is ignored, one post-update row is returned, and repeat execution
  is deterministic like the upstream fault-retry expectation.

## Focused Count

- 1000 dynamic upstream-derived seeds.
- 5002 focused TestRunner PASS cases in the new file.

## Non-overlap

- Does not repeat accepted `upsert4`, `upsert5`, `upsert2`, literal
  `excluded`, AUTOINCREMENT, `returning1-17`, `returning1-20`, trigger
  histogram, recursive trigger, replace-precedence, or hex-yield UPSERT
  RETURNING clusters.
- This slice owns the `upsertfault.test` composite unique conflict/update
  behavior and adds only generic application terminology.

## Dependency Closure

- No new support component is needed. The slice reuses the existing native
  composite UPSERT conflict executor and `RETURNING` row-image projection.
