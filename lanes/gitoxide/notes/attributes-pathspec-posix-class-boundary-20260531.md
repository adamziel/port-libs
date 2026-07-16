# Attributes Pathspec POSIX Class Boundary Parity

Slice: `gitoxide-attributes-pathspec-match-parity-20260531T124327Z`

Base accepted HEAD: `b46358aff7aa9b475bc4c01fea4fdbf8d07e53e1`

## Upstream Source Truth

- `gix-glob/src/wildmatch.rs` expands POSIX character classes with explicit ASCII byte checks. In particular, `blank` uses Rust `is_ascii_whitespace()`, while `space` matches only the literal space byte.
- The same wildmatch engine returns an abort result for unknown POSIX classes.
- `gix-attributes/src/search/attributes.rs` uses `matches_repo_relative_path()` directly for attribute patterns, so an unknown class does not fall back to a literal attribute-pattern match.
- `gix-pathspec/src/search/matching.rs` tries wildmatch first, then falls back to verbatim matching for pathspecs, so an unknown POSIX class can still match a path containing the literal bracket expression.

## Native PHP Delta

- `GitAttributes::globRegex()` and `PathspecSearch::globRegex()` now use explicit ASCII byte ranges for supported POSIX classes instead of relying on PCRE's POSIX class definitions.
- `[[:blank:]]` now matches all ASCII whitespace bytes, including vertical tab and form feed, matching Gitoxide's wildmatch implementation.
- Unknown POSIX classes no longer match through the attributes provider, while pathspec search still retains the upstream verbatim fallback after a glob miss.
- The WordPress attributes/pathspec example now exposes an odd-whitespace media path selection and an invalid-class attribute guard.

## Verification

- Red-first check before the fix:
  `php -r 'require "tools/bootstrap.php"; use PortLibs\Gitoxide\{GitAttributes,PathspecSearch}; $a=GitAttributes::fromString("\"wp-content/uploads/slot[[:blank:]]/**\" ws\n", withBuiltInMacros:false); var_export($a->attributesForPath("wp-content/uploads/slot\v/photo.jpg", ["ws"])); echo "\n"; var_export(PathspecSearch::fromSpecs([":(glob)wp-content/uploads/slot[[:blank:]]/photo.jpg"])->isIncluded("wp-content/uploads/slot\v/photo.jpg", false)); echo "\n";'`
  returned `ws => null` and `false`; Gitoxide wildmatch expects both to match.
- `php -l lanes/gitoxide/src/GitAttributes.php`
- `php -l lanes/gitoxide/src/PathspecSearch.php`
- `php -l lanes/gitoxide/tests/AttributesPathspecTest.php`
- `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php`
- `php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `2 test files, 197 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  passed `39 test files, 4589 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
  exited `0`.
- `git diff --check -- lanes/gitoxide`
  passed.

## Non-Overlap

This is additive to the accepted pathspec default search-mode tree walking, attributes/pathspec POSIX digit-class matching, selected-assignment semantics, and state-adjustment value-suffix parsing. It is limited to the remaining upstream POSIX class boundary where Gitoxide's ASCII `blank` class differs from PCRE and where attributes differ from pathspecs on invalid-class fallback.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local glob/pathspec/attribute implementation, the PHP test harness, and the existing WordPress attributes/pathspec example.
