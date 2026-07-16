# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T024127Z`

Base accepted HEAD: `5ed0aaa4d7c1c974c2a65ad595af51f1907f6f43`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset so empty plain
  scalars parse as `null` instead of empty strings.
- Applies to root metadata fields, nested map values, comment-only scalar
  values, flow-map values, and bare block sequence items.
- Preserves explicit quoted empty strings from `""` and `''` as empty strings,
  so WordPress review metadata can distinguish intentionally blank text from
  absent/null front-matter values.
- Updated the WordPress YAML metadata handoff smoke to exercise empty scalar
  nulls alongside quoted empty strings on the user-visible import path.

## Source Truth

Pandoc's `yaml_metadata_block` contract treats metadata blocks as YAML before
Pandoc converts metadata values into document meta. This slice ports only the
bounded YAML empty plain-scalar behavior needed by native front-matter metadata
handoff. Source-truth reference remains the Pandoc User's Guide
`yaml_metadata_block` contract recorded by earlier YAML notes:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- No current-base rework note was present:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2514 assertions, 0 failures`.
- Direct pre-edit behavior check:
  `php -r 'require "tools/bootstrap.php"; $doc=(new PortLibs\Pandoc\MarkdownReader())->read("---\nempty:\nitems:\n  -\n  - value\n...\n\nBody.\n"); var_export($doc->attr("meta")); echo "\n";'`
  - Result: `empty` and the bare sequence item were exposed as empty strings.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2531 assertions, 0 failures`.
  - Delta: `+17` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: 548 -> 549.
- Manifest mapped native checks: 1026 -> 1027.
- `mappedYamlMetadataEmptyScalarNullCases`: 0 -> 1.
- `yamlMetadataEmptyScalarNullAssertions`: 0 -> 17.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries, Word, LibreOffice, `zip`, `unzip`, external
template engines, TeX/PDF engines, browser renderers, roff, Typst, MathJax,
KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted YAML placement, fenced-code exclusion,
JSON-object metadata, flow-map metadata, block-style nested sequence metadata,
anchors, aliases, merge keys, explicit tags, comments, block-scalar mapping
values, double-quoted escapes, multiline double-quoted scalar folding,
multiline single-quoted scalar folding, merge-sequence precedence, or sequence
block-scalar metadata. It owns only empty plain-scalar null semantics and
quoted-empty-string preservation in metadata. It also does not touch accepted
Markdown/HTML reader/writer behavior, CSL/BibTeX, DOCX/ODT/EPUB3, legacy
DOC/CFB, ZIP/OPC, doctemplate, table geometry, Math/TeX, archive compression,
XML/HTML5 DOM, charset/Unicode, or PDF engine handoff support.

## Follow-Up

Keep writer-side YAML emission, full YAML schema resolution, custom tag
semantics, complex flow comments, multi-document YAML streams, and full
upstream runner dependency planning as separate bounded slices.
