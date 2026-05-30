# JSONB Patch Indexed Generated Current Next27

Adds a bounded planner helper for indexes over generated columns and direct
expressions shaped like `json_extract(jsonb_patch(option_value, PATCH), PATH)`.
The slice covers JSON5 merge-patch canonicalization, generated-column lookup,
partial-index proof through ordinary `autoload` predicates, expression-index
matching, covering metadata, ORDER BY compatibility, and malformed patch
rejection.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbPatchIndexedGeneratedCurrentNext27Test.php`
  - Result: `1 test files, 53 assertions, 0 failures`
  - PASS-line delta: `+53`
- `php lanes/libsqlite/examples/application-jsonb-patch-indexed-generated-current-next27.php`
  - Result: emitted `chosenIndex: wp_options_patch_enabled_autoload`,
    `generatedColumn: plugin_enabled`, canonical patch JSON, and
    `jsonPath: $.plugin.enabled`.
- `php -l lanes/libsqlite/src/SQLiteJsonbPatchGeneratedIndexPlan.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteJsonbPatchIndexedGeneratedCurrentNext27Test.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/examples/application-jsonb-patch-indexed-generated-current-next27.php`
  - Result: no syntax errors.
- `git diff --check -- lanes/libsqlite`
  - Result: no whitespace errors.

Dependency closure: no new support component is needed; this reuses the native
JSONB codec, JSON5 parser, merge-patch helper, JSON path validator, and
lane-local planner metadata.

Non-overlap: avoids accepted JSON table cursor/source/hidden/visible
constraints, JSON boolean operators, generic JSON scalar patch dispatch,
expression ORDER BY, expression-index range-cost, B-tree, WAL, and VFS clusters.
