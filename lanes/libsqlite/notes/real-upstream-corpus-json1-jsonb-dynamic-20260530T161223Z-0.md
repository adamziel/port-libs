# Real upstream JSON1/JSONB dynamic corpus

- Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260530T161223Z-0`
- Accepted base: `8bf0d9f81b29a5601901bb34dfd730670ed39bbc`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`

## Coverage Added

Added `SQLiteRealUpstreamJson1JsonbDynamicTest.php` with 108 focused assertions
ported from real upstream scenario names:

- `json101-1.1.*`, `json101-1.2`, `json101-1.3`, `json101-2.*`: JSON array/object constructor subtype, BLOB rejection, numeric-label rejection, and JSONB constructor parity.
- `json101-3.*`, `json101-4.5` through `json101-4.10`: mutation subtype distinction, duplicate path last-write behavior, no-op edit functions, validity, type, and root extraction behavior.
- `json102-100` through `json102-360`: documentation-derived constructor, array length, extract/multi-path, insert, replace, set, and JSONB text parity cases.
- `jsonb01-1.2.1` through `jsonb01-1.2.18`, `jsonb01-2.0`: JSONB remove object/member/array/reverse-index behavior and malformed JSONB rejection.

The batch is non-overlapping with accepted JSON table cursor/source/hidden and
visible constraint work because it exercises scalar JSON1/JSONB constructors,
inspection, extraction, mutation, and JSONB edit semantics directly.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicTest.php`
  - `1 test files, 108 assertions, 0 failures`

## Dependency Closure

No new support component is needed. This slice reuses existing native PHP JSON
helpers: `SQLiteJsonCanonical`, `SQLiteJsonConstructor`, `SQLiteJsonMutation`,
`SQLiteJsonInspection`, `SQLiteJsonExtract`, `SQLiteJsonRemove`, and
`SQLiteJsonB`.
