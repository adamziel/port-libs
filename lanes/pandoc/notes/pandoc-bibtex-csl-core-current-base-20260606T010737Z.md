# Pandoc BibTeX/CSL PubMed Identifier Slice

Session: `port-dev-pandoc-bibtex-csl-20260606T010737Z`
Micro-slice: `pandoc-bibtex-csl-core-current-base-20260606T010737Z`
Base accepted HEAD: `5ede50989da7cee0d4a9a04198c44e074eacbb0b`

## Behavior

- Added bounded BibTeX/BibLaTeX `pmid` and `pmcid` field mapping into CSL `PMID` and `PMCID` item fields.
- Normalized the identifiers as `pmid` and `pmcid` in `CitationCslProcessor` for direct CSL item input.
- Rendered PubMed identifiers in default bibliographies as `PMID ...` and `PMCID ...`.
- Exposed `PMID` and `PMCID` through custom CSL `<text variable="..."/>` rendering.
- Extended the WordPress BibTeX/CSL handoff example with a PubMed review source and self-test assertions.

## Source Truth And Non-Overlap

This is a native PHP support-library slice for bibliography handoff. It deliberately does not repeat accepted DOI, URL, ISBN, ISSN, archive/eprint, article-number/eid, call-number, pagination/bookpagination, shorthand, subtype, or related-entry behavior. The mapped case covers the distinct CSL PubMed identifier variables needed by medical and scholarly import packets.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, or live provider test was run.

## Verification

Baseline before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 1436 assertions, 0 failures`

After implementation:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 1455 assertions, 0 failures`
- Delta: `+1` focused PASS case, `+19` assertions

Example smoke:

- `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`
- Result: `wordpress-bibtex-csl-handoff self-test passed`

Changed PHP syntax checks:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php`
- Result: no syntax errors detected in all changed PHP files

Lane metadata and patch checks:

- `php -r '$paths=["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($paths as $path) { json_decode(file_get_contents($path), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $path . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $path . " ok" . PHP_EOL; }'`
- Result: both JSON files decoded successfully
- `git diff --check -- lanes/pandoc`
- Result: passed with no output

## Dependency Closure

No new support component is needed. This reuses `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, and the existing WordPress BibTeX/CSL handoff example. The upstream runner dependency blocker remains unchanged and belongs to the upstream-runner audit lane.

## Follow-Up

Possible follow-up slices should stay non-overlapping: reviewed-title/references/dimensions/scale CSL variables, richer relation rendering, or remaining BibLaTeX metadata with focused PHP tests. Do not repeat PubMed, DOI, ISBN, ISSN, archive/eprint, article-number/eid, call-number, or pagination/bookpagination identifier coverage.
