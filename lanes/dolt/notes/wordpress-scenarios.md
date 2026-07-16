# dolt WordPress Scenario

Versioned content/data migrations and inspectable database change sets.

## Current Native Slices

- Native table diff classification and Dolt-style `DOLT_DIFF_*` row projection by primary key.
- Native schema/tag comparison for column additions, drops, renames, type changes, primary-key movement, and constraints.
- Native table-delta matching that distinguishes exact-name changes, tag-overlap renames, drops, and adds.
- Native schema-aware row projection that maps historical rows into a target diff schema and reports Dolt-style warnings for coercion and primary-key-set changes.
- Native skinny diff projection that hides unchanged same-type columns while preserving primary keys, changed columns, added columns, and reviewer-requested `--include-cols`.
- Native projected row filtering that applies Dolt-style `--where` predicates and limits after diff rows are shaped.
- Native `dolt_diff_summary()` and `dolt_diff_stat()` projections for table-level review rows and aggregate row/cell counts.
- Native `dolt diff --summary` fixed-width CLI text rendering plus `--name-only` table-name output for table-level review queues, including upstream `--filter` values and the `removed` alias for dropped tables.
- Native `dolt diff --summary [tables...]` table-argument rendering, including upstream's current short-circuit behavior where a later changed table can produce successful empty output if an earlier changed table is outside the requested table set.
- Native `dolt diff --stat [tables...]` CLI text rendering for row/cell impact review, including upstream's different table-argument behavior that scans past earlier unrequested changed tables and the schema-only no-data message.
- Native `dolt diff --stat -r json` rendering for machine-readable row/cell impact review, including successful empty output for unchanged requested tables and empty `stats` objects for schema-only no-row diffs.
- Native keyless `dolt diff --stat` text and JSON rendering for duplicate/cardinality-based import logs where Dolt reports row inserts/deletes instead of modified cells.
- Native keyless `dolt_diff()` row projection plus `dolt diff -r sql` and tabular rendering for duplicate/cardinality-based import logs, where duplicate count changes become repeated added/removed rows and SQL deletes predicate on every keyless column.
- Native `dolt_patch()` row projection for schema/data SQL patch queues, including schema-before-data ordering, schema/data partition filters, focused CREATE/DROP/ALTER DDL, keyed INSERT/UPDATE/DELETE statements, and keyless duplicate-cardinality INSERT/DELETE statements.
- Native `dolt_patch()` table-function call parsing for explicit from/to refs, `A..B` ranges, merge-base-backed `A...B` ranges, same-ref `WORKING`/`STAGED` no-op rows, case-insensitive requested-table lookup, known unchanged-table empty results, table-not-found errors, and non-literal argument rejection.
- Native `dolt_patch()` ref resolution for supplied commit graphs, including branch/tag/ancestor specs resolving to commit hashes in patch rows, same-hash branch/tag comparisons returning no rows, `WORKING` / `STAGED` labels remaining materialized working-set roots, and upstream-shaped missing branch/hash errors.
- Native `dolt_patch()` revision snapshot materialization for supplied HEAD/STAGED/WORKING table roots, including forward and reverse schema/data patch rows, same-ref empty output, known unchanged-table empty output, and resolved HEAD commit hashes in patch rows.
- Native `dolt_patch()` SELECT privilege checks for revision databases, including base database extraction from names like `wp_review/review-working`, table-specific reviewer grants, database-wide reviewer grants, all-table checks for unscoped patch calls, and authorization before same-ref no-op output.
- Native `dolt_patch()` binary SQL rendering for media-library hashes and fingerprints stored in `binary` / `varbinary` columns, matching upstream `0x...` patch literals instead of quoted text.
- Native `dolt_patch()` primary-key-change warning collection for staged/worktree snapshots, preserving schema patch rows while skipping unsafe data SQL and recording upstream warning code `1235`.
- Native `dolt_patch()` secondary-index and foreign-key DDL rendering for staged/worktree snapshots, including `CREATE TABLE` KEY/CONSTRAINT clauses, index/foreign-key-only schema deltas, and upstream ordering of `ADD INDEX` before `ADD CONSTRAINT`.
- Native `dolt_patch()` modified/drop secondary-index and foreign-key DDL rendering, including upstream `DROP INDEX` / `ADD INDEX`, `DROP FOREIGN KEY` / `ADD CONSTRAINT`, and ALTER ADD foreign-key action omission while preserving actions in CREATE TABLE definitions.
- Native `dolt_patch()` create-table check-constraint rendering, including upstream `CONSTRAINT ... CHECK (...)` clause placement after keys and foreign keys.
- Native `dolt_patch()` table-collation rendering, including upstream `ALTER TABLE ... COLLATE='<collation>'` rows before data patches and `CREATE TABLE` default charset/collation metadata.
- Native `dolt_patch()` target-row-size rendering, including upstream `ALTER TABLE ... TARGET_ROW_SIZE=<bytes>` rows after collation DDL and before data patches.
- Native `dolt_patch()` default, generated, stored generated, and `ON UPDATE` column rendering, including upstream-shaped quoted default literals, generated expression wrapping, and timestamp update clauses in CREATE/ADD/MODIFY column patch rows.
- Native `dolt_patch()` metadata-only column and existing-table check-constraint boundaries, including upstream's no-row behavior for default/generated/on-update/not-null changes without a type change and for add/modify/drop check constraints outside `CREATE TABLE`.
- Native `dolt_patch()` auto-increment rendering for WordPress-style primary keys, including create-table `AUTO_INCREMENT` columns, metadata-only no-row boundaries, and primary-key type-change DDL replacement.
- Native check-constraint validation and `information_schema` projection for migration schema review, including SQL-style `NULL` unknown pass behavior, skipped `NOT ENFORCED` checks, `CHECK_CONSTRAINTS` rows, and `TABLE_CONSTRAINTS` CHECK/PRIMARY/FOREIGN rows.
- Native `dolt_constraint_violations` table-of-tables and `dolt_constraint_violations_<table>` row projection for merge/verify review, including `from_root_ish`, violation type, primary-key columns, row values, and CHECK/foreign-key/unique/not-null metadata.
- Native `dolt_constraint_violations_<table>` delete/cleanup semantics for merge resolution, including row-key bulk cleanup, violation-info targeted cleanup for multiple violations on the same row, and keyless unique/FK cleanup through `dolt_row_hash`.
- Native `dolt schema show` CREATE TABLE rendering for schema-review UIs, including visible-table selection, requested-table lookup, hidden internal table skipping, and CHECK constraint preservation across focused schema edits.
- Native `dolt diff -r sql` row rendering for added, modified, and removed rows, including upstream diff-type filters and the `removed` / `dropped` delete-row aliases.
- Native row-mode `dolt diff` tabular rendering for added, modified, and removed rows, including upstream diff-type filters, empty output for mismatched filters, and fixed-width multiline/NULL cell padding.
- Native tabular `dolt diff --diff-mode=row|line|in-place|context` rendering for modified rows, including upstream's default context behavior for multiline cells.
- Native summary/stat primary-key-change boundaries: table-specific calls error, while unscoped calls warn and continue.
- Native `dolt_diff_summary()` ignore-pattern filtering for working/staged comparisons, including wildcard patterns and false-pattern overrides.
- Native `dolt_ignore` conflict reporting for ambiguous true/false scratch-table patterns, with upstream-shaped pattern details.
- Native `dolt_status` and `dolt_status_ignored` row projection for staged/unstaged table changes, table renames, merge/conflict states, and ignored unstaged new tables.
- Native `dolt_merge_status` and `dolt_conflicts` row projection for active merge metadata, unmerged table lists, and table/root-object conflict counts.
- Native `dolt_preview_merge_conflicts_summary` and `dolt_preview_merge_conflicts` row projection for preflight merge review, including data/schema conflict counts, keyed row conflict previews, keyless duplicate/cardinality preview rows with `dolt_row_hash`, schema-conflict error boundaries, native `dolt_schema_conflicts`-style schema description rows for column, duplicate-index, check, and modify/delete conflicts before an import merge mutates the working set, and schema-conflict `--ours` / `--theirs` side-selection state after resolution.
- Native `dolt merge` transcript rendering for up-to-date, no-commit, squash/no-commit, successful stat output, failure artifact preludes, abort-state cleanup, SQL autocommit rollback cleanup visibility, autocommit-disabled queryable conflict state, and constraint-only rollback/queryability where `dolt_conflicts` rows stay empty but constraint guidance remains visible.
- Native `dolt_history_dolt_schemas` and `dolt_diff_dolt_schemas` row projection for versioned schema objects such as views, triggers, and events.
- Native `dolt_history_dolt_procedures` and `dolt_diff_dolt_procedures` row projection for versioned stored procedures.
- Native `DOLT_COMMIT_DIFF_<table>` row projection that requires exactly one `from_commit` and one `to_commit`, then applies `to_*` / `from_*` key predicates to commit snapshots.
- Native `dolt_log` and `dolt_commits` commit metadata projection, including computed commit order, selected-head ancestry, refs decoration, opt-in parents/signature columns, `dolt_log()` revision-range filtering, all branch-head traversal, table filtering over changed-table metadata, `--merges` / `--min-parents` parent-count filtering, and `-n` / `--number` log-limit aliases with `0` returning no rows.
- Native `dolt log` CLI rendering for `--oneline`, `--parents`, `--stat`, `--stat --oneline`, `--graph`, and `--graph --oneline`, including decorated ref parentheses, TTY-sensitive `--decorate=auto` boundaries, newline-flattened messages, modified-table bars, added/deleted table lines, skipped stats for merge commits, text graph branch paths, dense default graph fan-in lanes, exact compact graph ref/message spacing, and merge parent lines.
- Native `dolt_commit_ancestors` row projection, including root null-parent rows, merge parent indexes, commit_hash filtering that preserves both merge parents, and parent-hash joins back to `dolt_log` messages.
- Native `has_ancestor()` commit graph checks, including branch/tag/full-ref/HEAD/hash resolution plus Dolt ancestor suffixes (`^`, `^N`, and `~N`).
- Native `dolt_branches`, `dolt_remote_branches`, `active_branch()`, and `dolt_branch_activity` projection rows for branch metadata, current branch context, dirty branches, active sessions, and read/write activity timestamps.

