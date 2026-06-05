# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T110828Z`

Base accepted HEAD: `0147d7cd16fbde22482892e48538f86512fde76c`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to coerce explicit
  `!!int` base-prefixed scalar forms.
- Handles hexadecimal (`0x2A`), binary (`0b101010`), modern octal (`0o52`),
  legacy octal (`052`), signs, and underscores in explicit integer metadata.
- Invalid base-prefixed values such as `!!int 0xZZ` remain source text instead
  of being coerced to a misleading value.
- Updated the WordPress YAML metadata handoff smoke so review packets preserve
  integer-base metadata used for revisions, bit flags, and import batch ids.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats metadata blocks as YAML before
converting values into document metadata. This slice ports only the bounded
explicit integer base behavior needed by native front-matter metadata handoff.
The source-truth contract remains the Pandoc User's Guide
`yaml_metadata_block` behavior recorded by earlier YAML lane notes.

## Verification

- No current-base rework note was present:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2763 assertions, 0 failures`.
- Red-first check after adding integer-base expectations:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: failed as expected with `1 test files, 2767 assertions,
    1 failures`; `!!int 0x2A` was still exposed as raw string `0x2A`.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result after implementation: `1 test files, 2779 assertions,
    0 failures`.
  - Delta: `+16` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1313 -> 1314`.
- Lane status `phpPass`: `855 -> 856`.
- Added `mappedYamlMetadataExplicitIntegerBaseCases: 1`.
- Added `yamlMetadataExplicitIntegerBaseAssertions: 16`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries or parsers, Word, LibreOffice, `zip`,
`unzip`, external template engines, TeX/PDF engines, browser renderers, roff,
Typst, MathJax, KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, fenced-code
exclusion, JSON-object metadata, ordinary flow-map metadata, multiline flow
collection balancing, flow comments, flow quoted scalars, block-style nested
sequence metadata, compact sequence maps, anchors, aliases, ordinary merge
keys, merge-sequence precedence, existing decimal explicit scalar tags,
explicit set tags, timestamp/binary tags, comments outside flow collections,
scalar block-scalar chomping, quoted scalar folding, empty scalar null
semantics, sequence block-scalar metadata, scalar explicit mapping-key parsing,
explicit sequence-key parsing in mappings, block-form explicit map-key parsing,
explicit keys inside flow maps, explicit mapping keys inside sequence items,
plain spaced mapping-key parsing, folded block scalars with more-indented
lines, or bare non-specific tag pass-through. It owns only explicit `!!int`
base-prefixed scalar coercion in YAML metadata.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep alias-cycle diagnostics, custom application tag provenance,
multi-document YAML stream handling, writer-side YAML emission, full YAML
schema validation, and full upstream runner dependency planning as separate
bounded slices.
