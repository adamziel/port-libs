# Attributes Pathspec Empty Long Magic Parity

Slice: `gitoxide-attributes-pathspec-match-parity-20260531T210238Z`

Base accepted HEAD: `7a6ad881ab7ec5dade7133aeca014b7a5e54577c`

## Upstream Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-pathspec/src/parse.rs` accepts the exact empty long magic signature
  `:()` by returning early only when the whole long-keyword input is empty.
- The same parser rejects empty components in any non-empty long-keyword list:
  `:(attr:deploy,)`, `:(,attr:deploy)`, and `:(attr:deploy,,icase)` reach the
  invalid-keyword branch for the empty keyword.
- `gix-pathspec/src/search/matching.rs` only evaluates attr requirements after
  a parsed pathspec matches the path, so malformed attr filters must be rejected
  before matching begins.

## Native PHP Delta

- `PathspecPattern::parse()` now keeps accepting the exact empty `:()` long
  magic but rejects leading, trailing, and doubled empty long-magic components.
- `PathspecSearch` now matches the existing stricter `PathspecMatcher` behavior
  for malformed attr pathspec filters.
- `examples/wordpress-attributes-pathspec.php` records the guard so deployment
  selection cannot silently accept a malformed `:(attr:deploy,)` filter.

## Red-First Evidence

Before the change, this probe accepted all three malformed pathspecs:

```sh
php -r 'require "tools/bootstrap.php"; use PortLibs\Gitoxide\PathspecSearch; foreach ([":(attr:deploy,)wp-content/**", ":(,attr:deploy)wp-content/**", ":(attr:deploy,,icase)wp-content/**"] as $spec) { try { PathspecSearch::fromSpecs([$spec]); echo $spec . " accepted\n"; } catch (Throwable $e) { echo $spec . " rejected: " . $e->getMessage() . "\n"; } }'
```

## Verification

- `php -l lanes/gitoxide/src/PathspecPattern.php && php -l lanes/gitoxide/tests/AttributesPathspecTest.php && php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
  - no syntax errors
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  - `1 test files, 124 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 5699 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
  - exited `0`
- `git diff --check -- lanes/gitoxide`
  - exited `0`

## Non-Overlap

This deepens the accepted attributes/pathspec parser and selected-assignment
cluster without claiming a new mapped denominator row. It does not repeat
attribute value tab validation, POSIX class matching, recursive macro lookup,
state-adjustment value suffix handling, tree pathspec walking, sparse checkout,
protocol, transport, pack, object database, reference, reflog, or merge-base
behavior. The old May 25 smart-HTTP rework notes are stale for this slice
because they target receive-pack redirect/status metadata conflicts.

## Dependency Closure

No new support component is needed. The slice reuses native PHP pathspec
parsing/search, Git attribute requirement parsing, the PHP test harness, and
the existing WordPress attributes/pathspec example. It does not shell out to
Git, run live provider tests, read credentials, or require a shared support
activation gate.
