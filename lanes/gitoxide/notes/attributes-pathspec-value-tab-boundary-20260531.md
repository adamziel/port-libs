# Attributes Pathspec Value Tab Boundary Parity

Slice: `gitoxide-attributes-pathspec-match-parity-20260531T132757Z`

Base accepted HEAD: `4f24a4c1a8e6dd271c40ece1708b484039354fa3`

## Upstream Source Truth

- `gix-pathspec/src/parse.rs` validates pathspec `:(attr:...)` values in
  `unescape_attribute_values()` by splitting value-bearing chunks only on the
  literal space byte before handing the normalized requirement text to
  `gix_attributes::parse::Iter`.
- A tab before a later value-bearing requirement is still handled by the
  attributes iterator as field whitespace, but a tab after an `attr=value`
  chunk is part of that value and fails `is_valid_attr_value()`.
- `gix-pathspec/src/search/matching.rs` applies these parsed requirements to
  selected attribute outcomes after the pathspec path match.

## Native PHP Delta

- `GitAttributes::parseRequirements()` now normalizes and validates
  value-bearing pathspec attribute requirements with the same space-only
  pre-validation boundary as `gix-pathspec`.
- Valid tab-separated requirements such as `deploy<TAB>review=yes` still
  parse and match.
- Invalid value chunks such as `deploy=plugin<TAB>review=yes` and
  `deploy=plugin<TAB>review` now raise `InvalidArgumentException` before
  matching.
- `examples/wordpress-attributes-pathspec.php` records the valid
  tab-separated deployment filter and the rejected tab-after-value guard.

## Verification

- Red-first check before the fix:
  `php -r 'require "tools/bootstrap.php"; use PortLibs\Gitoxide\PathspecMatcher; try { PathspecMatcher::fromSpecs([":(attr:deploy=plugin\treview=yes)wp-content/**"]); echo "accepted invalid tab-separated values\n"; } catch (Throwable $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }'`
  returned `accepted invalid tab-separated values`.
- After the fix, the same probe reports
  `InvalidArgumentException: Invalid character in attribute value: <TAB>`.
- Syntax:
  - `php -l lanes/gitoxide/src/GitAttributes.php`
  - `php -l lanes/gitoxide/tests/AttributesPathspecTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
- Focused tests:
  `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  passed `1 test files, 95 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests`
  passed `39 test files, 4638 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php` exited `0`.
- Whitespace:
  `git diff --check -- lanes/gitoxide` exited `0`.

## Non-Overlap

This is additive to the accepted attributes/pathspec selected-assignment,
state-adjustment, search attr-filter, POSIX class, and POSIX class-boundary
slices. It does not repeat `.gitattributes` line parsing, path-aware class
matching, invalid POSIX class fallback, tree pathspec walking, sparse checkout
pathspec behavior, object database, pack, reference, protocol, or transport
behavior. The new mapped behavior is limited to `gix-pathspec` attr-value
tab-boundary validation.

## Dependency Closure

No new support component is needed. The slice reuses native PHP pathspec
parsing, Git attribute requirement parsing, the PHP test harness, and the
existing WordPress attributes/pathspec example. It does not shell out to Git,
run live provider tests, or read credential-bearing inputs.
