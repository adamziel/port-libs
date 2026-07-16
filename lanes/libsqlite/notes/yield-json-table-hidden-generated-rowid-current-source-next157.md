# JSON table hidden generated rowid current-source next157

## Behavior

Adds `SQLiteJsonTablePlan::currentSourceHiddenGeneratedRowidNext157()`, composing the accepted hidden path/generated-cost planner with a rowid alias intersection over the pinned current JSON table source. The slice covers `rowid`, `_rowid_`, and `oid` normalization, generated value filters, point rowid seeks, range/non-point rowid constraints, JSONB next-source preservation, SQL NULL unrunnable next-source handling, transition reasons, and reader-policy selection.

## Application smoke

`examples/application-json-table-hidden-generated-rowid-current-source-next157.php` models a copied `wp_options` plugin-setting preview where a `json_tree()` hidden path seek and generated `slug`/`priority`/`enabled` filters remain pinned to the current cache rule rowid while a next import mutates sibling JSON values.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableHiddenGeneratedRowidCurrentSourceNext157Test.php`
  - `1 test files, 80 assertions, 0 failures`
- Expected dashboard movement after clean integration: `phpPass` `69549 -> 69629` (`+80` focused PASS lines).
- Mapped upstream coverage unchanged at `607 / 1589`; this is current-source planner behavior coverage rather than a new manifest-mapped upstream unit.

## Non-overlap

Avoids accepted batch148 JSON table rowid hidden/generated behavior by adding the missing hidden-generated-rowid composition on the current source. It does not repeat parser-level JSON table SELECT sources/cursors, visible/hidden constraint extraction, generated-hidden-rowid `next142`, rowid-hidden-generated `next149`, or hidden-generated cost `next148`; it composes those accepted primitives with new rowid alias intersection state and transition evidence.

## Dependency closure

No new support component is needed. The patch reuses existing native JSON table planning, JSON path validation, JSONB input handling, generated value extraction, rowid alias normalization, and current-source transition machinery.
