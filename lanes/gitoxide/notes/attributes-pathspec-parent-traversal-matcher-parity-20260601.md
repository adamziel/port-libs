# Gitoxide Attributes Pathspec Parent Traversal Matcher Parity

Micro-slice: `gitoxide-attributes-pathspec-match-parity-20260601T144726Z`
Base accepted HEAD: `876fa5fa7672f3eb2386e9413d4469eeec0f3d54`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/pattern.rs`
  normalizes pathspec paths against an empty worktree-root guard and returns an
  error when parent components would leave the worktree.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/init.rs`
  normalizes every pathspec before it can participate in search matching.

## Native Delta

- `PathspecMatcher` now rejects root-escaping `..` components while parsing
  pathspec paths and while normalizing candidate paths passed to the matcher.
- In-root parent normalization is preserved, so
  `wp-content/plugins/../themes/**` continues to match theme paths before
  attribute requirements are evaluated.
- The WordPress attributes/pathspec example records the deploy guard so a
  parent-traversal pathspec cannot select protected plugin content by
  normalizing back into the repository.

## Evidence

- Red-first after adding the focused assertions:
  `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  failed with `Expected exception InvalidArgumentException was not thrown`.
- After the fix:
  `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  passed `1 test files, 360 assertions, 0 failures`.
- Full lane evidence:
  `php tools/run-tests.php lanes/gitoxide/tests` passed
  `40 test files, 9859 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local PHP
`PathspecMatcher`, `GitAttributes`, focused attributes/pathspec tests, and the
existing WordPress deployment example. No shell-out to `git`, live provider
access, credential input, or shared support-library activation gate is needed.

## Non-Overlap

This extends the accepted tree-pathspec parent-escape normalization to the
standalone attributes/pathspec matcher. It does not repeat POSIX class parsing,
malformed bracket fallback, ASCII whitespace parsing, selected assignment
semantics, recursive macro lookup, tree walking, sparse checkout, transport,
reference, pack, object database, merge-base, or URL/refspec behavior.
