# Attributes Pathspec POSIX Class Name Icase Parity - 2026-06-01

Micro-slice: `gitoxide-attributes-pathspec-match-parity-20260601T121714Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  lowercases pattern bytes while `Mode::IGNORE_CASE` is active before parsing
  bracket expressions and POSIX class names.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/pattern.rs`
  adds `Mode::IGNORE_CASE` when callers pass `Case::Fold`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  passes `Case::Fold` for `:(icase)` pathspecs before wildmatch evaluation.

## Native Change

- `GitAttributes::globRegex()` now folds POSIX class names before lookup when
  attribute matching is explicitly case-insensitive.
- `PathspecSearch::globRegex()` now does the same for `:(icase)` pathspec
  search.
- `PathspecMatcher` reuses `GitAttributes::globMatches()`, so its icase
  pathspec behavior follows the same fix.

## Focused Evidence

Before the change:

- `GitAttributes::fromString("WP-CONTENT/uploads/[[:UPPER:]]LUGINS/** folded-class\n")
  ->attributesForPath("wp-content/uploads/plugins/block.json", ["folded-class"], false, true)`
  returned `null`.
- `PathspecSearch::fromSpecs([":(icase)wp-content/uploads/[[:UPPER:]]LUGINS/**"])
  ->isIncluded("wp-content/uploads/plugins/block.json", false)` returned `false`.

After the change:

- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  passed: `1 test files, 335 assertions, 0 failures`.
- Pre-change focused evidence was `1 test files, 324 assertions, 0 failures`;
  this slice adds 11 focused assertions.

## Example Smoke

The WordPress attributes/pathspec example now exposes:

- case-sensitive uppercase POSIX class-name pathspec miss;
- folded `:(icase)` POSIX class-name pathspec hit;
- attr-filtered pathspec hit for a deployable plugin path;
- direct case-insensitive attribute lookup parity for a deployment rule.

## Non-Overlap

This deepens the accepted attributes/pathspec POSIX class cluster without
repeating POSIX blank semantics, invalid class fallback, malformed brackets,
reversed ranges, quoted attributes, ASCII whitespace field splitting,
backslash byte matching, LF byte wildcard matching, recursive macro lookup,
sparse-checkout pathspec behavior, tree pathspec walking, transport, pack,
object database, reference transaction, URL/refspec, merge-base, or tree-merge
work.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
attributes/pathspec parsing, PCRE-backed wildcard translation, the existing
WordPress example, and the repo PHP test harness. It does not shell out to
Git, run live providers, read credentials, or require a shared support
activation gate.
