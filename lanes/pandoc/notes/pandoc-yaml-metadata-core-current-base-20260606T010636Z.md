# Pandoc YAML Metadata Core Current Base 20260606T010636Z

Slice: `pandoc-yaml-metadata-core-current-base-20260606T010636Z`
Base: `77c1544413102a40f5eff045cbae96edd32c5b21`

## Behavior Added

- `MarkdownReader` now records JSON-pointer-style `path` strings on YAML alias
  diagnostics.
- Paths are carried through block mappings, block sequences, flow maps, and
  flow sequences, for example `/references/0/metadata/source`.
- Parsed fallback metadata values remain unchanged, so unresolved aliases still
  surface as source-visible `*alias_name` values for WordPress review queues.
- `wordpress-yaml-metadata-handoff.php --self-test` now verifies alias
  diagnostic paths in the existing YAML handoff packet.

## Source Truth Boundary

Pandoc's `yaml_metadata_block` contract treats each YAML metadata block as an
independent YAML document and exposes metadata fields to document processing.
This bounded PHP support-library slice does not claim full YAML parser parity;
it improves native review diagnostics for alias failures without invoking
Pandoc or an external YAML parser.

Primary source checked: Pandoc User's Guide `yaml_metadata_block` section,
which states that metadata blocks are YAML objects, may occur multiple times,
and are handled independently:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors detected.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors detected.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3033 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1129 -> 1130`.
- `UPSTREAM_TEST_MANIFEST.json` mapped native checks: `1581 -> 1582`.
- Added manifest inventory keys:
  - `mappedYamlMetadataAliasPathCases: 1`
  - `yamlMetadataAliasPathAssertions: 11`

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST metadata
and WordPress YAML handoff example. No Pandoc, Cabal solver/build/test command,
Haskell runner, external YAML parser, browser renderer, online sanitizer,
online service, or live provider test was executed.

## Non-Overlap

This patch does not repeat accepted YAML block placement, omitted opening
markers, fenced-code exclusion, JSON-object metadata, top-level flow mapping
documents, flow-map parsing, multiline flow collections, flow comments, quoted
flow scalars, block sequences, compact sequence maps, anchors, valid aliases,
alias fallback values, ordinary merge keys, merge-sequence precedence, explicit
scalar/core tags, non-specific tags, custom tag provenance, `!!set`,
`!!omap`/`!!pairs`, timestamp/binary tags, block-scalar handling, explicit
mapping keys, flow explicit null keys, plain multiline scalars, ambiguous
top-level field diagnostics, or quoted ambiguous field preservation. It owns
only path provenance on YAML alias diagnostics.

## Follow-Up

Keep multi-document YAML stream handling, writer-side YAML emission, full YAML
schema validation, and broader path-aware diagnostics beyond alias diagnostics
as separate bounded slices.
