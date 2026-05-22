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
- Native summary/stat primary-key-change boundaries: table-specific calls error, while unscoped calls warn and continue.
- Native `dolt_diff_summary()` ignore-pattern filtering for working/staged comparisons, including wildcard patterns and false-pattern overrides.
- Native `dolt_ignore` conflict reporting for ambiguous true/false scratch-table patterns, with upstream-shaped pattern details.
- Native `dolt_status` and `dolt_status_ignored` row projection for staged/unstaged table changes, table renames, merge/conflict states, and ignored unstaged new tables.
- Native `dolt_merge_status` and `dolt_conflicts` row projection for active merge metadata, unmerged table lists, and table/root-object conflict counts.
- Native `dolt_history_dolt_schemas` and `dolt_diff_dolt_schemas` row projection for versioned schema objects such as views, triggers, and events.
- Native `dolt_history_dolt_procedures` and `dolt_diff_dolt_procedures` row projection for versioned stored procedures.
- Native `DOLT_COMMIT_DIFF_<table>` row projection that requires exactly one `from_commit` and one `to_commit`, then applies `to_*` / `from_*` key predicates to commit snapshots.
- Native `dolt_log` and `dolt_commits` commit metadata projection, including computed commit order, selected-head ancestry, refs decoration, and opt-in parents/signature columns.
- Native `dolt_commit_ancestors` row projection, including root null-parent rows, merge parent indexes, commit_hash filtering that preserves both merge parents, and parent-hash joins back to `dolt_log` messages.
- Native `has_ancestor()` commit graph checks, including branch/tag/full-ref/HEAD/hash resolution plus Dolt ancestor suffixes (`^`, `^N`, and `~N`).
- Native `dolt_branches`, `dolt_remote_branches`, `active_branch()`, and `dolt_branch_activity` projection rows for branch metadata, current branch context, dirty branches, active sessions, and read/write activity timestamps.

## Scenario Fixtures

