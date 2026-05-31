# real-upstream-corpus-json103-aggregate-dynamic-20260531T000346Z

Base accepted HEAD: `dd1b1090c602dc6e35c0593d57edce4faedf25d2`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test`
- Covered scenarios: `json103-100` empty array aggregate, `json103-101` BLOB rejection, `json103-102` JSONB empty array aggregate, `json103-110` range array aggregation, `json103-120` grouped array aggregation, `json103-200` empty object aggregate, `json103-201` BLOB rejection, `json103-202` JSONB empty object aggregate, `json103-210` object aggregation, and `json103-400` window array aggregation.

Local behavior added:

- Added `SQLiteRealUpstreamJson103AggregateDynamicExpansionTest.php`.
- The file ports dynamic JSON aggregate coverage from the upstream `json103.test` seed table into focused PHP behavior tests over `SQLiteJsonAggregate`.
- Coverage varies range boundaries, modulo group selection, JSON object label/value subsets, empty aggregate JSONB/text parity, BLOB error boundaries, and window frame `ROWS n PRECEDING / m FOLLOWING` behavior.
- Non-overlap: this does not touch parser-level JSON table `FROM`/hidden/visible constraints, JSON table cursor/source wiring, json101/json102/json105/json109 path mutation clusters, JSON merge-patch clusters, or JSON table planner coverage already accepted.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson103AggregateDynamicExpansionTest.php`
  - `1 test files, 5325 assertions, 0 failures`
  - `1193` focused `PASS` lines

Dependency closure:

- No new support component needed. This reuses the existing native PHP JSON aggregate, JSONB, canonicalization, and inspection helpers.

Expected dashboard movement:

- Count as focused PHP PASS-line growth only: `+1193`.
- Mapped denominator coverage remains `1589 / 1589`.
