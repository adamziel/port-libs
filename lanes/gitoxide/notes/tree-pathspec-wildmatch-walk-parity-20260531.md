# Tree Pathspec Wildmatch Walk Parity - 2026-05-31

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260531T102219Z`

Base accepted HEAD: `abe349fe4c5a6f978b53aa40c7bbfdcb020ef0a8`

## Upstream Source Truth

- `gix-pathspec/src/search/matching.rs`: pathspec search delegates wildcard
  matches to `gix_glob::wildmatch`, using shell-glob mode for default
  pathspecs and `NO_MATCH_SLASH_LITERAL` for `:(glob)` path-aware matching.
- `gix-pathspec/src/search/init.rs`: normalized search patterns retain the
  first wildcard position used by tree-pruning checks.
- `gix-glob/src/wildmatch.rs`: wildcard matching supports backslash-escaped
  literals, bracket/range classes, `!`/`^` negated classes, POSIX classes, and
  `**/` matching zero or more directory components.

## Native Behavior

- `PathspecSearch` now preserves backslash escapes while normalizing pathspec
  patterns, instead of converting them to path separators before matching.
- Tree-walk pathspec matching now handles escaped literals such as
  `theme.\?son` and `\[literal\]`, bracket/range classes such as `[ag]` and
  `[!1-4]`, POSIX classes such as `[[:digit:]]`, and recursive `**/` matches
  that include the zero-directory case.
- `:(glob)` path-aware matching rejects slash bytes from `*`, `?`, and
  bracket classes, while default shell-glob pathspecs can still match slash
  through those wildcards.
- `examples/wordpress-tree-pathspec-walk.php` now records these rules for a
  deployment tree with literal `?`/`[]` filenames, plugin block selection, and
  upload-range pruning.

## Verification

- Red observation before change:
  `PathspecSearch::fromSpecs([":(glob)wp-content/**/theme.\\?son", ":(glob)wp-content/plugins/[ag]*/block.[jt]son"])`
  returned `false` for `wp-content/theme.?son`,
  `wp-content/themes/site/theme.?son`, and
  `wp-content/plugins/akismet/block.json`.
- Focused after fix:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files / 84 assertions / 0 failures`.
- Full Gitoxide lane guard:
  `php tools/run-tests.php lanes/gitoxide/tests` passed
  `38 test files / 4137 assertions / 0 failures`.
- Example smoke:
  `php -r '$out = require "lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php"; ...'`
  reported `tree pathspec wildmatch example ok`.

## Non-Overlap And Dependency Closure

This extends the accepted tree/pathspec walking slices without repeating empty
pathspec matching, caller-prefix case sensitivity, sparse-checkout wildmatch
matching, attributes/pathspec state filters, protocol, object, pack, ref, or
transport behavior. The mapped behavior is limited to using Gitoxide-style
wildmatch semantics from `PathspecSearch` during tree-pathspec walks.

No new support component is needed. This slice reuses native PHP byte-string
pathspec parsing/search and in-memory tree traversal; it does not shell out to
Git, run live provider tests, or require credential-bearing inputs.