- `fixtures/wp-posts-diff.php` models a WordPress import review where one post is published, one legacy page is removed, and one imported resource is added.
- `examples/wordpress-post-diff.php` returns Dolt-shaped diff rows with `to_*`, `from_*`, commit metadata, and `diff_type` fields. This is the shape a WordPress migration review tool can render before promoting imported content.
- `fixtures/wp-table-deltas.php` models a content-table rename from `wp_posts` to `wp_content_posts`, a dropped legacy links table, and a new import audit table.
- `examples/wordpress-table-delta-summary.php` returns Dolt-style table summaries with `renamed`, `dropped`, and `added` classifications. A migration UI can use this before row rendering to avoid presenting a table rename as unrelated delete/create noise.
- `fixtures/wp-plugin-schema-drift.php` models a plugin-owned event table where a numeric count column was dropped and later recreated as a string column.
- `examples/wordpress-plugin-schema-drift.php` returns schema-aware diff rows plus warnings so a migration review UI can explain schema drift without shelling out to Dolt.
- `fixtures/wp-skinny-diff.php` models a Data Liberation import review where the post title and import batch changed while GUID/order/comment-count noise stayed constant.
- `examples/wordpress-skinny-diff.php` returns skinny Dolt-shaped rows that keep `post_status` via include-cols so a reviewer can confirm publication state without seeing unchanged metadata.
- `fixtures/wp-filtered-review-diff.php` models a publish-impacting review where draft/private churn is hidden and only public-content changes are paged into the reviewer queue.
- `examples/wordpress-filtered-diff-review.php` returns the Dolt-shaped rows after applying the fixture's `to_post_status = 'publish' OR from_post_status = 'publish'` predicate and review limit.
- `fixtures/wp-diff-stat-review.php` models a migration review where one published post changes, one post is added, one public page is removed, and one draft remains unchanged.
- `examples/wordpress-diff-stat-review.php` returns a Dolt-style `dolt_diff_stat()` row with unmodified, added, deleted, modified, and cell-change counts for a compact review dashboard.
- `fixtures/wp-ignore-summary.php` models a migration workspace with generated scratch/cache tables that should be hidden by `dolt_ignore`, while `dolt_ignore`, review tables, and explicit false-pattern exceptions remain visible.
- `examples/wordpress-ignore-summary.php` returns ignore-aware `dolt_diff_summary()` rows for that workspace, so a WordPress migration UI can focus on reviewable data changes instead of generated scratch tables.
- `fixtures/wp-ignore-conflict.php` models a migration workspace where generated-table rules conflict: `wp_tmp_*` says ignore while `*_cache` says keep.
- `examples/wordpress-ignore-conflict.php` returns the upstream-shaped conflict error so a migration UI can surface the exact rules that need operator cleanup.
- `fixtures/wp-primary-key-warning.php` models a `wp_postmeta` key migration from `meta_id` to a composite content key.
- `examples/wordpress-primary-key-warning.php` returns summary/stat warnings for that blocked table while still showing unaffected `wp_posts` review rows.
- `fixtures/wp-status-review.php` models a migration review queue with staged post changes, unstaged option edits, a term relationship conflict, a visible import-review table, and a generated cache table ignored by `dolt_ignore`.
- `examples/wordpress-status-review.php` returns both `dolt_status` rows and `dolt_status_ignored` rows, so a WordPress UI can show reviewable work while still explaining hidden generated tables.
- `fixtures/wp-merge-review.php` models an active import-branch merge where `wp_posts` has row conflicts, `wp_postmeta` has a constraint violation, `wp_options` has a schema conflict, and a preview view has a root-object conflict.
- `examples/wordpress-merge-status-review.php` returns the `dolt_merge_status` row plus `dolt_conflicts` table/count rows, so a WordPress migration UI can display unresolved merge state without shelling out to Dolt.
- `fixtures/wp-schema-history.php` models versioned WordPress migration views, an import cleanup trigger, and working changes that add review/checkpoint schema objects while removing the trigger.
- `examples/wordpress-schema-history-review.php` returns `dolt_history_dolt_schemas` rows plus working `dolt_diff_dolt_schemas` rows, so a migration UI can audit schema-object history without shelling out to Dolt.
- `fixtures/wp-procedure-history.php` models versioned WordPress import/review stored procedures, including a modified post-prep routine, a new review cursor, and a removed media queue routine.
- `examples/wordpress-procedure-history-review.php` returns `dolt_history_dolt_procedures` rows plus working `dolt_diff_dolt_procedures` rows, so a migration UI can audit stored-routine drift without shelling out to Dolt.
- `fixtures/wp-commit-diff-review.php` models a WordPress import review between two named commits, with a bounded post-ID window over the changed `wp_posts` rows.
- `examples/wordpress-commit-diff-review.php` returns `DOLT_COMMIT_DIFF`-style rows after applying the fixture's `to_ID > 900 AND to_ID < 950` predicate, so a migration UI can review a commit-to-commit window without shelling out to Dolt.
- `fixtures/wp-commit-log-review.php` models a reviewed WordPress import merge with a main branch, media-import side branch, import tags, merge parents, and separate author/committer metadata.
- `examples/wordpress-commit-log-review.php` returns `dolt_log` rows with parents and decorated refs plus `dolt_commits` rows, so a migration UI can audit which import branch produced each data checkpoint without shelling out to Dolt.
- `fixtures/wp-commit-ancestors-review.php` models the same reviewed import merge as parent-edge rows from `dolt_commit_ancestors`.
- `examples/wordpress-commit-ancestors-review.php` returns merge parent hashes and parent-index-ordered log messages, so a migration UI can explain which branch each reviewed data checkpoint merged without shelling out to Dolt.
- `fixtures/wp-has-ancestor-review.php` models branch/tag containment checks for the reviewed import merge, including whether `main` contains the media-import branch and whether `main^2` / `main~2` resolve to the expected review parents.
- `examples/wordpress-has-ancestor-review.php` returns `has_ancestor` booleans and resolved commit specs, so a migration UI can gate promotion on branch ancestry without shelling out to Dolt.
- `fixtures/wp-branch-review.php` models active WordPress migration branches, including an upstream-tracked main branch, a dirty media-import branch, a review-drafts branch, active reviewer session counts, and branch activity timestamps.
- `examples/wordpress-branch-review.php` returns `dolt_branches` rows, `dolt_branch_activity` rows, the active branch, and a compact branch review queue so a migration UI can prioritize dirty or actively reviewed branches without shelling out to Dolt.

## Next Task

Add revision-range filtering for native `dolt_log()` / `dolt_log()` table-function semantics.
