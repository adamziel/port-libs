# PRAGMA index_xinfo / foreign-key current-source next189

Status: focused PRAGMA/FK behavior growth for current-source next189.

Behavior:

- Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`.
- Extends the accepted PRAGMA `index_xinfo` plus foreign-key current/next chain with parent UNIQUE-index rejection rows for two SQLite parent-key blockers:
  - partial UNIQUE indexes surfaced by `PRAGMA index_list(...).partial = 1`;
  - expression UNIQUE indexes surfaced by `PRAGMA index_xinfo(...)` key rows with expression columns.
- Decorates missing parent-key rows with the rejected index name and rejection reason, and reports current/next deltas when a full column UNIQUE index replaces the unusable parent candidates.
- Adds a WordPress term/meta import smoke showing partial/expression parent indexes rejected before a repaired `wp_terms(slug COLLATE NOCASE)` UNIQUE index is admitted.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
55 PASS lines
1 test files, 61 assertions, 0 failures
```

Expected dashboard movement:

- `phpPass`: `90084 -> 90139` (`+55` focused PASS lines verified locally).
- `phpFail`: remains `0`.
- Mapped upstream coverage remains `616 / 1589`; this is focused PHP behavior coverage and does not claim a new manifest-backed upstream row.

Non-overlap:

- Avoids accepted next186 child-index collation repair, next185 null child-key exemption, next184 parent sort-order rows, next183 child-index prefix rows, next182 parent collation repair, and the batch173 accepted PRAGMA index_xinfo/foreign-key behavior.
- Does not repeat quick_check/integrity/rootpage/pointer-map PRAGMA clusters, recursive FK catalog output, or accepted trigger/FK cascade behavior.
- New surface is the parent-key admission reason when current schema exposes only partial or expression UNIQUE indexes through PRAGMA metadata, and next schema supplies a valid full parent UNIQUE index.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP schema catalog, PRAGMA `index_list`, PRAGMA `index_xinfo`, and foreign-key catalog parsing.
