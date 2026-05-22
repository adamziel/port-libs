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

## Next Task

Port a narrow native merge-status/conflict-summary or schema-history table slice from the focused upstream BATS and sqle integration evidence.
