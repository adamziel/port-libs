# Tree Merge Super-2 Resolve-Tree Parity

Slice: `gitoxide-tree-merge-conflict-fixture-parity-20260601T022247Z`

Accepted base: `28ec15ab9aa5188bc23d7c22caf22b5083cf6e4e`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/gitoxide`
- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Fixture: `gix-merge/tests/fixtures/tree-baseline.sh`
- Mapped cases: `super-2` `make_resolve_tree ancestor A B`,
  `ancestor B A`, `ours A B`, and `ours B A`.

The upstream fixture expects ancestor resolution to keep branch A's renamed
directory tree while restoring branch B's changed-and-moved `foo` blob back at
`foo`. It expects side resolution to drop the moved file for `ours A B` and to
keep the moved blob as `newdir/bar` for `ours B A`.

## Native Delta

- `TreeMerge` records dropped-source ancestor entries for directory
  rename/add-leaf directory-file conflicts when the added leaf is a strict best
  blob-similarity match for a source path deleted by both sides.
- `TreeMergeResult` now applies conflict `ancestorEntries` during tree
  conflict ancestor resolution, not only content-conflict resolution.
- Existing unresolved conflict/index shape for `super-2` remains unchanged.

## Evidence

- Red-first: `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php`
  failed before source changes with missing resolved `foo` in the new
  `super-2 resolve-tree` test.
- Focused after fix: `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php`
  passed `1 test files, 668 assertions, 0 failures`.
- Full lane after fix: `php tools/run-tests.php lanes/gitoxide/tests` passed
  `40 test files, 6861 assertions, 0 failures`.
- PHP lint and `git diff --check -- lanes/gitoxide` passed.

## Non-Overlap

This maps one additional gix-merge tree-baseline resolve-tree fixture. It does
not repeat the already accepted simple directory-file resolve-tree,
type-change-and-renamed resolve-tree, renamed-symlink resolve-tree, or
rename-rename-delete-delete resolve-tree slices.

## Dependency Closure

No new support component is needed. The slice reuses existing in-memory Git
object and tree merge primitives; no live provider, Git binary, or upstream
Cargo workspace runner was required.
