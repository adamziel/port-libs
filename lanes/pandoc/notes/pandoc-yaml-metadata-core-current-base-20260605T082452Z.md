# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T082452Z`

Base accepted HEAD: `d36e1e98e24a92bc490dde83eb92cd3f4021c21c`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to normalize
  explicit YAML `? key` entries inside flow maps.
- Covers flow-map explicit sequence keys such as `? [source, uri]`, map-valued
  keys such as `? {owner: desk, ticket: 7}`, quoted scalar keys, and nested
  flow-map metadata inside reference packets.
- Updated the WordPress YAML metadata handoff smoke so source-audit packets can
  preserve these flow explicit keys without external YAML libraries.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats metadata blocks as YAML before
converting values into document metadata. This slice ports only the bounded
explicit-key behavior inside YAML flow maps needed by native front-matter
metadata handoff. The source-truth contract remains the Pandoc User's Guide
`yaml_metadata_block` behavior recorded by earlier YAML lane notes.

## Verification

- No current-base rework note was present:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2678 assertions, 0 failures`.
- Direct pre-edit behavior check:
  `php -r 'require "tools/bootstrap.php"; $doc=(new PortLibs\Pandoc\MarkdownReader())->read("---\nflow-explicit-review: {? [source, uri]: https://example.test/import#flow, ? {owner: desk, ticket: 7}: queued, ? \"source:key\": metadata value}\n...\n\n# Body\n"); var_export($doc->attr("meta")); echo "\n";'`
  - Result before edit: explicit flow keys were exposed as literal keys:
    `? [source, uri]`, `? {owner: desk, ticket: 7}`, and `? "source:key"`.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2693 assertions, 0 failures`.
  - Delta: `+15` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1233 -> 1234`.
- Lane status `phpPass`: `774 -> 775` for the new focused PASS case.
- `mappedYamlMetadataFlowExplicitKeyCases`: `0 -> 1`.
- `yamlMetadataFlowExplicitKeyAssertions`: `0 -> 15`.

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
sequence-key parsing, or block-form explicit map-key parsing. It owns only
bounded explicit key normalization inside YAML flow maps.

## Follow-Up

Keep multi-document YAML streams, writer-side YAML emission, full YAML schema
validation, custom application tag semantics, and full upstream runner
dependency planning as separate bounded slices.
