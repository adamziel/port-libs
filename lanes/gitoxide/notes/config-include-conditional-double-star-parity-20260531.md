# Gitoxide Config Conditional Include Double-Star Parity

Slice: `gitoxide-config-include-conditional-parity-20260531T093041Z`

Accepted base: `505e973c7fba58525b7fffcb767bf99390508892`

Upstream source truth:

- `gix-config/src/file/includes/mod.rs` evaluates `gitdir`, `onbranch`, and
  `hasconfig:remote.*.url` conditions through `gix_glob::wildmatch` with slash
  literals protected from single `*` and `?` matches.
- `gix-config/tests/config/file/init/from_paths/includes/conditional/gitdir/mod.rs`
  includes `star_star_in_the_middle`, where `gitdir:**/dir/**/worktree/**`
  matches a `dir/worktree/.git` path with zero path components consumed by the
  middle `**/`.
- `gix-config/tests/config/file/init/from_paths/includes/conditional/onbranch.rs`
  covers branch `**` path globs. This slice applies the same `**/` zero
  component handling across the native config matcher so branch and remote URL
  conditional includes share the Gitoxide wildcard boundary.

Native PHP movement:

- `GitConfig::wildmatch()` now treats `**/` as an optional directory span,
  matching zero or more path components while preserving the existing single
  `*` and `?` no-slash behavior.
- `GitConfigTest.php` adds focused `includeIf` assertions for zero-component
  `**/` matches in `gitdir`, `onbranch`, and `hasconfig:remote.*.url`
  conditions.
- The WordPress config include fixture/example now places the repository under
  `sites/wp-content.git` and resolves a recursive deployment policy through
  `gitdir:**/sites/**/wp-content.git/**`.

Focused evidence:

- Red-first probes before the implementation returned `base` for
  `gitdir:**/dir/**/worktree/**` and `onbranch:feature/**/start` when the
  middle `**/` needed to consume zero components.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php` passed
  `1 test files, 53 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed
  `38 test files, 3801 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  exited 0.

Dependency closure:

No new support component is needed. This slice reuses the existing native Git
config parser, include resolver, and bounded glob matcher; no shared dependency
row or activation gate is proposed.

Non-overlap:

This extends the accepted config include/includeIf and escape-parity slices with
the missing `**/` zero-component conditional glob boundary. It does not touch
protocol, transport, pack, references, object database, index, attributes,
pathspec, or merge behavior. The old Gitoxide smart-HTTP rework notes are stale
for this slice because the accepted tree already contains the redirect behavior
they asked to rebase.
