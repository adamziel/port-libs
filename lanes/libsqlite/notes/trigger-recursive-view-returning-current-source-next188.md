# Trigger Recursive View RETURNING Current Source Next188

- Slice: row-ordinal current-source RETURNING watermark admission after the accepted next185 nested-depth drain model.
- Behavior: next-source rows are visible only after the current view/trigger source generation is stable, nested recursive current-source RETURNING rows drain, the current watermark token matches, and every current-source RETURNING row ordinal is acknowledged without gaps.
- Application path: copied `wp_options` imports through recursive INSTEAD OF view triggers can hold a changed next view/trigger source until current RETURNING rows have been consumed by the importer cursor.
- Non-overlap: this does not repeat next184 checkpoint acknowledgements, next185 nested-depth epochs, row-value RETURNING, WAL, VFS, schema-reparse, or accepted trigger/FK cascade slices.
- Dependency closure: no new support component is needed; the slice reuses existing recursive view RETURNING source generation and nested-depth drain models.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext188Test.php` passes with 69 assertions / 0 failures after implementation.
