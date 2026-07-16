# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T174015Z`

Base accepted HEAD: `ce5d135927da77922b2bb3c9aeff94d3c43440f3`

## Behavior Added

- Added native `MarkdownReader` support for Pandoc YAML metadata blocks whose
  opening `---` marker is omitted at the very beginning of the document.
- The bounded form still requires an explicit closing `...` or `---` marker,
  then reuses the existing native YAML parser for anchors, aliases, merge
  keys, ignored underscore fields, title/author inline metadata, and
  WordPress handoff output.
- Refactored explicit and omitted-opening YAML blocks through the same isolated
  parser state reset so anchors, diagnostics, and tag handles do not leak
  between metadata blocks.

## Source Truth

Pandoc's `yaml_metadata_block` extension allows the opening metadata marker to
be omitted when the YAML metadata block occurs at the beginning of the
document. This slice ports only that document-start boundary for native PHP
metadata handoff.

No local hydrated Pandoc upstream checkout was present under the worktree.
No Pandoc binary, Cabal build, Haskell runner, or external YAML parser was
executed.

## Verification

- Current-base rework notes:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2959 assertions, 0 failures`.
- Red-first after adding the focused omitted-opening test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2960 assertions, 1 failures`.
  - Failure: the beginning-of-document YAML packet without the opening marker
    produced `meta = null`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2974 assertions, 0 failures`.
  - Delta: `+15` focused assertions and `+1` focused PASS case.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownReader.php`
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.
- JSON metadata validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
- `git diff --check -- lanes/pandoc`
  - Result: no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1475 -> 1476`.
- Lane status `phpPass`: `1021 -> 1022`.
- Added `mappedYamlMetadataImplicitOpeningCases: 1`.
- Added `yamlMetadataImplicitOpeningAssertions: 15`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses existing AST and
`WordPressBlockWriter` handoff paths. It does not invoke Pandoc, Cabal,
Haskell test binaries, external YAML libraries or parsers, Word, LibreOffice,
`zip`, `unzip`, external template engines, TeX/PDF engines, browser renderers,
roff, Typst, MathJax, KaTeX, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted YAML explicit-opening metadata placement,
metadata blocks outside the opening position, fenced-code exclusion,
JSON-object metadata, ordinary flow-map metadata, multiline flow collection
balancing, flow comments, flow quoted scalars, verbatim flow tag scanner
handling, block-style nested sequence metadata, compact sequence maps,
anchors, valid aliases, alias diagnostics, ordinary merge keys,
merge-sequence precedence, explicit scalar/core tags, explicit integer base
tags, non-specific tags, explicit set tags, ordered `!!omap` / `!!pairs`
metadata handoff, timestamp/binary tags, comments outside flow collections,
scalar block-scalar chomping, quoted scalar folding, empty scalar null
semantics, sequence block-scalar metadata, explicit mapping-key parsing,
explicit sequence/map keys, explicit key/value entries inside flow maps,
explicit key-only entries inside flow maps, explicit mapping keys inside
sequence items, plain spaced mapping-key parsing, plain colon-bearing flow
keys, ambiguous top-level field-name diagnostics, or plain multiline scalar
continuation folding. It owns only omitted-opening YAML metadata blocks at the
beginning of a document.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep full multi-document YAML stream handling, writer-side YAML emission,
path-aware tag provenance, quoted ambiguous top-level field policy, and full
upstream runner dependency planning as separate bounded slices.
