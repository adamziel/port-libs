# Pandoc BibTeX/CSL License Relation Slice

- Session: `port-dev-pandoc-bibtex-csl-20260606T073320Z`
- Micro-slice: `pandoc-bibtex-csl-core-current-base-20260606T073320Z`
- Base accepted HEAD: `b03dbfb6f34d3383aa6d1c0bb24447ed232247bd`

## Behavior

- Added a bounded CSL bibliography label for BibLaTeX `relatedtype=license` entries when no explicit `relatedstring` is present.
- Preserved existing generic related-entry rendering for explicit labels and unrelated `relatedtype` values.
- Added a focused BibTeX/CSL case covering a data-only license entry, missing related-license keys, custom CSL `related-type`/`related`/`related-summary` rendering, direct CSL item input, and WordPress bibliography output.
- Extended the WordPress BibTeX/CSL handoff example with a licensed dataset source and self-test assertions.

## Source Truth And Non-Overlap

This is a native PHP support-library slice for bibliography handoff. The source-truth behavior is BibLaTeX license metadata carried through the existing related-entry mechanism, specifically `related` plus `relatedtype=license`.

This does not repeat accepted DOI/URL/ISBN/ISSN/PMID/PMCID/archive/eprint, article-number/eid, call-number, pagination/bookpagination, issue-title, reviewed-work metadata, software/dataset pubstate, entry-subtype, source-file attachment, or generic related-entry handoff behavior.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online sanitizer, online service, or live provider test was executed.

## Verification

Baseline before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 1554 assertions, 0 failures`

After implementation:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 1574 assertions, 0 failures`
- Delta: `+1` focused PASS case, `+20` assertions

Example smoke:

- `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`
- Result: `wordpress-bibtex-csl-handoff self-test passed`

## Dependency Closure

No new support component is needed. This slice reuses `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, and the existing WordPress BibTeX/CSL handoff example. The upstream runner dependency blocker remains unchanged and belongs to the upstream-runner audit lane.

## Follow-Up

Keep broader BibLaTeX license-style variants, locale-specific license wording, full citeproc relation parity, and upstream Haskell runner closure as separate bounded slices.
