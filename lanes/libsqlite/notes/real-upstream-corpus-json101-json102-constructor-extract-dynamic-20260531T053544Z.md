# Real Upstream Corpus JSON101/JSON102 Constructor Extract Dynamic

- Lane: `libsqlite`
- Micro-slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T053544Z-0`
- Base accepted HEAD: `4492e9529d6540daf2941a27323f36260b8cf64c`
- Upstream source files:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`

## Ported Behavior

Added `SQLiteRealUpstreamJson101102ConstructorExtractDynamicTest.php`, covering real upstream JSON1/JSONB behavior from:

- `json101.test` sections `1.1` through `4.10`: `json_array`, `json_object`, JSON subtype insertion, JSONB constructor parity, root extraction, path extraction, no-op/mutation behavior, and malformed constructor argument errors.
- `json102.test` sections `100` through `360`: plain text versus JSON subtype arguments, JSONB constructor parity, root/object/array extraction, multi-path extraction, `jsonb_extract`, `json_array_length`, `json_type`, and `json_insert`/`json_set`/`json_replace` text and JSONB parity.

The new file generates 130 distinct generic application JSON documents and exercises eight behavior cases per document, plus one malformed/citation case: `1041` focused TestRunner PASS lines and `4295` assertions.

## Non-Overlap

This does not repeat accepted JSON table cursor/source/hidden/visible constraint coverage, JSON501/JSON502 escaped stress, JSON103/104/105/106/109 mutation-specific dynamic files, JSONB remove-only coverage, JSON aggregate/window files, or WordPress-shaped scenarios. It stays on constructor/extract/inspection/mutation parity across generic documents using the hydrated upstream JSON101/JSON102 source files.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101102ConstructorExtractDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101102ConstructorExtractDynamicTest.php`
  - `1 test files, 4295 assertions, 0 failures`
  - `1041` focused PASS lines

## Dependency Closure

No new support component is needed. This reuses existing lane-local JSON constructor, JSONB, canonicalization, extraction, inspection, mutation, subtype, and blob primitives.

## Dashboard Movement

Expected integrator-countable movement is `+1041` PHP TestRunner PASS lines if accepted. Mapped denominator coverage is unchanged because the lane already reports `1589 / 1589` mapped upstream inventory.
