# Attributes/Pathspec Malformed POSIX Resume Parity - 2026-06-01

Micro-slice: `gitoxide-attributes-pathspec-match-parity-20260601T133313Z`

Base accepted HEAD: `3fbf3e52f7c6e6a72c8a17054cab01a393183925`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at pinned commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-glob/src/wildmatch.rs` treats malformed POSIX-class openers inside bracket classes, such as `[[:digit]`, as resumed byte-class matching instead of aborting the whole wildcard match.
- `gix-pathspec/src/search/matching.rs` runs the glob match first and falls back to verbatim matching only if the wildcard branch fails.
- `gix-attributes/src/parse.rs` feeds parsed attribute patterns through the same `gix-glob` wildcard matcher before attr-filtered pathspec matching evaluates requirements.

## Native PHP Delta

- `PathspecSearch::characterClassRegex()` no longer forces malformed POSIX-class opener bodies to a never-match regex. It now preserves the same byte-class behavior already used by `GitAttributes::globMatches()` and `PathspecMatcher`.
- `AttributesPathspecTest.php` adds focused attr-filtered `PathspecSearch` coverage for `[[:digit]ab]`, `[[:]ab]`, and the still-rejected `[[::]ab]` empty-valid-class boundary.
- `PathspecTreeWalkTest.php` updates the shared tree-walk expectations so tree traversal observes the same upstream resume behavior through `PathspecSearch`.
- `examples/wordpress-attributes-pathspec.php` and `examples/wordpress-tree-pathspec-walk.php` expose the WordPress upload-selection edge for unusual bracketed path bytes.

## Red-First Evidence

Before the change:

```bash
php -r 'require "tools/bootstrap.php"; use PortLibs\Gitoxide\GitAttributes; use PortLibs\Gitoxide\PathspecSearch; $a=GitAttributes::fromString("wp-content/uploads/[[:digit]ab] malformed-posix\n", withBuiltInMacros:false); var_export([$a->attributesForPath("wp-content/uploads/[ab]", ["malformed-posix"]), PathspecSearch::fromSpecs([":(glob,attr:malformed-posix)wp-content/uploads/[[:digit]ab]"])->isIncluded("wp-content/uploads/[ab]", false, $a), PathspecSearch::fromSpecs([":(glob,attr:malformed-posix)wp-content/uploads/[[:digit]ab]"])->isIncluded("wp-content/uploads/[[:digit]ab]", false, $a)]); echo PHP_EOL;'
```

Result before: `array ( 0 => array ( 'malformed-posix' => true, ), 1 => false, 2 => false, )`.

Result after: `array ( 0 => array ( 'malformed-posix' => true, ), 1 => true, 2 => false, )`.

## Verification

- `php -l lanes/gitoxide/src/PathspecSearch.php`
  - Result: no syntax errors detected.
- `php -l lanes/gitoxide/tests/AttributesPathspecTest.php`
  - Result: no syntax errors detected.
- `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  - Result: no syntax errors detected.
- `php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
  - Result: no syntax errors detected.
- `php -l lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php`
  - Result: no syntax errors detected.
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  - Result: `1 test files, 352 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  - Result: `2 test files, 692 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php && php lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php`
  - Result: both examples exited 0.
- `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `40 test files, 9483 assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide`
  - Result: no output, exit 0.

## Dependency Closure

No new support component is needed. This slice reuses the lane-local PHP pathspec parser/search, attribute matcher, tree-walk integration, PCRE-backed byte matching, WordPress examples, and PHP test harness. It does not shell out to Git, run live provider tests, inspect credentials, or require a shared support-library activation gate.

## Non-Overlap

This extends accepted malformed bracket fallback, valid bracket fallback, POSIX class-name icase, POSIX blank/unknown-class, reversed range, LF byte, dangling backslash, double-star component-boundary, recursive macro, sparse-checkout malformed POSIX, and tree/pathspec traversal work. The new production behavior is limited to malformed POSIX-class opener resume semantics in shared `PathspecSearch`, including attr-filtered pathspec matching and tree walking.
