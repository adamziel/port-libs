# Attributes Pathspec Selected Assignment Parity

Slice: `gitoxide-attributes-pathspec-match-parity-20260531T100701Z`

Base accepted HEAD: `db6e720333280b900b4f227c59e0153ddd55f2fc`

## Upstream Source Truth

- `gix-pathspec/src/search/matching.rs` initializes a selected attribute outcome for `:(attr:...)`, calls the attribute provider, and compares each selected actual assignment exactly against the parsed requirement.
- `gix-attributes/src/search/attributes.rs` reports a match only when at least one selected attribute assignment was filled. A fallback unspecified value from an absent selected attribute is not enough by itself.
- `gix-worktree/src/stack/state/attributes.rs` reinitializes the pathspec-selected outcome with the real attribute metadata collection before searching worktree attributes.

## Native PHP Delta

- `GitAttributes::matchesRequirements()` now uses a selected-assignment resolver instead of treating every absent selected attribute as a filled unspecified assignment.
- `:(attr:!deploy)` no longer matches a path that only has unrelated attributes. It still matches an explicit `!deploy` assignment, and combined requirements like `deploy=plugin !review` continue to match when at least one selected attribute is filled and the other selected attribute remains unspecified.
- The WordPress attributes/pathspec example now exposes both explicit-unspecified and absent-unspecified checks for deployment filters.

## Verification

- Red-first check before the fix:
  `php -r 'require "tools/bootstrap.php"; $a=PortLibs\Gitoxide\GitAttributes::fromString("wp-content/uploads/** binary\nwp-content/plugins/** deploy=plugin\n"); var_export(PortLibs\Gitoxide\PathspecMatcher::matchesOne(":(attr:!deploy)wp-content/uploads/**", "wp-content/uploads/logo.png", false, $a)); echo PHP_EOL;'`
  returned `true`; Gitoxide selected-assignment parity requires `false`.
- `php -l lanes/gitoxide/src/GitAttributes.php`
- `php -l lanes/gitoxide/tests/AttributesPathspecTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-attributes-pathspec.php`
- `php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  passed `1 test files, 52 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  passed `38 test files, 4033 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
  exited `0`.
- `git diff --check -- lanes/gitoxide`
  passed.

## Non-Overlap

This is additive to the accepted attributes/pathspec state-adjustment slice. It does not repeat value-suffix parsing, escaped value validation, tree pathspec walking, sparse checkout pathspecs, or empty pathspec tree walks. The behavior is limited to Gitoxide's selected attribute outcome semantics during `:(attr:...)` matching.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local Git attributes parser, pathspec matcher, fixture, example, and PHP test harness.
