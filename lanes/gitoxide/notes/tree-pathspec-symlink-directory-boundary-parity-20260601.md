# Tree Pathspec Symlink Directory Boundary Parity - 2026-06-01

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260601T173642Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-traverse/src/tree/breadthfirst.rs`
  sends only entries whose mode `is_tree()` through the tree-visit and queued
  descent path; all other entries, including symlinks, are non-tree visits.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  keeps `MUST_BE_DIR` pathspecs dependent on the caller-provided directory
  classification during verbatim matching.

## Native Delta

- `PathspecTreeWalkTest.php` now pins the boundary between accepted gitlink
  directory matching and symlink non-directory behavior: a mode `120000`
  symlink named like a plugin directory does not match `linked-plugin/` as a
  tree entry and is not passed to the tree reader, while a real neighboring
  tree still descends and materializes its manifest.
- `wordpress-tree-pathspec-walk.php` records the same deployment-review case
  for WordPress plugin symlinks so a directory-only pathspec cannot cause the
  in-memory tree walker to follow a symlink object as if it were a subtree.
- No production source change was required; the existing `TreeEntry` mode
  classifier and `TreePathspecWalk::breadthFirst()` already follow this
  upstream boundary. The patch makes the parity explicit and non-regressable.

## Verification

- Baseline focused tree/pathspec run before this patch:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files, 370 assertions, 0 failures`.
- After adding parity coverage:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files, 381 assertions, 0 failures`.
- `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php` and
  `php -l lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php` reported
  no syntax errors.
- `php lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php` exited `0`.
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php"; if (($out["symlinkDirectoryBoundaryContentPaths"] ?? null) !== ["wp-content/plugins/gutenberg/block.json", "wp-content/plugins/gutenberg/block.gson", "wp-content/plugins/gutenberg/build/index.js", "wp-content/plugins/gutenberg/src/editor.js"] || in_array("wp-content/plugins/linked-plugin", $out["symlinkDirectoryBoundaryReadPaths"] ?? [], true) || ($out["symlinkDirectoryBoundaryFileModeSkipped"] ?? null) !== true || ($out["symlinkDirectoryBoundaryDirectoryModeWouldMatch"] ?? null) !== true) { fwrite(STDERR, "tree pathspec symlink example failed\n"); exit(1); } echo "tree pathspec symlink example ok\n";'`
  reported `tree pathspec symlink example ok`.
- `git diff --check -- lanes/gitoxide` passed.

## Non-Overlap

This extends tree/pathspec walking without repeating accepted gitlink directory
matching, rootless absolute pathspec rejection, whitespace directory fallback,
negative wildcard directory traversal, escaped-byte traversal, raw component
guards, sparse-checkout pathspecs, attributes/pathspec filters, transport,
pack/object database, reference transactions, merge-base, or tree-merge work.

## Dependency Closure

No new support component is needed. This reuses the lane-local tree model,
pathspec matcher/search APIs, in-memory tree-pathspec walker, existing
WordPress tree-pathspec example, PHP test harness, and hydrated upstream
Gitoxide source cache. No live provider tests, credentials, or upstream Cargo
workspace run were required.
