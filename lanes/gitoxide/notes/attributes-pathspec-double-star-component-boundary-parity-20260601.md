# Attributes Pathspec Double-Star Component Boundary Parity

Micro-slice: `gitoxide-attributes-pathspec-match-parity-20260601T012909Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-attributes/src/search/attributes.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-attributes/tests/fixtures/make_attributes_baseline.sh`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-attributes/tests/fixtures/generated-do-not-edit/make_attributes_baseline/sha1/2640449821-unix/basics/baseline`

Upstream `gix-glob` treats a `**` run as slash-crossing only when the run is at a path component boundary and is followed by `/`, escaped `/`, or the end of the pattern. Otherwise, while matching paths with slash-literal semantics, it remains component-local like `*`.

The generated attributes baseline confirms the practical edge: `a**f` matches `af` and `axf`, while `a/f` does not receive the `test-double-star-no-slash` assignment from `a**f`.

## Native Delta

- `GitAttributes::globRegex()` now keeps non-boundary `**` component-local for path-aware attribute patterns while preserving recursive `**/` and terminal `**` behavior.
- `PathspecSearch::globRegex()` applies the same rule for `:(glob)` pathspec matching, while ordinary shell-style pathspec search continues to let `**` cross slashes.
- `AttributesPathspecTest.php` adds coverage for attributes, `PathspecMatcher`, and `PathspecSearch` across `a**f.php`, `**.php`, and recursive `**/block.json`.
- `examples/wordpress-attributes-pathspec.php` now smokes a WordPress plugin deployment path that distinguishes top-level component-local matches from recursive block metadata matches.

## Red-First Probe

Before the fix, a direct PHP probe showed these incorrect matches:

- `:(glob)wp-content/plugins/ab**cd/file.php` matched `wp-content/plugins/ab/x/cd/file.php`.
- `:(glob)wp-content/plugins/a**/file.php` matched `wp-content/plugins/a/x/file.php`.
- `:(glob)wp-content/plugins/**file.php` matched `wp-content/plugins/a/file.php`.
- Attribute pattern `wp-content/plugins/a**b/** odd` applied to `wp-content/plugins/a/x/b/file.php`.

The new focused test asserts those path-aware cases stay component-local, while `wp-content/plugins/**/block.json` still matches both direct and nested block metadata paths.

## Verification

- `php -l lanes/gitoxide/src/GitAttributes.php && php -l lanes/gitoxide/src/PathspecSearch.php && php -l lanes/gitoxide/tests/AttributesPathspecTest.php && php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
  - Result: no syntax errors detected in all 4 changed PHP files.
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  - Result: `2 test files, 359 assertions, 0 failures`.
  - Assertion delta over the same focused pair before the patch: `336 -> 359` (`+23`).
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-attributes-pathspec.php"; foreach (["componentLocalDoubleStarSkipsNestedPath", "componentLocalDoubleStarSearchMatchesSibling", "topLevelDoubleStarSkipsNestedPhp", "recursiveDoubleStarMatchesDirectBlock", "recursiveDoubleStarMatchesNestedBlock", "recursiveDoubleStarSkipsSuffixBlock"] as $key) { if (($out[$key] ?? null) !== true) { fwrite(STDERR, $key . " failed\n"); exit(1); } } echo "attributes pathspec double-star example ok\n";'`
  - Result: `attributes pathspec double-star example ok`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `40 test files, 6743 assertions, 0 failures`.

`git diff --check -- lanes/gitoxide` is part of final verification for this handoff.

## Non-Overlap

This does not repeat accepted attributes POSIX class, backslash byte, reversed range, value-tab, recursive macro, empty long magic, sparse-checkout double-star, config includeIf double-star, tree pathspec, transport, protocol, pack, reference transaction, credential, partial-clone, URL/refspec, or merge-base slices. It is limited to the shared path-aware double-star slash-crossing rule used by Git attributes and pathspec matching.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `GitAttributes`, `PathspecMatcher`, `PathspecSearch`, focused tests, and WordPress-relevant example path. No shell-out to `git`, live provider access, credential inputs, or upstream Cargo workspace run was required.
