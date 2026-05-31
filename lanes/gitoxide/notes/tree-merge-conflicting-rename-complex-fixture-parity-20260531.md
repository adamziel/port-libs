# Tree Merge Conflicting-Rename-Complex Fixture Parity

Slice: `gitoxide-tree-merge-conflict-fixture-parity-20260531T153111Z`

Base accepted HEAD: `a7ecc1c03f47b919bbd97dfd951b936133999f9f`

Upstream source truth:

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Fixture generator: `gix-merge/tests/fixtures/tree-baseline.sh`
- Generated archive: `gix-merge/tests/fixtures/generated-archives/tree-baseline.tar`
- Fixture case: `conflicting-rename-complex`

Mapped behavior:

- Side A renames directory `a` to `a-renamed`, edits `a/sub/y.f`, and edits root file `a/x.f`.
- Side B keeps the old directory path `a` but replaces that directory with the former `a/sub` subtree hoisted to the root, including a modified `y.f`.
- The upstream A-B fixture is not clean: the merged tree keeps `a-renamed/sub`, overlays the hoisted replacement root entries, and carries staged conflicts for the renamed directory plus the old root paths.
- The reversed B-A fixture preserves the same merged tree content while swapping the side-specific index stages.

Implementation note:

- `TreeMerge::tryMergeDirectoryRenameSubtreeReplacement()` detects this narrow directory-rename plus subtree-replacement shape before the generic changed-entry merge path.
- The helper starts from the renamed directory tree, overlays non-colliding replacement-root entries, copies a strict-best replacement leaf back to a changed root file when the upstream fixture implies that relocation, and emits expanded index stages through the existing `MergeIndexFile` machinery.
- This preserves the existing tree-merge behavior for simple directory renames, same-target nested renames, and directory rename target collisions.

Verification:

- Red-first native probe before the source change produced only `a-renamed/sub`, `a-renamed/y.f`, and `a-renamed/z`, with a single delete-modify conflict at `a-renamed/sub`; it missed the upstream `w`/`x.f` overlay and old-path conflict stages.
- Focused green: `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` passed with `1 test files, 512 assertions, 0 failures`.
- Full lane green: `php tools/run-tests.php lanes/gitoxide/tests` passed with `39 test files, 4823 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-recursive-tree-merge.php` exited 0 and reported `subtreeReplacementRename.clean=false` with conflicts at `wp-content/plugins/acme-pro`, `wp-content/plugins/acme/bootstrap.php`, and `wp-content/plugins/acme/rest.php`.
- Syntax and whitespace checks: changed PHP files pass `php -l`; `git diff --check -- lanes/gitoxide` passes.

Dependency closure:

- No new support component is needed. The slice reuses native `TreeMerge`, tree/blob object storage, rename similarity scoring, merge-index stage expansion, and the existing WordPress recursive merge example.
