# Real upstream JSON1/JSONB dynamic high-yield batch

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260530T185014Z-0`

Base accepted HEAD: `133a53d7c328acb7a2a9f5b43747e45d705421ba`

Added `SQLiteRealUpstreamJson1JsonbDynamicHighYieldTest.php`, a focused PHP
corpus batch sourced from hydrated upstream SQLite files:

- `json101.test`: top-level JSON validity, no-edit JSON functions, scalar root
  extraction, and object/array canonical behavior from `json101-4.*`.
- `json102.test`: `json_array_length`, `json_type`, and mutation behavior from
  `json102-190..390`.
- `json105.test`: reverse array path extraction from `json105-1.*`.
- `json107.test`: JSON BLOB input compatibility behavior from `json107-1.*`
  and `json107-2.*`.
- `json108.test`: `json_pretty` canonical round-trip behavior from
  `json108-1.*`.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicHighYieldTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS real upstream JSON1 JSONB high yield json101 valid top-level values
PASS real upstream JSON1 JSONB high yield json101 no-edit functions preserve documents
PASS real upstream JSON1 JSONB high yield json102 array length and type parity
PASS real upstream JSON1 JSONB high yield json102 mutation parity
PASS real upstream JSON1 JSONB high yield json105 reverse path extraction parity
PASS real upstream JSON1 JSONB high yield json107 blob text compatibility
PASS real upstream JSON1 JSONB high yield json108 pretty canonical parity
PASS real upstream JSON1 JSONB high yield source coverage cites hydrated upstream files

1 test files, 10641 assertions, 0 failures
```

This handoff is countable as behavior-assertion growth: `8` focused TestRunner
PASS cases and `10,641` behavior assertions. It does not move mapped upstream
denominator coverage.

Non-overlap: this is not the accepted JSON103 aggregate, JSONB remove,
JSON102/JSON1 no-edit small batch, JSON visible/hidden constraint pushdown,
JSON table source/cursor, or JSON table host-join work. It broadens the
existing real JSON1/JSONB corpus with high-volume assertions over distinct
real upstream sections.

Exclusion/follow-up: while validating the batch, the current PHP JSONB
representation collapses empty objects inside JSONB decode/canonical paths to
empty arrays (`jsonb('{}')` and arrays containing `{}`). Those assertions are
not claimed in this handoff. A follow-up behavior fix can target empty-object
JSONB object-vs-array preservation directly.

Dependency closure: no new support component is needed; this reuses existing
native PHP JSON1/JSONB helpers.
