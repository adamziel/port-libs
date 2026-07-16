# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T020905Z`

Base accepted HEAD: `bdf90b2b384d1961ee2dae7dd2d567daab766cb4`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to parse block
  scalar sequence items.
- Literal sequence entries such as `- |-` now preserve line breaks, while
  folded entries such as `- >-` fold content before metadata title/author
  inline parsing and WordPress handoff.
- Applies to scalar metadata lists, nested reviewer note lists, and
  sequence-of-map fields that already reuse the existing YAML mapping parser.
- Updated the WordPress YAML metadata handoff smoke so reviewer note list items
  using literal and folded block scalars survive import review metadata.

## Source Truth

Pandoc's `yaml_metadata_block` contract treats metadata blocks as YAML before
Pandoc converts metadata values into document meta. This slice ports only the
bounded YAML block-scalar sequence item behavior needed by native front-matter
metadata handoff. Source-truth reference remains the Pandoc User's Guide
`yaml_metadata_block` contract recorded by earlier YAML notes:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2502 assertions, 0 failures`
- Red-first check after adding the sequence block-scalar expectation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: failed as expected with `1 test files, 2505 assertions,
    1 failures`; a sequence item `|-` was exposed as literal metadata.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2514 assertions, 0 failures`
  - Delta: `+12` focused assertions and `+1` focused PASS line.
- `php -l lanes/pandoc/src/MarkdownReader.php && php -l lanes/pandoc/tests/MarkdownReaderTest.php && php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected in all three changed PHP files.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json metadata valid\n";'`
  - Result: `pandoc json metadata valid`
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: 527 -> 528.
- Manifest mapped native checks: 1002 -> 1003.
- `mappedYamlMetadataSequenceBlockScalarCases`: 0 -> 1.
- `yamlMetadataSequenceBlockScalarAssertions`: 0 -> 12.

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
multiline single-quoted scalar folding, or merge-sequence precedence. It owns
only bounded YAML block scalar values used as sequence items in metadata. It
also does not touch accepted Markdown/HTML reader/writer behavior, CSL/BibTeX,
DOCX/ODT/EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table geometry,
Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode, or PDF engine
handoff support.

## Follow-Up

Keep writer-side YAML emission, full YAML schema resolution, custom tag
semantics, complex flow comments, multi-document YAML streams, empty scalar
null semantics, and full upstream runner dependency planning as separate
bounded slices.
