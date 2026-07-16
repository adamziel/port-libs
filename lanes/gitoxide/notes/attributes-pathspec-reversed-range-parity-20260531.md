# Attributes Pathspec Reversed Range Parity

Micro-slice: `gitoxide-attributes-pathspec-match-parity-20260531T231122Z`

Base accepted HEAD: `b77f76b33ac877becd8fb58514949f334f0fbc0d`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs` at upstream `87433ed33eee9ba974111d20b854f6acb07cd4a6` evaluates bracket classes byte-by-byte. For a reversed range such as `[z-a]`, the starting byte still matches literally, the consumed endpoint does not become a literal match, and no regex compilation failure is possible.
- The same `gix-glob` range branch makes alphabetic reversed ranges match either endpoint order when `Mode::IGNORE_CASE` is active.
- `gix-attributes/src/search/attributes.rs` and `gix-pathspec/src/search/matching.rs` both route attribute/pathspec bracket classes through `gix_glob` matching with path-aware slash handling.

## Native PHP Delta

- `GitAttributes::characterClassRegex()` and `PathspecSearch::characterClassRegex()` now track the previous class byte and emit valid PCRE for range tails instead of passing reversed ranges directly into PCRE.
- Case-sensitive reversed ranges keep Gitoxide's start-byte-only behavior, including negated classes like `[!z-a]`.
- Icase alphabetic reversed ranges are normalized into a forward ASCII range so `:(icase)` pathspecs and case-folded attribute searches match Gitoxide's either-order range behavior.
- `examples/wordpress-attributes-pathspec.php` now exposes deployment upload selection with reversed range, negated reversed range, and icase reversed range checks.

## Verification

- Red-first before the fix:
  `php -d display_errors=1 -r 'require "tools/bootstrap.php"; var_dump(PortLibs\Gitoxide\GitAttributes::globMatches("[z-a]", "m"));'`
  emitted a `preg_match(): Compilation failed: range out of order in character class` warning.
- `php -l lanes/gitoxide/src/GitAttributes.php`
- `php -l lanes/gitoxide/src/PathspecSearch.php`
- `php -l lanes/gitoxide/tests/AttributesPathspecTest.php`
- `php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  passed `1 test files, 156 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `3 test files, 540 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  passed `39 test files, 6131 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
  exited `0`.
- `git diff --check -- lanes/gitoxide`
  passed.

## Non-Overlap

This extends the accepted attributes/pathspec character-class work without repeating POSIX class parsing, selected assignment semantics, tab-value boundaries, empty long magic rejection, recursive macros, nested precedence, sparse-checkout POSIX fallback, or tree pathspec parent/prefix walking. The behavior is bounded to reversed bracket ranges shared by `gix-glob`, `gix-attributes`, and `gix-pathspec`.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local PHP pathspec parser, Git attributes provider, PCRE-backed wildmatch translation, WordPress attributes example, and PHP test harness.
