# pandoc-bibtex-csl-core-current-base-20260605T111331Z

## Scope

Lane: pandoc
Micro-slice: pandoc-bibtex-csl-core-current-base-20260605T111331Z
Accepted base: 614193f8d761b9f7ba01ed479006912fb35fcd87

This slice implements a bounded BibLaTeX journal-abbreviation handoff.
`BibtexCslParser` now maps common short journal fields (`shortjournal`,
`shortjournaltitle`, and related spellings) into CSL `container-title-short`
and `journalAbbreviation` metadata. `CitationCslProcessor` preserves that
metadata in normalized items, exposes it through CSL text variables, includes a
default review bibliography part, and the WordPress BibTeX handoff example now
covers the visible importer path.

Source-truth boundary: this ports the format contract needed for reviewer
bibliography handoff. It does not attempt full citeproc, BibTeX, Biber, Pandoc
runner, journal-abbreviation locale expansion, or bibliography manager parity.

## Red-first evidence

Baseline before adding the new focused expectation:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: `1 test files, 1033 assertions, 0 failures`.

After adding the new focused expectation and before implementation:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: `1 test files, 1035 assertions, 1 failures`. The new case failed
because `CitationCslProcessor::bibtexItems()` did not populate
`container-title-short` from a BibLaTeX `shortjournal` field.

After implementation:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: `1 test files, 1049 assertions, 0 failures`.

```sh
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
```

Result: `wordpress-bibtex-csl-handoff self-test passed`.

## Status delta

- `lanes/pandoc/lane-status.json` `phpPass`: `856` -> `857`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: `1314` -> `1315`.
- `mappedBibtexCslCoreCases`: `2` -> `3`.
- `bibtexCslCoreAssertions`: `38` -> `54`.
- Focused citation coverage: `51` PASS cases / `1033` assertions -> `52` PASS
  cases / `1049` assertions.

## Final verification

```sh
php -l lanes/pandoc/src/BibtexCslParser.php && php -l lanes/pandoc/src/CitationCslProcessor.php && php -l lanes/pandoc/tests/CitationCslProcessorTest.php && php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php
```

Result: no syntax errors detected in all four changed PHP files.

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: `1 test files, 1049 assertions, 0 failures`.

```sh
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
```

Result: `wordpress-bibtex-csl-handoff self-test passed`.

```sh
php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); } echo "json ok\n";'
```

Result: `json ok`.

```sh
git diff --check -- lanes/pandoc
```

Result: passed with no output.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted CSL date/name metadata, BibTeX crossref/xdata,
entry sets, related entries, translation/original-publication metadata, legal
metadata, date ranges, title details, publication volume/issue/identifier
metadata, first-page metadata, main-title/volume metadata, note/addendum,
entry-subtype, editorial roles, name annotations, shorthand aliases, software
or dataset status, event metadata, citation parsing, CSL layout/date/name/text
rendering, citation-number, citation-position, year-suffix, subsequent-author,
table geometry, DOCX/ODT/EPUB3, PDF handoff, YAML, archive, charset, or
XML/HTML5 DOM slices. It is limited to short journal abbreviation metadata and
the existing native BibTeX/CSL handoff path.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`,
`WordPressBlockWriter`, and `CslStyle` support. No Pandoc, citeproc, BibTeX,
Biber, bibliography manager, Cabal build, Haskell runner, Word, LibreOffice,
zip/unzip, external template engine, TeX/PDF engine, browser renderer, online
sanitizer, or online service was executed.

Remaining upstream-runner gate: hydrate a local Pandoc checkout at
0640c4c9859aa5a3ede082c190fcd5883c24ac83 with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal` present, then
record a non-mutating Cabal solver/build plan before attempting bounded
Haskell runner execution.

## Follow-up

Keep related-entry clone rendering, reprint/original-entry relationships,
multi-place publisher/source-location lists, journal-abbreviation locale
expansion, citation-position disambiguation, note-style output, and full
citeproc parity as separate bounded slices.
