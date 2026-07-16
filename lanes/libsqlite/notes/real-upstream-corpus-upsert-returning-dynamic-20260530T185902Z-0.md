# real-upstream-corpus-upsert-returning-dynamic-20260530T185902Z-0

Status: focused real upstream corpus PASS-case growth on launcher base
`49b5c4e4a088c53e02910590cc011ce37a3ffc52`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
  - Ported `upsert1-700` through `upsert1-780`: targeted UPSERT conflict
    priority across rowid and WITHOUT ROWID table shapes with `a`, `b`, and
    `e` unique constraints.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - Ported `upsert4-9.1`: trigger-maintained histogram rows using `INSERT ...
    ON CONFLICT DO UPDATE`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - Ported `returning1-23.1` and `returning1-23.2`: top-level `RETURNING`
    emits only the inserted row while recursive trigger side effects populate
    later rows.

Focused behavior:

- Adds `SQLiteRealUpstreamUpsertReturningDynamicTailTest.php`.
- Adds 2001 distinct TestRunner PASS cases and 4401 behavior assertions.
- The cases vary table mode, conflict-target order, seed row shape, incoming
  conflict shape, trigger histogram sequence, and recursive RETURNING depth.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicTailTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicTailTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicTailTest.php`
  - `1 test files, 4401 assertions, 0 failures`

Non-overlap:

- This does not repeat the earlier `upsert5.test` multi-arm matrix,
  `upsert2.test` / `upsert3.test` repeated-conflict and composite-target
  follow-up, `returning1.test` sections 4/17/20, trigger/view current-source
  RETURNING fences, row-value RETURNING, or recursive view UPSERT slices.
- The new surface is upstream `upsert1` targeted conflict priority plus
  upstream `upsert4` trigger UPSERT histogram and upstream `returning1`
  recursive-trigger RETURNING tail behavior.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP
  UPSERT conflict-arm and RETURNING projection helper.
