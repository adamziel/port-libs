# Pandoc YAML Metadata Core Current Base 20260605T232837Z

## Behavior Added

- `MarkdownReader` now parses a YAML metadata block whose entire document is a balanced top-level flow mapping, for example `{title: Flow document **Packet**, authors: [Flow Reviewer]}`.
- The parsed metadata reuses the existing inline YAML scalar, nested flow map, flow sequence, explicit tag, citation/reference, and title-inline normalization paths.
- Source-quoted top-level flow keys keep the existing quoted-field provenance, so boolean-looking or number-looking names such as `"yes"` and `? "15"` remain string metadata and do not trigger ambiguous-field diagnostics.
- `wordpress-yaml-metadata-handoff.php --self-test` now includes a separate flow-style metadata block with nested review/reference data and quoted top-level flow keys.

## Source Truth Boundary

Pandoc YAML metadata blocks accept YAML mappings as document metadata before the Markdown body is parsed. This slice implements the bounded native PHP support-library behavior needed for that contract: a whole flow mapping document in a metadata block is metadata, not body text or a skipped block. It does not attempt full YAML schema parity, multi-document stream handling, writer-side YAML emission, or path-aware diagnostics.

## Non-Overlap

This does not repeat prior YAML slices for block placement, JSON object metadata documents, nested flow maps inside block mappings, multiline flow values, comments, quoted scalars, anchors/aliases, merge keys, explicit tags, `!!set`, `!!omap`/`!!pairs`, block scalars, explicit sequence keys, flow explicit key-only null values, or source-quoted ambiguous block-style top-level fields. The owned behavior is only a top-level YAML flow mapping document inside a metadata block.

## Red-First Evidence

- Baseline before this slice: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 3006 assertions, 0 failures`.
- Direct pre-edit probe returned `meta = NULL` and no YAML diagnostics for the top-level flow mapping document.
- Red-first after adding the focused test: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` failed with `1 test files, 3007 assertions, 1 failures` because `meta.title` was `NULL`.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php && php -l lanes/pandoc/tests/MarkdownReaderTest.php && php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php` passed with no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 3022 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test` passed with `yaml metadata handoff self-test ok`.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'` passed for both JSON files.
- `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `lane-status.json` `phpPass`: `1111 -> 1112`.
- `UPSTREAM_TEST_MANIFEST.json` mapped native checks: `1563 -> 1564`.
- Added manifest inventory keys:
  - `mappedYamlMetadataTopLevelFlowDocumentCases: 1`
  - `yamlMetadataTopLevelFlowDocumentAssertions: 16`

## Dependency Closure

No new support component is needed. The slice reuses native PHP `MarkdownReader`, Pandoc AST metadata attributes, title inline parsing, and `WordPressBlockWriter` handoff paths. No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML parser, external template engine, online sanitizer, online service, or live provider test was executed.

## Follow-Up

Keep multi-document YAML streams, writer-side YAML emission, full YAML schema validation, path-aware YAML diagnostics, and full upstream Pandoc runner parity as separate bounded slices.
