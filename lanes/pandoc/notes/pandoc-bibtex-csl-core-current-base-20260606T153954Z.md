# Pandoc BibTeX/CSL Xref Current-Base Slice

Session: `port-dev-pandoc-bibtex-csl-20260606T153954Z`

Accepted base: `cdc1ad2ba331b2145aba60331aca87c783d5f08e`

## Scope

This slice adds bounded BibLaTeX `xref` relation handoff for native PHP citation review packets. `xref` keys are preserved as reference metadata, known data-only xref entries are summarized, and missing xref keys are exposed for follow-up. The child item does not inherit parent container or publisher metadata; `crossref` remains the only inheritance path in this bounded support layer.

## Implementation

- `BibtexCslParser` now parses `xref` into `xrefKeys`, `xrefItems`, and `missingXrefKeys`.
- `CitationCslProcessor` normalizes direct CSL item xref fields, exposes `xref`, `xref-summary`, `xref-keys`, and `missing-xref-keys` style variables, and includes xref summaries in fallback bibliography entries.
- The WordPress BibTeX/CSL handoff example now covers an xref chapter with a data-only dossier parent and one missing xref key.

## Evidence

- Red-first focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result before implementation: `1 test files, 1726 assertions, 1 failures`
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result after implementation: `1 test files, 1747 assertions, 0 failures`
- Final example smoke:
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`
  - Result: passed
- Syntax and JSON checks:
  - `php -l lanes/pandoc/src/BibtexCslParser.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php`
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": OK" . PHP_EOL; }'`
  - Result: passed
- Diff check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed

## Status Delta

- `lane-status.json` `phpPass`: `1356 -> 1357`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1770 -> 1771`
- `mappedBibtexCslCoreCases`: `4 -> 5`
- `bibtexCslCoreAssertions`: `65 -> 92`

## Dependency Closure

No new support component is needed. This reuses the existing native `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` support rows. No Pandoc, citeproc, BibTeX, Biber, Cabal build, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not alter accepted crossref inheritance, xdata/data inheritance, related/license metadata, ids/aliases, event-place lists, pagination, article-number/eid handling, library call-number metadata, entry-subtype metadata, source-file policy, or full citeproc/CSL locale parity.

## Next Task

Continue bounded BibTeX/CSL closure with xdata/data inheritance, broader entry-set relation diagnostics, TeX accent decoding, CSL style locale/date-term behavior, note-style citation-position handling, or a separate hydrated upstream runner audit.

Root harness: not run - isolated micro-slice.
