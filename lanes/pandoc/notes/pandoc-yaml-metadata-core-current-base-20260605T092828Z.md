# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T092828Z`

Base accepted HEAD: `58d9511dd8bc830eb17ad085a2d55060773fb172`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to parse block
  sequence items that start with explicit mapping keys.
- Covers list items such as `- ? [source, uri]` followed by `: value`,
  map-valued explicit keys such as `- ? {owner: desk, ticket: 7}`, quoted
  scalar explicit keys such as `- ? "source:key"`, and additional fields on
  the same sequence-item map.
- Updated the WordPress YAML metadata handoff smoke so source-audit review
  lists can preserve structured sequence-item keys without external YAML
  libraries.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats metadata blocks as YAML before
converting values into document metadata. YAML explicit mapping keys are valid
inside sequence items, so a review list item keyed by a structured source id
must parse as a map rather than as a literal `? key` scalar. This slice ports
only that bounded sequence-item explicit-key behavior.

## Verification

- No current-base rework note was present:
  `ls /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  - Result: no matching file.
- Red-first direct behavior probe:
  `php -r 'require "tools/bootstrap.php"; $doc=(new PortLibs\Pandoc\MarkdownReader())->read("---\nreview-items:\n  - ? [source, uri]\n    : https://example.test/import#sequence-item\n    status: queued\n  - ? {owner: desk, ticket: 7}\n    : approved\n    labels:\n      - migration\n...\n\n# Body\n"); var_export($doc->attr("meta")); echo "\n";'`
  - Result before edit: `review-items` contained literal `? [source, uri]`
    and `? {owner: desk, ticket: 7}` scalar strings, dropping the values and
    follow-up fields.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result after implementation: `1 test files, 2729 assertions, 0 failures`.
  - Delta: `+19` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- Syntax and metadata checks are recorded in the final lane handoff:
  `php -l` for changed PHP files, JSON decode for lane metadata, and
  `git diff --check -- lanes/pandoc`.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1266 -> 1267`.
- Lane status `phpPass`: `806 -> 807`.
- Added `mappedYamlMetadataSequenceExplicitKeyCases: 1`.
- Added `yamlMetadataSequenceExplicitKeyAssertions: 19`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries or parsers, Word, LibreOffice, `zip`,
`unzip`, external template engines, TeX/PDF engines, browser renderers, roff,
Typst, MathJax, KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, fenced-code
exclusion, JSON-object metadata, ordinary flow-map metadata, multiline flow
collection balancing, flow comments, flow quoted scalars, block-style nested
sequence metadata, compact sequence maps, anchors, aliases, ordinary merge
keys, merge-sequence precedence, explicit scalar tags, explicit set tags,
timestamp/binary tags, comments outside flow collections, block-scalar mapping
values, quoted scalar folding, empty scalar null semantics, sequence
block-scalar metadata, scalar explicit mapping-key parsing, explicit
sequence-key parsing in mappings, block-form explicit map-key parsing, explicit
keys inside flow maps, or plain spaced mapping-key parsing. It owns only
bounded explicit mapping keys that appear at the start of YAML sequence items.

## Follow-Up

Keep multi-document YAML streams, writer-side YAML emission, full YAML schema
validation, custom application tag semantics, alias-cycle diagnostics, and
full upstream runner dependency planning as separate bounded slices.
