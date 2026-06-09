# Pandoc BibTeX/CSL Director Creator Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260609T011951Z`

Base accepted HEAD: `403bbfa850b87a30b18d0488738d4e785be58580`

## Behavior

`BibtexCslParser` now maps bounded BibLaTeX `director` name lists into CSL
`director` metadata for media entries. `director+an` annotations are handled as
name annotations, so review notes stay attached to the director name list instead
of being downgraded to generic field annotations.

The new focused case uses `@movie` input with a personal director, a literal
institution director, and scoped name annotations. It verifies:

- raw `CitationCslProcessor::bibtexItems()` parser output includes `director`;
- normalized `CitationCslProcessor::fromBibtex()` items expose `directors`;
- CSL `<names variable="director">` renders citations and bibliography entries;
- WordPress bibliography blocks preserve director credits and annotation summary
  text without invoking external bibliography tooling.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` notes existed before work.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 3212 assertions, 0 failures`.
- Red-first after adding the focused test: same command failed with `director`
  output `NULL`, `1 test files, 3215 assertions, 1 failures`.
- Final: same command passed with `1 test files, 3226 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-director-handoff.php --self-test`
  passed.

## Manifest Delta

- `benchmarkDenominator.mapped`: `2444 -> 2445`
- `inventory.mappedBibtexCslCoreCases`: `7 -> 8`
- `inventory.bibtexCslCoreAssertions`: `121 -> 135`
- `lane-status.json phpPass`: `2029 -> 2030`

## Non-Overlap

This does not repeat accepted BibTeX/CSL media type alias coverage,
audio/artwork alias coverage, unpublished speech mapping, event place lists,
field/name annotations, shorthand/list-of-shorthands, rights, skipbib, or
entryset slices. The accepted media slices covered type conditionals; this slice
covers a missing creator-name handoff for media entries.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`BibtexCslParser`, existing CSL name rendering in `CitationCslProcessor`,
`MarkdownReader`, and `WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, Word, LibreOffice,
zip/unzip, external bibliography manager, online service, live provider test, or
live-service provider test was executed.
