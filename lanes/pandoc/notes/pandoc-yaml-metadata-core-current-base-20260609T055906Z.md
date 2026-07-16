# Pandoc YAML Metadata Alias Provenance Handoff

Slice: `pandoc-yaml-metadata-core-current-base-20260609T055906Z`
Base accepted HEAD: `7ed2f69b027c00a8c9af1b63d2dfcdebbab97ac6`
Date: 2026-06-09 UTC

## Behavior

`MarkdownReader` now exposes YAML alias-use provenance through the document attr
`yamlMetadataAliasProvenance`. The handoff preserves existing resolved metadata
values and unresolved-alias diagnostics while adding review metadata for alias
tokens used in block mappings, merge keys, flow mappings, and sequence entries.

Each provenance record includes the alias token, resolved anchor when known,
resolved status, value kind, normalized metadata path, source line, and chained
alias value when the resolved value is itself an alias token.

## Evidence

- Baseline focused check before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  `1 test files, 4735 assertions, 0 failures`
- Final focused check:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  `1 test files, 4757 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  `yaml metadata handoff self-test ok`

Status delta:

- `lane-status.json` `phpPass`: `2411 -> 2412`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2800 -> 2801`
- `mappedYamlMetadataAliasProvenanceCases`: `1`
- `yamlMetadataAliasProvenanceAssertions`: `22`

## Non-Overlap

This slice avoids already accepted YAML metadata behavior for placement,
JSON/flow map parsing, block sequences, anchor/alias resolution values,
explicit merge tags, indented document-marker scalars, explicit null keys,
block scalar comments, invalid escape diagnostics, and collection tag
provenance. It also avoids PDF, ODF/OpenDocument, DOCX, EPUB, archive,
doctemplate, CSL/BibTeX, XML/HTML5 DOM, charset, syntax-highlighting, and
math/TeX surfaces.

## Dependency Closure

No new native support component is needed. The slice reuses the existing native
`MarkdownReader` YAML metadata parser, focused `MarkdownReaderTest.php`
coverage, and the `wordpress-yaml-metadata-handoff.php` example smoke. Full
upstream Pandoc runner parity remains out of scope for this isolated slice.

## Exclusions And Follow-Up

No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, external
template engine, external converter, TeX/PDF engine, browser renderer, online
service, live provider test, or live-service provider test was executed. The
root harness was not run for this isolated micro-slice.

Possible follow-up: writer-side alias provenance emission, flow collection
syntax diagnostics, or richer source-span handoff for YAML metadata entries.
