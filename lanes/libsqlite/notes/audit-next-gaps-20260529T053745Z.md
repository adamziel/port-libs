# libsqlite audit: next highest-yield gaps

Audit worktree: `.tmux-team/worktrees/libsqlite-audit-gaps-20260529T053745Z`  
Base ref: `841f9e58fdcd137ff784d157173e52f4d5beeaed`  
Scope: audit note only; no implementation or coordination-file changes.

## Snapshot evidence

- `goal.md` still defines libsqlite as a pure PHP SQLite database-file reader/writer plus low-level SQLite primitives, with upstream-denominator honesty and no bridge/shell-out progress claims.
- `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json` has a real upstream anchor (`sqlite/sqlite`, commit `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`, version `3.54.0`) and a large static inventory (`testDirectoryTclTests: 1189`, `extensionTclTests: 278`, `staticCommonDoTestCommandLines: 51981`, `permutationSuitesDeclared: 58`). The same source text repeatedly qualifies recent rows as focused current-source/veryquick-shard evidence and explicitly says many slices make "no release/all parity claim" or "without claiming fresh upstream execution".
- Lane size is now consolidation-risky: `find lanes/libsqlite/src -name '*.php' | wc -l` => `859`, `find lanes/libsqlite/tests -name '*.php' | wc -l` => `2711`.
- Variant-plan density is high: `find lanes/libsqlite/src -name '*Plan.php' | wc -l` => `630`; `find lanes/libsqlite/src -name '*Current*Next*Plan.php' | wc -l` => `443`; `find lanes/libsqlite/tests -name '*Current*Next*Test.php' | wc -l` => `2586`.
- Several mega-files now dominate behavior and review surface:
  - `lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php`: `29840` lines.
  - `lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`: `28794` lines.
  - `lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan.php`: `7634` lines.
  - `lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`: `10680` lines.
  - `lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`: `31648` lines.
  - `lanes/libsqlite/tests/SQLiteHeaderTest.php`: `26975` lines.

## Highest-yield functional gaps

1. **Replace source-next variant accretion with stable primitives.**
   Evidence: WAL, B-tree, pager, row-value/window, and encoding/collation work is concentrated in giant `*CurrentSourceNextPlan.php` files with many numbered entry points, for example `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php`, `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`, and repeated examples requiring/calling the same plan class. This is useful audit evidence, but it is not yet a maintainable SQLite primitive surface.
   Next slice: extract one stable primitive boundary from the densest cluster, starting with either B-tree freeblock/pointer-map mutation or WAL checkpoint/savepoint reader handoff. Keep the existing current-source tests as regression tests, but make them call the stable primitive through thin adapters.
   Suggested focused tests: the latest nearby `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext*Test.php` group or `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext*Test.php` group, plus the matching `examples/application-btree-vacuum-pointermap-freeblock-*.php` or `examples/application-wal-hot-journal-savepoint-checkpoint-*.php`.

2. **Centralize SQLite numeric, affinity, and storage-class comparison semantics.**
   Evidence: numeric coercion and comparison are implemented in multiple places with materially different rules:
   - `lanes/libsqlite/src/SQLiteSelectExpression.php:586` through `:668` parses arithmetic operands with `numericPrefix()` and returns `0` for non-numeric text.
   - `lanes/libsqlite/src/SQLitePragmaForeignKeyCheck.php:203` through `:213` uses PHP `is_numeric(trim($value))`.
   - `lanes/libsqlite/src/SQLiteVdbeWindowAggregateCursor.php:918` through `:928` accepts only bool/int/float for RANGE frame keys.
   - `lanes/libsqlite/src/SQLiteIndexLeafPage.php:544` through `:554` compares record values with raw PHP `<=>`.
   Risk: SQLite comparison, arithmetic conversion, FK affinity, index ordering, and window RANGE behavior can diverge silently for strings like `1e2`, `01`, `1abc`, blobs, infinities, signed zero, integer-overflow text, and mixed storage classes.
   Next slice: introduce a single internal comparator/coercion utility for storage class ordering, numeric affinity, arithmetic numeric-prefix parsing, and collation handoff. First wire it into `SQLiteIndexLeafPage`, `SQLitePragmaForeignKeyCheck`, and one SELECT/window path while preserving existing public APIs.
   Suggested focused tests: `SQLiteAffinityComparisonStorageClass*`, `SQLiteEncodingCollationAffinityLikeCurrentSourceNext*`, `SQLiteVdbeSorterAffinityWindowCurrentSourceNext*`, `SQLitePragmaForeignKey*`, and one index-leaf page test. Add edge cases that compare PHP output against accepted SQLite behavior, not just internal expectations.

