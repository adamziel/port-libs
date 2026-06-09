# Pandoc BibTeX/CSL Direct Creator Role Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260609T014907Z`

Base accepted HEAD: `08f16fc4bbcf45b83d9ea2497b2ad817ee73416e`

## Behavior

`BibtexCslParser` now maps bounded BibTeX/BibLaTeX creator fields that are
already supported by the lane's CSL renderer but were not imported from BibTeX
source text:

- `chair`;
- `collectioneditor` / `collection-editor`;
- `compiler`;
- `composer`;
- `contributor`;
- `curator`;
- `editortranslator` / `editor-translator`;
- `editorialdirector` / `editorial-director`;
- `illustrator`;
- `interviewer`;
- `recipient`;
- `reviewedauthor` / `reviewed-author`.

The same fields are now treated as BibLaTeX name-annotation fields, so inputs
such as `chair+an` and `recipient+an:family` remain attached to the parsed CSL
name list instead of being downgraded to generic field annotations.

The focused test starts from BibTeX text, not direct CSL JSON. It verifies raw
`CitationCslProcessor::bibtexItems()` parser output, normalized
`CitationCslProcessor::fromBibtex()` aliases, CSL `<names>` rendering,
WordPress bibliography blocks, and annotation summaries for the new direct
creator-field handoff.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` notes existed before work.
- Source inspection before edits showed the CSL processor already normalized and
  rendered these creator variables, while `BibtexCslParser::entryToCslItem()`
  did not import their direct BibTeX fields.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 3261 assertions, 0 failures`.
- Final focused test: the same command passed with `1 test files, 3292 assertions, 0 failures`.
- PHP lint passed for `BibtexCslParser.php`,
  `CitationCslProcessorTest.php`, and
  `wordpress-bibtex-csl-direct-creator-roles-handoff.php`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-direct-creator-roles-handoff.php --self-test`
  passed.
- JSON status/manifest validation passed, and
  `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Manifest Delta

- `benchmarkDenominator.mapped`: `2489 -> 2490`
- `inventory.mappedBibtexCslCoreCases`: `7 -> 8`
- `inventory.bibtexCslCoreAssertions`: `121 -> 152`
- `lane-status.json phpPass`: `2077 -> 2078`

## Non-Overlap

This does not repeat the accepted direct CSL participant-name or editorial-label
slices, which construct CSL item arrays directly. It does not repeat the
accepted BibTeX/BibLaTeX director, audiovisual creator, secondary editor role,
redactor/editorial role alias, event-place, keyword, related-entry, pagination,
article-number, library call-number, entry-subtype, shorthand, or annotation
slices. This slice only closes the missing BibTeX-source import path for direct
CSL creator fields.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`BibtexCslParser`, existing CSL name normalization/rendering in
`CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, Word, LibreOffice,
zip/unzip, external bibliography manager, online service, live provider test, or
live-service provider test was executed.
