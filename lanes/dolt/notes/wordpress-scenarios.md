# dolt WordPress Scenario

Versioned content/data migrations and inspectable database change sets.

## Current Native Slice

Native table diff classification and Dolt-style `DOLT_DIFF_*` row projection by primary key.

## Scenario Fixture

- `fixtures/wp-posts-diff.php` models a WordPress import review where one post is published, one legacy page is removed, and one imported resource is added.
- `examples/wordpress-post-diff.php` returns Dolt-shaped diff rows with `to_*`, `from_*`, commit metadata, and `diff_type` fields. This is the shape a WordPress migration review tool can render before promoting imported content.

## Next Task

Map schema/tag-aware row conversion and begin porting Dolt table delta matching for renamed tables and primary key set changes.
