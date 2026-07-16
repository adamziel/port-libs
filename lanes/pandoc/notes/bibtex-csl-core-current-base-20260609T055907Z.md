# BibTeX/CSL Core Current-Base Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260609T055907Z`
Base: `7ed2f69b027c00a8c9af1b63d2dfcdebbab97ac6`

## Source Truth

CSL 1.0.2 defines `part-title` as the title of the specific part being cited and `volume-title` as the title of the volume or container volume, including special issue style titles. It also directs `part-number` and `volume` number variables to use those title variables for titles when present:
https://docs.citationstyles.org/en/stable/specification.html#appendix-iv-variables

## Implemented Behavior

- `BibtexCslParser` now maps flat BibTeX/BibLaTeX handoff fields `volumetitle` and `parttitle` into CSL `volume-title` and `part-title`.
- `CitationCslProcessor` now normalizes direct CSL `volume-title` / `part-title` fields into `volumeTitle` / `partTitle`.
- Default bibliography output preserves the title metadata as `Volume title:` and `Part title:` review details.
- CSL style XML `<text variable="volume-title"/>` and `<text variable="part-title"/>` render through citation and bibliography output.
- The WordPress smoke keeps volume/part title metadata visible in review blocks from a `.bib` source without invoking external bibliography tools.

## Evidence

Red check before implementation:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 3808 assertions, 1 failures`; the new focused case failed because `volumetitle` was not exposed as CSL `volume-title`.

Focused verification after implementation:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 3827 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-bibtex-csl-volume-part-title-handoff.php --self-test`

Result: `wordpress-bibtex-csl-volume-part-title-handoff self-test passed`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `BibtexCslParser`, `CitationCslProcessor`, `CslStyle`, `MarkdownReader`, and `WordPressBlockWriter` paths.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external template engine, external converter, TeX/PDF engine, browser renderer, online service, live provider test, BibTeX, Biber, or citeproc process was executed.

## Non-Overlap

This handoff avoids the accepted BibTeX/CSL clusters for source/section/supplement, printing/supplement-number, call-number/library, label-prefix/sort-init, xdata provenance, crossref title inheritance, reviewed/reprint metadata, archive/eprint, and PDF engine handoff slices. It owns only bounded volume/part title metadata.
