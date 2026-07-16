# Pandoc BibTeX/CSL Current-Base PDF Attachment Alias Slice

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260607T203810Z`
Accepted base: `5c6ad6b05a31e14db0b0d8415f0ee93984f83b0f`

## Behavior

- Added bounded native BibLaTeX `pdf` attachment alias handoff.
- `BibtexCslParser` now treats `pdf` as an alias for the existing source-file attachment field when `file` is absent.
- The alias reuses the existing safe relative-path policy, percent-decoded path normalization, and unsafe remote/traversal/absolute/Windows/backslash/percent diagnostics.
- `wordpress-bibtex-csl-handoff.php` now includes a `pdf` alias source with one importable reviewer PDF and one blocked remote PDF diagnostic.

## Source Truth

- The BibLaTeX manual documents `pdf` as an alias of the source-file attachment field. This slice keeps the alias bounded to metadata handoff and does not read attachment bytes or invoke external bibliography tooling.
- CTAN BibLaTeX manual: https://mirrors.mit.edu/CTAN/macros/latex/contrib/biblatex/doc/biblatex.pdf

## Red/Green Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 2079 assertions, 0 failures`.
- Red-first: same command -> `FAIL maps bounded biblatex pdf aliases into source file attachment metadata`; `1 test files, 2081 assertions, 1 failures`.
- Final: same command -> `1 test files, 2091 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test` -> `wordpress-bibtex-csl-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `mappedBibtexCslCoreCases`: `7 -> 8`.
- `bibtexCslCoreAssertions`: `121 -> 133`.
- `benchmarkDenominator.mapped`: `1956 -> 1957`.
- `CitationCslProcessorTest.php`: `2079 -> 2091` assertions (`+12`) with one new focused PASS case.
- Lane `phpPass`: `1537 -> 1538`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, the existing WordPress BibTeX/CSL handoff example, and focused PHP tests.

No Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice only owns the bounded `pdf` source-file attachment alias. It does not repeat the accepted `file` attachment policy diagnostics, xdata/crossref inheritance, URL labels, pagination/bookpagination, article-number/eid, event-place lists, identifiers, call numbers, reviewed/reprint/original-title metadata, custom user/verbatim fields, or CSL rendering slices.

## Follow-Up

Keep future BibTeX/CSL work bounded to non-overlapping BibLaTeX data-model aliases, name-list annotations, or CSL variable handoff gaps with focused PHP tests.
