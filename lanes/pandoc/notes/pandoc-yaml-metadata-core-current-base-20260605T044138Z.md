# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T044138Z`

Base accepted HEAD: `65530467850a2f179b5e97d0f0d14d580fe10713`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to parse bounded
  explicit mapping keys using YAML `? key` / `: value` pairs.
- Supports scalar inline explicit keys, block-form scalar keys, quoted keys
  containing colons, explicit `<<` merge keys, anchors, aliases, flow-map
  values, and nested explicit-key maps inside front-matter metadata.
- Updated the WordPress YAML metadata handoff smoke so reviewer metadata with
  explicit keys remains visible on the import-audit path.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats metadata blocks as YAML before
converting values into document metadata. This slice ports only the bounded YAML
explicit mapping-key behavior needed by native front-matter metadata handoff.
Source-truth reference remains the Pandoc User's Guide
`yaml_metadata_block` contract recorded by earlier YAML notes:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- No current-base rework note was present:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2583 assertions, 0 failures`.
- Direct pre-edit behavior check:
  `php -r 'require "tools/bootstrap.php"; $doc=(new PortLibs\Pandoc\MarkdownReader())->read("---\n? title\n: Explicit key **Packet**\n? review\n:\n  status: queued\n? \"source:key\"\n: metadata value\n...\n\n# Explicit key YAML body\n"); var_export($doc->attr("meta")); echo "\n";'`
  - Result before edit: `NULL`.
- Intermediate post-edit focused check:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result before the nested-map fix: `1 test files, 2589 assertions, 1 failures`.
  - Failure: an indented explicit-key map was treated as a scalar string until
    explicit `?` lines were recognized as mapping starts.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2597 assertions, 0 failures`.
  - Delta: `+14` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7114 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - Result from captured output: `625`.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1102 -> 1103`.
- Lane status `phpPass`: `628 -> 629` for the new focused PASS case. The local
  full-lane PASS-line count is `625`; the pre-existing status counter was ahead
  of the local PASS-line count, so this slice preserves counter direction and
  records exact local evidence above.
- `mappedYamlMetadataExplicitMappingKeyCases`: `0 -> 1`.
- `yamlMetadataExplicitMappingKeyAssertions`: `0 -> 14`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries, Word, LibreOffice, `zip`, `unzip`, external
template engines, TeX/PDF engines, browser renderers, roff, Typst, MathJax,
KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, fenced-code
exclusion, JSON-object metadata, implicit flow-map metadata, block-style nested
sequence metadata, anchors, aliases, ordinary merge keys, explicit scalar tags,
comments, block-scalar mapping values, double-quoted escape decoding,
multiline quoted scalar folding, merge-sequence precedence, empty scalar null
semantics, sequence block-scalar metadata, multiline flow collection parsing,
or flow quoted scalar folding. It owns only bounded explicit mapping-key
parsing for scalar metadata keys.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode, or PDF
engine handoff support.

## Follow-Up

Keep complex non-scalar YAML mapping keys, timestamp/binary/set tag families,
complex flow comments, multi-document YAML streams, writer-side YAML emission,
full YAML schema validation, and full upstream runner dependency planning as
separate bounded slices.