3. **Convert upstream-suite evidence from admission records into actionable denominator slices.**
   Evidence: `UPSTREAM_TEST_MANIFEST.json` has a credible inventory, but the current merged head still leans on many "runner-countability blocker row" and "no release/all parity claim" entries. The manifest maps lots of focused rows, but the next work should identify which upstream TCL scripts or command-line families are still unimplemented versus merely unrun.
   Next slice: create a small machine-readable "uncovered top upstream families" artifact for libsqlite, derived from the manifest inventory and current test names. Use it to pick the next functional implementation slice instead of another numbered current-source runner note.
   Suggested focused tests/tools: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext*.php` for the latest runner-admission classes, then a new focused test proving the artifact classifies at least btree, pager/WAL, select/expr, pragma/schema, json/jsonb, and vfs families.

4. **Reduce duplicated evidence examples before they become false confidence.**
   Evidence: repeated example files and repeated `require_once` lines exist in the current-source examples; e.g. `examples/application-pager-master-journal-reader-cache-current-source-next189.php`, `...next192.php`, and `examples/application-rowvalue-returning-window-current-source-next250.php` contain multiple `require_once` references to the same plan class. This is minor functionally, but it signals generated or hand-duplicated evidence that can mask missing canonical examples.
   Next slice: add one canonical example per stable primitive family after extraction, then mark numbered source-next examples as regression fixtures. Do not delete useful fixtures yet; first add a note or naming convention that separates canonical Application examples from source-next admission artifacts.
   Suggested focused examples: one B-tree mutation example, one WAL checkpoint/savepoint example, one SELECT/affinity example, one JSON table/index example.

## Duplicate/consolidation risk ranking

1. **Critical:** `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php` and `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php` are too large to review safely as functional primitives. Extract stable service classes before adding more numbered methods.
2. **High:** Numeric/affinity/comparison semantics are fragmented across SELECT, PRAGMA/FK, VDBE window, b-tree/index page, planner/stat4, LIKE/GLOB cursors, and row-value/update paths. Centralize before more planner and index behavior is built on inconsistent ordering.
3. **High:** Runner/evidence admission work is extensive, but many manifest rows explicitly do not claim fresh upstream execution. The next upstream-denominator slice should classify uncovered families and choose an implementation target, not add another admission ledger row.
4. **Medium:** `SQLiteHeaderTest.php` and `SQLiteUpstreamSuiteEvidence.php` are now audit liabilities by size. Split new coverage into focused files and avoid adding unrelated assertions to those files.

## Assignable next slices

1. **B-tree primitive extraction:** create `SQLiteBTreePointerMapFreeblockMutator` or equivalent, migrate one current-source variant path, and run the migrated B-tree current-source tests plus `php -l` on touched PHP files.
2. **Numeric semantics utility:** create a central storage-class/numeric-affinity comparator, migrate FK + index leaf + one SELECT/window path, and add explicit edge-case tests for text numeric prefixes, integer overflow text, blobs, nulls, and collations.
3. **Upstream gap classifier:** produce a lane-local note or JSON artifact listing uncovered upstream families from the manifest inventory, with a focused test that prevents broad-runner admission rows from being counted as implementation parity.
4. **Canonical examples pass:** add or rename canonical examples for stable primitives while leaving numbered current-source artifacts as regression fixtures; run the touched examples as self-tests where they return/assert values.

## Validation for this audit note

No PHP implementation files were changed. Required validation for this note is `git diff --check`; no `php -l`, `composer dump-autoload`, or focused PHP test run is applicable unless a follow-up slice changes PHP/autoload behavior.
