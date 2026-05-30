# encoding-collation-malformed-current-next56

## Behavior

`SQLiteDatabase::likeMatches()` and `SQLiteDatabase::globMatches()` now split malformed UTF-8 text the same way the selected SQLite pattern behavior needs for mixed valid/malformed input: valid UTF-8 codepoints stay intact, and malformed bytes are consumed one byte at a time.

This closes the current gap where a copied `wp_options.option_name` such as `plugin_é\xc3` failed `LIKE 'plugin_é_'` or `GLOB 'plugin_é?'` because the old fallback byte-split the entire invalid string, including the well-formed `é` prefix.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteMalformedTextPatternCurrentNext56Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 58 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-malformed-text-like-glob-current-next56.php --self-test
application-malformed-text-like-glob-current-next56 self-test passed
```

## Dashboard Delta

- `phpPass`: `20008 -> 20066` for the 58 new focused PASS lines in this isolated lane patch.
- `benchmarkDenominator.mapped`: unchanged at `462 / 1589`; this is focused PHP behavior coverage and does not claim a new upstream inventory unit.

## Non-Overlap

This avoids the accepted Unicode GLOB range slice, UTF-16 malformed record guard, LIKE prefix planning, collation predicate, SELECT expression `ORDER BY`, JSON table, WAL, VFS, and B-tree clusters. The behavior here is specifically malformed UTF-8 pattern splitting for LIKE/GLOB comparisons and SELECT predicate filtering.

## Dependency Closure

No new support component is needed. The patch reuses native PHP string handling plus existing UTF-8 validation paths and adds a bounded malformed-byte fallback inside libsqlite.
