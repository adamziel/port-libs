# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260606T020912Z`
Base accepted HEAD: `4d33d47fb36955cc34140ee8701a095138710cb8`

## Behavior Added

- `MarkdownReader` now records JSON-pointer-style `path` fields on custom YAML
  tag provenance entries when the tag is parsed at a concrete metadata value.
- Covered value positions include block mappings, nested flow maps, flow
  sequence entries, nested reference metadata, and block sequence items.
- Metadata values are unchanged: raw custom tag syntax remains provenance only
  and is not leaked into `meta`.
- The WordPress YAML metadata handoff smoke now verifies reviewer-visible
  provenance paths on the existing native import packet.

## Source Truth Boundary

Pandoc's `yaml_metadata_block` contract treats metadata blocks as YAML objects
before converting fields into document metadata. This bounded PHP slice ports
only review/audit provenance for custom YAML tags in native metadata handling.
It does not claim full YAML parser parity, tag provenance for every possible
explicit-key tag position, or upstream runner parity.

No Pandoc binary, Cabal build/test command, Haskell runner, external YAML
parser, browser renderer, online sanitizer, online service, or live provider
test was executed.

## Evidence

- Rework-note check:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null`
  - Result: no current Pandoc rework note was present.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3057 assertions, 0 failures`.
- Red-first direct probe before implementation:
  custom `yamlMetadataTagProvenance` entries contained `tag`,
  `normalizedTag`, and `kind`, but no `path` fields.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3071 assertions, 0 failures`.
  - Delta: `+1` PASS case / `+14` assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownReader.php`
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.

Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1151 -> 1152`.
- `UPSTREAM_TEST_MANIFEST.json` mapped native checks: `1601 -> 1602`.
- Added manifest inventory keys:
  - `mappedYamlMetadataTagProvenancePathCases: 1`
  - `yamlMetadataTagProvenancePathAssertions: 14`

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset in `MarkdownReader` and reuses the existing AST metadata and
WordPress YAML metadata handoff example. It does not require Pandoc, Cabal,
Haskell runners, external YAML libraries, external template engines, online
sanitizers, online services, or live provider tests.

## Non-Overlap

This patch does not repeat accepted YAML block placement, omitted opening
markers, fenced-code exclusion, JSON-object metadata, top-level flow mapping
documents, flow-map parsing, multiline flow collections, flow comments, quoted
flow scalars, block sequences, compact sequence maps, anchors, aliases, alias
diagnostics, merge keys, merge-sequence precedence, explicit scalar/core tags,
non-specific tags, custom tag value preservation, tag directives, `!!set`,
`!!omap`/`!!pairs`, timestamp/binary tags, block-scalar handling, explicit
mapping keys, flow explicit null keys, plain multiline scalars, ambiguous
top-level field diagnostics, quoted ambiguous field preservation, or writer
metadata emission. It owns only metadata-value paths on custom YAML tag
provenance.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep multi-document YAML stream handling, richer Pandoc `MetaValue` fidelity,
tag provenance for explicit-key-only tag positions, non-initial metadata writer
emission, and full upstream runner dependency planning as separate bounded
slices.
