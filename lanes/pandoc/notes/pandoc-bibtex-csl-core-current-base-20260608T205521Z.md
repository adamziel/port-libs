# Pandoc BibTeX/CSL Short Series Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T205521Z`
Base accepted HEAD: `65a6df3ab5094e251e3a86a2aa20ace8a8f50ea8`

## Behavior

- `BibtexCslParser` maps bounded BibLaTeX `shortseries`, `short-series`, `series-short`, `shortcollection`, and `collection-title-short` aliases into CSL-like `collection-title-short` metadata.
- `CitationCslProcessor` normalizes that metadata as `collectionTitleShort`, exposes it through `collection-title-short`, and uses it when a CSL style asks for `collection-title` short-form rendering.
- Default bibliography output now includes a reviewer-visible `Series abbreviation` sentence when short collection metadata is present.
- `wordpress-bibtex-csl-series-short-handoff.php` exercises the WordPress review path without invoking Pandoc, citeproc, BibTeX, Biber, external bibliography managers, Haskell runners, online services, live provider tests, or live-service provider tests.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` files existed before editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 2883 assertions, 0 failures`.
- Red-first: after adding the focused short-series test, `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 2886 assertions, 1 failures` at missing `collection-title-short` metadata.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 2897 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-series-short-handoff.php --self-test` -> `wordpress-bibtex-csl-series-short-handoff self-test passed`.
- PHP lint: `php -l lanes/pandoc/src/BibtexCslParser.php`, `php -l lanes/pandoc/src/CitationCslProcessor.php`, `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`, and `php -l lanes/pandoc/examples/wordpress-bibtex-csl-series-short-handoff.php` all reported no syntax errors.
- JSON validation: `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $f . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $f . " ok" . PHP_EOL; }'` reported both JSON files ok.
- Diff hygiene: `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1838` -> `1839`.
- `UPSTREAM_TEST_MANIFEST.json` benchmark mapped denominator: `2262` -> `2263`.
- `mappedBibtexCslCoreCases`: `7` -> `8`.
- `bibtexCslCoreAssertions`: `121` -> `135`.
- Focused assertion delta: `+14` assertions over the accepted-base Citation/CSL baseline.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, and the existing focused Citation/CSL test harness.

## Non-Overlap

This slice does not repeat existing BibTeX/CSL work for journal abbreviations, shorthand lists, event-place lists, pagination/bookpagination, article numbers, call numbers, entry subtype metadata, related/xref, keywords, refsection/refsegment, language options, or field/name annotations.

## Follow-Up

The next BibTeX/CSL slice should choose a non-overlapping datamodel or CSL rendering gap such as abbreviation-file handoff or another safe BibLaTeX metadata field that is not already represented by short series support.
