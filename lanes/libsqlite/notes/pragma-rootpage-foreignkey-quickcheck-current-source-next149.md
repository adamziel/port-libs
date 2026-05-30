# PRAGMA rootpage foreign-key quickcheck current-source next149

Slice: `pragma-rootpage-foreignkey-quickcheck-current-source-next149`.

This patch makes the existing current/next quickcheck-rootpage plus
foreign-key-rootpage composer honor SQLite's numeric `PRAGMA quick_check(N)` and
`PRAGMA quick_check = N` forms. The quickcheck rootpage phase is capped before
the foreign-key phase is appended, so a Application import preflight can bound
rootpage diagnostic volume without hiding `foreign_key_check` blockers.

Focused behavior:

- `quick_check(2)` emits only two quickcheck rootpage rows before the FK rows;
- `quick_check = 3` uses the same bounded database-scope behavior;
- unbounded `quick_check` still emits all rootpage diagnostics;
- the numeric limit participates in current/next source identity and stale
  cursor rejection;
- repaired next images can clear both bounded quickcheck diagnostics and all FK
  rootpage rows, while dirty next schemas still block on FK violations.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaRootpageForeignKeyQuickcheckCurrentSourceNext149Test.php`
  - `1 test files, 65 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-rootpage-foreignkey-quickcheck-current-source-next149.php --self-test`
  - `application-pragma-rootpage-foreignkey-quickcheck-current-source-next149 self-test passed`

Non-overlap:

This does not repeat accepted next142/next145 quickcheck plus FK/rootpage
composition, next140 FK rootpage repair deltas, next143 index-list rootpage
repair, or accepted pointer-map/FK pagination. The new surface is numeric
`quick_check` limiting inside the combined current-source stream.

Dependency closure:

No new support component is needed. The slice reuses lane-local rootpage
analysis, attached schema catalog, pointer-map, quickcheck, and
foreign_key_check primitives.
