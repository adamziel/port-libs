# Real upstream corpus date affinity dynamic

Slice: `real-upstream-corpus-date-affinity-dynamic-20260530T160256Z-0`

Update: `real-upstream-corpus-date-affinity-dynamic-20260530T164737Z-0`

Behavior:
- Ports a focused valid subset of hydrated upstream `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test` into PHP scalar-function coverage.
- Covers upstream scenario ids `date-1.1` through selected `date-1.23b`, `date-2.1` through selected `date-2.60`, `date-3.23` through `date-3.40`, timezone ids `date-5.1` through selected `date-5.12`, UTC-offset ids `date-6.25.1` through `date-6.26`, and standalone time ids `date-10.1` through `date-10.3`.
- Fixes `SQLiteCoreScalarFunction` date/time parsing for repeated `T`/space separators, standalone `HH:MM[:SS]` values defaulting to `2000-01-01`, and separated `Z` / `+HH:MM` / `-HH:MM` timezone suffixes while preserving existing guarded unsupported-modifier behavior.
- Adds one generic application expiry-bucket check proving numeric/text Unix-epoch affinity for date bucketing without domain-specific API or fixture names.
- Extends the hydrated upstream `date4.test` strftime libc-parity range from `date4-512` through `date4-1023`, following the same source-truth formula `TS = i*86390` and the upstream Linux format set with `%k`, `%l`, and `%P`.

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php`
  - Latest focused run: `1 test files, 1112 assertions, 0 failures`

Dashboard delta:
- `phpPass`: `188377 -> 188465` from the 88 newly passing focused PHP TestRunner cases.
- Additional expected focused PASS-line delta for the `20260530T164737Z` update is `+512` from the non-overlapping `date4-512` through `date4-1023` range.
- `mapped` coverage unchanged at `958 / 1589`; this patch does not claim new denominator rows or release/all parity.

Dependency closure:
- No new support component is needed. The patch reuses `SQLiteCoreScalarFunction` and the existing PHP test harness.

Non-overlap:
- Avoids suite-denominator burnup metadata, runner-map admission, window/WAL/B-tree/helper consolidation, and domain-shaped API work. The only source behavior is real SQLite date/time parser parity for hydrated upstream `date.test` cases.
- The extension avoids the already accepted `date4-0` through `date4-511` range and does not claim new denominator rows; it only adds real upstream behavior assertions from the same hydrated `date4.test` loop.
