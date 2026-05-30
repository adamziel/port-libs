# jsonb-generated-index-operator-current-source-next107

Adds a current-source to next-source planner for generated JSONB expression
indexes declared with SQLite JSON path operators `->` and `->>`.

Behavior covered:

- parses JSON operator expression indexes with collation, descending flags, and
  partial-index predicates;
- evaluates current and next JSON text/JSONB rows through operator semantics;
- emits current delete entries and next insert entries for changed, inserted,
  deleted, activated, and deactivated rows;
- preserves canonical JSON values for `->` object fragments and SQL scalar
  values for `->>`;
- rejects missing rowid/source-column and non-JSON source inputs.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbGeneratedIndexOperatorCurrentSourceNext107Test.php`
- `php lanes/libsqlite/examples/application-jsonb-generated-index-operator-current-source-next107.php`

Non-overlap: this does not repeat accepted JSONB generated UPDATE/UPSERT/DELETE
maintenance, JSON table cursor/source/constraint behavior, JSON aggregate
window/DISTINCT handling, malformed JSONB path corpus, expression `ORDER BY`,
or expression-index range-cost planning. The new surface is current/next index
entry generation for JSON operator expression indexes over Application-style
JSONB option rows.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP JSONB codec, JSON path locator, JSON canonical encoder,
CREATE INDEX expression parser, and partial-index predicate model.
