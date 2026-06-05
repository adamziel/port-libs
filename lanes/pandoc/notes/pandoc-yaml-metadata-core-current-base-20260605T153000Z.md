# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T153000Z`

Base accepted HEAD: `a8b37a6c78def5f375d3b587281c6b6133d50b82`

## Behavior Added

- Added bounded custom YAML tag provenance to the native `MarkdownReader`
  front-matter parser.
- Local shorthand tags such as `!wp-reviewer` and verbatim tags such as
  `!<tag:example.test,2026:reviewer>` now remain visible as document-level
  `yamlMetadataTagProvenance` audit entries.
- Parsed metadata values remain tag-stripped, matching the existing metadata
  handoff behavior, while core YAML tags such as `!!str` and bare
  non-specific `!` directives are excluded from custom provenance.
- Updated the WordPress YAML metadata handoff smoke so import review packets
  expose custom tag provenance without external YAML libraries.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats front matter as YAML before it
is converted into document metadata. This slice keeps the lane's bounded native
subset focused on review-handoff fidelity: application-specific YAML tags are
not promoted into typed values, but they should stay visible for WordPress
import/audit tooling when source exports use local or verbatim tag directives.

No Pandoc binary, Cabal build, Haskell runner, or external YAML parser was
executed.

## Verification

- Rework notes:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null | tail -20`
  - Result: no current pandoc rework note was present.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2893 assertions, 0 failures`.
- Baseline example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2908 assertions, 0 failures`.
  - Delta: `+15` focused assertions and `+1` focused PASS case.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownReader.php`
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.
- Whitespace:
  `git diff --check -- lanes/pandoc`
  - Result: no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1426 -> 1427`.
- Lane status `phpPass`: `971 -> 972`.
- Added `mappedYamlMetadataCustomTagProvenanceCases: 1`.
- Added `yamlMetadataCustomTagProvenanceAssertions: 15`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress YAML handoff example. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries or parsers, Word, LibreOffice, `zip`,
`unzip`, external template engines, TeX/PDF engines, browser renderers, roff,
Typst, MathJax, KaTeX, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, fenced-code
exclusion, JSON-object metadata, ordinary flow-map metadata, multiline flow
collection balancing, flow comments, flow quoted scalars, verbatim flow tag
scanner handling, block-style nested sequence metadata, compact sequence maps,
anchors, valid aliases, alias diagnostics, ordinary merge keys,
merge-sequence precedence, explicit scalar/core tags, explicit integer base
tags, non-specific tags, explicit set tags, ordered `!!omap` / `!!pairs`
metadata handoff, timestamp/binary tags, comments outside flow collections,
scalar block-scalar chomping, quoted scalar folding, empty scalar null
semantics, sequence block-scalar metadata, explicit mapping-key parsing, or
explicit sequence/map keys. It owns only document-level provenance for custom
local and verbatim YAML tags.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep multi-document YAML stream handling, writer-side YAML emission, full YAML
schema validation, path-aware tag provenance, and full upstream runner
dependency planning as separate bounded slices.
