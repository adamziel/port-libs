# Tree Pathspec Dangling Backslash Wildmatch Parity - 2026-06-01

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260601T072352Z`

Base accepted HEAD: `811f5d6b83ee52b766cc41e0a53273a78acf91cd`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  at `87433ed33eee9ba974111d20b854f6acb07cd4a6` returns a non-match when a
  pattern ends with a dangling backslash escape.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  routes wildcard pathspecs through wildmatch first, then falls back to
  verbatim matching when wildmatch does not match.

## Native Behavior

- `PathspecSearch` now makes dangling trailing backslash escapes fail the
  wildcard regex translation, allowing the existing verbatim fallback to decide
  exact tree path matches.
- A pathspec such as `:(glob)wp-content/plugins/dangling\` reports a verbatim
  match for the exact trailing-backslash tree entry.
- A wildcard prefix such as `:(glob)wp-content/plugins/dang*\` no longer
  expands to `dangling\`; it only matches the literal `dang*\` path through the
  verbatim fallback.
- The WordPress tree-pathspec example records deployable plugin entries with
  trailing backslash bytes and proves the wildcard expansion is skipped.

## Verification

- Red observation before the fix:
  `PathspecSearch::fromSpecs([":(glob)wp-content/plugins/dangling\\\\"])->match("wp-content/plugins/dangling\\\\", false)?->kind`
  returned `wildcard`, and
  `PathspecSearch::fromSpecs([":(glob)wp-content/plugins/dang*\\\\"])->isIncluded("wp-content/plugins/dangling\\\\", false)`
  returned `true`.
- Focused pathspec tree walk:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files / 257 assertions / 0 failures`.
- Adjacent pathspec guard:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/AttributesPathspecTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `3 test files / 866 assertions / 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests`
  passed `40 test files / 8036 assertions / 0 failures`.
- Full upstream Cargo workspace runner was not executed for this isolated
  micro-slice.

## Non-Overlap And Dependency Closure

This deepens the accepted tree/pathspec walking cluster without repeating raw
component preservation, prefixed nil/empty magic normalization, empty-pattern
prefix bypass, newline byte wildmatch, malformed POSIX class fallback,
absolute-root normalization, sparse-checkout matching, attribute filters,
transport, protocol, pack, reference transaction, merge-base, or tree-merge
behavior. It is bounded to dangling trailing backslash wildmatch abort plus
pathspec verbatim fallback during tree walks.

No new support component is needed. The slice reuses the native PHP pathspec
search implementation, in-memory tree traversal, existing WordPress
tree-pathspec example, and local upstream Gitoxide source reads; it does not
shell out to Git, read credentials, run live providers, or require a shared
support-library activation gate.
