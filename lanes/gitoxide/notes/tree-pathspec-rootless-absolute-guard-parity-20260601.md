# Tree Pathspec Rootless Absolute Guard Parity - 2026-06-01

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260601T162039Z`
Base accepted HEAD: `2bc998068458c71f9e16d8cff706109fe8afb13f`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/pathspec.rs`
  creates repository pathspec searches with the repository worktree or git-dir
  realpath as the root.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/pattern.rs`
  sends absolute pathspec paths through `strip_prefix(root)` before matching
  repository-relative paths, returning an `AbsolutePathOutsideOfWorktree`
  normalization error when the absolute path cannot be made root-relative.
- Existing PHP coverage already handled rooted absolute pathspecs. The missing
  edge was the rootless API path, which silently stripped the leading slash and
  matched repository-relative tree entries.

## Native Delta

- `PathspecSearch::fromSpecs()` now rejects absolute pathspecs unless a
  pathspec root is provided.
- Rooted absolute pathspecs such as
  `/srv/www/example.com/current/wp-content/plugins/gutenberg/block.json` still
  normalize to repository-relative tree paths under the supplied root.
- The WordPress tree-pathspec example records this guard for deployment tools
  that must not interpret a user-supplied absolute filesystem path as a
  repository-relative WordPress content path.

## Red-First Evidence

Before the change:

```sh
php -r 'require "tools/bootstrap.php"; $s=PortLibs\Gitoxide\PathspecSearch::fromSpecs(["/wp-content/plugins/safe.php"]); var_export([$s->patterns()[0]->path, $s->isIncluded("wp-content/plugins/safe.php", false)]); echo "\n";'
```

returned:

```php
array (
  0 => 'wp-content/plugins/safe.php',
  1 => true,
)
```

The rootless absolute pathspec should not be interpreted as a safe
repository-relative path.

## Verification

- `php -l lanes/gitoxide/src/PathspecSearch.php`
- `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php`
- `php -l lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php`
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files, 368 assertions, 0 failures`.
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php"; if (($out["rootlessAbsolutePathspecRejected"] ?? null) !== true || ($out["absoluteRootContentPaths"] ?? null) !== ["index.php", "wp-content/plugins/gutenberg/block.json"]) { fwrite(STDERR, "tree pathspec absolute-root example failed\n"); exit(1); } echo "tree pathspec absolute-root example ok\n";'`
  reported `tree pathspec absolute-root example ok`.
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/AttributesPathspecTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `3 test files, 1166 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed
  `40 test files, 10129 assertions, 0 failures`.

Root harness: not run - isolated micro-slice.
Full upstream Cargo workspace: not run.

## Non-Overlap

This extends tree/pathspec walking without repeating accepted rooted absolute
normalization, root-dot, prefixed nil, empty-pattern prefix bypass, raw
component preservation, escaped-byte traversal, whitespace fallback, gitlink
directory semantics, malformed/POSIX class fallback, sparse-checkout pathspec,
attributes/pathspec filters, transport/protocol, pack/object database,
reference transactions, merge-base, or tree-merge slices. The mapped behavior
is limited to rejecting absolute pathspecs when no worktree root is available
to normalize them.

## Dependency Closure

No new support component is needed. This reuses the lane-local pathspec parser,
tree walker, WordPress tree-pathspec example, PHP test harness, and hydrated
Gitoxide upstream cache for source-truth inspection. It does not shell out to
Git, run live provider tests, inspect credentials, or require a shared support
activation gate.
