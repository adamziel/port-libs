# PRAGMA index_xinfo foreign-key child suffix current-source next227

Timestamp: 2026-05-28T18:44:50Z
Base accepted HEAD: b9fcee36c556626531170fcc810da81f50a4b54c

## Behavior

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a PRAGMA catalog evidence helper for child foreign-key indexes whose FK columns are present in a non-partial index only after non-leading key terms. SQLite FK enforcement needs an index where child columns are the leftmost prefix; suffix-only indexes can look useful in copied WordPress import schemas but still force scans.

The WordPress smoke models `wp_postmeta_import` indexes such as `(meta_value, post_id, meta_key)` and `(autoload, site_id)`, then a repaired next schema using `(post_id, meta_key, meta_value)` and `(site_id, autoload)`.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
48 PASS lines
1 test files, 61 assertions, 0 failures
```

Expected lane-status movement: `phpPass` +48 from 108262 to 108310. No mapped upstream denominator change is claimed.

## Non-overlap

This slice avoids accepted PRAGMA integrity/quickcheck, parent unique permutation, parent collation, child prefix coverage, child prefix collation/order quality, and current-source next219 parent-key permutation surfaces. It reuses existing catalog helpers and adds only the suffix-only child-index blocker row family.

## Dependency Closure

No new support component is needed. The slice reuses bounded native PHP PRAGMA catalog support: `SQLitePragmaSchemaCatalog`, `PRAGMA foreign_key_list`, and `PRAGMA index_xinfo`.
