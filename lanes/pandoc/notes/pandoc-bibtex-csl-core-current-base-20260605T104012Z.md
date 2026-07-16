# pandoc-bibtex-csl-core-current-base-20260605T104012Z

## Scope

Lane: pandoc
Micro-slice: pandoc-bibtex-csl-core-current-base-20260605T104012Z
Accepted base: 083d706f01e32c6c825d469c61c5045dda443144

This slice implements a bounded BibLaTeX/CSL handoff for `entrysubtype` /
`entry-subtype` source-kind metadata. The native PHP BibTeX parser now emits
CSL `entry-subtype`, `CitationCslProcessor` normalizes it to item
`entrySubtype`, CSL style rendering exposes the `entry-subtype` variable, and
review bibliography output includes `Entry subtype` metadata for WordPress
review queues.

Source-truth boundary: BibLaTeX treats `entrysubtype` as an entry field used to
specialize bibliography handling. This PHP lane ports the format contract for
handoff metadata only; it does not attempt full citeproc, BibTeX, Biber, or
Pandoc runner parity.

## Red-first evidence

Before implementation:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: `1 test files, 1002 assertions, 1 failures`. The new focused case
failed because `CitationCslProcessor::bibtexItems()` did not populate
`entry-subtype` for a BibLaTeX `entrysubtype` field.

After implementation:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: `1 test files, 1020 assertions, 0 failures`.

```sh
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
```

Result: `wordpress-bibtex-csl-handoff self-test passed`.

## Status delta

- `lanes/pandoc/lane-status.json` `phpPass`: `840` -> `841`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: `1299` -> `1300`.
- `mappedBibtexCslCoreCases`: `2` -> `3`.
- `bibtexCslCoreAssertions`: `38` -> `56`.

## Final verification

```sh
php -l lanes/pandoc/src/BibtexCslParser.php
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php
```

Result: no syntax errors detected.

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: `1 test files, 1020 assertions, 0 failures`.

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

This does not repeat the accepted CSL date/name metadata, BibTeX crossref,
secondary editor role, shorthand/alias, title detail, event metadata, table
geometry, PDF engine, YAML, DOCX, ODT, EPUB3, archive, charset, XML/HTML5 DOM,
or upstream-runner dependency audit slices. It is limited to BibLaTeX
entry-subtype metadata and the existing native citation handoff path.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and
`WordPressBlockWriter` support. No Pandoc, citeproc, BibTeX, Biber, Cabal
build, Haskell runner, Word, LibreOffice, zip/unzip, external template engine,
TeX/PDF engine, browser renderer, online sanitizer, or online service was
executed.

Remaining upstream-runner gate: hydrate a local Pandoc checkout at
0640c4c9859aa5a3ede082c190fcd5883c24ac83 with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal` present, then
record a non-mutating Cabal solver/build plan before attempting bounded Haskell
runner execution.

## Follow-up

Keep related-entry clone rendering, reprint/original-entry relationships,
journal abbreviation/locales, citation-position disambiguation, note-style
output, and full citeproc parity as separate bounded slices.
