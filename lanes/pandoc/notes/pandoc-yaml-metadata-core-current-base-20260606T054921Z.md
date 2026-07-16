# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260606T054921Z`
Base accepted HEAD: `3020a8be3f79d45948a22ca25ae18ab50e5f7727`

## Behavior Added

- `MarkdownReader` now recognizes YAML document marker lines after stripping
  trailing YAML comments.
- Covered marker positions are explicit metadata opening markers, directive
  document-start markers after `%YAML` / `%TAG` preambles, ordinary metadata
  close markers, and omitted-opening metadata close markers.
- The WordPress YAML metadata handoff example now exercises commented marker
  lines on its main front-matter packet.

## Source Truth Boundary

Pandoc's `yaml_metadata_block` extension treats front matter as YAML metadata,
and YAML document marker lines may carry trailing comments after separation.
This bounded slice ports only that marker-recognition behavior for native PHP
metadata handoff. It does not claim true multi-document stream handling,
writer-side directive/comment emission, full YAML schema validation, or
upstream runner parity.

No local hydrated Pandoc upstream checkout was present under the worktree or
upstream cache for this slice. No Pandoc binary, Cabal build/test command,
Haskell runner, external YAML parser, online sanitizer, online service, or
live provider test was executed.

## Evidence

- Rework-note check:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null`
  - Result: no current Pandoc rework note was present.
- Baseline before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3117 assertions, 0 failures`.
- Red-first direct probe before implementation:
  `php -r 'require "tools/bootstrap.php"; $doc=(new PortLibs\Pandoc\MarkdownReader())->read("--- # source front matter\ntitle: Commented marker **Packet**\n... # metadata ends\n\n# Body\n"); var_export($doc->attr("meta"));'`
  - Result: metadata was `NULL`; the commented marker source stayed in the
    Markdown body.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3131 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+14` assertions.

Additional required checks are recorded in the final handoff: example smoke,
PHP lint for changed PHP files, JSON metadata validation, and
`git diff --check -- lanes/pandoc`.

Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1218 -> 1219`.
- `UPSTREAM_TEST_MANIFEST.json` mapped native checks: `1662 -> 1663`.
- Added manifest inventory keys:
  - `mappedYamlMetadataCommentedDocumentMarkerCases: 1`
  - `yamlMetadataCommentedDocumentMarkerAssertions: 14`

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset in `MarkdownReader` and reuses existing AST metadata,
`MarkdownWriter`, `WordPressBlockWriter`, and the WordPress YAML metadata
handoff example. It does not require Pandoc, Cabal, Haskell runners, external
YAML libraries, external template engines, browser renderers, online
sanitizers, online services, or live provider tests.

## Non-Overlap

This patch does not repeat accepted YAML block placement, omitted opening
metadata without comments, fenced-code exclusion, JSON-object metadata,
top-level flow mapping documents, flow-map parsing, multiline flow
collections, flow comments inside collections, quoted flow scalars, block
sequences, compact sequence maps, anchors, aliases, alias diagnostics,
merge keys, merge-sequence precedence, explicit scalar/core tags, explicit
integer base or sexagesimal parsing, non-specific tags, custom tag provenance
paths, tag directives without commented document-start markers, `!!set`,
`!!omap`/`!!pairs`, timestamp/binary tags, block-scalar handling, explicit
mapping key normalization, flow explicit null keys, plain multiline scalars,
ambiguous top-level field diagnostics, quoted ambiguous field preservation, or
writer metadata emission. It owns only YAML document marker lines with trailing
comments in metadata-block recognition.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep true multi-document YAML streams, writer-side directive/comment emission,
richer source-location diagnostics, full YAML schema validation, and full
upstream runner dependency planning as separate bounded slices.
