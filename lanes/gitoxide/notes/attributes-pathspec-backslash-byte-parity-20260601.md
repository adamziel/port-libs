# Gitoxide Attributes Pathspec Backslash Byte Parity

Micro-slice: `gitoxide-attributes-pathspec-match-parity-20260601T002246Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/parse.rs` at upstream `87433ed33eee9ba974111d20b854f6acb07cd4a6` includes backslash in `GLOB_CHARACTERS`, so an escaped literal is routed through wildmatch instead of raw verbatim comparison.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs` treats `\` as a pattern escape and leaves `/` as the only path separator with special slash matching behavior.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs` passes repository-relative byte paths into `gix_glob::Pattern::matches_repo_relative_path()`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-attributes/src/search/attributes.rs` routes attribute patterns through the same byte-oriented glob matcher.

## Implementation

- `GitAttributes`, `PathspecMatcher`, and `PathspecSearch` no longer normalize candidate backslash bytes to `/` before matching.
- `PathspecMatcher` now treats `\` as a wildcard-mode trigger so escaped literal backslash pathspecs use the wildmatch translator instead of raw verbatim comparison.
- `AttributesPathspecTest.php` adds focused assertions that `wp-content/plugins/f\oo/block.json` matches an escaped-backslash attribute/pathspec while `wp-content/plugins/f/oo/block.json` does not.
- `examples/wordpress-attributes-pathspec.php` records the same WordPress deployment edge as a lane-local smoke.

## Verification

- Red-first probe before the change returned `backslash-plugin => null`, `PathspecMatcher => false`, and `PathspecSearch => false` for `wp-content/plugins/f\oo/block.json` with escaped-backslash patterns.
- `php -l lanes/gitoxide/src/GitAttributes.php && php -l lanes/gitoxide/src/PathspecMatcher.php && php -l lanes/gitoxide/src/PathspecSearch.php && php -l lanes/gitoxide/tests/AttributesPathspecTest.php && php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php` passed `1 test files, 170 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/AttributesPathspecTest.php` passed `2 test files, 325 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 6467 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php` exited `0`.

## Non-Overlap

This extends the accepted attributes/pathspec work without repeating POSIX class parsing, reversed range handling, value-tab parsing, selected assignment semantics, recursive macro lookup, empty long-magic rejection, short-magic rejection, sparse-checkout backslash byte behavior, tree pathspec parent/prefix walking, protocol, transport, pack, object database, reference, reflog, or merge-base behavior. The new behavior is limited to escaped backslash repository path bytes in attributes/pathspec matching.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local PHP pathspec parser/search implementation, Git attributes provider, PCRE-backed wildmatch translation, WordPress example, and PHP test harness. It does not shell out to Git, run live provider tests, read credentials, or require a shared support activation gate.