## Scenario Fixtures

- `fixtures/wp-posts-diff.php` models a WordPress import review where one post is published, one legacy page is removed, and one imported resource is added.
- `examples/wordpress-post-diff.php` returns Dolt-shaped diff rows with `to_*`, `from_*`, commit metadata, and `diff_type` fields. This is the shape a WordPress migration review tool can render before promoting imported content.
- `examples/wordpress-filtered-diff-sql.php` renders the same `wp_posts` changes as native `dolt diff -r sql` INSERT, UPDATE, and DELETE statements separated by diff-type filters for import promotion review queues.
- `examples/wordpress-filtered-diff-tabular.php` renders the same `wp_posts` changes as row-mode tabular `dolt diff` output, separating `<`/`>` modified rows, `-` removed rows, and `+` added rows for reviewer-facing import queues.
- `fixtures/wp-diff-mode-review.php` models a multiline `post_content` block edit during WordPress import review.
- `examples/wordpress-diff-mode-review.php` renders the block edit through row, line, in-place, and default context tabular diff modes, so a migration UI can choose compact old/new rows or reviewer-friendly line-level cells without shelling out to Dolt.
- `fixtures/wp-table-deltas.php` models a content-table rename from `wp_posts` to `wp_content_posts`, a dropped legacy links table, and a new import audit table.
- `examples/wordpress-table-delta-summary.php` returns Dolt-style table summaries with `renamed`, `dropped`, and `added` classifications. A migration UI can use this before row rendering to avoid presenting a table rename as unrelated delete/create noise.
- `examples/wordpress-diff-summary-cli.php` renders that same table-delta fixture as fixed-width `dolt diff --summary` text, so a migration dashboard can show Dolt-compatible CLI output without shelling out.
- `examples/wordpress-filtered-diff-summary-cli.php` renders the same table-delta fixture through diff-type filters, separating renamed content-table work, added audit tables, and dropped legacy table names for migration review queues.
- `examples/wordpress-summary-table-arg-boundary.php` contrasts generic table filtering with upstream CLI table-argument rendering, showing that `wp_import_audit` prints when it is the first changed table while a later `wp_legacy_links` request returns successful empty output because of the upstream short-circuit.
- `fixtures/wp-plugin-schema-drift.php` models a plugin-owned event table where a numeric count column was dropped and later recreated as a string column.
- `examples/wordpress-plugin-schema-drift.php` returns schema-aware diff rows plus warnings so a migration review UI can explain schema drift without shelling out to Dolt.
- `fixtures/wp-skinny-diff.php` models a Data Liberation import review where the post title and import batch changed while GUID/order/comment-count noise stayed constant.
- `examples/wordpress-skinny-diff.php` returns skinny Dolt-shaped rows that keep `post_status` via include-cols so a reviewer can confirm publication state without seeing unchanged metadata.
- `fixtures/wp-filtered-review-diff.php` models a publish-impacting review where draft/private churn is hidden and only public-content changes are paged into the reviewer queue.
- `examples/wordpress-filtered-diff-review.php` returns the Dolt-shaped rows after applying the fixture's `to_post_status = 'publish' OR from_post_status = 'publish'` predicate and review limit.
- `fixtures/wp-diff-stat-review.php` models a migration review where one published post changes, one post is added, one public page is removed, and one draft remains unchanged.
- `examples/wordpress-diff-stat-review.php` returns a Dolt-style `dolt_diff_stat()` row with unmodified, added, deleted, modified, and cell-change counts for a compact review dashboard.
- `examples/wordpress-diff-stat-cli.php` renders those counters as CLI-style `dolt diff --stat` text and compact `-r json` output for `wp_posts`, `wp_import_audit`, and a keyless `wp_import_log`, while showing that a requested `wp_posts` stat is not hidden by an earlier changed table and that schema-only `wp_options` changes report Dolt's no-data message or empty JSON `stats` object.
- `fixtures/wp-keyless-import-log.php` models a keyless WordPress import log where duplicate scan/post rows change cardinality during a migration review.
- `examples/wordpress-keyless-import-log-diff.php` renders that keyless import log as Dolt-shaped rows, `dolt diff -r sql`, and tabular `dolt diff` output, so duplicate audit events can be reviewed without inventing a synthetic primary key.
- `fixtures/wp-patch-review.php` models a WordPress patch review where `wp_posts.post_status` is renamed, `import_batch` is added, post rows are updated/inserted, and a keyless import log gains a duplicate audit event.
- `examples/wordpress-patch-review.php` returns native `dolt_patch()`-style rows split into all/schema/data queues, so a migration UI can preview DDL and data SQL patches without shelling out to Dolt.
- `fixtures/wp-binary-patch-review.php` models a WordPress media hash review where `wp_attachment_hashes` stores attachment fingerprints in `varbinary(32)` and `binary(4)` columns.
- `examples/wordpress-binary-patch-review.php` returns native `dolt_patch()` data statements with upstream-shaped `0x...` SQL literals, so media migration tools can review binary checksums without corrupting them as text.
- `examples/wordpress-patch-call-boundary.php` returns native `dolt_patch()` call-boundary rows for `review-base..review-working`, a merge-base-backed `main...review-working` review, resolved review branch/tag hashes, a `WORKING` patch target, an unchanged known `wp_options` table, a missing table/ref error, and a non-literal table-argument error.
- `fixtures/wp-patch-worktree-review.php` models a WordPress post migration where HEAD, STAGED, and WORKING each carry distinct `wp_posts` snapshots: staged post queue changes are ready for review, while unstaged worktree edits rename `post_status`, drop `legacy_checksum`, add `import_batch`, and add a media-note post.
- `examples/wordpress-patch-worktree-review.php` returns HEAD-to-STAGED, STAGED-to-WORKING, WORKING-to-STAGED, and WORKING-to-WORKING `dolt_patch()` rows, so a migration UI can preview staged and unstaged SQL patch queues without shelling out to Dolt.
- `fixtures/wp-patch-primary-key-warning.php` models a staged-to-working `wp_postmeta` key migration from `meta_id` to a composite content key.
- `examples/wordpress-patch-primary-key-warning.php` returns schema-only `dolt_patch()` rows plus warning code `1235`, so a migration UI can show DDL while blocking unsafe data SQL when primary-key sets differ.
- `fixtures/wp-patch-foreign-key-review.php` models a staged WordPress relational review where `wp_postmeta.post_id` gains an index and foreign key to `wp_posts.ID` while `wp_posts` moves to a composite import primary key.
- `examples/wordpress-patch-foreign-key-review.php` returns the index, foreign-key, and parent primary-key `dolt_patch()` rows plus warning code `1235`, so a migration UI can apply safe DDL ordering while blocking unsafe data SQL.
- `fixtures/wp-patch-foreign-key-maintenance.php` models a WordPress import relation table where one foreign key/index moves from legacy post IDs to imported post IDs while a taxonomy relation is dropped.
- `examples/wordpress-patch-foreign-key-maintenance.php` returns modified and dropped index/foreign-key `dolt_patch()` rows, so a migration UI can review relation-maintenance DDL without shelling out to Dolt.
- `fixtures/wp-patch-check-constraint-review.php` models a WordPress import audit table that guards allowed migration statuses with a check constraint.
- `examples/wordpress-patch-check-constraint-review.php` returns the `CREATE TABLE` patch statement with the native Dolt-style check clause, so a migration UI can review status guards before creating the audit table.
- `fixtures/wp-patch-check-constraint-maintenance.php` models a later `wp_import_audit` maintenance pass where one check is added, one is modified, and one is dropped.
- `examples/wordpress-patch-check-constraint-maintenance.php` returns an empty upstream-compatible patch statement list while exposing the classified check diff types, so a migration UI does not invent unsupported ALTER CHECK DDL.
- `fixtures/wp-check-constraint-information-schema.php` models `wp_import_audit` status checks plus candidate import rows that violate status and failed-note guards.
- `examples/wordpress-check-constraint-information-schema.php` returns native `information_schema.CHECK_CONSTRAINTS` / `TABLE_CONSTRAINTS` rows plus check-violation rows, so a migration UI can surface schema guards and invalid plugin statuses before promotion.
- `fixtures/wp-constraint-violation-review.php` models an import-branch merge where `wp_import_audit` rows violate CHECK guards after branch integration.
- `examples/wordpress-constraint-violation-review.php` returns native `dolt_constraint_violations` summary rows plus per-table violation rows and compact review rows, so a migration UI can explain invalid imported audit records without shelling out to Dolt.
- `fixtures/wp-foreign-key-constraint-violation-review.php` models an import-branch merge where `wp_postmeta` rows reference missing imported `wp_posts` parents.
- `examples/wordpress-foreign-key-constraint-violation-review.php` returns native foreign-key `dolt_constraint_violations` summary rows, per-table violation rows, compact cleanup guidance, and single-row / bulk violation cleanup results for orphaned postmeta relations without shelling out to Dolt.
- `fixtures/wp-schema-show-check-survival.php` models a renamed `wp_import_audit_review` table after migration-audit schema maintenance with status and failure-note CHECK guards intact.
- `examples/wordpress-schema-show-check-survival.php` returns native `dolt schema show`-style CREATE TABLE text, so a migration UI can review preserved CHECK guards without shelling out to Dolt.
- `fixtures/wp-patch-collation-review.php` models a `wp_options` review where legacy `utf8mb4_unicode_ci` comparison semantics are normalized to Dolt's default binary collation while the site URL changes.
- `examples/wordpress-patch-collation-review.php` returns the collation DDL before the `wp_options` data update, so a migration UI can flag comparison/sort drift separately from option-value changes.
- `fixtures/wp-patch-target-row-size-review.php` models a `wp_postmeta` review where large page-builder metadata moves to a wider target row size while the serialized meta payload changes.
- `examples/wordpress-patch-target-row-size-review.php` returns the target-row-size DDL before the `wp_postmeta` data update, so a migration UI can flag storage-layout drift separately from large meta-value changes.
- `fixtures/wp-patch-generated-default-review.php` models a `wp_import_queue` review where default post names, generated import slugs, stored generated metadata, and review-status defaults change before import promotion.
- `examples/wordpress-patch-generated-default-review.php` returns the default/generated column DDL plus the generated slug data update, so a migration UI can review import queue automation without shelling out to Dolt.
- `fixtures/wp-patch-autoincrement-review.php` models a new `wp_posts` snapshot with imported rows and a Dolt/MySQL-style `ID` auto-increment primary key.
- `examples/wordpress-patch-autoincrement-review.php` returns the `CREATE TABLE` patch row with `AUTO_INCREMENT` plus explicit imported post inserts, so migration tooling can review restored post IDs without shelling out to Dolt.
- `fixtures/wp-patch-metadata-only-column-review.php` models a `wp_import_queue` review where only default/generated/not-null metadata changes without supported type-changing patch DDL.
- `examples/wordpress-patch-metadata-only-column-review.php` returns the upstream-compatible empty patch statement list, so a migration UI does not invent unsupported DDL for metadata-only changes.
- `examples/wordpress-patch-privilege-review.php` returns a revision-database patch review where a limited reviewer can inspect `wp_posts` changes but an unscoped patch fails until the reviewer has database-wide SELECT over `wp_import_log` and the other database tables.
- `fixtures/wp-ignore-summary.php` models a migration workspace with generated scratch/cache tables that should be hidden by `dolt_ignore`, while `dolt_ignore`, review tables, and explicit false-pattern exceptions remain visible.
- `examples/wordpress-ignore-summary.php` returns ignore-aware `dolt_diff_summary()` rows for that workspace, so a WordPress migration UI can focus on reviewable data changes instead of generated scratch tables.
- `fixtures/wp-ignore-conflict.php` models a migration workspace where generated-table rules conflict: `wp_tmp_*` says ignore while `*_cache` says keep.
- `examples/wordpress-ignore-conflict.php` returns the upstream-shaped conflict error so a migration UI can surface the exact rules that need operator cleanup.
- `fixtures/wp-primary-key-warning.php` models a `wp_postmeta` key migration from `meta_id` to a composite content key.
- `examples/wordpress-primary-key-warning.php` returns summary/stat warnings for that blocked table while still showing unaffected `wp_posts` review rows.
- `fixtures/wp-status-review.php` models a migration review queue with staged post changes, unstaged option edits, a term relationship conflict, a visible import-review table, and a generated cache table ignored by `dolt_ignore`.
- `examples/wordpress-status-review.php` returns both `dolt_status` rows and `dolt_status_ignored` rows, so a WordPress UI can show reviewable work while still explaining hidden generated tables.
- `fixtures/wp-merge-review.php` models an active import-branch merge where `wp_posts` has row conflicts plus not-null blockers, `wp_postmeta` has foreign-key violations, `wp_import_audit` has CHECK violations, `wp_options` has a schema conflict plus duplicate unique-index blockers, and a preview view plus stored procedure have root-object conflicts. It also includes a clean divergent import merge where `wp_posts` is modified, `wp_import_audit` is added, and `wp_terms` is deleted.
- `examples/wordpress-merge-status-review.php` returns the `dolt_merge_status` row, `dolt_conflicts` table/count rows, preflight `dolt_preview_merge_conflicts_summary` / keyed `dolt_preview_merge_conflicts` rows for `wp_posts`, keyless/cardinality preview conflict rows for import-log-style duplicate events, upstream-shaped schema-conflict preview errors for `wp_options`, native `dolt_schema_conflicts`-style rows with CREATE TABLE text and column/check descriptions for `wp_options`, schema-conflict resolution state after `dolt add wp_options`, `--ours` / `--theirs` schema-conflict side-selection state for `wp_postmeta`, root-object conflict detail and resolution state for WordPress migration views/procedures, upstream-shaped `dolt merge` artifact prelude lines, immediate failure summary, merged transcript, successful merge stat output, no-commit and squash/no-commit transcripts, up-to-date output, fast-forward-only output, no-ff output, native `CALL DOLT_MERGE` result rows for fast-forward/no-ff/no-commit/conflict/up-to-date/ahead/abort states, SQL `@autocommit` unresolved-conflict rollback guidance with the `@@dolt_allow_commit_conflicts` escape hatch, SQL rollback state that clears visible conflict rows after autocommit rollback while keeping autocommit-disabled conflicts queryable, constraint-only rollback state for `wp_postmeta` / `wp_import_audit` where `dolt_conflicts` rows remain empty and `dolt_constraint_violations` guidance remains visible, ff-only failure and incompatible flag errors, abort-state cleanup, unresolved constraint-violation error text with duplicate row counts, and Dolt-style status/commit guidance strings for fixed versus unresolved merge states, so a WordPress migration UI can display unresolved, resolved-but-uncommitted, successful, deferred, squashed, up-to-date, fast-forwarded, no-ff, rejected, or aborted merge state without shelling out to Dolt.
- `fixtures/wp-schema-history.php` models versioned WordPress migration views, an import cleanup trigger, and working changes that add review/checkpoint schema objects while removing the trigger.
- `examples/wordpress-schema-history-review.php` returns `dolt_history_dolt_schemas` rows plus working `dolt_diff_dolt_schemas` rows, so a migration UI can audit schema-object history without shelling out to Dolt.
- `fixtures/wp-procedure-history.php` models versioned WordPress import/review stored procedures, including a modified post-prep routine, a new review cursor, and a removed media queue routine.
- `examples/wordpress-procedure-history-review.php` returns `dolt_history_dolt_procedures` rows plus working `dolt_diff_dolt_procedures` rows, so a migration UI can audit stored-routine drift without shelling out to Dolt.
- `fixtures/wp-commit-diff-review.php` models a WordPress import review between two named commits, with a bounded post-ID window over the changed `wp_posts` rows.
- `examples/wordpress-commit-diff-review.php` returns `DOLT_COMMIT_DIFF`-style rows after applying the fixture's `to_ID > 900 AND to_ID < 950` predicate, so a migration UI can review a commit-to-commit window without shelling out to Dolt.
- `fixtures/wp-commit-log-review.php` models a reviewed WordPress import merge with a main branch, media-import side branch, review-drafts side branch, import tags, merge parents, separate author/committer metadata, and an abandoned scratch checkpoint.
- `examples/wordpress-commit-log-review.php` returns `dolt_log` rows with parents and decorated refs, a latest-review row capped with `--number`, import-base and media-promotion revision ranges, `wp_posts` / `wp_postmeta` table-filtered histories, all branch-head review history, all-branch `wp_posts` history that excludes the abandoned scratch checkpoint, merge-only review rows, non-root checkpoint rows, `dolt_commits` rows, a compact `--oneline --stat` rendering, and a compact `--graph --oneline` branch graph, so a migration UI can audit which import branch produced each data checkpoint without shelling out to Dolt.
- `fixtures/wp-commit-log-fan-in-review.php` models four WordPress import branches for products, taxonomy, media, and redirect/cache options converging into a reviewed main branch.
- `examples/wordpress-commit-log-fan-in-review.php` returns `dolt_log` rows plus default and compact `dolt log --graph` text for the dense import branch fan-in, so a migration history UI can render multi-branch review lanes without shelling out to Dolt.
- `fixtures/wp-commit-ancestors-review.php` models the same reviewed import merge as parent-edge rows from `dolt_commit_ancestors`.
- `examples/wordpress-commit-ancestors-review.php` returns merge parent hashes and parent-index-ordered log messages, so a migration UI can explain which branch each reviewed data checkpoint merged without shelling out to Dolt.
- `fixtures/wp-has-ancestor-review.php` models branch/tag containment checks for the reviewed import merge, including whether `main` contains the media-import branch and whether `main^2` / `main~2` resolve to the expected review parents.
- `examples/wordpress-has-ancestor-review.php` returns `has_ancestor` booleans and resolved commit specs, so a migration UI can gate promotion on branch ancestry without shelling out to Dolt.
- `fixtures/wp-branch-review.php` models active WordPress migration branches, including an upstream-tracked main branch, a dirty media-import branch, a review-drafts branch, active reviewer session counts, and branch activity timestamps.
- `examples/wordpress-branch-review.php` returns `dolt_branches` rows, `dolt_branch_activity` rows, the active branch, and a compact branch review queue so a migration UI can prioritize dirty or actively reviewed branches without shelling out to Dolt.

