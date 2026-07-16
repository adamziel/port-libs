# pandoc-bibtex-csl-core-current-base-20260605T121607Z

## Scope

Implemented bounded BibLaTeX publisher and source-location literal-list handoff on accepted base `db0a228eb07b0f12263314fd229427abcb5374d6`.

This slice keeps the behavior native PHP and external-tool free. It parses top-level BibLaTeX `and` literal lists for:

- `publisher`, `institution`, `school`, and `organization`
- `location`, `address`, and `venue`
- `origpublisher`
- `origlocation` and `origaddress`

The parser now emits CSL scalar display values plus list audit variables:

- `publisher-list`
- `publisher-place-list`
- `original-publisher-list`
- `original-publisher-place-list`

`CitationCslProcessor` normalizes those list variables into `publisherList`, `publisherPlaceList`, `originalPublisherList`, and `originalPublisherPlaceList`, renders multi-place publisher diagnostics in the default bibliography, exposes CSL text variables for custom styles, and carries the metadata through the WordPress BibTeX/CSL handoff example.

## Red-first Evidence

Before implementation, the focused test was added and failed as expected:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1073 assertions, 1 failures
```

Failure reducer: the new `distributed-review` BibLaTeX entry expected `Review Press; Archive Desk` for `publisher`, but the parser still returned the unsplit literal `Review Press and Archive Desk`.

## Verification

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1097 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

Status delta:

- Focused PASS cases: `53 -> 54`
- Focused assertions: `1071 -> 1097`
- `lane-status.json` `phpPass`: `890 -> 891`
- Manifest mapped checks: `1347 -> 1348`
- `mappedBibtexCslCoreCases`: `2 -> 3`
- `bibtexCslCoreAssertions`: `38 -> 64`

## Non-overlap

This does not repeat accepted BibTeX/CSL work for crossref/xdata inheritance, source-file policy, set and related entries, original/translation metadata, legal fields, date ranges, title details, publication/eprint metadata, journal abbreviations, page-first metadata, main-title/multivolume metadata, note/addendum, entry-subtype, editorial roles, name annotations, shorthand aliases, software/dataset metadata, event metadata, or event organizers.

The slice is limited to bounded publisher/source-location literal lists and their CSL/WordPress handoff.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP BibTeX parser, CSL processor, Markdown reader, and WordPress block renderer.

No Pandoc, citeproc, BibTeX, Biber, Haskell runner, Cabal solver/build, Word, LibreOffice, zip/unzip, TeX/PDF engine, browser renderer, online sanitizer, or online service was executed.

Remaining upstream runner blocker is unchanged: full upstream Pandoc runner parity still requires a hydrated checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and a bounded non-mutating dependency plan for `test:test-pandoc` and `test:test-pandoc-lua-engine`.
