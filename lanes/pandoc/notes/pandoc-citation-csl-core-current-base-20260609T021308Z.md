# pandoc-citation-csl-core-current-base-20260609T021308Z

## Scope

Implemented one bounded Citation/CSL behavior cluster on accepted base
`ae05f994f04ccc78db62e7bd6dd42669f76246b1`: `cs:label` children inside
`cs:names` now preserve and render `quotes="true"` metadata for creator role
labels. This reuses the existing CSL locale term lookup for `open-quote` and
`close-quote`, applies quotes before label affixes, and keeps citation and
bibliography role-label output native PHP.

## Status Delta

- `mappedCitationCslCoreCases`: `12 -> 13`.
- `benchmarkDenominator.mapped`: `2551 -> 2552`.
- `phpPass`: `2124 -> 2125`.
- Focused citation assertions: `3304 -> 3316`.
- Added WordPress smoke: `examples/wordpress-citation-csl-name-label-quotes-handoff.php --self-test`.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 3304 assertions, 0 failures`
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 3316 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-name-label-quotes-handoff.php --self-test`
  - `wordpress-citation-csl-name-label-quotes-handoff self-test passed`
- Syntax checks:
  - `php -l lanes/pandoc/src/CslStyle.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-name-label-quotes-handoff.php`
- JSON validation:
  - `lanes/pandoc/lane-status.json valid`
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json valid`
- Whitespace: `git diff --check -- lanes/pandoc`
  - passed

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded CSL
style parser, locale term lookup, citation renderer, Markdown reader, and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell runners,
citeproc, bibliography managers, Word, LibreOffice, zip/unzip, external
template engines, TeX/PDF engines, online services, live provider tests, or
live-service provider tests.

## Non-Overlap

This does not repeat accepted CSL source-variable, part/version/section number,
editorial/audiovisual creator role label, contextual locator label,
punctuation-in-quote, first-reference-note-number, display/substitute, or
citation-collapse slices. Follow-up CSL work should choose a separate behavior
such as broader citation-position disambiguation, remaining locale term forms,
or BibTeX/BibLaTeX edge parsing.
