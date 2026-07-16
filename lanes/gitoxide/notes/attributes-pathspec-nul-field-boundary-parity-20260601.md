# Gitoxide Attributes Pathspec NUL Field Boundary Parity

Micro-slice: `gitoxide-attributes-pathspec-match-parity-20260601T071227Z`
Base accepted HEAD: `0c72e2d3dc6140f90e575fbd71aef1cf0d69e30f`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-attributes/src/parse.rs` at upstream `87433ed33eee9ba974111d20b854f6acb07cd4a6` parses assignment fields with `Iter { attrs: input.fields() }`, where fields are split on ASCII whitespace only. NUL bytes stay inside fields and invalid attribute names are rejected.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/parse.rs` feeds `:(attr:...)` requirements through the same `gix_attributes::parse::Iter`, after separately validating attribute values. NUL bytes in requirement names or values are rejected instead of being stripped.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-attributes/tests/parse/mod.rs` confirms attribute values are raw byte payloads, while attribute names remain ASCII-only.

## Native Delta

- `GitAttributes::parseAssignments()` now trims only the ASCII whitespace bytes used by the upstream field splitter (`space`, `tab`, `CR`, `LF`, `FF`, `VT`) instead of PHP's default `trim()` charlist, which also removed NUL bytes.
- `AttributesPathspecTest.php` adds focused coverage for NUL-terminated assignment names being skipped, NUL-terminated attribute values being preserved, and NUL-tainted `:(attr:...)` requirements being rejected by both pathspec matchers.
- `examples/wordpress-attributes-pathspec.php` records the same WordPress deployment boundary for generated metadata lines and pathspec filters.

## Evidence

- Red-first probe before the fix: `GitAttributes::fromString("wp-content/plugins/** deploy\\0\\n")` returned `["deploy" => true]`, and `PathspecSearch::fromSpecs([":(attr:deploy\\0)wp-content/plugins/**"])` was accepted.
- `php -l lanes/gitoxide/src/GitAttributes.php` passed.
- `php -l lanes/gitoxide/tests/AttributesPathspecTest.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php` passed `1 test files, 277 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 7956 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php` exited `0`.
- `git diff --check -- lanes/gitoxide` passed.

## Non-Overlap

This extends accepted attributes/pathspec work without repeating ASCII whitespace field splitting, all-whitespace pattern skipping, POSIX class parsing, reversed range handling, malformed bracket fallback, quoted pattern unquoting, selected assignment semantics, value-tab rejection, recursive macro lookup, double-star component boundaries, backslash byte matching, sparse-checkout pathspecs, tree pathspec walking, transport, protocol, pack, object database, references, or merge-base behavior. The change is limited to NUL-byte preservation at assignment field boundaries before attr-filtered pathspec matching.

## Dependency Closure

No new support component is needed. This slice reuses the lane-local PHP attributes parser, pathspec matcher/search, WordPress example, PCRE-backed wildmatch translation, and PHP test harness. It does not shell out to Git, run live provider tests, inspect credentials, or require a shared support-library activation gate.
