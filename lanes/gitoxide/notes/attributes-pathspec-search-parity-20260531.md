# Attributes Pathspec Search Parity

Slice: `gitoxide-attributes-pathspec-match-parity-20260531T104438Z`

Base accepted HEAD: `f9d9e6312c63dfc0751eedbcf238e9e6c2d6e7da`

## Upstream Source Truth

- `gix-pathspec/src/search/init.rs` creates an attribute-selection outcome for every parsed pathspec whose `attributes` list is non-empty.
- `gix-pathspec/src/search/matching.rs` skips a matching pathspec when the attribute provider cannot fill at least one selected assignment, then compares `attrs.iter_selected()` against the parsed `pattern.attributes` in order.
- `gix-attributes/src/search/outcome.rs` preserves the selected attribute order and yields unspecified fallback assignments for selected attributes that were not filled after another selected attribute matched.

## Native PHP Delta

- `PathspecPattern::parse()` now accepts `:(attr:...)` in the richer `PathspecSearch` path and stores the parsed `GitAttributes` requirements on the pattern.
- `PathspecPattern::withPath()` preserves attribute requirements through prefix normalization.
- `PathspecSearch::match()` and `isIncluded()` now accept an optional `GitAttributes` provider and apply upstream-style selected-assignment filtering before returning a match.
- The WordPress attributes/pathspec example now proves the same deployment selection through both the existing simple matcher and the richer `PathspecSearch` API.

## Verification

- Red-first check before the fix:
  `php -r 'require "tools/bootstrap.php"; try { PortLibs\Gitoxide\PathspecSearch::fromSpecs([":(attr:deploy=plugin)wp-content/**"]); echo "accepted\n"; } catch (Throwable $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }'`
  returned `InvalidArgumentException: Pathspec attribute matching is not implemented in the PHP tree walker`.
- `php -l lanes/gitoxide/src/PathspecPattern.php && php -l lanes/gitoxide/src/PathspecSearch.php && php -l lanes/gitoxide/tests/AttributesPathspecTest.php && php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
  passed with no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  passed `1 test files, 67 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files, 79 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  passed `39 test files, 4271 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
  exited `0`.
- `git diff --check -- lanes/gitoxide`
  passed.

## Non-Overlap

This is additive to the accepted attributes/pathspec state-adjustment and selected-assignment slices. It does not repeat value-suffix parsing, absent-versus-explicit unspecified behavior in `PathspecMatcher`, tree pathspec empty-search parity, sparse-checkout pathspecs, or prefix/case tree-walk behavior. The new behavior is limited to carrying `:(attr:...)` requirements through `PathspecSearch`.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local pathspec parser/search implementation, `GitAttributes` provider, PHP test harness, and existing WordPress attributes/pathspec fixture.
