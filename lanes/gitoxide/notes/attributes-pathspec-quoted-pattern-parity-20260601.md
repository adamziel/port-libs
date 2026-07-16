# Gitoxide Attributes Pathspec Quoted Pattern Parity

Micro-slice: `gitoxide-attributes-pathspec-match-parity-20260601T034904Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-attributes/src/parse.rs` at upstream `87433ed33eee9ba974111d20b854f6acb07cd4a6` unquotes quoted `.gitattributes` patterns through `gix_quote::ansi_c::undo()` before parsing the glob pattern.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-quote/src/ansi_c.rs` accepts `\n`, `\r`, `\t`, `\a`, `\b`, `\v`, `\f`, `\"`, `\\`, and three-digit octal escapes beginning with `0` through `3`, and rejects unknown quoted escapes.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-attributes/tests/parse/mod.rs` includes the invalid quoted escape boundary (`"\!hello"` is an unquote error).
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs` applies attr requirements after the pathspec match, so skipped invalid attribute lines must not satisfy an `:(attr:...)` filter.

## Implementation

- `GitAttributes::consumeQuotedPattern()` now mirrors the bounded `gix-quote` ANSI-C unquote behavior needed by `.gitattributes` pattern parsing:
  - `\040` octal escapes materialize literal bytes such as a space in the pattern.
  - `\f` materializes form-feed bytes.
  - Invalid quoted escapes such as `\q` make the attribute line leniently skipped instead of turning into literal pattern bytes.
- `AttributesPathspecTest.php` adds focused attr-filtered pathspec assertions for quoted space/form-feed upload paths and invalid quoted escape skipping.
- `examples/wordpress-attributes-pathspec.php` exposes the WordPress media-library deployment edge as a lane-local smoke.

## Evidence

- Red-first probe before the change returned `quoted-space => null`, `formfeed-upload => null`, and `invalid-escape => true` for quoted octal/form-feed/invalid escape patterns.
- `php -l lanes/gitoxide/src/GitAttributes.php && php -l lanes/gitoxide/tests/AttributesPathspecTest.php && php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php` passed `1 test files, 230 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 7198 assertions, 0 failures`.
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-attributes-pathspec.php"; foreach (["quotedSpaceUploadPathspecMatches", "quotedSpaceUploadOctalTextSkipped", "quotedFormFeedUploadPathspecMatches", "invalidQuotedEscapeAttributeSkipped"] as $key) { if (($out[$key] ?? null) !== true) { fwrite(STDERR, $key . " failed\n"); exit(1); } } echo "attributes pathspec quoted pattern example ok\n";'` passed.
- `git diff --check -- lanes/gitoxide` passed.

## Non-Overlap

This extends the accepted attributes/pathspec work without repeating POSIX class parsing, reversed range handling, malformed bracket fallback, selected assignments, value-tab parsing, recursive macro lookup, double-star component boundaries, backslash byte matching, sparse-checkout pathspecs, tree pathspec walking, transport, protocol, pack, object database, references, or merge-base behavior. The old May 25 Gitoxide smart-HTTP rework notes target stale receive-pack metadata conflicts and are unrelated to this quoted `.gitattributes` parser behavior.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local PHP attributes parser, PCRE-backed wildmatch translation, pathspec matcher/search, WordPress example, and PHP test harness. It does not shell out to Git, run live provider tests, inspect credentials, or require a shared support activation gate.
