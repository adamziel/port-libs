# Gitoxide Attributes Pathspec Dangling Backslash Parity

Slice: `gitoxide-attributes-pathspec-match-parity-20260601T082452Z`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-glob/src/wildmatch.rs` returns `NoMatch` when a wildcard pattern ends with an unpaired backslash.
- `gix-attributes/src/search/attributes.rs` applies glob results directly for attributes, so a dangling backslash attributes pattern does not match a literal trailing backslash path.
- `gix-pathspec/src/search/matching.rs` can still fall back to verbatim matching for malformed wildcard pathspecs, so pathspec behavior must stay distinct from `.gitattributes` behavior.

## Native PHP Delta

- `GitAttributes::globRegex()` now emits an impossible match for dangling backslash escapes instead of treating the backslash as a literal byte.
- Attributes/pathspec tests now cover the source-truth split: a `.gitattributes` pattern ending in one backslash aborts, an escaped double-backslash pattern still matches a literal backslash path, and a dangling pathspec still falls back to a verbatim match.
- The WordPress attributes/pathspec example now includes the dangling-backslash deployment filter boundary.

## Red-First Evidence

Before the fix, the PHP port incorrectly selected `dangling => true` for an attributes rule ending in one backslash:

```sh
php -r 'require "lanes/gitoxide/src/GitAttributes.php"; require "lanes/gitoxide/src/PathspecMatch.php"; require "lanes/gitoxide/src/PathspecPattern.php"; require "lanes/gitoxide/src/PathspecSearch.php"; $attrs = \PortLibs\Gitoxide\GitAttributes::fromString("wp-content/plugins/trailing\\ dangling\n"); $path = "wp-content/plugins/trailing\\"; var_export(["attr" => $attrs->attributesForPath($path), "pathspec" => \PortLibs\Gitoxide\PathspecSearch::fromSpecs([$path])->isIncluded($path)]); echo "\n";'
```

Observed before the change:

```text
array (
  'attr' =>
  array (
    'dangling' => true,
  ),
  'pathspec' => true,
)
```

After the change, focused tests assert the attribute is unspecified while the pathspec fallback remains included.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  - `1 test files, 290 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 8263 assertions, 0 failures`
- `php -r '$example = require "lanes/gitoxide/examples/wordpress-attributes-pathspec.php"; if (!array_key_exists("dangling-backslash", $example["danglingBackslashAttributeSkipped"]) || $example["danglingBackslashAttributeSkipped"]["dangling-backslash"] !== null || $example["danglingBackslashPathspecFallsBackVerbatim"] !== "verbatim" || $example["escapedBackslashAttrPathspecMatches"] !== true) { exit(1); } echo "example ok\n";'`
  - `example ok`

Additional lint and `git diff --check -- lanes/gitoxide` are recorded in the worker final handoff.

## Non-Overlap

This is additive to accepted attributes/pathspec POSIX class, reversed range, malformed bracket, NUL-field, whitespace, recursive macro, double-star, escaped-backslash byte, and config includeIf trailing-backslash slices. It does not repeat pathspec parsing, pathspec fallback implementation, sparse checkout, transport, pack/index, reference, or tree-merge work.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local Git attributes parser, pathspec matcher/search APIs, existing WordPress attributes/pathspec example, PHP test harness, and the hydrated upstream Gitoxide cache for source-truth inspection.
