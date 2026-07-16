# Tree Merge Rename-Within-Rename Resolve-Tree Parity - 2026-06-01

Micro-slice: `gitoxide-tree-merge-conflict-fixture-parity-20260601T032558Z`
Base accepted HEAD: `639880c48c54d40c3ed0188758af6aee8d8d2712`

## Source Truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/gitoxide`
- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Fixture script: `gix-merge/tests/fixtures/tree-baseline.sh`
- Fixture cluster: `rename-within-rename`, `resolve-tree A/B` and `resolve-tree B/A`

The upstream fixture resolves the nested directory rename by keeping the
renamed top-level directory while restoring the base nested directory for the
ancestor strategy. In the opposite direction, the forced ours strategy keeps
the doubly-renamed nested directory. Both resolved trees are clean and leave no
index stages.

## Native Delta

- `TreeMerge::nestedDirectoryRenameConflicts()` now records the original
  ancestor subtree path and the renamed source-path peer for nested directory
  rename conflicts.
- `TreeMergeResult::resolveConflictAncestorEntries()` removes the renamed peer
  source path before restoring ancestor entries and can recreate missing parent
  directories while applying ancestor resolution.
- `TreeMergeTest.php` now asserts the upstream `rename-within-rename`
  resolve-tree A/B and B/A shapes for forced ancestor and forced ours.
- `wordpress-recursive-tree-merge.php` now exposes a smoke for WordPress plugin
  directory rename resolution, including clean forced ancestor/ours output and
  zero post-resolution index stages.

## Red/Green Evidence

Red-first after adding only the upstream fixture assertions:

```text
php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php
1 test files, 668 assertions, 1 failures
Failure: Fixture object not found
```

Final focused verification:

```text
php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php
1 test files, 695 assertions, 0 failures
```

Full lane verification:

```text
php tools/run-tests.php lanes/gitoxide/tests
40 test files, 7161 assertions, 0 failures
```

Example smoke:

```text
php lanes/gitoxide/examples/wordpress-recursive-tree-merge.php
exits 0; nestedDirectoryRename ancestorResolvedClean=true,
oursResolvedClean=true, ancestorIndexStages=0, oursIndexStages=0
```

## Non-Overlap

This slice avoids the accepted tree-merge clusters for `rename-within-rename-2`
same-target conflicts, `renamed-symlink`, `type-change-and-renamed`,
`directory-file`, `super-2`, conflicting renames, and
`rename-rename-delete-delete`. It maps one conservative additional upstream
fixture behavior: `rename-within-rename` forced resolve-tree parity.

## Dependency Closure

No new support component is needed. The slice reuses the existing in-memory
Git object/tree model, recursive tree merge implementation, merge-index
expansion, and WordPress recursive tree-merge example.
