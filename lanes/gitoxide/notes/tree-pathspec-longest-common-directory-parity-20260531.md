# Tree Pathspec Longest Common Directory Parity - 2026-05-31

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260531T211334Z`

Base accepted HEAD: `3a3374ad59c06e8a3561833481036dd945373160`

## Upstream Source Truth

- `gix-pathspec/src/search/mod.rs`: `Search::longest_common_directory()`
  returns a directory hint for non-exclusive pathspecs, returns `None` when the
  result would be empty, and does not report the caller prefix as a longer
  directory by itself.
- `gix-pathspec/src/search/init.rs`: `common_prefix_len()` computes the
  common prefix from non-excluded pathspecs, using only the caller
  `prefix_len` for `ICASE` pathspecs.
- `gix-pathspec/src/search/matching.rs`: tree and directory walkers use
  `can_match_relative_path()` and `directory_matches_prefix()` after this
  prefix pruning hint, so the hint must not include trailing slash bytes or
  file-name prefixes.

## Native Behavior

- `PathspecSearch::longestCommonDirectory()` now mirrors the upstream
  directory-boundary rule: wildcard/file prefixes return their containing
  directory without a trailing slash, single-component file prefixes return
  `null`, and directory-only pathspecs return the full directory.
- Caller-prefix-only `:(icase)` searches keep their case-sensitive
  `prefixDirectory()` but now report no longer common directory, matching the
  upstream `prefix_len` behavior.
- `examples/wordpress-tree-pathspec-walk.php` records the WordPress deployment
  pruning hints for plugin JSON walks, caller-prefix-only walks, and
  directory-only plugin walks.

## Verification

- Red observation before change:
  `PathspecSearch::fromSpecs(["foo/bar", "foo/baz"])->longestCommonDirectory()`
  returned `'foo/'`, and `PathspecSearch::fromSpecs([":(icase)bar"], "FOO")->longestCommonDirectory()`
  returned `'FOO'`.
- Focused after fix:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files / 140 assertions / 0 failures`.
- Full Gitoxide lane guard:
  `php tools/run-tests.php lanes/gitoxide/tests` passed `39 test files / 5762
  assertions / 0 failures`.
- Syntax:
  `php -l lanes/gitoxide/src/PathspecSearch.php`,
  `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php`, and
  `php -l lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php` reported
  no syntax errors.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php` exited `0`.
- Whitespace:
  `git diff --check -- lanes/gitoxide` exited `0`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap And Dependency Closure

This extends accepted tree/pathspec walking without repeating empty-search
materialization, prefix/case matching, parent escape rejection, wildcard/POSIX
class matching, sparse-checkout pathspecs, attributes filters, protocol,
object, reference, pack, transport, or merge behavior. The mapped behavior is
limited to upstream longest-common-directory pruning hint parity for tree and
directory walks.

No new support component is needed. The slice reuses the native PHP pathspec
parser/search implementation, in-memory tree traversal tests, and existing
WordPress tree-pathspec example; it does not shell out to Git or require live
provider credentials.
