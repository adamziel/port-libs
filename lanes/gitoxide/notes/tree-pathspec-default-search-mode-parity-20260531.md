# Tree Pathspec Default Search Mode Parity - 2026-05-31

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260531T120443Z`

Base accepted HEAD: `e4074c45f1e9d3c2408ad3ef65aec8f4e6ec75cf`

## Upstream Source Truth

- `gix-pathspec/src/defaults.rs`: `Defaults` carries the process/repository
  pathspec defaults for literal pathspecs, no-glob literal matching,
  path-aware glob matching, and inherited `icase`.
- `gix-pathspec/src/parse.rs`: literal defaults bypass pathspec parsing, while
  default search modes are applied after parsing so explicit `:(glob)` and
  `:(literal)` magic can override no-glob/glob defaults.
- `gix-pathspec/src/search/init.rs` and `matching.rs`: normalized caller
  prefixes remain case-sensitive even when the pathspec suffix inherits
  case-folding from defaults.

## Native Behavior

- `PathspecSearch::fromSpecs()` now accepts `defaultSearchMode` and
  `defaultIgnoreCase`, passing both into `PathspecPattern::parse()` for tree
  pathspec searches.
- `PathspecPattern::parse()` now applies default search mode only after
  explicit pathspec magic is parsed. This lets `:(glob)` override a default
  no-glob/literal search mode and keeps `literalDefault: true` as the stronger
  bypass that treats `:` and `:(glob)...` as literal path bytes.
- Tree pathspec walking now proves literal/no-glob selection, path-aware glob
  default pruning, explicit `:(glob)` override under default literal mode, and
  inherited `icase` with the caller prefix still matched case-sensitively.
- `examples/wordpress-tree-pathspec-walk.php` records these defaults for a
  WordPress deployment tree: no-glob avoids unintended wildcard expansion,
  explicit glob recovers plugin manifest selection, path-aware defaults avoid
  nested source traversal, and inherited `icase` selects mixed-case plugin
  loader files under a stable content prefix.

## Verification

- Red observation before change:
  `PathspecSearch::fromSpecs(["wp-content/plugins/*.php"], defaultSearchMode: PathspecPattern::SEARCH_LITERAL)`
  and `PathspecSearch::fromSpecs(["wp-content/*.php"], defaultIgnoreCase: true)`
  both failed with `Unknown named parameter`.
- First focused run after wiring exposed the parser parity gap:
  `:(glob)` under default literal mode still failed with
  `'literal' and 'glob' keywords cannot be used together in the same pathspec`.
- Focused after fix:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files / 102 assertions / 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests`
  passed `39 test files / 4502 assertions / 0 failures`.
- Syntax:
  `php -l lanes/gitoxide/src/PathspecSearch.php`,
  `php -l lanes/gitoxide/src/PathspecPattern.php`,
  `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php`, and
  `php -l lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php`
  reported no syntax errors.
- Example smoke:
  `php -r '$out = require "lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php"; ...'`
  reported `tree pathspec default modes example ok`.
- Whitespace:
  `git diff --check -- lanes/gitoxide` exited 0.

## Non-Overlap And Dependency Closure

This extends accepted tree/pathspec walking, empty-search, prefix-case,
wildmatch, sparse-checkout default-mode, and attributes/pathspec slices without
repeating their behavior. The new behavior is limited to exposing upstream
`gix-pathspec::Defaults` search mode and inherited `icase` semantics through
the tree-walk `PathspecSearch` API.

No new support component is needed. The slice reuses native PHP pathspec
parsing/search and in-memory tree traversal; it does not shell out to Git, run
live provider tests, or require credential-bearing inputs.
