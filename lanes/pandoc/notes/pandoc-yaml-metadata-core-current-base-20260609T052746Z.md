# pandoc-yaml-metadata-core-current-base-20260609T052746Z

Base accepted HEAD: `003cd766d197b04fb23d7e77772dd1e8b0ccc6a3`

## Behavior

This slice preserves safe trailing reviewer comments attached to YAML block-scalar headers when Markdown metadata is read and rewritten. `MarkdownReader` now records trailing comment provenance for sequence-item block scalar headers, matching the existing mapping-value handling. `MarkdownWriter` now reattaches safe trailing comments to emitted block scalar headers for scalar value, mapping value, and list item positions.

The metadata values remain unchanged after round-trip parsing. The preservation path continues to use the existing safe comment filter, so unsafe comment text is not emitted by the writer.

## Evidence

Red-first check before the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 4717 assertions, 1 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 4735 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
yaml metadata handoff self-test ok
```

Status delta:

- `lane-status.json` `phpPass`: `2375` -> `2376`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2769` -> `2770`
- `mappedYamlMetadataWriterBlockScalarHeaderCommentCases`: `1`
- `yamlMetadataWriterBlockScalarHeaderCommentAssertions`: `19`

## Non-Overlap

This does not repeat existing YAML document marker comment handling, scalar trailing comment writer coverage, explicit key provenance, invalid escape diagnostics, directives/tags/anchors/aliases/merge handling, or plain block-scalar emission. The new behavior is limited to comments on block-scalar headers and the missing sequence-item provenance path.

## Dependency Closure

No new support component is needed. This reuses the native PHP `MarkdownReader` YAML metadata parser, `MarkdownWriter` YAML metadata emitter, focused `MarkdownReaderTest.php` coverage, and the existing WordPress YAML metadata handoff example. Full upstream Pandoc runner parity remains a separate upstream-runner dependency task requiring a hydrated Pandoc checkout and Haskell test executables.

## Exclusions

No Pandoc, Cabal solver/build/test command, Haskell runner, tar, gzip, lz4, zip/unzip, ZipArchive, Word, LibreOffice, TeX/PDF engine, Typst, browser renderer, external converter, external validator, online service, live provider test, or live-service provider test was executed. Root harness was not run for this isolated micro-slice.

## Follow-Up

Suggested non-overlapping follow-up: downstream WordPress metadata review display, additional source-span handoff, or writer diagnostics for malformed scalar provenance.
