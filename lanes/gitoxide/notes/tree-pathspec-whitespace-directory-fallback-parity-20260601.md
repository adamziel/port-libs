# Gitoxide Tree Pathspec Whitespace Directory Fallback Parity

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260601T084004Z`
Base accepted HEAD: `56d05df2fec029b5e619e6a16107a698092a4221`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/init.rs`
  creates a `gix_glob::Pattern` for each normalized pathspec and falls back to
  a literal pattern when `gix_glob::Pattern::from_bytes_without_negation()`
  returns `None`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/parse.rs`
  returns `None` for patterns whose bytes are all ASCII whitespace.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  checks the glob mapping's `MUST_BE_DIR` bit during verbatim matching. The
  all-whitespace fallback mapping does not carry that bit, so `   /` and
  `\f/` still match files named with those exact whitespace bytes.

## Native Delta

- `PathspecSearch::verbatimMatchKind()` now skips the directory-only
  requirement for normalized pathspecs made entirely of ASCII whitespace,
  matching the upstream gix-glob fallback edge while keeping ordinary
  directory-only pathspec behavior unchanged.
- `PathspecTreeWalkTest.php` adds direct matching and tree-walk coverage for
  space-only and form-feed-only directory pathspecs matching exact file names.
- `wordpress-tree-pathspec-walk.php` records the same repository-root artifact
  edge for WordPress deployment snapshots that preserve unusual archive paths.
- `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` record the focused
  assertion growth and conservative mapped coverage change.

## Red-First Evidence

Before the change:

```sh
php -r 'require "tools/bootstrap.php"; $s = PortLibs\Gitoxide\PathspecSearch::fromSpecs(["   /"]); var_export([$s->isIncluded("   ", false), $s->match("   ", false)?->kind, $s->isIncluded("   /file.php", false), $s->match("   /file.php", false)?->kind]); echo "\n";'
```

returned:

```php
array (
  0 => false,
  1 => NULL,
  2 => true,
  3 => 'prefix',
)
```

The exact file named with three spaces should match like upstream.

## Verification

- `php -l lanes/gitoxide/src/PathspecSearch.php`
- `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php`
- `php -l lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php`
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files, 270 assertions, 0 failures`.
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php"; foreach (["whitespaceDirectoryOnlySpaceFileIncluded", "whitespaceDirectoryOnlyFormFeedFileIncluded"] as $key) { if (($out[$key] ?? null) !== true) { fwrite(STDERR, $key . " failed\n"); exit(1); } } if (($out["whitespaceDirectoryOnlyContentPaths"] ?? []) !== ["   ", "\f"]) { fwrite(STDERR, "whitespace content paths failed\n"); exit(1); } echo "tree pathspec whitespace fallback example ok\n";'`
  reported `tree pathspec whitespace fallback example ok`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed
  `40 test files, 8324 assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide` passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This extends tree/pathspec walking without repeating accepted nil/top/exclude
matching, default search modes, caller-prefix normalization, absolute-root
walking, raw path components, newline-byte wildmatch, dangling-backslash
fallback, malformed/POSIX class fallback, attributes/pathspec filters,
sparse-checkout pathspec behavior, transport/protocol, pack/object database,
reference transactions, merge-base, or tree-merge slices. The mapped behavior
is limited to the gix-glob all-ASCII-whitespace parser fallback as observed
through `gix-pathspec` tree walking.

## Dependency Closure

No new support component is needed. This reuses the lane-local pathspec parser,
tree walker, existing WordPress example, PHP test harness, and the hydrated
Gitoxide upstream cache for source-truth inspection. It does not shell out to
Git, run live provider tests, inspect credentials, or require a shared support
activation gate.
