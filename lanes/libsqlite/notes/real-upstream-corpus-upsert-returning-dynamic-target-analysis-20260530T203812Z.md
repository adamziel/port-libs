# Real upstream UPSERT RETURNING dynamic target analysis

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T203812Z-0`

Base accepted HEAD: `80c609b1de0bbfd42f2c3e021c00d868ce6dbc14`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`

Ported sections:

- `upsert4.test` sections `1.1` through `1.8`: primary-key and unique-column conflict handling, `DO NOTHING`, `DO UPDATE`, update conflict rejection, and tuple update row image behavior.
- `upsert4.test` section `2`: composite unique-index target analysis, including order-insensitive target matching and unmatched target rejection.
- `upsert4.test` section `3`: expression-index target matching and mismatched expression target rejection.
- `upsert4.test` section `4`: partial-index target matching and unmatched unqualified target rejection.
- `upsert4.test` section `6`: `ON CONFLICT` processing before `OR REPLACE` processing.
- `returning1.test` sections `4.2` and `4.5`: changed UPSERT rows are yielded through RETURNING, while `DO NOTHING` and failed conflict rows yield no RETURNING row.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicTargetAnalysisTest.php`
- Result: `1 test files, 2081 assertions, 0 failures`
- PASS cases: `1281` distinct TestRunner cases.

Non-overlap:

- This does not repeat the accepted broad `upsert2`, broad `returning1`, upsert5 priority, redundant-conflict, or correlated-delete handoffs. It adds the separate `upsert4.test` target-analysis cluster and ties it to RETURNING yield behavior for changed versus skipped rows.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded `SQLiteUpsertDoUpdateWherePlan` row-array UPSERT helper and focused PHP test runner.
