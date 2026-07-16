# Tree Pathspec Prefixed Nil Parity - 2026-06-01

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260601T040109Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/pattern.rs`
  at `87433ed33eee9ba974111d20b854f6acb07cd4a6` normalizes every pattern,
  including `:` and empty-magic pathspecs, against the caller prefix unless
  the pathspec is marked `top`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/init.rs`
  computes the search common prefix from normalized non-excluded patterns.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  applies that common prefix before returning an always-match result, so a
  prefixed nil pathspec only walks entries under the caller prefix.

## Native PHP Delta

- `PathspecSearch::normalizePattern()` now applies the caller prefix to nil
  and empty-path magic patterns before search construction, unless the pattern
  is explicitly `top`.
- `PathspecTreeWalkTest.php` covers prefixed `:`, prefixed `:()`, and
  prefixed `:(exclude)` tree walking so explicit nil searches no longer leak
  to repository-root entries.
- `examples/wordpress-tree-pathspec-walk.php` records WordPress deployment
  tree output for prefixed nil, empty-magic, and exclude-only pathspecs.

## Verification

- Focused pathspec tree walk:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files / 226 assertions / 0 failures`.
- Adjacent pathspec/attributes/sparse guard:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/AttributesPathspecTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `3 test files / 755 assertions / 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed
  `40 test files / 7311 assertions / 0 failures`.
- Example smoke:
  `php -r '...'` reported `tree pathspec prefixed nil example ok`.

Full upstream Cargo workspace tests were not run for this isolated micro-slice.

## Non-Overlap

This extends the accepted tree/pathspec walk cluster without repeating
absolute-root normalization, raw component preservation, newline-byte
wildmatch, empty-search fallback, default search modes, POSIX class handling,
sparse-checkout matching, attributes/pathspec filters, object database,
transport, reference transaction, merge-base, or tree-merge behavior.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP pathspec
parser/search implementation, in-memory tree traversal, and the existing
WordPress tree-pathspec example; it does not shell out to Git or require live
provider credentials.
