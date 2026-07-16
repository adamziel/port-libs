# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T041321Z`

Base accepted HEAD: `276d957d132bcfe8fdf40c27a05f3d73bc978742`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset so quoted
  multiline scalars fold consistently even when they appear inside flow
  sequences and flow maps.
- Covers double-quoted and single-quoted flow values, commas and colons inside
  quoted text, escaped double-quoted URI continuations, Unicode escapes,
  sequence-of-map reference metadata, and explicit numeric tags inside nested
  flow date-parts.
- Updated the WordPress YAML metadata handoff smoke so reviewer metadata with
  quoted flow-map notes, owners, labels, and source URIs survives into import
  review metadata.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats metadata blocks as YAML before
converting values into document metadata. This slice ports only the bounded YAML
quoted-scalar folding behavior needed by native front-matter flow collections.
Source-truth reference remains the Pandoc User's Guide
`yaml_metadata_block` contract recorded by earlier YAML notes:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- No current-base rework note was present:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2568 assertions, 0 failures`.
- Direct pre-edit behavior check:
  `php -r 'require "tools/bootstrap.php"; $doc=(new PortLibs\Pandoc\MarkdownReader())->read("---\nlabels: [\n  \"Reviewer,\n    One\",\n  '\''Editor: Two'\''\n]\nreview: {note: \"Line one\n  line two\", single: '\''Owner\n  Desk'\''}\n...\n\nBody.\n"); var_export($doc->attr("meta")); echo "\n";'`
  - Result before edit: quoted flow scalars retained embedded newlines and
    indentation instead of folding to spaces.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2583 assertions, 0 failures`.
  - Delta: `+15` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6826 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - Result: `606`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1084 -> 1085`.
- Current worktree `phpPass`: verified as `606` PASS lines with 0 failures.
- `mappedYamlMetadataFlowQuotedMultilineCases`: `0 -> 1`.
- `yamlMetadataFlowQuotedMultilineAssertions`: `0 -> 15`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries, Word, LibreOffice, `zip`, `unzip`, external
template engines, TeX/PDF engines, browser renderers, roff, Typst, MathJax,
KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, fenced-code
exclusion, JSON-object metadata, single-line flow-map metadata, block-style
nested sequence metadata, anchors, aliases, merge keys, explicit tags, comments,
block-scalar mapping values, double-quoted escape decoding, multiline
standalone quoted scalar folding, merge-sequence precedence, empty scalar null
semantics, sequence block-scalar metadata, or multiline flow collection
balancing. It owns only quoted multiline scalar folding inside flow sequences
and flow maps.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode, or PDF
engine handoff support.

## Follow-Up

Keep explicit `?` mapping keys, timestamp/binary/set tag families, complex flow
comments, multi-document YAML streams, schema validation, writer-side YAML
emission, and full upstream runner dependency planning as separate bounded
slices.
