# BibTeX/CSL Thesis Type Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T100335Z`
Base accepted HEAD: `cce01e209ef14871f2c6eba17fb429599adb1e02`

## Behavior

This slice maps bounded BibLaTeX thesis entry aliases into native CSL handoff metadata:

- `@phdthesis`, `@mastersthesis`, and BibLaTeX `@mathesis` normalize to CSL `type: thesis`.
- Explicit BibLaTeX `thesistype` / `thesis-type` fields are preserved as CSL `thesis-type`.
- Thesis-entry `type` fields remain available as degree metadata for `@thesis`, `@phdthesis`, `@mastersthesis`, and `@mathesis` without leaking that behavior into reports, patents, or legal entries.
- CSL style variables `thesis-type` and `thesistype` render from normalized item metadata.
- The default bibliography review metadata includes `Thesis type` so WordPress import review packets keep degree/provenance visible.

## Focused Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2386 assertions, 0 failures`.
- Intermediate red check: the new thesis test initially produced `5 failures` because BibLaTeX `type` was over-applied to non-thesis entries.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2412 assertions, 0 failures`.
- Focused delta: `+1` PHP PASS case and `+26` focused assertions in `CitationCslProcessorTest.php`.

## Dependency Closure

No new support component is needed. This reuses the native PHP `BibtexCslParser`, `CitationCslProcessor`, Markdown reader, and WordPress block writer paths. No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat the accepted BibTeX/CSL entry-subtype, call-number, pagination/bookpagination, article-number/eid, event-place list, or Citation/CSL name/date/empty-else slices. The bounded behavior is specific to thesis alias normalization and thesis degree metadata handoff.
