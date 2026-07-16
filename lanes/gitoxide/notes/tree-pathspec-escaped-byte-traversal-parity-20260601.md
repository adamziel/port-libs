# Tree Pathspec Escaped Byte Traversal Parity - 2026-06-01

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260601T134458Z`

Base accepted HEAD: `9cec814218deb6c90aaec05ae00c825ef24541da`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/parse.rs`
  defines backslash as a glob metacharacter in `GLOB_CHARACTERS`, so an
  escaped byte participates in first-wildcard and prefix calculations.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  matches a backslash-escaped byte against the following byte, while still
  reporting a normal non-match for alternate spellings.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  uses `first_wildcard_pos` in `can_match_relative_path()` and then falls
  back to verbatim matching if wildcard matching does not include the path.

## Native Behavior

- `PathspecTreeWalkTest.php` now pins the tree-walk behavior for
  `:(glob)wp-content/plugins/f\oo/block.json`.
- The same pathspec keeps `wp-content/plugins/f` and
  `wp-content/plugins/foo` traversable, matches `foo/block.json` by wildcard
  escaped-byte behavior, and matches `f\oo/block.json` by the upstream
  verbatim fallback.
- `wordpress-tree-pathspec-walk.php` records the WordPress deployment review
  case with adjacent `f`, `foo`, and `f\oo` plugin directories.
- No production source change was required; the existing PHP pathspec matcher
  already had the upstream escaped-byte traversal behavior after earlier
  pathspec work.

## Verification

- Baseline focused tree/pathspec run before this patch:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files, 338 assertions, 0 failures`.
- Focused after patch:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files, 354 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests`
  passed `40 test files, 9642 assertions, 0 failures`.
- Syntax:
  `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php` and
  `php -l lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php` reported
  no syntax errors.
- Example smoke:
  `php -r '$out = require "lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php"; ...'`
  reported `tree pathspec escaped byte example ok`.
- `git diff --check -- lanes/gitoxide` passed.
- Full upstream Cargo workspace runner was not executed for this isolated
  micro-slice.

## Non-Overlap And Dependency Closure

This extends tree/pathspec walking without repeating sparse-checkout escaped
byte traversal, dangling-backslash fallback, newline byte wildmatch, malformed
POSIX class fallback, whitespace directory fallback, raw component
preservation, empty/prefixed pathspec handling, absolute-root normalization,
attributes/pathspec filters, config include, transport/protocol, pack/object
database, reference transactions, merge-base, or tree-merge behavior.

No new support component is needed. The slice reuses the lane-local pathspec
parser/search implementation, in-memory tree traversal, existing WordPress
tree-pathspec example, PHP test harness, and the hydrated local Gitoxide
upstream checkout for source-truth reads; it does not shell out to Git, read
credentials, contact remotes, or require a shared support-library activation
gate.
