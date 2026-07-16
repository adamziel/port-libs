# Pandoc BibTeX/CSL Current-Base Season Date Slice

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260608T041839Z`  
Base accepted HEAD: `e8c43317726abb932805c171a399c58fb2c01c99`

## Behavior

Added bounded native BibLaTeX season-date handoff for numeric season month codes:

- `YYYY-21` -> CSL `season: 1` (`Spring`)
- `YYYY-22` -> CSL `season: 2` (`Summer`)
- `YYYY-23` -> CSL `season: 3` (`Autumn`)
- `YYYY-24` -> CSL `season: 4` (`Winter`)

The parser now maps these single-date forms into CSL `date-parts` with the year only plus the CSL `season` value for `date`, `origdate`, `urldate`, and `eventdate` users. Malformed season dates with a day component, such as `2026-22-04`, remain fail-closed. This is intentionally not a broad EDTF implementation and does not add season ranges.

## Non-Overlap

This does not repeat the existing BibLaTeX date-range/open-ended date slices or the existing direct-CSL season renderer slice. Those already covered CSL items containing `season`; this slice only maps bounded BibLaTeX season month codes into that existing CSL date model.

## Evidence

- Red-first focused run before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 2267 assertions, 1 failures`
  - Failure: `BibTeX date month must be between 1 and 12` for `date = {2026-22}`.
- Final focused run:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 2292 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`
  - Result: `wordpress-bibtex-csl-handoff self-test passed`.
- Syntax checks:
  - `php -l lanes/pandoc/src/BibtexCslParser.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php`
  - Result: no syntax errors detected.
- Whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed.

Status delta: +1 mapped BibTeX/CSL support case, +1 PHP PASS case, +25 focused assertions. Lane `phpPass` is now recorded as `1540`; `benchmarkDenominator.mapped` is now `1960`.

## Dependency Closure

No new support component is needed. This slice reuses native `BibtexCslParser` date parsing, existing `CitationCslProcessor` CSL season rendering, `MarkdownReader`, `WordPressBlockWriter`, focused `CitationCslProcessorTest` coverage, and the lane-local WordPress BibTeX/CSL handoff example. Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runners, external bibliography managers, online services, live provider tests, and live-service provider tests were not run.

## Follow-Up

A future non-overlapping BibTeX/CSL slice can choose bounded season date ranges, additional EDTF uncertainty/unknown markers, richer date interval normalization, or remaining BibLaTeX metadata fields not already covered.
