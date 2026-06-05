# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T034103Z`

Base accepted HEAD: `9b8cda1eda5add842959c80a999a025da28ae740`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to parse bounded
  multiline flow sequences and flow maps when a metadata value starts with `[` or
  `{` and closes on later continuation lines.
- Covers root metadata values, anchored multiline flow values, nested flow maps,
  multiline `date-parts`, sequence-item flow collections, and the WordPress YAML
  metadata handoff example.
- Preserves existing anchors, aliases, explicit scalar tags, merge-key behavior,
  empty scalar nulls, block scalars, and later metadata-block overrides.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats metadata blocks as YAML before
converting values into document metadata. This slice ports only the bounded
standard YAML flow-collection behavior needed by front-matter metadata handoff.
Source-truth reference remains the Pandoc User's Guide
`yaml_metadata_block` contract recorded by earlier YAML notes:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- No current-base rework note was present:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2551 assertions, 0 failures`.
- Direct pre-edit behavior check:
  `php -r 'require "tools/bootstrap.php"; $doc=(new PortLibs\Pandoc\MarkdownReader())->read("---\nkeywords: [\n  migration,\n  wordpress\n]\nreview: {\n  status: queued,\n  labels: [audit, import]\n}\n...\n\nBody.\n"); var_export($doc->attr("meta")); echo "\n";'`
  - Result before edit: `keywords` was exposed as literal `[` and `review` as
    literal `{`.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2568 assertions, 0 failures`.
  - Delta: `+17` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6536 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - Result: `584`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1062 -> 1063`.
- Verified full lane PASS lines: `584`.
- `mappedYamlMetadataMultilineFlowCases`: `0 -> 1`.
- `yamlMetadataMultilineFlowAssertions`: `0 -> 17`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test binaries,
external YAML libraries, Word, LibreOffice, `zip`, `unzip`, external template
engines, TeX/PDF engines, browser renderers, roff, Typst, MathJax, KaTeX, or
online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, fenced-code exclusion,
JSON-object metadata, single-line flow-map metadata, block-style nested sequence
metadata, anchors, aliases, merge keys, explicit scalar tags, comments,
block-scalar mapping values, double-quoted escapes, multiline quoted scalar
folding, merge-sequence precedence, empty scalar null semantics, or sequence
block-scalar metadata. It owns only bounded multiline flow sequence/map
collection parsing for metadata values split over continuation lines.

It also does not touch accepted Markdown/HTML reader/writer behavior, CSL/
BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table geometry,
Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode, or PDF engine
handoff support.

## Follow-Up

Keep full YAML schema validation, complex flow comments, explicit `?` mapping
keys, timestamp/binary/set tag families, multi-document YAML streams, and
writer-side YAML emission as separate bounded slices.
