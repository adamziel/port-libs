# Gitoxide Attributes Pathspec ASCII Whitespace Field Parity

Micro-slice: `gitoxide-attributes-pathspec-match-parity-20260601T050018Z`
Base accepted HEAD: `9d2966b89133306c89e1d8c9ef9d120cd603e55f`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-attributes/src/parse.rs` at upstream `87433ed33eee9ba974111d20b854f6acb07cd4a6` parses assignment lists with `Iter { attrs: input.fields() }`, so assignment fields after the pattern are split on ASCII whitespace, not only space, tab, and carriage return.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/parse.rs` unescapes attr requirement values before handing the result to `gix_attributes::parse::Iter::new()`. This means vertical-tab/form-feed bytes can separate state requirements, but value chunks containing those bytes before the next field remain invalid.

## Native Delta

- `GitAttributes::parseAssignments()` now splits fields on ASCII whitespace (`space`, `tab`, `CR`, `LF`, `FF`, and `VT`) to match `gix_attributes::parse::Iter`.
- `AttributesPathspecTest.php` adds a focused attr-filtered pathspec case covering vertical-tab and form-feed separators in `.gitattributes` assignments and `:(attr:...)` requirements, plus the preserved rejection of value chunks containing those bytes.
- `examples/wordpress-attributes-pathspec.php` records the same WordPress deployment selection edge for generated plugin/theme/upload metadata.

## Evidence

- Red-first probe before the change: `GitAttributes::fromString("wp-content/plugins/** deploy\\vreview=yes\\n")` left both selected attributes unspecified, and `PathspecMatcher::fromSpecs([":(attr:deploy\\vreview=yes)wp-content/plugins/**"])` threw `Attribute specification cannot be empty`.
- `php -l lanes/gitoxide/src/GitAttributes.php`
- `php -l lanes/gitoxide/tests/AttributesPathspecTest.php`
- `php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php` passed `1 test files, 246 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 7505 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php` exited `0`.
- `git diff --check -- lanes/gitoxide` passed.

## Non-Overlap

This extends accepted attributes/pathspec work without repeating POSIX class parsing, reversed range handling, malformed bracket fallback, quoted pattern unquoting, selected assignment semantics, value-tab rejection, recursive macro lookup, double-star component boundaries, backslash byte matching, sparse-checkout pathspecs, tree pathspec walking, transport, protocol, pack, object database, references, or merge-base behavior. The old May 25 Gitoxide smart-HTTP rework notes target stale receive-pack metadata conflicts and are unrelated to this parser field boundary.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local PHP attributes parser, pathspec matcher/search, PCRE-backed wildmatch translation, WordPress example, and PHP test harness. It does not shell out to Git, run live provider tests, inspect credentials, or require a shared support-library activation gate.
