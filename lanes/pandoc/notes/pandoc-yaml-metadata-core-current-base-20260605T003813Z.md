# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T003813Z`

Base accepted HEAD: `547037c192ab015b7b147821804623f6ff376004`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to fold multi-line
  double-quoted scalars.
- Single line breaks inside unfinished double-quoted metadata scalars fold to a
  space, while an escaped line break suppresses both the break and indentation.
- Applies to root metadata fields, nested map values, block sequence scalars,
  and sequence-of-map reference titles before metadata title/author Markdown
  inline parsing runs.
- Updated the WordPress YAML metadata handoff smoke so folded multiline source
  titles and escaped continuation URIs survive reviewer import metadata.

## Source Truth

Pandoc's `yaml_metadata_block` contract treats metadata blocks as YAML and
interprets string scalars as Markdown. This slice ports the bounded YAML
double-quoted multiline scalar behavior needed for native front-matter metadata
handoff. Source-truth reference remains the Pandoc User's Guide
`yaml_metadata_block` contract recorded by earlier YAML notes:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2461 assertions, 0 failures`
- Red-first check after adding the multiline scalar expectation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: failed on the expected folded title value with `1 test files,
    2462 assertions, 1 failures`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2472 assertions, 0 failures`
  - Delta: `+11` focused assertions and `+1` focused PASS line.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4747 assertions, 0 failures`

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: 474 -> 475.
- Manifest mapped native checks: 946 -> 947.
- `mappedYamlMetadataMultilineDoubleQuotedCases`: 0 -> 1.
- `yamlMetadataMultilineDoubleQuotedAssertions`: 0 -> 11.

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
anchors, aliases, merge keys, explicit tags, comments, block-scalar chomping,
or single-line double-quoted escape decoding. It owns only bounded multi-line
double-quoted YAML scalar folding and escaped line-continuation handling in
metadata values. It also does not touch accepted Markdown/HTML reader/writer
behavior, CSL/BibTeX, DOCX/ODT/EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate,
table geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
or PDF engine handoff support.

## Follow-Up

Keep writer-side YAML emission, full YAML schema resolution, custom tag
semantics, complex flow comments, multi-document YAML streams, and full
upstream runner dependency planning as separate bounded slices.
