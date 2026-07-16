# Tree pathspec root-dot parity

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260601T100348Z`

Base accepted HEAD: `c6000a6885bc6b5b6b4980e335c606d935a6fb65`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/pattern.rs`
  sets `Pattern::nil = true` when normalized relative path components resolve
  to `.`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/init.rs`
  still builds the search common prefix from the normalized pattern bytes, so
  a root-normalized nil `.` pathspec has common prefix `.` and prunes ordinary
  repository entries.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  rejects candidates outside that common prefix before fallback matching.

Focused local upstream probe after `cargo build -p gix-pathspec`:

- `.` at an empty prefix normalized to pattern `:` with `nil=true`,
  `common_prefix="."`, and did not match `index.php`.
- `:(top).` under `wp-content/plugins` normalized the same way.
- `../..` under `wp-content/plugins` normalized the same way.
- `.` under `wp-content/plugins` stayed a prefixed pathspec for
  `wp-content/plugins`, with prefix directory `wp-content`.

## Implementation

- `PathspecPattern::withPath()` can now carry an explicit normalized `nil`
  state.
- `PathspecSearch` preserves the upstream root `.` sentinel instead of
  collapsing it into the empty match-all pattern.
- Prefix length calculation now mirrors Gitoxide's slash-index based
  `prefix_len` for dot-normalized prefixed pathspecs.
- The WordPress tree-pathspec example records root-dot pruning and the distinct
  prefixed-dot traversal case.

## Red-first evidence

Before the change, native PHP collapsed root-normalized dot pathspecs into an
empty path:

- `PathspecSearch::fromSpecs(['.'])->isIncluded('index.php')` returned `true`.
- `PathspecSearch::fromSpecs(['../..'], 'wp-content/plugins')->isIncluded('index.php')`
  returned `true`.
- `TreePathspecWalk::breadthFirst(... PathspecSearch::fromSpecs(['.']) ...)`
  walked the whole tree.

After the change, those searches have common prefix `.` and prune all ordinary
tree entries, while `PathspecSearch::fromSpecs(['.'], 'wp-content/plugins')`
continues to walk the prefixed plugin subtree.

## Verification

- `php -l lanes/gitoxide/src/PathspecPattern.php` passed.
- `php -l lanes/gitoxide/src/PathspecSearch.php` passed.
- `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed: `1 test files, 301 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/AttributesPathspecTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed: `3 test files, 954 assertions, 0 failures`.
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php"; if (($out["rootDotCommonPrefix"] ?? null) !== "." || ($out["rootDotContentPaths"] ?? null) !== [] || ($out["topDotContentPaths"] ?? null) !== [] || ($out["parentToRootDotCommonPrefix"] ?? null) !== "." || ($out["parentToRootDotContentPaths"] ?? null) !== [] || ($out["prefixedDotPrefixDirectory"] ?? null) !== "wp-content" || !in_array("wp-content/plugins/gutenberg/block.json", $out["prefixedDotContentPaths"] ?? [], true)) { fwrite(STDERR, "tree pathspec dot example failed\n"); exit(1); } echo "tree pathspec dot example ok\n";'`
  reported `tree pathspec dot example ok`.
- `git diff --check -- lanes/gitoxide` passed.

## Non-overlap

This extends accepted tree/pathspec walking without repeating absolute-root
normalization, parent-escape rejection, LF wildmatch, dangling-backslash
fallback, malformed POSIX class fallback, raw candidate component preservation,
attributes/pathspec filters, sparse-checkout pathspecs, transport, pack,
object-database, reference, merge-base, or tree-merge behavior. The new
behavior is limited to `gix-pathspec` root-normalized `.` handling during
search pruning and tree walks.

## Dependency closure

No new support component is needed. The patch reuses native PHP pathspec
normalization/search, the lane-local tree walker, the existing WordPress
tree-pathspec example, the PHP test harness, and the hydrated upstream
Gitoxide cache for source-truth inspection. It does not shell out to Git, run
live provider tests, inspect credentials, or require shared support-library
activation.
