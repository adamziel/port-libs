# Gitoxide Attributes Pathspec ASCII Whitespace Pattern Parity

Micro-slice: `gitoxide-attributes-pathspec-match-parity-20260601T060411Z`
Base accepted HEAD: `e2c270ed3a9929039fa26f779e2d74a975c61aa8`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-attributes/src/parse.rs` at upstream `87433ed33eee9ba974111d20b854f6acb07cd4a6` unquotes leading quoted patterns, then sends the resulting bytes to `gix_glob::Pattern::from_bytes()`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/parse.rs` drops patterns whose bytes are all ASCII whitespace with `pat.iter().all(u8::is_ascii_whitespace)`. This includes form-feed and vertical-tab bytes, not just the `BLANKS` bytes used to split unquoted pattern text from attributes.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs` applies `:(attr:...)` requirements only after the pathspec path matched, so a skipped whitespace-only attribute pattern must not satisfy an attr-filtered pathspec.

## Native Delta

- `GitAttributes::parsePattern()` now skips patterns made entirely of ASCII whitespace instead of relying on PHP `trim()`, which missed form-feed-only patterns.
- `AttributesPathspecTest.php` adds a focused upstream-shaped case for unquoted, quoted, and spaced form-feed-only patterns, vertical-tab-only patterns, and a non-overlapping embedded form-feed filename that must still match.
- `examples/wordpress-attributes-pathspec.php` records the same WordPress deployment edge for unusual upload path bytes while preserving the existing embedded form-feed quoted-pattern behavior.
- `lane-status.json` records the isolated focused and full-lane evidence.

## Evidence

- Red-first probe before the change: `GitAttributes::fromString("\f formfeed-only\n")->attributesForPath("\f", ["formfeed-only"])` returned `["formfeed-only" => true]`; upstream would drop that all-whitespace pattern.
- `php -l lanes/gitoxide/src/GitAttributes.php`
- `php -l lanes/gitoxide/tests/AttributesPathspecTest.php`
- `php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php` passed `1 test files, 263 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 7723 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php` exited `0`.
- `git diff --check -- lanes/gitoxide` passed.

## Non-Overlap

This extends accepted attributes/pathspec work without repeating ASCII field splitting, POSIX class parsing, reversed range handling, malformed bracket fallback, quoted non-whitespace pattern unquoting, selected assignment semantics, value-tab rejection, recursive macro lookup, double-star component boundaries, backslash byte matching, sparse-checkout pathspecs, tree pathspec walking, transport, protocol, pack, object database, references, or merge-base behavior. The new behavior is limited to all-ASCII-whitespace `.gitattributes` patterns before attr-filtered pathspec matching.

## Dependency Closure

No new support component is needed. This slice reuses the lane-local PHP attributes parser, pathspec matcher/search, PCRE-backed wildmatch translation, WordPress example, and PHP test harness. It does not shell out to Git, run live provider tests, inspect credentials, or require a shared support-library activation gate.
