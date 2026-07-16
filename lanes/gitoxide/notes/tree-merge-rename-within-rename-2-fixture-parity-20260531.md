# Tree Merge Rename-Within-Rename-2 Fixture Parity

Slice: `gitoxide-tree-merge-conflict-fixture-parity-20260531T135918Z`

Base accepted HEAD: `7f53fcd353eeefd16948edc334eb7d1204b1ec5b`

Upstream source truth:

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Fixture generator: `gix-merge/tests/fixtures/tree-baseline.sh`
- Generated archive: `gix-merge/tests/fixtures/generated-archives/tree-baseline.tar`
- Fixture case: `rename-within-rename-2`

Mapped behavior:

- Side A edits `a/x.f` and `a/sub/y.f`, renames `a/sub` to `a/sub-renamed`, then renames parent `a` to `a-renamed`.
- Side B edits the same files and renames `a/sub` to `a/sub-renamed` without renaming parent `a`.
- The upstream A-B fixture is not clean: it keeps `a-renamed/sub-renamed/y.f` as a marker blob conflict and writes only stage 2 and stage 3 entries for that nested path.
- The reversed B-A fixture remains clean and preserves the merged nested file content.

Implementation note:

- `TreeMerge::sameTargetNestedRenameConflicts()` detects the narrow same-source/same-target nested directory rename that sits under a parent directory rename only on our side.
- `TreeMerge::mergeSameTargetRenameTreeWithoutBase()` rebases that nested rename target without an ancestor, writes a marker blob for conflicting nested files, and keeps clean additions or equal entries intact.

Verification:

- Red-first check before the source change: `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` failed the new fixture assertion with `1 test files, 481 assertions, 1 failures`.
- Focused green: `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` passed with `1 test files, 495 assertions, 0 failures`.
- Full lane green: `php tools/run-tests.php lanes/gitoxide/tests` passed with `39 test files, 4740 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-recursive-tree-merge.php` exited 0 and reported `sameTargetNestedRename.clean=false` with stage 2 and 3 entries at `wp-content/plugins/acme-pro/src/rest.php`.
- Syntax and whitespace checks: changed PHP files pass `php -l`; `git diff --check -- lanes/gitoxide` passes.

Dependency closure:

- No new support component is needed. The slice reuses native `TreeMerge`, `BlobMerge`, object-store tree/blob writes, merge-index stage expansion, and the existing WordPress recursive merge example.
