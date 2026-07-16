# Sparse Checkout Negative Wildcard Directory Prefix Parity

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260531T155156Z`

Base accepted HEAD: `58c47241a5b6db59dbbfb8ad74725a55a4e899e0`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`: `directory_matches_prefix()` returns true for excluded wildcard patterns because traversal must prefer false positives over pruning descendants that can still match.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/tests/search/mod.rs`: `directory_matches_prefix_negative_wildcard`, `simplified_search_respects_all_excluded`, and `included_directory_and_excluded_subdir_top_level_with_prefix` cover negative wildcard traversal, all-excluded fallback, and exact directory-only exclusion boundaries.

## Native PHP Delta

- `SparseCheckoutSpec` now distinguishes exact negative pathspec exclusions from wildcard negative pathspecs during directory traversal.
- Matching negative wildcard directories stay traversable when descendants can still be included by a positive pathspec or by all-excluded fallback.
- Exact directory-only excludes remain authoritative, so `:(exclude)wp-content/generated-cache/` still prunes that directory and descendants.
- `examples/wordpress-sparse-checkout.php` now exposes generated-cache deployment selection behavior for path-aware negative wildcard sparse pathspecs.

## Red-First Evidence

Before the implementation:

```sh
php -r 'require "tools/bootstrap.php"; $spec=\PortLibs\Gitoxide\SparseCheckoutSpec::fromPathspecs(["wp-content/**", ":(exclude,glob)wp-content/*-cache"]); foreach (["dir"=>["wp-content/generated-cache", true], "file"=>["wp-content/generated-cache", false], "child"=>["wp-content/generated-cache/index.php", false]] as $name=>$case) { [$path,$isDir]=$case; echo $name.":".($spec->includesPath($path,$isDir)?"included":"skipped")."\n"; }'
```

Result:

```text
dir:skipped
file:skipped
child:included
```

The directory should remain traversable so the included child can be discovered.

## Verification

- Syntax:
  - `php -l lanes/gitoxide/src/SparseCheckoutSpec.php`
  - `php -l lanes/gitoxide/tests/SparseCheckoutTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php`
  - Result: no syntax errors detected in all three files.
- Focused sparse checkout: `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `1 test files, 219 assertions, 0 failures`.
- Related pathspec/tree traversal: `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `2 test files, 337 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `39 test files, 4891 assertions, 0 failures`.
- Example smoke:
  - `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; foreach (["negativeWildcardCacheDirectoryTraversable", "negativeWildcardCacheFileNameSkipped", "negativeWildcardCacheDescendantIncluded", "negativeWildcardExcludeOnlyDescendantIncluded", "directoryOnlyWildcardExcludeDirectoryTraversable"] as $key) { echo $key . "=" . json_encode($out[$key]) . "\n"; }'`
  - Result: all five emitted `true`.
- Whitespace check: `git diff --check -- lanes/gitoxide`
  - Result: passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Focused sparse-checkout assertions: `200 -> 219`.
- Full Gitoxide lane assertions: `4872 -> 4891`.
- Expected mapped denominator: `1593 / 2886 -> 1594 / 2886`.

## Dependency Closure

No new support component is needed. This reuses the existing lane-local pathspec parser, sparse checkout matcher, tree filtering, and PHP PCRE-based wildmatch support. It does not shell out to Git, read credentials, run live transports, or require a shared support-library activation gate.

## Non-Overlap

This does not repeat accepted sparse-checkout directory-type boundaries, directory-only exact excludes, POSIX class fallback, absolute-root normalization, absolute wildcard icase prefix handling, prefix pathspecs, tree pathspec parent-escape rejection, or transport/protocol slices. It is bounded to upstream `gix-pathspec` negative wildcard directory-prefix traversal behavior.
