# BibTeX/CSL Source Locator Handoff

- Micro-slice: `pandoc-bibtex-csl-core-current-base-20260609T021952Z`
- Accepted base: `a3acdbf651a3d75d5d84e3bea3aaa5d49ff7e5c6`
- Behavior: bounded `.bib` source-locator handoff for `source` / `source-title`, `section`, and `supplement` fields into CSL item metadata and WordPress bibliography review output.
- Non-overlap: extends existing BibTeX/CSL metadata import after accepted source-file, entryset, date, title, identifier, creator-role, language, keyword, shorthand, event, rights, CSL-JSON source, and CSL-JSON section/supplement slices. It does not repeat citation rendering, source sorting, section/supplement CSL-JSON label formatting, source-file attachment diagnostics, refsection/refsegment, entryset/related, syntax-highlighting, ODF, DOCX, XML/HTML5 DOM, YAML, or archive/package work.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 3324 assertions, 0 failures`.
- Red-first: the new source-locator test failed before implementation with `1 test files, 3325 assertions, 1 failures` because `.bib` source metadata was not mapped.
- Final: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 3342 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-source-locator-handoff.php --self-test` passed.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` behavior. No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, office tools, zip/unzip, external converters, online services, live provider tests, or live-service provider tests were executed.

## Follow-Up

Keep remaining legal publication fields, richer media labels, and additional BibLaTeX provenance/date aliases as separate bounded BibTeX/CSL slices.
