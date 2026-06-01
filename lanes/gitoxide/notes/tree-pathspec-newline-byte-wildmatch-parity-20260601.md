# Tree Pathspec Newline Byte Wildmatch Parity

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260601T025107Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  at `87433ed33eee9ba974111d20b854f6acb07cd4a6`: `?` consumes the next byte
  unless path-aware slash blocking is enabled; there is no special newline
  exclusion.
- The same source lets shell `*` consume arbitrary bytes, including `/` and
  newline bytes, while `NO_MATCH_SLASH_LITERAL` only blocks slash in
  path-aware glob mode.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  routes default shell-glob pathspecs through `gix_glob::wildmatch::Mode::empty()`
  and `:(glob)` pathspecs through `NO_MATCH_SLASH_LITERAL`.

## Native PHP Delta

- `PathspecSearch::globMatches()` now uses the PCRE DOTALL modifier so the
  regex-backed shell `*` and `?` paths are byte-style for LF-containing Git
  tree paths.
- `PathspecTreeWalkTest.php` adds direct matcher and tree-walk coverage for
  `new\nline` path components, including the upstream distinction where shell
  `?` can cross `/` but path-aware `:(glob)` `?` cannot.
- `examples/wordpress-tree-pathspec-walk.php` now records selection of a
  newline-containing plugin directory path without invoking `git`.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed: `1 test files, 203 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  passed: `3 test files, 692 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  passed: `40 test files, 7046 assertions, 0 failures`.
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php"; ...'`
  reported `tree pathspec newline example ok`.

## Non-Overlap

This extends the accepted tree/pathspec walk slices without repeating
absolute-root normalization, raw component preservation, empty-search behavior,
default search modes, POSIX class handling, sparse-checkout absolute backslash
paths, attributes/pathspec filters, protocol, transport, object database,
reference transaction, merge-base, or tree-merge work. The mapped behavior is
limited to upstream byte wildmatch treatment of LF bytes in tree pathspec
matching.

## Dependency Closure

No new support component is needed. This reuses the lane-local PHP pathspec
parser, regex-backed wildmatch translation, tree walker, and WordPress
tree-pathspec example; no upstream binary, live Git provider, credential store,
or shared support-library activation gate is required.
