# JSON table numbered methods thirty-ninth pass

Consolidated one remaining JSON-table generated-path rowid production entrypoint
and its direct private helpers into a stable descriptive name on
`SQLiteJsonTablePlan`:

- `currentSourceGeneratedPathRowidXColumnCache`

The direct focused test and WordPress JSON-table smoke now call the stable
entrypoint. Existing payload keys and assertion labels remain unchanged so the
scenario coverage stays comparable while the production method/helper surface no
longer exposes the generated numeric method name for this family.

Verification:

- `php -l` on changed PHP files: passed.
- `php tools/run-tests.php` on the changed JSON-table test: passed with
  `1 test files, 67 assertions, 0 failures`.
- The changed WordPress JSON-table example passed with `--self-test`.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure: reuses the existing native JSON table generated-path rowid,
xCurrent, xColumn cache, JSONB decoder, and test runner components. No new
support component is needed.
