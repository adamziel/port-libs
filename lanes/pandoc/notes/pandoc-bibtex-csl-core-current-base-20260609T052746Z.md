# Pandoc BibTeX/CSL Split End Date Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260609T052746Z`
Session: `port-dev-pandoc-bibtex-csl-20260609T052746Z`
Base accepted HEAD: `003cd766d197b04fb23d7e77772dd1e8b0ccc6a3`

## Behavior

`BibtexCslParser` now maps BibLaTeX split end-date fields into CSL date
ranges when an entry uses split date parts instead of a literal `date` range.
The importer now preserves:

- `year` / `month` / `day` plus `endyear` / `endmonth` / `endday`;
- `origyear` plus `origendyear`;
- `urlyear` / `urlmonth` / `urlday` plus URL end-date fields;
- `availableyear` and `submittedyear` families plus their end-date fields;
- `eventyear` / `eventmonth` / `eventday` plus event end-date fields.

The renderer path was already able to render CSL date ranges. This slice only
closes the `.bib` import gap, so WordPress review packets keep source review
windows visible without requiring Pandoc, citeproc, BibTeX, or Biber.

## Evidence

No current `port-pandoc-*.needs-lane-rework.md` notes existed before work.

Red probe before implementation:

```text
php -r 'require "tools/bootstrap.php"; $items=\PortLibs\Pandoc\CitationCslProcessor::bibtexItems("@book{split,title={Split Date},year={2020},month={5},endyear={2021},endmonth={6},url={https://example.test},urlyear={2026},urlmonth={6},urlday={1},urlendyear={2026},urlendmonth={6},urlendday={2}}\n"); var_export([$items[0]["issued"] ?? null, $items[0]["accessed"] ?? null]); echo "\n";'
array (
  0 =>
  array (
    'date-parts' =>
    array (
      0 =>
      array (
        0 => 2020,
        1 => 5,
      ),
    ),
  ),
  1 =>
  array (
    'date-parts' =>
    array (
      0 =>
      array (
        0 => 2026,
        1 => 6,
        2 => 1,
      ),
    ),
  ),
)
```

Baseline focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3747 assertions, 0 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3767 assertions, 0 failures
```

PHP lint:

```text
php -l lanes/pandoc/src/BibtexCslParser.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-bibtex-csl-split-end-date-handoff.php
No syntax errors detected in all changed PHP files.
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-split-end-date-handoff.php --self-test
wordpress-bibtex-csl-split-end-date-handoff self-test passed
```

Lane JSON and whitespace checks:

```text
php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc lane JSON ok\n";'
pandoc lane JSON ok

git diff --check -- lanes/pandoc
```

`git diff --check -- lanes/pandoc` produced no output and exited cleanly.

Focused delta:

- `phpPass`: `2375 -> 2376`
- `benchmarkDenominator.mapped`: `2769 -> 2770`
- `mappedBibtexCslCoreCases`: `7 -> 8`
- `bibtexCslCoreAssertions`: `121 -> 141`
- `CitationCslProcessorTest.php`: `3747 -> 3767` focused assertions

Root harness was not run - isolated micro-slice.

## WordPress Smoke

Added `lanes/pandoc/examples/wordpress-bibtex-csl-split-end-date-handoff.php`
covering Markdown citation replacement, CSL date rendering, and WordPress
definition-list bibliography output for split end-date review packets.

## Non-Overlap

This does not repeat accepted BibTeX/CSL text `date={start/end}` ranges,
open-ended date ranges, season dates, date-time parts, date addenda,
available/submitted date variable rendering, or the accepted split URL start
date handoff. It only adds split end-date import behavior for already-supported
CSL date range rendering.

This also does not repeat recent entry-type routing slices for manual,
booklet, letter, suppperiodical, periodical, or direct creator-field handoff.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`BibtexCslParser`, existing `CitationCslProcessor` date range normalization and
CSL rendering, `MarkdownReader`, `WordPressBlockWriter`, and the focused PHP
test runner.

Full upstream Pandoc/citeproc runner parity remains a separate upstream-runner
dependency task requiring hydrated pinned upstream sources and Haskell test
executables. No Pandoc, Cabal/Haskell runner, BibTeX, Biber, citeproc, Word,
LibreOffice, zip/unzip, external converter, online service, live provider test,
or live-service provider test was executed.
