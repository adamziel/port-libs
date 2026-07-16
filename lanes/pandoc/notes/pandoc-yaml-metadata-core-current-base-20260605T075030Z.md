# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T075030Z`

Base accepted HEAD: `0550e27964d2b56b5f6dd54410b622bcac00b555`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to normalize
  explicit map-valued YAML keys into stable brace-form metadata keys such as
  `{source: uri, type: review}`.
- Covers same-line flow-map keys, block-form map keys, nested map keys,
  map-valued keys inside explicit `!!set` values, and map-valued keys inside
  reference-entry maps.
- Updated the WordPress YAML metadata handoff smoke so source-audit packets can
  preserve map-keyed reviewer metadata without external YAML libraries.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats metadata blocks as YAML before
converting values into document metadata. This slice ports only the bounded
explicit map-key behavior needed by native front-matter metadata handoff. The
source-truth contract remains the Pandoc User's Guide `yaml_metadata_block`
behavior recorded by earlier YAML lane notes.

## Verification

- No current-base rework note was present:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print | sort`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2665 assertions, 0 failures`.
- Red-first check after adding the focused expectation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: failed as expected with `1 test files, 2669 assertions,
    1 failures`; top-level `{source: uri, type: review}` metadata was absent.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2678 assertions, 0 failures`.
  - Delta: `+13` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1215 -> 1216`.
- Lane status `phpPass`: `756 -> 757` for the new focused PASS case.
- `mappedYamlMetadataExplicitMapKeyCases`: `0 -> 1`.
- `yamlMetadataExplicitMapKeyAssertions`: `0 -> 13`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries or parsers, Word, LibreOffice, `zip`,
`unzip`, external template engines, TeX/PDF engines, browser renderers, roff,
Typst, MathJax, KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, fenced-code
exclusion, JSON-object metadata, flow-map metadata, multiline flow collection
balancing, flow comments, flow quoted scalars, block-style nested sequence
metadata, compact sequence maps, anchors, aliases, ordinary merge keys,
merge-sequence precedence, explicit scalar tags, explicit set tags,
timestamp/binary tags, comments outside flow collections, block-scalar mapping
values, quoted scalar folding, empty scalar null semantics, sequence
block-scalar metadata, scalar explicit mapping-key parsing, or explicit
sequence-key parsing. It owns only bounded explicit map-valued key handling for
YAML metadata.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep multi-document YAML streams, writer-side YAML emission, full YAML schema
validation, and full upstream runner dependency planning as separate bounded
slices.