## Next Task

Next best slice: map the next bounded SQL merge visibility edge, preferably commit/rollback cleanup after constraint-only violations, with a WordPress smoke path.

## Watchdog Next 2026-05-24

- Status delta: added native keyless/cardinality `dolt_preview_merge_conflicts` rows for duplicate import-log-style data where both branches change duplicate counts differently from base.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/PreviewMergeConflictsTableTest.php` passed with 1 test file, 16 assertions, and 0 failures; syntax checks passed for the changed source, test, fixture, and example; `wordpress-merge-status-review.php` smoke returned one `previewKeylessConflictRows` row.
- Blocker: no Dolt PHP blocker in this micro-slice; full upstream Go/BATS parity remains out of scope for the isolated worker boundary.
- Dependency closure: no new support component is needed; this reuses existing bounded PHP row projection and hashing primitives, with no shell-outs and no activation of a shared dependency.

## Supervisor Rearm 2026-05-25

- Status delta: added native `dolt_schema_conflicts`-style description rows to the preview merge-conflict cluster, covering upstream row shape, column tag-collision descriptions, check name-collision descriptions, `<deleted>` schema sides, and modify/delete descriptions.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/PreviewMergeConflictsTableTest.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 2 files, 141 assertions, and 0 failures; syntax checks passed for the changed source, tests, fixture, and example; `wordpress-merge-status-review.php` smoke returned one `previewSchemaConflictDescriptionRows` row with the expected WordPress `wp_options` description text.
- Blocker: no Dolt PHP blocker in this micro-slice; full upstream Go/BATS parity remains out of scope for the isolated worker boundary.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge-preview projection surface and static schema description formatting, with no shell-outs and no activation of a shared dependency.

## Supervisor Rearm 2026-05-25 02:33 UTC

- Status delta: extended native `dolt_schema_conflicts`-style description rows for duplicate index column-set conflicts plus check column, invalid-column, and deleted/modified collisions; accepted camelCase conflict-kind aliases at the PHP boundary while preserving upstream-shaped output strings.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/PreviewMergeConflictsTableTest.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 2 files, 142 assertions, and 0 failures; syntax checks passed for changed PHP files; `wordpress-merge-status-review.php` smoke returned three schema-conflict description rows for `wp_options`, `wp_postmeta`, and `wp_import_queue`.
- Blocker: no Dolt PHP blocker in this micro-slice; full upstream Go/BATS parity remains out of scope for the isolated worker boundary.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge-preview projection surface and static schema description formatting, with no shell-outs and no activation of a shared dependency.

## Supervisor Rearm 2026-05-25 02:50 UTC

- Status delta: added native root-object conflict detail rows for schema objects such as WordPress migration views and stored procedures, complementing the existing `dolt_conflicts` root-object count rows with base/our/their definitions, `<deleted>` sides, and description text.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 1 file, 128 assertions, and 0 failures; syntax checks passed for the changed source, test, fixture, and example; `wordpress-merge-status-review.php` smoke returned two root object rows, including `wp_import_preview_view` and a `<deleted>` procedure side.
- Blocker: no Dolt PHP blocker in this micro-slice; full upstream Go/BATS parity remains out of scope for the isolated worker boundary.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge/status projection surface and supplied schema-object definitions, with no shell-outs and no activation of a shared dependency.

## Supervisor Rearm 2026-05-25 02:56 UTC

- Status delta: added native schema-conflict resolution state for `dolt add <table>` clearing commit-blocking schema paths, including remaining schema-conflict rows plus status/commit guidance after resolution.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 1 file, 133 assertions, and 0 failures; syntax checks passed for the changed source, test, fixture, and example; `wordpress-merge-status-review.php` smoke returned zero remaining schema conflicts and the upstream all-merged status prompt.
- Blocker: no Dolt PHP blocker in this micro-slice; full upstream Go/BATS parity remains out of scope for the isolated worker boundary.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge/status projection surface and table-name normalization, with no shell-outs and no activation of a shared dependency.

## Supervisor Rearm 2026-05-25 03:19 UTC

- Status delta: added native root-object conflict resolution state for `dolt add`-style clearing of schema objects such as WordPress migration views and stored procedures, including remaining root-object `dolt_conflicts` rows and failure-summary clearing.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 1 file, 138 assertions, and 0 failures; syntax checks passed for changed PHP files; `wordpress-merge-status-review.php` smoke returned zero remaining root-object conflicts after resolving the preview view and import procedure.
- Blocker: no Dolt PHP blocker in this micro-slice; full upstream Go/BATS parity remains out of scope for the isolated worker boundary.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge/status projection surface and root-object table-name normalization, with no shell-outs and no activation of a shared dependency.

## Watchdog Next 2026-05-25 04:53 UTC

- Status delta: added native mixed post-resolution merge artifact state after selected data conflicts, schema conflicts, constraint-violation tables, and root-object conflicts are cleared.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 1 file, 145 assertions, and 0 failures; syntax checks passed for the changed source, test, fixture, and example; `wordpress-merge-status-review.php` smoke returned one remaining data conflict, two remaining constraint tables, and the expected `Automatic merge failed; 3 table(s) are unmerged.` summary.
- Blocker: no Dolt PHP blocker in this micro-slice; full upstream Go/BATS parity remains out of scope for the isolated worker boundary.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge/status/constraint projection surface, with no shell-outs and no activation of a shared dependency.
- Next task: map schema-conflict table mutation semantics next, keeping the slice bounded to visible status/conflict rows and WordPress migration review output.

## Watchdog Next 2026-05-25 05:55 UTC

- Status delta: added native schema-conflict side-selection state for `dolt_conflicts_resolve('--ours'|'--theirs', table)`, including selected schema text and remaining schema-conflict status/commit guidance.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 1 file, 150 assertions, and 0 failures; syntax checks passed for the changed source, test, fixture, and example; `wordpress-merge-status-review.php` smoke returned `wp_postmeta`, `theirs`, two remaining schema-conflict rows, and selected the `idx_meta_review` schema side.
- Blocker: no Dolt PHP blocker in this micro-slice; full upstream Go/BATS parity remains out of scope for the isolated worker boundary.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge/status/schema-conflict projection surface and supplied schema strings, with no shell-outs and no activation of a shared dependency.
- Next task: map SQL merge transaction/autocommit unresolved-conflict error boundaries next, keeping it bounded to one focused merge behavior and WordPress smoke evidence.

## Watchdog Next 2026-05-25 06:05 UTC

- Status delta: added native SQL merge transaction/autocommit unresolved-conflict error projection, including the upstream `@autocommit transaction rolled back` text, normal transaction rollback text, and the `@@dolt_allow_commit_conflicts` no-error gate.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 1 file, 159 assertions, and 0 failures; syntax checks passed for the changed source, test, fixture, and example; `wordpress-merge-status-review.php` smoke returned the upstream `@autocommit transaction rolled back` guidance and `conflicts found`.
- Blocker: no Dolt PHP blocker in this micro-slice; full upstream Go/BATS parity remains out of scope for the isolated worker boundary.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge/status/procedure projection surface and static upstream error text, with no shell-outs and no activation of a shared dependency.
- Next task: map SQL merge abort/rollback cleanup visibility after unresolved conflicts next, keeping it bounded to one focused merge behavior and WordPress smoke evidence.

## Watchdog Next 2026-05-25 06:33 UTC

- Status delta: added native SQL merge rollback visibility for unresolved merge conflicts, including autocommit rollback clearing `dolt_merge_status`, `dolt_conflicts`, status guidance, commit guidance, and failure summary while autocommit-disabled conflicts remain queryable for resolution.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 1 file, 181 assertions, and 0 failures; syntax checks passed for changed PHP files; `wordpress-merge-status-review.php` smoke returned `rolled_back=true`, inactive merge status, zero rollback conflict rows, and queryable conflict rows when autocommit is disabled.
- Blocker: no Dolt PHP blocker in this micro-slice; full upstream Go/BATS parity remains out of scope for the isolated worker boundary.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge/status/procedure projection surface and static upstream transaction evidence, with no shell-outs and no activation of a shared dependency.

## Priority Keeper 2026-05-25 11:54 UTC

- Status delta: reworked the SQL rollback visibility cluster additively on current accepted HEAD by adding constraint-only rollback/queryability coverage for `wp_postmeta` and `wp_import_audit`: autocommit rollback clears visible merge state, while autocommit-disabled transactions keep merge status and constraint guidance queryable without adding `dolt_conflicts` rows.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 1 file, 200 assertions, and 0 failures; syntax checks passed for changed PHP files; `wordpress-merge-status-review.php` smoke returned `rolled_back=true`, `queryable_conflict_rows=0`, `wp_postmeta, wp_import_audit`, and `constraint_guidance=yes`.
- Blocker: no Dolt PHP blocker in this micro-slice; full upstream Go/BATS parity remains out of scope for the isolated worker boundary.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge/status/procedure projection surface and static upstream transaction/constraint guidance, with no shell-outs and no activation of a shared dependency.
- Next task: map the next bounded SQL merge visibility edge, preferably commit/rollback cleanup after constraint-only violations, and keep it to one focused behavior with WordPress smoke evidence.

## Continuous Dev 2026-05-25 23:09 UTC

- Status delta: added native constraint-only SQL merge allow-commit visibility for `@@dolt_allow_commit_conflicts = 1`: constraint-only WordPress migration artifacts commit without transaction rollback/error, active merge status clears, `dolt_conflicts` rows remain empty, and `wp_postmeta` / `wp_import_audit` constraint artifact tables remain available for post-commit review.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 1 file, 216 assertions, and 0 failures; syntax checks passed for changed PHP files; `wordpress-merge-status-review.php` smoke returned `committed=true`, inactive merge status, no conflict rows, and retained `wp_postmeta` / `wp_import_audit` constraint table names.
- Blocker: no Dolt PHP blocker in this micro-slice; full upstream Go/BATS parity remains out of scope for the isolated worker boundary.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge/status/constraint projection surface and static upstream transaction guidance, with no shell-outs and no activation of a shared dependency.
- Next task: map post-commit cleanup/deletion semantics for constraint-only violation rows, keeping the slice bounded to visible status/conflict/constraint rows and WordPress migration review output.
