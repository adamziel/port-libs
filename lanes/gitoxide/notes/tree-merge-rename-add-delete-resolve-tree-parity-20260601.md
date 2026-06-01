# Tree Merge Rename-Add-Delete Resolve-Tree Parity

Micro-slice: `gitoxide-tree-merge-conflict-fixture-parity-20260601T104107Z`

Source truth:

- Upstream cache: `/home/claude/port-libs/.upstream-cache/gitoxide`
- Pinned upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Upstream fixture: `gix-merge/tests/fixtures/tree-baseline.sh`
- Upstream parser/test harness: `gix-merge/tests/merge/tree/baseline.rs`
- Focused fixture case: `rename-add-delete`

The selected upstream fixture creates a base `foo` blob with `original file`,
side A deletes `foo` and adds `bar` with `different file`, and side B renames
`foo` to `bar` while preserving the original contents. The generated
resolve-tree expectations keep `bar` with the original base contents for
`ancestor A B`, and keep `bar` with side A contents for `ours A B`.

Red-first evidence:

- Before the fix, `TreeMergeResult::resolveTreeConflicts(... RESOLVE_ANCESTOR)`
  removed `bar` for this fixture shape because the rename-target-add conflict
  only carried source ancestor entries when more than one source was present.
- `TreeMergeTest.php` passed at 795 assertions before the added assertions.

Implementation:

- `TreeMerge` now attaches a single source ancestor at the rename target for
  rename-target-add content conflicts.
- Multi-source rename-target collisions still use source-path ancestor entries,
  preserving the existing `rename-rename-delete-delete` resolve-tree behavior.
- The WordPress tree-merge fixture/example now includes the same
  rename/add/delete shape using plugin review filenames.

Verification:

- `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php`
  - Result: `1 test files, 804 assertions, 0 failures`
  - Focused delta: `+9` assertions from the pre-change 795-assertion baseline
- `php -l lanes/gitoxide/src/TreeMerge.php`
  - Result: no syntax errors
- `php -l lanes/gitoxide/tests/TreeMergeTest.php`
  - Result: no syntax errors
- `php -l lanes/gitoxide/fixtures/wordpress-tree-merge.php`
  - Result: no syntax errors
- `php -l lanes/gitoxide/examples/wordpress-tree-merge.php`
  - Result: no syntax errors
- `php lanes/gitoxide/examples/wordpress-tree-merge.php`
  - Result: prints `rename-add-delete-conflicts=1` and the ancestor-resolved
    `acme-review.php` body with `Status: stable`

Non-overlap:

- This slice is distinct from the already mapped `rename-add-symlink`,
  `type-change-and-renamed`, `rename-rename-plus-content`, and
  `rename-rename-delete-delete` fixture parity checks.
- No transport, object database, reference transaction, pack/index, or
  pathspec behavior was changed.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP
  `TreeMerge`, `TreeMergeResult`, in-memory object callbacks, fixture, and
  example infrastructure.

Next task:

- Continue with remaining non-overlapping `gix-merge` resolve-tree fixture
  parity, or pivot to high-signal protocol/transport, object database,
  reference transaction, pack/index, or pathspec gaps.
