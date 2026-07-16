# SQLite malformed UTF-16 LIKE range current-source next97

## Scope

This handoff covers malformed UTF-16LE/UTF-16BE text bytes in copied
`wp_options.option_name` LIKE/GLOB range scans. It is intentionally distinct
from the accepted UTF-16 insert guard, Unicode GLOB range handling, and earlier
malformed UTF-8 pattern tests: this slice verifies current-source to next-source
range scans continue over valid rows while malformed UTF-16 rows are
quarantined with rowid, byte, and decoder-error evidence.

## Behavior

- `SQLiteMalformedLikeGlobSourceNextPlan` now exposes valid rowids and usable
  range bounds for the current and next source.
- Odd-length UTF-16 payloads, unpaired high surrogates, unpaired low
  surrogates, and high-surrogate/non-low-surrogate pairs are recorded as
  malformed rows without aborting the scan.
- LIKE `NOCASE`, case-sensitive LIKE `BINARY`, escaped literal LIKE, GLOB,
  Unicode GLOB ranges, leading-wildcard no-range cases, repaired rows, newly
  malformed rows, entered rows, exited rows, and retained rows are covered.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteMalformedUtf16LikeRangeCurrentSourceNext97Test.php`
  - `1 test files, 56 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-malformed-utf16-like-range-current-source-next97.php --self-test`
  - `application-malformed-utf16-like-range-current-source-next97 self-test passed`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP UTF-16
encoder/decoder, encoding source cursor, LIKE/GLOB matchers, and current/next
malformed source planner.
