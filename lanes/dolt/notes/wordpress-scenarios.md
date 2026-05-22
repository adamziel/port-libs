# dolt WordPress Scenario

Versioned content/data migrations and inspectable database change sets.

## Current Native Slices

- Native table diff classification and Dolt-style `DOLT_DIFF_*` row projection by primary key.
- Native schema/tag comparison for column additions, drops, renames, type changes, primary-key movement, and constraints.
- Native table-delta matching that distinguishes exact-name changes, tag-overlap renames, drops, and adds.
- Native schema-aware row projection that maps historical rows into a target diff schema and reports Dolt-style warnings for coercion and primary-key-set changes.
- Native skinny diff projection that hides unchanged same-type columns while preserving primary keys, changed columns, added columns, and reviewer-requested `--include-cols`.
- Native projected row filtering that applies Dolt-style `--where` predicates and limits after diff rows are shaped.

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

## Next Task

Map broader `dolt diff` output modes or `dolt_diff_summary` / `dolt_diff_stat` table-function behavior from focused upstream tests.
