# real-upstream-corpus-upsert-returning-dynamic-20260531T224735Z-0

Status: focused real upstream corpus PASS-case growth on launcher base
`33a65237308053a0654b3629f3bffe8d77c73515`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/qrf05.test`
  - Ported `qrf05-1.1`: `db format -style list` formats an `INSERT ...
    RETURNING *` row as list text.
  - Ported `qrf05-1.2`: a `NOT NULL` constraint failure is reported before any
    `RETURNING` row is formatted.
  - Ported `qrf05-1.3`: an unsupported query-result formatter version is
    rejected with `unusable sqlite3_qrf_spec.iVersion (99)`.

Focused behavior:

- Adds `SQLiteReturningQueryResultFormatterPlan`, a bounded native PHP model of
  QRF list formatting over `INSERT ... RETURNING` results.
- Adds `SQLiteRealUpstreamReturningQrfDynamicTest.php` with 1000 dynamic
  formatter cases plus source, malformed-input, non-overlap, and dependency
  closure guards.
- The cases vary generic table and column names while preserving the upstream
  success/error ordering from `qrf05.test`.

Non-overlap:

- This does not repeat accepted UPSERT conflict-arm priority, upsert5
  catch-all matrices, trigger RETURNING streams, prepared `changes2` counters,
  virtual-table `bestindexB` side effects, row-value RETURNING windows, or
  `returning1` name-resolution/trigger/foreign-key batches.
- The new surface is QRF list formatting and error ordering for an `INSERT ...
  RETURNING` statement.

Dependency closure:

- No new support component is needed. The slice adds a small native PHP
  formatter plan over existing row-array statement result behavior.

Verification:

- `php -l lanes/libsqlite/src/SQLiteReturningQueryResultFormatterPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteReturningQueryResultFormatterPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamReturningQrfDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamReturningQrfDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningQrfDynamicTest.php`
  - `1 test files, 25011 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output
