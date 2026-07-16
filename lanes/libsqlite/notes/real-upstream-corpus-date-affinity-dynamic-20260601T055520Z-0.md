# real-upstream-corpus-date-affinity-dynamic-20260601T055520Z-0

## Scope

- Lane: `libsqlite`
- Base accepted HEAD: `7db0bee1b6d6b17fcc1ae3a0e1b10ac7a87ade2d`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/timediff1.test`
- Ported coverage: `timediff-1.1..1.13` and `timediff-2.1..2.13`

This slice adds focused real-upstream coverage for leap-year and common-year month overflow in SQLite date/time modifier handling:

- `datetime(..., '+1 month')`, `datetime(..., '-1 month')`, `datetime(..., '+1 year')`, and `datetime(..., '+4 years')` around February overflow.
- Signed `+YYYY-MM-DD HH:MM[:SS]` and `-YYYY-MM-DD HH:MM[:SS]` date/time modifiers from `timediff1.test`.
- Dynamic follow-up coverage across 1200 generated leap/common month-overflow cases with `datetime`, `date`, `time`, `typeof`, `julianday`, and TEXT-affinity storage assertions.

## Non-Overlap

Owned upstream surface:

- `timediff1.test` section 1: February overflow on a leap year.
- `timediff1.test` section 2: February overflow on a non-leap year.

Avoided accepted or queued surfaces:

- Existing `timediff1.test` section 3 exact `timediff()` strings.
- Existing `timediff1.test` section 4 roundtrip matrix.
- Existing `timediff1.test` section 5 partial modifier grammar.
- Existing `timediff1.test` section 6 month-boundary roundtrip matrix.
- Existing date-affinity shards for `date4`, `date19`, `date20`, `date3`, and `date5`.
- Current handoff candidates for JSONB cleanup, row-value parity, and real VFS behavior.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTimediffMonthOverflow20260601T055520ZTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTimediffMonthOverflow20260601T055520ZTest.php`
  - `1 test files, 8587 assertions, 0 failures`
  - 1227 distinct TestRunner PASS cases

## Status Delta

- `phpPass`: `5618074 -> 5619301` (`+1227`)
- `phpFail`: unchanged at `7`
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`

## Dependency Closure

No new support component is needed. This slice reuses the existing native `SQLiteCoreScalarFunction` date/time modifier dispatch and `SQLiteRealExpressionAffinityCorpusPlan` TEXT-affinity storage behavior.
