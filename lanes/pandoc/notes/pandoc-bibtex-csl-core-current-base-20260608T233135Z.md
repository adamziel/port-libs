# BibTeX/CSL Entry-Set Summary Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T233135Z`
Base: `9eb676a5cd9add619cf3b6f2435447962ecbfb04`

## Behavior

This slice promotes already parsed BibLaTeX `entryset` metadata into normalized
CSL review metadata. `CitationCslProcessor` now exposes `entrySetKeys`,
`entrySetItems`, `missingEntrySetKeys`, and `entrySetSummary`, renders entry-set
member summaries in default bibliography output, and supports bounded CSL text
variables `entry-set`, `entry-set-summary`, `entry-set-keys`, and
`missing-entry-set-keys`.

The WordPress smoke covers a `@set` entry with two data-only members and one
missing member key, keeping bundle membership visible in review bibliography
output before import.

## Source Truth

BibLaTeX `@set` entries use `entryset` to list member entry keys. This lane
ports the bounded native handoff contract for reviewer-visible membership
summary only. It does not execute or claim full Pandoc, citeproc, BibTeX,
Biber, Cabal, Haskell runner, bibliography-manager, or online-service parity.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this lane before
  editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 3123 assertions, 0 failures`.
- Red-first: the focused test failed before implementation with
  `1 test files, 3115 assertions, 1 failures` because normalized items did not
  expose `entrySetKeys`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 3136 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-bibtex-csl-entryset-handoff.php --self-test`
  passed.
- Coupled existing example smoke:
  `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`
  passed.
- PHP lint passed for changed PHP source, test, and example files.
- JSON validation passed for `lane-status.json` and
  `UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1973 -> 1974`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2393 -> 2394`
- `mappedBibtexCslCoreCases`: `7 -> 8`
- `bibtexCslCoreAssertions`: `121 -> 134`
- Focused assertion delta: `+13`

## Dependency Closure

No new support component is needed. The patch reuses native `BibtexCslParser`,
`CitationCslProcessor`, `CslStyle`, `MarkdownReader`, and
`WordPressBlockWriter` behavior.

## Non-Overlap And Follow-Up

This does not repeat the accepted raw entry-set parser metadata, related/xref
summary output, citation aliases, shorthand-list output, event-place lists,
pagination, article-number/eid, library call-number, media entry-type, or
source-variable slices. A useful follow-up would be entry-set sorting/driver
behavior beyond summary output, another remaining BibLaTeX datamodel field, or
a distinct CSL style-variable exposure.
