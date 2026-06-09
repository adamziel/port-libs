# BibTeX/CSL Item Rights Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T235414Z`
Base: `d882dae9d858147bc44d510727ef5cac23951c50`

## Behavior

This slice preserves item-level source-use metadata from bounded BibTeX and
BibLaTeX records. `BibtexCslParser` now maps `rights`, `copyright`, `license`,
and `licence` fields into normalized CSL `rights` metadata.
`CitationCslProcessor` keeps the same aliases visible for imported CSL items,
default bibliography review output, WordPress bibliography blocks, and bounded
CSL text variables `rights`, `copyright`, `license`, and `licence`.

The existing related-entry license path remains separate: `relatedtype =
license` still renders related work summaries, while item-level rights metadata
is rendered as source review metadata on the item itself.

## Source Truth

Pandoc conversion needs bounded bibliography review packets to keep source-use
metadata visible during imports. This lane ports that native PHP handoff
contract for item-level rights/copyright/license fields only. It does not claim
full citeproc, BibTeX, Biber, bibliography-manager, or upstream Pandoc runner
parity.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this lane before
  editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 3148 assertions, 0 failures`.
- Red-first: the focused test failed before implementation with
  `1 test files, 3150 assertions, 1 failures` because parsed items did not
  expose `rights` metadata.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 3165 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-bibtex-csl-rights-handoff.php --self-test`
  passed.
- PHP lint passed for changed PHP source, test, and example files.
- JSON validation passed for `lane-status.json` and
  `UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1988 -> 1989`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2406 -> 2407`
- `mappedBibtexCslCoreCases`: `7 -> 8`
- `bibtexCslCoreAssertions`: `121 -> 138`
- Focused assertion delta: `+17`

## Dependency Closure

No new support component is needed. The patch reuses native `BibtexCslParser`,
`CitationCslProcessor`, `CslStyle`, `MarkdownReader`, and
`WordPressBlockWriter` behavior.

## Non-Overlap And Follow-Up

This does not repeat related-entry license summaries, raw related/xref
metadata, entry-set summaries, citation aliases, shorthand-list output,
event-place lists, pagination, article-number/eid, library call-number, media
entry-type, keyword, or source-variable slices. A useful follow-up would cover a
different remaining BibLaTeX datamodel field or a distinct CSL style-variable
handoff, not another rights/license alias.
