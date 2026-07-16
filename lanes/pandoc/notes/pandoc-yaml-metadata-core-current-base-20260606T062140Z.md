# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260606T062140Z`
Base accepted HEAD: `9ad62bbe4e7fbecd3bd98f43017c2a6dc597e8c8`

## Behavior Added

- `MarkdownReader` now records duplicate-key diagnostics for repeated direct
  YAML mapping keys in both block mappings and flow mappings.
- Diagnostics carry `type: yaml-duplicate-key`, `reason: duplicate-key`, the
  repeated `field`, and the existing JSON-pointer-style metadata `path`.
- Final direct metadata values still win, so WordPress review packets keep the
  imported final values while exposing duplicate-key provenance for reviewers.
- Merge-key materialization does not create false duplicate warnings; this
  slice only diagnoses repeated explicit keys in the parsed direct map.
- The WordPress YAML metadata handoff example now includes a duplicate-key
  review packet and self-test coverage for final values plus diagnostic paths.

## Source Truth Boundary

Pandoc's Markdown reader delegates `yaml_metadata_block` front matter through
the YAML metadata conversion path, and the upstream metadata reader treats
duplicate YAML keys as warnings while continuing metadata conversion. This
bounded slice ports that warning shape into the native PHP metadata handoff by
preserving final values and surfacing duplicate-key paths.

Source references:

- `src/Text/Pandoc/Readers/Markdown.hs` at
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/Markdown.hs
- `src/Text/Pandoc/Readers/Metadata.hs` at
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/Metadata.hs

No local hydrated Pandoc checkout was used for execution. No Pandoc binary,
Cabal build/test command, Haskell runner, external YAML parser, online
sanitizer, online service, or live provider test was executed.

## Evidence

- Rework-note check:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - Result: no current Pandoc rework note was present.
- Baseline before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3131 assertions, 0 failures`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3148 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+17` assertions.

Additional required checks are recorded in the final handoff: example smoke,
PHP lint for changed PHP files, JSON metadata validation, and
`git diff --check -- lanes/pandoc`.

Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1228 -> 1229`.
- `UPSTREAM_TEST_MANIFEST.json` mapped native checks: `1671 -> 1672`.
- Added manifest inventory keys:
  - `mappedYamlMetadataDuplicateKeyDiagnosticCases: 1`
  - `yamlMetadataDuplicateKeyDiagnosticAssertions: 17`

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset in `MarkdownReader` and reuses the existing AST metadata,
YAML diagnostics path stack, `MarkdownWriter`, `WordPressBlockWriter`, and the
WordPress YAML metadata handoff example. It does not require Pandoc, Cabal,
Haskell runners, external YAML libraries, external template engines, browser
renderers, online sanitizers, online services, or live provider tests.

## Non-Overlap

This patch does not repeat accepted YAML block placement, document marker
comments, omitted opening metadata, fenced-code exclusion, JSON-object
metadata, top-level flow mapping documents, flow-map parsing, multiline flow
collections, flow comments inside collections, quoted flow scalars, block
sequences, compact sequence maps, anchors, aliases, alias diagnostics, alias
path diagnostics, merge keys, merge-sequence precedence, explicit
scalar/core tags, explicit integer base or sexagesimal parsing, non-specific
tags, custom tag provenance paths, tag directives, `!!set`, `!!omap`/`!!pairs`,
timestamp/binary tags, block-scalar handling, explicit mapping key
normalization, flow explicit null keys, plain multiline scalars, ambiguous
top-level field diagnostics, quoted ambiguous field preservation, or writer
metadata emission. It owns only duplicate direct mapping-key diagnostics and
their metadata paths.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep full warning parity, true multi-document YAML streams, writer-side
directive/comment emission, richer source-location diagnostics, full YAML
schema validation, and full upstream runner dependency planning as separate
bounded slices.
