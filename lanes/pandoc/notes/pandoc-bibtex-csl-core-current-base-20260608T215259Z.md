# Pandoc BibTeX/CSL Abbreviation JSON Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T215259Z`
Base accepted HEAD: `6f8463809fe932bed047f1bc503ab1bca68687f8`

## Behavior

- Added a bounded native CSL abbreviation JSON handoff for imported BibTeX
  bibliography packets.
- `CitationCslProcessor::cslAbbreviationsFromJson()` now accepts CSL-style
  abbreviation JSON object maps, including the common top-level `default`
  bucket, and reuses the existing abbreviation category/value validation.
- `CitationCslProcessor::withCslAbbreviationsJson()` lets BibTeX-derived items
  render `form="short"` title, container, collection, publisher, place, and
  genre variables from a supplied abbreviations payload.
- Added a WordPress smoke for BibTeX input plus CSL abbreviations JSON without
  invoking Pandoc, citeproc, BibTeX, Biber, external bibliography managers,
  Haskell runners, online services, live provider tests, or live-service
  provider tests.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` files existed before editing.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 2973 assertions, 0 failures`.
- Red-first focused test after adding the abbreviation JSON expectations: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 2973 assertions, 1 failures` because `CitationCslProcessor::cslAbbreviationsFromJson()` was absent.
- Final focused test after implementation: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 2987 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-abbreviation-file-handoff.php --self-test` -> `wordpress-bibtex-csl-abbreviation-file-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1890` -> `1891`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2312` -> `2313`.
- `mappedBibtexCslCoreCases`: `7` -> `8`.
- `bibtexCslCoreAssertions`: `121` -> `135`.
- Focused assertion delta: `+14` assertions over the accepted-base Citation/CSL baseline.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`CitationCslProcessor`, `BibtexCslParser`, `MarkdownReader`,
`WordPressBlockWriter`, and existing CSL abbreviation lookup logic. Full
Pandoc/citeproc runner parity remains gated on the upstream Haskell runner and
Cabal plan; no external runner or bibliography tool was executed.

## Non-Overlap

This slice does not repeat accepted CSL abbreviation array lookup, BibLaTeX
journal abbreviations, short-series metadata, shorthand-list output,
event-place lists, pagination/bookpagination, article numbers, call numbers,
entry subtype metadata, related/xref metadata, keywords, refsection/refsegment,
language options, field/name annotations, or eprint/archive summaries. It only
owns native parsing and validation of supplied CSL abbreviation JSON payloads
for BibTeX/CSL rendering handoff.

## Follow-Up

Possible follow-ups should stay non-overlapping: additional citeproc-style
abbreviation provenance diagnostics, bibliography disambiguation, note-style
citation behavior, or another safe BibLaTeX datamodel field not already mapped.
