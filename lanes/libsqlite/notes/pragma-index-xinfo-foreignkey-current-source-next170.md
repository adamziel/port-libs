# PRAGMA index_xinfo / foreign-key current-source next170

## Behavior

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current-source wrapper over the accepted `index_xinfo` plus catalog-derived `foreign_key_list` path that keeps FK timing visible:

- annotates `foreign_key_check` rows with immediate vs deferrable initially-deferred timing;
- preserves `ON UPDATE`, `ON DELETE`, and `MATCH` metadata on violation rows;
- splits current/next violation counts into immediate, deferred, and commit-blocking buckets;
- reports timing-aware blockers as `immediate_foreign_key_check` and `deferred_foreign_key_check`;
- keeps accepted cursor/source validation, table-valued `pragma_index_xinfo`, parent-index admission, and action/deferrability extraction.

The Application smoke models a copied multisite `wp_options` import where one missing `wp_sites` parent is initially deferred, while option-name and group parents are immediate blockers. The repaired next source clears both immediate statement blockers and commit-time deferred blockers.

## Focused evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
53 PASS lines
1 test files, 63 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next170.php --self-test
application-pragma-index-xinfo-foreignkey-current-source-next170 self-test passed
```

## Dashboard expectation

`lane-status.json` `phpPass` moves from `76936` to `76989` from the 53 verified focused PASS lines. Mapped upstream coverage remains `611 / 1589`; this is focused PHP behavior over already mapped PRAGMA `index_xinfo`, `foreign_key_list`, and `foreign_key_check` inventory rather than a fresh manifest row.

## Non-overlap

This does not repeat accepted next159 catalog-derived FK extraction, next161/next163 implicit parent-column handling, next164 casefolded row keys, next165 action row annotation, or next166 action/deferrability source summaries. The new behavior is timing-aware violation row annotation and immediate/deferred blocker accounting for the current-source PRAGMA cursor.

## Dependency closure

No new support component is needed. The slice reuses the existing schema catalog, `PRAGMA index_xinfo`, `PRAGMA foreign_key_list`, parent-index admission, `foreign_key_check`, and action/deferrability helpers.
