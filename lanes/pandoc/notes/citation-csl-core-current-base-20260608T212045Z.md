# Citation/CSL Current-Base Abbreviation Handoff

Slice: `pandoc-citation-csl-core-current-base-20260608T212045Z`
Base accepted HEAD: `28fa19ccf3ea58dcc60033aba187e21c553c5024`

## Behavior

- Added bounded CSL abbreviation-list support through `CitationCslProcessor::withCslAbbreviations()`.
- Normalized supplied abbreviation categories and exposed them in `cslStyleSummary()` for review metadata.
- Applied abbreviation lookups for `cs:text form="short"` when direct CSL short fields are absent, while preserving direct `title-short`, `container-title-short`, and `collection-title-short` precedence.
- Covered title, container-title, collection-title, publisher, place-backed publisher-place, and genre short-form rendering for citation clusters, bibliography entries, and WordPress blocks.
- Invalid abbreviation category shapes now fail closed with `InvalidArgumentException`.

## Verification

- Red-first focused run before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 2941 assertions, 1 failures` because `withCslAbbreviations()` was missing.
- Final focused run:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2951 assertions, 0 failures`.
- Added `+1` PHP PASS case; lane `phpPass` moved from `1861` to `1862`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-abbreviation-handoff.php --self-test`
  passed with `wordpress-citation-csl-abbreviation-handoff self-test passed`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `CitationCslProcessor`, `CslStyle`, `MarkdownReader`, `WordPressBlockWriter`, the focused CSL tests, and the lane-local WordPress CSL abbreviation example.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted Citation/CSL short-field metadata, institution short parts, date-part rendering, name-part formatting, ordinal/gender forms, et-al/subsequent-author behavior, choose conditionals, display formatting, or note-style context. It only adds bounded abbreviation-list lookup for short text variables when item-level short metadata is not already present.

## Follow-Up

Future Citation/CSL work should choose a separate native gap, such as richer locale abbreviation category import, note-style bibliography state, or remaining non-overlapping name/date formatting parity.
