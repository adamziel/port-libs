# Attributes Pathspec Directory Metadata Parity - 2026-06-01

Micro-slice: `gitoxide-attributes-pathspec-match-parity-20260601T183702Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/pattern.rs`
  has `matches_repo_relative_path()` treat missing directory metadata as
  `false`, so a `MUST_BE_DIR` pattern only matches when the caller explicitly
  provides directory classification.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  unwraps `is_dir` to `false` before invoking attribute lookups for
  attr-filtered pathspec matching.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-attributes/src/search/attributes.rs`
  routes attribute pattern matching through the same glob matcher with slash
  literals, preserving the directory-only requirement.

## Native Delta

- `GitAttributes::patternMatches()` now rejects directory-only attribute
  patterns unless `$isDirectory` is exactly `true`, matching Gitoxide's
  `None -> false` directory default.
- `AttributesPathspecTest.php` covers direct attribute lookups and
  `PathspecMatcher` / `PathspecSearch` attr-filtered pathspecs for explicit
  directory metadata, missing metadata, file metadata, and child-file paths.
- `wordpress-attributes-pathspec.php` records the deployment-review scenario:
  a directory-only deploy attribute on `wp-content/plugins/**/` cannot include
  a plugin directory through an attr pathspec unless the caller classified that
  path as a directory.

## Red-First Evidence

Before the production change, this focused probe showed missing directory
metadata incorrectly matching the directory-only attribute and attr pathspec:

```bash
php -r 'require "tools/bootstrap.php"; $a = \PortLibs\Gitoxide\GitAttributes::fromString("wp-content/plugins/**/ dir-only\n", withBuiltInMacros: false); var_export([$a->attributesForPath("wp-content/plugins/editor", ["dir-only"]), $a->attributesForPath("wp-content/plugins/editor", ["dir-only"], false), \PortLibs\Gitoxide\PathspecMatcher::matchesOne(":(attr:dir-only)wp-content/plugins/**", "wp-content/plugins/editor", null, $a), \PortLibs\Gitoxide\PathspecMatcher::matchesOne(":(attr:dir-only)wp-content/plugins/**", "wp-content/plugins/editor", false, $a)]); echo PHP_EOL;'
```

Output before the fix:

```php
array (
  0 =>
  array (
    'dir-only' => true,
  ),
  1 =>
  array (
    'dir-only' => NULL,
  ),
  2 => true,
  3 => false,
)
```

## Verification

- `php -l lanes/gitoxide/src/GitAttributes.php` reported no syntax errors.
- `php -l lanes/gitoxide/tests/AttributesPathspecTest.php` reported no syntax
  errors.
- `php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php` reported
  no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  passed `1 test files, 386 assertions, 0 failures`.
- Example smoke:
  `php -r '$out = require "lanes/gitoxide/examples/wordpress-attributes-pathspec.php"; foreach (["directoryOnlyAttrPathspecRequiresDirectoryMetadata", "directoryOnlyAttrPathspecMatchesExplicitDirectory", "directoryOnlyAttrPathspecSkipsFileChild"] as $key) { if (($out[$key] ?? null) !== true) { fwrite(STDERR, $key . " failed\n"); exit(1); } } echo "attributes directory-only pathspec example ok\n";'`
  reported `attributes directory-only pathspec example ok`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 10518
  assertions, 0 failures`.
- `jq empty lanes/gitoxide/lane-status.json` passed.
- `git diff --check -- lanes/gitoxide` passed.

## Non-Overlap

This is limited to directory-only attribute pattern metadata before
attr-filtered pathspec matching. It does not repeat accepted POSIX class,
blank/vtab, malformed bracket/POSIX, quoted pattern, ASCII whitespace,
NUL-field, selected assignment, recursive macro, double-star, backslash,
tree/sparse pathspec, transport, pack/object database, reference transaction,
or merge-base work.

## Dependency Closure

No new support component is needed. This reuses the lane-local attributes,
pathspec matcher/search, WordPress example, PHP test harness, and hydrated
upstream Gitoxide source cache. No live provider tests, credentials, shared
support activation, or upstream Cargo workspace run were required.
