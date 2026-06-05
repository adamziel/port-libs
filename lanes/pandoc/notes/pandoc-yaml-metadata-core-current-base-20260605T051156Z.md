# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T051156Z`

Base accepted HEAD: `0050f4e914c4e6207953a8c269ec4ee0dec173ba`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to parse compact
  block-sequence mapping items such as `- id: compact-ref` when the item has no
  additional child lines.
- Supports compact sequence maps with quoted keys, inline merge keys, and
  URL-valued map values.
- Preserves colon-bearing scalar sequence values such as `https://...:443/...`
  and `mailto:...` as strings rather than misclassifying them as maps.
- Updated the WordPress YAML metadata handoff smoke so compact reviewer items
  and scalar URL lists are exercised on the import-audit path.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats metadata blocks as YAML before
converting values into document metadata. This slice ports only the bounded
block-sequence compact mapping behavior needed by native front-matter metadata
handoff. Source-truth reference remains the Pandoc User's Guide
`yaml_metadata_block` contract recorded by earlier YAML notes:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- No current-base rework note was present:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2597 assertions, 0 failures`.
- Direct pre-edit behavior check:
  `php <<'PHP' ... MarkdownReader()->read("---\nreview-items:\n  - label: Migration review\n  - \"source:key\": metadata value\nreferences:\n  - id: single-ref\n...\n\nBody.\n") ... PHP`
  - Result before edit: compact sequence maps were exposed as literal strings:
    `label: Migration review`, `"source:key": metadata value`, and
    `id: single-ref`.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2610 assertions, 0 failures`.
  - Delta: `+13` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1118 -> 1119`.
- Lane status `phpPass`: `643 -> 644` for the new focused PASS case.
- `mappedYamlMetadataCompactSequenceMapCases`: `0 -> 1`.
- `yamlMetadataCompactSequenceMapAssertions`: `0 -> 13`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries, Word, LibreOffice, `zip`, `unzip`, external
template engines, TeX/PDF engines, browser renderers, roff, Typst, MathJax,
KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, fenced-code
exclusion, JSON-object metadata, flow-map metadata, multiline flow collection
balancing, block-style nested sequence metadata with child lines, anchors,
aliases, ordinary merge keys, explicit scalar tags, comments, block-scalar
mapping values, quoted scalar folding, merge-sequence precedence, empty scalar
null semantics, sequence block-scalar metadata, or explicit mapping-key
parsing. It owns only compact one-line mapping entries inside YAML block
sequences and the scalar URL guard needed by that parser path.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep timestamp/binary/set tag families, complex flow comments, explicit
sequence-key forms, multi-document YAML streams, writer-side YAML emission,
full YAML schema validation, and full upstream runner dependency planning as
separate bounded slices.
