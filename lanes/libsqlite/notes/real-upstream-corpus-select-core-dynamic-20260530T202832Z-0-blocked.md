# real-upstream-corpus-select-core-dynamic-20260530T202832Z-0 blocked

Attempted upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test`
- `selectH-1.2` / `selectH-1.3`: omit unused `counter(1)` projection columns from a compound derived table when the outer query only needs `c44` and filters by `c60`.
- `selectH-2.1` / `selectH-2.2`: omit unused `counter(1)` projection columns from compound arms even when another projected column is used for `ORDER BY`.
- `selectH-3.1` through `selectH-3.7`: preserve omitted unused projection expressions through a compound view until the outer query explicitly selects the side-effect column.

Blocker:

- The current `SQLiteSelectSql::derivedTableReference()` path materializes a derived SELECT by calling `SQLiteSelectSql::execute($body, $tables)` before the outer query has supplied a required-column set.
- That eagerly evaluates every projection expression in each compound arm through `SQLiteSelectProjection::project()`.
- A direct probe of `selectH-1.2` with a generic single-row `t1` fails before assertions with `Unsupported SQLite core scalar function: counter`, because the unused `counter(1)` projection is evaluated while materializing the derived table.
- A correct fix needs planner support for required-column propagation into derived-table and compound-arm projection, with explicit preservation for columns referenced by outer `WHERE`, `ORDER BY`, `GROUP BY`, `HAVING`, wildcard expansion, and derived-table column aliases.

Why this is not a ready patch:

- The real upstream-corpus hard floor requires at least 1,000 distinct focused PASS cases, 5,000 behavior assertions, a blocker fix that proves it unlocks at least 2,000 PASS cases / 10,000 assertions in the next admitted batch, or mapped denominator movement.
- This slice found a central SELECT planner blocker, but a safe implementation is larger than a bounded micro-edit because the planner currently builds the source before parsing the outer select list.
- Shipping a small note-plus-test or a custom `counter()` shim would not satisfy upstream `selectH.test` semantics and would risk metadata-only PASS inflation.

Next larger batch to try:

- Add required-column analysis to `SQLiteSelectSql::plan()` before `sourcePlan()` materializes derived tables.
- Thread the required set into `derivedTableReference()` and `executeCompoundPlan()` so compound arm select lists can drop expressions not needed by outer predicates, output projection, ordering, grouping, or wildcard expansion.
- Use `selectH.test` sections `1.2` through `3.7` as the red/green corpus, then expand with dynamic column-count and compound-arm variants to reach the real-corpus floor.

Verification:

- No PHP source or test file was changed.
- Dependency closure: no new support component is needed; this is a planner/executor dependency inside existing `SQLiteSelectSql` and `SQLiteSelectProjection`.
