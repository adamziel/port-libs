# Attributes Pathspec Glob Class Parity

Slice: `gitoxide-attributes-pathspec-match-parity-20260531T115757Z`

Base accepted HEAD: `ab384a0d481bd4acef6592a38a3540df9d0cc3f2`

## Upstream Source Truth

- `gix-attributes/src/search/attributes.rs` matches attribute patterns through `pattern.matches_repo_relative_path(..., gix_glob::wildmatch::Mode::NO_MATCH_SLASH_LITERAL)`.
- `gix-attributes/tests/search/mod.rs` confirms attributes use path-aware matching, including `dir/**` recursive matching and case-folded local/global prefix behavior.
- `gix-pathspec/src/search/matching.rs` applies the selected attribute outcome after a pathspec path match, so `:(attr:...)` filters depend on the attribute provider honoring the same gix-glob character-class semantics.

## Native PHP Delta

- `GitAttributes::globRegex()` now parses bracket classes with the same character-class handling already used by the lane-local `PathspecSearch` implementation.
- POSIX classes such as `[[:digit:]]` no longer produce malformed PCRE patterns and now fill selected attributes for matching paths.
- Character classes stay path-aware for attributes: `[/]` does not match a slash when attribute patterns are evaluated with gix's `NO_MATCH_SLASH_LITERAL` mode.
- The WordPress attributes/pathspec example now includes a dated upload selection through `:(attr:dated-upload)` and proves a slash character class does not cross directories.

## Verification

- Red-first check before the fix:
  `php -r 'require "tools/bootstrap.php"; $a=PortLibs\Gitoxide\GitAttributes::fromString("wp-content/uploads/[[:digit:]][[:digit:]]/** dated\nwp-content/plugins/foo[/]bar.php slash\n", withBuiltInMacros:false); var_export($a->attributesForPath("wp-content/uploads/05/photo.jpg", ["dated"])); echo PHP_EOL; var_export($a->attributesForPath("wp-content/plugins/foo/bar.php", ["slash"])); echo PHP_EOL;'`
  emitted `preg_match()` compilation warnings, returned `dated => null`, and incorrectly returned `slash => true`.
- `php -l lanes/gitoxide/src/GitAttributes.php && php -l lanes/gitoxide/tests/AttributesPathspecTest.php && php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
  passed with no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  passed `1 test files, 79 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  passed `39 test files, 4460 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
  exited `0`.
- `git diff --check -- lanes/gitoxide`
  passed.

## Non-Overlap

This is additive to the accepted pathspec wildmatch tree-walk, config include bracket-class, attributes selected-assignment, and attributes/pathspec search attr-filter slices. It does not repeat pathspec glob matching itself; it fixes the Git attributes provider used by `:(attr:...)` filters.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local `GitAttributes`, `PathspecMatcher`, `PathspecSearch`, WordPress example, and PHP test harness.
