# BibTeX/CSL Conference-State Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T231223Z`
Base: `e4c5b8530d7050cd247624ff66dfa0499e76de2a`

## Behavior

This slice maps one bounded BibTeX/CSL conference publication-state case.
`@unpublished` records with `eventtitle` now keep `venue` as CSL
`event-place` metadata without also using it as `publisher-place`. Published
`@inproceedings` records still preserve their venue/status handoff, including
the existing publisher-place fallback expected by current tests.

The WordPress example covers an unpublished poster packet with
`pubstate = {forthcoming}` and a published proceedings paper with
`pubstate = {inpress}`. The CSL style and WordPress bibliography output expose
the unpublished event place without publisher-place leakage and keep the
published proceedings publisher/event venue visible.

## Source Truth

Pandoc's bibliography documentation distinguishes published conference papers
from unpublished conference presentations. This lane ports the bounded format
contract locally in PHP without invoking Pandoc, citeproc, BibTeX, Biber,
Cabal/Haskell runners, external bibliography managers, online services, live
provider tests, or live-service provider tests.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this lane before
  editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 3089 assertions, 0 failures`.
- Red-first: the focused test failed before the implementation with
  `1 test files, 3094 assertions, 1 failures` because the unpublished
  `eventtitle` + `venue` record still populated `publisher-place` as
  `Portland`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 3112 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-bibtex-csl-conference-state-handoff.php --self-test`
  passed.
- PHP lint passed for the changed PHP source, test, and example files.
- JSON validation passed for `lane-status.json` and
  `UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1957 -> 1958`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2379 -> 2380`
- `mappedBibtexCslCoreCases`: `7 -> 8`
- `bibtexCslCoreAssertions`: `121 -> 144`
- Focused assertion delta: `+23`

## Dependency Closure

No new support component is needed. The patch reuses native
`BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and
`WordPressBlockWriter` behavior.

## Non-Overlap And Follow-Up

This does not repeat the accepted BibTeX/CSL audio/artwork alias,
event-place-list, pagination, article-number, library call-number, or entry
subtype slices. A useful follow-up would be a separate BibLaTeX-to-CSL gap such
as creator-role metadata, safe entry-type aliases, or date/status edge
handling, without repeating unpublished eventtitle venue publisher-place
separation.
