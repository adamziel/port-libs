# Pandoc BibTeX/CSL Core Current Base - Page-First Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260605T100551Z`
Base accepted HEAD: `12b7d5ef667f40d14c72177f9deb79e0568eab0c`

## Behavior

Added bounded native BibTeX/BibLaTeX `pages` / `page` handoff into CSL `page-first` metadata. The parser now derives the first page token from normalized page ranges, direct CSL input keeps explicit `page-first` values when present, and bounded CSL styles can render `page-first` through `text`, `number`, and `label` elements.

Covered page-range shapes:

- alphanumeric ranges such as `A12--A18` -> `page-first: A12`
- single-page values such as `77` -> `page-first: 77`
- front-matter ranges such as `ii--iv` -> `page-first: ii`
- direct CSL items deriving from `page` or preserving explicit `page-first`

## Source Truth

CSL includes `page-first` as a page variable. The native Pandoc lane already normalizes BibTeX/BibLaTeX `pages` into CSL `page`; this slice extends that same bounded support-library contract instead of invoking external citeproc, Pandoc, BibTeX, Biber, Cabal, or Haskell runners.

## Evidence

Red-first before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 967 assertions, 1 failures`
- Failure: `page-first` was `NULL` for a BibLaTeX `pages = {A12--A18}` range.

Final focused verification:

- `php -l lanes/pandoc/src/BibtexCslParser.php` - passed
- `php -l lanes/pandoc/src/CitationCslProcessor.php` - passed
- `php -l lanes/pandoc/src/CslStyle.php` - passed
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php` - passed
- `php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php` - passed
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` - `1 test files, 983 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test` - `wordpress-bibtex-csl-handoff self-test passed`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` - `json ok`
- `git diff --check -- lanes/pandoc` - passed

Focused delta: `+17` assertions in `CitationCslProcessorTest.php`, `+1` mapped BibTeX/CSL core case, and lane `phpPass` `822 -> 823`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat recent BibTeX/CSL slices for shorthand labels, short creator lists, event organizers, ID aliases, secondary editor roles, or publication-detail/eprint metadata. It only adds first-page derivation/rendering for existing page range handoff behavior.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP BibTeX parsing, CSL item normalization, CSL style parsing, and WordPress bibliography output. Full upstream Pandoc/citeproc parity remains gated on hydrating the upstream Pandoc checkout and Cabal project files; no external runner or online service was used.

## Follow-Up

Keep broader citeproc parity, disambiguation, locale/style coverage, and upstream Haskell runner execution as separate bounded slices once the upstream runner dependency gate is solved.
