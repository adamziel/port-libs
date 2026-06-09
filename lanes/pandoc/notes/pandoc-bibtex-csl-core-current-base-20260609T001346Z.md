# Pandoc BibTeX/CSL Skipbib Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260609T001346Z`
Base accepted HEAD: `2db0e80f0d313cd1b86adb66fbde40c6e33a2164`

## Behavior

- Added bounded native normalization for BibLaTeX entry `options` containing
  `skipbib`, `skipbib=true`, or `skipbib=false`.
- `CitationCslProcessor` now exposes `biblatexSkipBibliography` and
  `biblatexBibliographyVisibility` review metadata.
- CSL text variables `skipbib`, `biblatex-skipbib`,
  `biblatex-skip-bibliography`, and `biblatex-bibliography-visibility` are
  available to bounded review styles.
- `appendBibliography()` omits cited entries marked `skipbib=true` or bare
  `skipbib` while preserving citation rendering and direct
  `renderBibliographyEntry()` review/debug output for those entries.
- Documents where every cited entry is skipped no longer append an empty
  bibliography heading.
- Added a WordPress smoke showing a visible cited source and two citeable
  skipped packets without invoking Pandoc, citeproc, BibTeX, Biber, external
  bibliography managers, Cabal, Haskell runners, online services, live
  provider tests, or live-service provider tests.

Source truth: the existing native BibTeX/CSL lane already preserves bounded
BibLaTeX `options` values and filters `dataonly` entries before CSL handoff;
this slice maps the preserved `skipbib` entry option into appended
bibliography visibility only, leaving direct review rendering intact.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` files existed before
  editing.
- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  -> `1 test files, 3176 assertions, 0 failures`.
- Red-first focused test after adding the `skipbib` expectation:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  -> `1 test files, 3181 assertions, 1 failures` because normalized
  `biblatexSkipBibliography` metadata was still `null`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  -> `1 test files, 3194 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-bibtex-csl-skipbib-handoff.php --self-test`
  -> `wordpress-bibtex-csl-skipbib-handoff self-test passed`.
- PHP lint:
  `php -l lanes/pandoc/src/CitationCslProcessor.php` -> no syntax errors;
  `php -l lanes/pandoc/tests/CitationCslProcessorTest.php` -> no syntax
  errors;
  `php -l lanes/pandoc/examples/wordpress-bibtex-csl-skipbib-handoff.php` ->
  no syntax errors.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  -> `json ok`.
- Whitespace check: `git diff --check -- lanes/pandoc` -> passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2004` -> `2005`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2420` -> `2421`.
- `mappedBibtexCslCoreCases`: `7` -> `8`.
- `bibtexCslCoreAssertions`: `121` -> `139`.
- Focused assertion delta: `+18` assertions over the accepted-base
  Citation/CSL baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP `BibtexCslParser`
option preservation, `CitationCslProcessor`, `MarkdownReader`, and
`WordPressBlockWriter`. Full Pandoc/citeproc runner parity remains gated on
the upstream Haskell/Cabal runner path; no external runner or bibliography
tool was executed.

## Non-Overlap

This slice does not repeat accepted BibLaTeX entry option preservation,
`dataonly` filtering, related/xref/crossref metadata, refsection/refsegment
provenance, keyword lists, date sorting, or CSL citation/bibliography sorting
behavior. It only owns the bounded `skipbib` entry-option effect on appended
bibliography visibility.

## Follow-Up

Possible follow-ups should stay non-overlapping: another bounded BibLaTeX
entry option, a distinct bibliography visibility/provenance handoff, or a
safe CSL variable gap not already covered by the current BibTeX/CSL cases.
