# real-upstream-corpus-date-affinity-dynamic-20260530T225109Z-0

- Base accepted HEAD: `dc9a740fd34e07dba61e9143b3604d183ad170bf`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`.
- Ported upstream scenarios: `date-2.2c-0` through `date-2.2c-999`.
- Focused behavior: `strftime('%H:%M:%f', real_unix_timestamp, 'unixepoch')`
  must preserve every millisecond fraction from `.000` through `.999`.
- Non-overlap: existing date/affinity corpus files cover earlier/follow-up
  date modifiers, Julian date edges, `types2`, and `e_expr` affinity behavior.
  This slice owns the full upstream `date-2.2c` millisecond loop only.
- Expected dashboard movement: `+1000` focused PHP TestRunner PASS cases if
  accepted.
- Dependency closure: no new support component is needed; this reuses the
  existing bounded `SQLiteSelectSql` date/time scalar execution path.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamic20260530T225109ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamic20260530T225109ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
