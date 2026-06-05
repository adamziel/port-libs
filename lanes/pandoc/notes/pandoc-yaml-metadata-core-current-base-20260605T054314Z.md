# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T054314Z`

Base accepted HEAD: `e0ea57bf3e21d0fc119155f3a11338ab3897fe53`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to ignore comments
  inside bounded multiline flow sequences and flow maps.
- Comments are stripped only outside quoted scalars and only when `#` starts a
  YAML comment after whitespace, so URI fragments such as
  `https://example.test/export#front` and quoted text such as
  `"Keep # quoted hash"` remain intact.
- Updated the WordPress YAML metadata handoff smoke so reviewer labels and
  import notes with flow comments are exercised on the audit path.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats metadata blocks as YAML before
converting values into document metadata. This slice ports only the bounded YAML
comment behavior needed by multiline flow-collection front-matter metadata.
Source-truth reference remains the Pandoc User's Guide
`yaml_metadata_block` contract recorded by earlier YAML notes:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- No current-base rework note was present:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2610 assertions, 0 failures`.
- Direct pre-edit behavior check:
  `php <<'PHP' ... MarkdownReader()->read("---\nkeywords: [\n  migration, # source label\n  wordpress\n]\nreview: {\n  status: queued, # reviewer state\n  labels: [front-matter, # imported source\n    wordpress]\n}\n...\n\n# Body\n") ... PHP`
  - Result before edit: `keywords` became `['migration', null]`, and `review`
    contained a bogus `"# reviewer state\n  labels"` key.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2622 assertions, 0 failures`.
  - Delta: `+12` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1140 -> 1141`.
- Lane status `phpPass`: `660 -> 661` for the new focused PASS case.
- `mappedYamlMetadataFlowCommentCases`: `0 -> 1`.
- `yamlMetadataFlowCommentAssertions`: `0 -> 12`.

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
balancing, block-style nested sequence metadata, compact sequence maps, anchors,
aliases, ordinary merge keys, explicit scalar tags, comments outside flow
collections, block-scalar mapping values, quoted scalar folding,
merge-sequence precedence, empty scalar null semantics, sequence block-scalar
metadata, or explicit mapping-key parsing. It owns only bounded comment
stripping inside multiline YAML flow collections.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep timestamp/binary/set tag families, complex non-scalar YAML mapping keys,
multi-document YAML streams, writer-side YAML emission, full YAML schema
validation, and full upstream runner dependency planning as separate bounded
slices.
