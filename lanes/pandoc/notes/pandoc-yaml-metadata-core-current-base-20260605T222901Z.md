# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T222901Z`

Base accepted HEAD: `b443ae30800217bc383a5cb0858e1e28348f48a0`

## Behavior Added

- Tightened native `MarkdownReader` YAML metadata handling for literal and
  folded block scalars.
- A candidate YAML metadata block is now rejected when the first nonblank
  scalar content line is not indented relative to the `|` / `>` scalar header.
- The rejected source remains in the Markdown body and WordPress handoff output
  for reviewer visibility instead of being silently promoted into document
  metadata.
- Existing valid indented, explicit-indent, chomped, folded, and sequence
  block-scalar metadata paths continue to pass.

## Source Truth

Pandoc's `yaml_metadata_block` documentation states that the literal block
after `|` must be indented relative to the line containing `|`; if it is not,
the YAML is invalid and Pandoc does not interpret it as metadata. The bounded
native PHP behavior here ports only that block-scalar indentation gate.

Reference checked during this slice:
`https://pandoc.org/demo/example1.html#extension-yaml_metadata_block`.

No local hydrated Pandoc upstream checkout was present under this worktree or
the upstream cache. No Pandoc binary, Cabal build, Haskell runner, or external
YAML parser was executed.

## Verification

- Current-base rework notes:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null`
  - Result: no current Pandoc rework note was present.
- Red-first after adding the focused invalid block-scalar test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2996 assertions, 1 failures`.
  - Failure: invalid unindented block-scalar metadata was accepted and exposed
    as document `meta.abstract`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3006 assertions, 0 failures`.
  - Delta: `+11` focused assertions and `+1` focused PASS case.
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

- Manifest mapped native checks: `1546 -> 1547`.
- Lane status `phpPass`: `1094 -> 1095`.
- Added `mappedYamlMetadataInvalidBlockScalarCases: 1`.
- Added `yamlMetadataInvalidBlockScalarAssertions: 11`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress YAML handoff example. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries or parsers, Word, LibreOffice, `zip`,
`unzip`, external template engines, TeX/PDF engines, browser renderers, roff,
Typst, MathJax, KaTeX, online sanitizers, online services, or live provider
tests.

## Non-Overlap

This patch does not repeat accepted YAML block placement, omitted opening
markers, fenced-code exclusion, JSON-object metadata parsing, ordinary flow-map
metadata, multiline flow collection balancing, flow comments, flow quoted
scalars, verbatim flow tag scanner handling, block-style nested sequence
metadata, compact sequence maps, anchors, valid aliases, alias diagnostics,
ordinary merge keys, merge-sequence precedence, explicit scalar/core tags,
explicit integer base tags, non-specific tags, explicit set tags, ordered
`!!omap` / `!!pairs` metadata handoff, timestamp/binary tags, comments outside
flow collections, scalar block-scalar chomping, quoted scalar folding, empty
scalar null semantics, sequence block-scalar metadata, explicit mapping-key
parsing, explicit sequence/map keys, explicit key/value entries inside flow
maps, explicit key-only entries inside flow maps, explicit mapping keys inside
sequence items, plain spaced mapping-key parsing, plain colon-bearing flow
keys, ambiguous top-level field-name diagnostics, quoted ambiguous top-level
field-name preservation, plain multiline scalar continuation folding, or
folded block-scalar more-indented line preservation. It owns only invalid
literal/folded block-scalar indentation rejection for YAML metadata blocks.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep multi-document YAML stream handling, writer-side YAML emission, full YAML
schema validation, and path-aware diagnostics as separate bounded slices.
