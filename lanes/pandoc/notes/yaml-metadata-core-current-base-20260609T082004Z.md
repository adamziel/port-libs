# YAML Metadata Core Current-Base Handoff - 2026-06-09T08:20:04Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-yaml-metadata-core-current-base-20260609T082004Z`
- Base accepted HEAD: `e8462716baed1244ed5b9f195429af80b17d479b`
- Scope: bounded native PHP YAML/front-matter metadata behavior under `lanes/pandoc/**`.

## Behavior

`MarkdownReader` now records `yamlMetadataMergeProvenance` for YAML merge keys while preserving the existing resolved metadata semantics and merge diagnostics. Each record is sanitized out of plain `meta` and exposed as a document attr with:

- `path` and `sourceLine`
- `valueKind`
- `mergeSourceCount`
- `invalidMergeSourceCount`
- `policy` as `applied`, `partial`, or `invalid`

The focused coverage exercises block merge sequences, direct map merges, flow-map merges, explicit `!!merge` keys, and partially invalid merge sequences.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` failed only on the new missing `yamlMetadataMergeProvenance` count (`expected 4`, actual `0`), with the rest of the file passing.
- `php -l lanes/pandoc/src/MarkdownReader.php` => no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php` => no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php` => no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'` => both JSON files decode.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` => `1 test files, 5031 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test` => `yaml metadata handoff self-test ok`.

## Status Delta

- `lane-status.json` `phpPass`: `2524` -> `2525`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2895` -> `2896`.
- Added inventory keys: `mappedYamlMetadataMergeProvenanceCases` and `yamlMetadataMergeProvenanceAssertions`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP YAML/front-matter parser, anchor/alias resolver, merge-key diagnostics, metadata attr sanitizer, review summary, Markdown writer provenance filtering, and WordPress block writer. The local upstream Pandoc cache was unavailable in this isolated worktree, and no Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external converter, browser renderer, online service, live provider, or TeX/PDF engine was executed.

## Non-Overlap

This slice does not repeat the accepted YAML behaviors for YAML versions, directives, comments, block scalars, typed scalar provenance, anchor/alias provenance, explicit merge-tag resolution, invalid merge diagnostics, collection provenance, or YAML writer round-trips. It adds the missing merge-source provenance channel needed for review handoff.

Root harness: not run - isolated micro-slice.
