# Gitoxide Config Include Onbranch Simple Glob Parity

Micro-slice: `gitoxide-config-include-conditional-parity-20260601T121356Z`

Accepted base: `104a9f5fce0ab0f0e77688b3f9277242f2f9e31c`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/includes/mod.rs`
  routes `includeIf "onbranch:<glob>"` through `gix_glob::wildmatch` with
  `NO_MATCH_SLASH_LITERAL`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/tests/config/file/init/from_paths/includes/conditional/onbranch.rs`
  covers simple branch globs: `prefix*` and `*suffix` match only within one
  branch path component, `*/suffix` matches one component before `suffix`, and
  trailing slash conditions are expanded recursively.

## Native PHP Movement

- `GitConfigTest.php` now verifies the upstream `onbranch` simple-glob
  component boundary: `prefix*` and `*suffix` do not cross `/`, `*/suffix`
  matches a one-component branch path, and a trailing `feature/b/` condition
  expands recursively.
- The WordPress config include fixture/example now exposes a deployment branch
  policy loaded by `includeIf "onbranch:deploy/*"` and rejects the sibling
  `includeIf "onbranch:deploy*"` policy for `refs/heads/deploy/site-a`.

## Focused Evidence

- Baseline before this patch: `php tools/run-tests.php
  lanes/gitoxide/tests/GitConfigTest.php` passed `1 test files, 282
  assertions, 0 failures`.
- `php -l lanes/gitoxide/tests/GitConfigTest.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`: `1 test
  files, 292 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  exits `0`.
- `php -r 'foreach (["lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json",
  "lanes/gitoxide/lane-status.json"] as $f) { json_decode(file_get_contents($f),
  true, flags: JSON_THROW_ON_ERROR); echo $f, " ok\n"; }'`: both JSON files
  decode successfully.
- `git diff --check -- lanes/gitoxide`: exits `0`.

## Dependency Closure

No new support component is needed. This slice reuses the native Git config
parser, include resolver, and byte-oriented wildmatch compiler; no shared
dependency row or activation gate is proposed.

## Non-overlap

This verifies the remaining `onbranch` simple-glob conditional include boundary
without repeating accepted config include double-star, POSIX class, escaped
hyphen, reversed-range, malformed bracket, trailing-backslash, path
interpolation, symlink gitdir, optional-prefix, directive-case, environment
pair, named-user interpolation, or max-depth slices. It does not touch
protocol, transport, pack/index, reference transactions, object database,
sparse checkout, attributes/pathspec, tree merge, merge-base, credentials, or
URL/refspec behavior.
