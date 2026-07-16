# Tree Pathspec Empty Pattern Prefix Bypass Parity - 2026-06-01

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260601T061540Z`

Base accepted HEAD: `e62cd70f8878634e62c625c3c5a18ef1e32398d5`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/pathspec.rs` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`: `Pathspec::new()` accepts an
  `empty_patterns_match_prefix` switch. When there are no user patterns and
  the switch is `false`, the repository prefix is not passed to
  `gix_pathspec::Search::from_specs()`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/init.rs`
  still adds an artificial prefix pattern only when a prefix is supplied with
  an empty pattern list.
- `gix-pathspec/tests/search/mod.rs` case `no_pathspecs_respect_prefix`
  verifies the prefix-constrained side of that behavior.

## Native Behavior

- `PathspecSearch::fromSpecs()` now has a default-preserving
  `emptyPatternsMatchPrefix` flag. Existing callers keep the prefix-constrained
  behavior, while repository-wide callers can opt out when the pattern list is
  empty.
- `TreePathspecWalk` coverage proves an empty pattern list with caller prefix
  can still walk the whole in-memory repository when the flag is disabled.
- `examples/wordpress-tree-pathspec-walk.php` records the WordPress deployment
  case where a global review with no pathspec should include `wp-admin` and
  plugin build paths even if the caller's current prefix is under
  `wp-content/themes`.

## Verification

- Focused pathspec tree walk:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files, 247 assertions, 0 failures`.
- Changed-file syntax:
  `php -l lanes/gitoxide/src/PathspecSearch.php`,
  `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php`, and
  `php -l lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php` reported
  no syntax errors.
- Example smoke:
  `php -r '$out = require "lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php"; ...'`
  reported `tree pathspec empty-pattern repository-wide example ok`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed
  `40 test files, 7729 assertions, 0 failures`.
- JSON status/manifest validation:
  `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); ...'`
  and the matching manifest validation both passed.
- Diff hygiene:
  `git diff --check -- lanes/gitoxide` passed with no output.
- Full upstream Cargo workspace runner was not executed for this isolated
  micro-slice.

## Non-Overlap And Dependency Closure

This does not repeat accepted tree/pathspec empty-search materialization,
prefixed nil/empty magic normalization, absolute-root normalization, raw
component preservation, newline byte wildmatch, malformed POSIX class fallback,
longest common directory hints, attribute filters, sparse checkout, merge-base,
tree-merge, pack, object database, reference, protocol, transport, config,
credential, or partial-clone behavior. It is bounded to the upstream
repository-level switch controlling whether empty pathspecs inherit the current
prefix.

No new support component is needed. The slice reuses the native PHP pathspec
search API, in-memory tree traversal, the existing WordPress tree-pathspec
example, and local upstream Gitoxide source reads; it does not shell out to Git,
read credentials, run live providers, or require a shared support-library
activation gate.
