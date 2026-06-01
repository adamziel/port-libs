# Tree Pathspec Raw Component Parity - 2026-06-01

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260601T003156Z`

Base accepted HEAD: `5b87111468b46af8cd72097f10d11bf759b0ca92`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  at `87433ed33eee9ba974111d20b854f6acb07cd4a6` matches the caller-supplied
  repository-relative path bytes directly in `pattern_matching_relative_path()`,
  `can_match_relative_path()`, and `directory_matches_prefix()`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/pattern.rs`
  documents that repository-relative path matching treats slashes, and only
  slashes, literally.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-object/src/tree/ref_iter.rs`
  decodes tree entry filenames as raw bytes up to NUL and compares path
  components verbatim.

## Native Behavior

- `PathspecSearch` now validates candidate paths for NUL bytes without
  normalizing separators or `.`/`..` components during matching/pruning.
- Tree pathspec walks no longer let malformed raw tree entries such as
  `plugins/../secret.php` match a clean `wp-content/secret.php` deployment
  pathspec.
- Backslash bytes in tree entry names are no longer treated as `/` separators,
  so `weird\name.php` cannot satisfy `weird/name.php`.
- The WordPress tree-pathspec example records this deployment guard with a
  safe plugin file, a raw `..` subtree, and a raw backslash filename.

## Verification

- Red observation before the fix:
  `PathspecSearch::fromSpecs(["wp-content/plugins/weird/name.php"])->isIncluded("wp-content/plugins/weird\\name.php", false)`
  returned `true`, and `wp-content/plugins/../secret.php` matched
  `wp-content/secret.php` after candidate-path normalization.
- Focused after fix:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files / 166 assertions / 0 failures`.
- Adjacent pathspec guard:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/AttributesPathspecTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `3 test files / 590 assertions / 0 failures`.
- Full Gitoxide lane guard:
  `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files / 6496
  assertions / 0 failures`.
- Full upstream Cargo workspace runner was not executed for this isolated
  micro-slice.

## Non-Overlap And Dependency Closure

This deepens the accepted tree/pathspec walking cluster without repeating
empty-search materialization, prefix/case matching, parent pathspec
normalization, longest-common-directory hints, wildcard/POSIX class matching,
attribute filters, sparse checkout, tree-merge, pack, object database,
reference, protocol, or transport behavior.

No new support component is needed. The slice reuses the native PHP pathspec
parser/search implementation, in-memory tree traversal, the existing
WordPress tree-pathspec example, and the local upstream Gitoxide checkout for
source-truth reads; it does not shell out to Git or require live credentials.
