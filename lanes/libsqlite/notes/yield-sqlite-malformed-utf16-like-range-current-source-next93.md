# SQLite malformed UTF-16 LIKE range current-source next93

## Behavior

- Added `SQLiteMalformedUtf16LikeRangeCurrentSourceNextPlan` for Application-style
  `wp_options.option_name` LIKE prefix scans over current/next copied sources
  stored as UTF-16LE/UTF-16BE bytes.
- Valid UTF-16 rows are decoded, checked against accepted LIKE range bounds,
  and residual-matched with the existing LIKE matcher.
- Malformed UTF-16 rows are classified without aborting the whole current/next
  comparison, omitted from usable range rowsets, and reported as invalidation
  evidence (`malformed-utf16` and `omitted-malformed-range-row`).

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteMalformedUtf16LikeRangeCurrentSourceNext93Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 51 assertions, 0 failures
```

The focused run emitted 51 PASS lines for malformed UTF-16 odd-byte,
trailing-high-surrogate, unpaired-high-surrogate, unpaired-low-surrogate,
case-sensitive LIKE, current/next source invalidation, and Application option
row diagnostics.

## Application Smoke

Added `examples/application-malformed-utf16-like-range-current-source-next93.php`
to show copied `wp_options` rows where malformed UTF-16 option names are
omitted from LIKE prefix matches while valid current/next rows remain
countable.

## Non-overlap

This does not repeat accepted UTF-16 record-serialization guard work,
Unicode GLOB ranges, LIKE/GLOB source switching, or current-source cursor
range work. The new surface is tolerant current/next LIKE range comparison
when source rows already contain malformed UTF-16 bytes.

## Dependency Closure

No new support component is required. The slice reuses existing LIKE range
planning and LIKE residual matching, adding only bounded native PHP UTF-16
diagnostics in the libsqlite lane.
