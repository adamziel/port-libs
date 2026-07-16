# Tree Pathspec Gitlink Directory Parity - 2026-06-01

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260601T145908Z`

Base accepted HEAD: `230af65eea9aebb1e5494b80a95d24a010885d55`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/tests/search/mod.rs`
  includes the directory baseline runner, where pathspecs such as `a/` match
  directory-like entries from the `match_baseline_dirs` fixture.
- The same upstream fixture archive records submodule/gitlink paths in
  `gix-pathspec/tests/fixtures/generated-archives/match_baseline_dirs.tar`;
  focused baseline rows include `+a/` matching the submodule path `a`.
- `gix-dir/src/walk/classify.rs` feeds directory status into
  `pattern_matching_relative_path()`, so pathspecs with `MUST_BE_DIR` are
  evaluated with directory semantics for directory-like worktree entries.

## Native Behavior

- `TreePathspecWalk::breadthFirst()` now treats `TreeEntry` mode `160000`
  gitlinks as directory-like for pathspec matching.
- Gitlinks are still not descended into. Only real tree entries are passed to
  the subtree reader, preserving object-database boundaries.
- `examples/wordpress-tree-pathspec-walk.php` now records a WordPress plugin
  submodule selected by `wp-content/plugins/commerce-submodule/` without a
  subtree read.

## Verification

- Red observation before the change:
  `TreePathspecWalk::breadthFirst()` over a root containing mode `160000`
  `commerce-submodule` returned `[]` for pathspec `commerce-submodule/`.
- Focused after fix:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files / 366 assertions / 0 failures`.
- The WordPress example smoke was run directly:
  `php lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php` exited `0`.

## Non-Overlap And Dependency Closure

This is bounded to tree/pathspec walking for gitlink/submodule directory
semantics. It does not repeat accepted wildmatch bracket/POSIX/newline/escape
handling, nil/exclude pruning, absolute-root normalization, raw component
guards, attr-filtered pathspecs, sparse-checkout pathspec behavior, transport,
object database, pack/index, reference transaction, merge-base, or tree-merge
slices.

No new support component is needed. The patch reuses native PHP tree entry
modes, `PathspecSearch`, the existing in-memory tree walker, the lane test
harness, and the existing WordPress tree pathspec example. No live provider,
credential store, shell-out Git process, or full upstream Cargo workspace run
was used.
