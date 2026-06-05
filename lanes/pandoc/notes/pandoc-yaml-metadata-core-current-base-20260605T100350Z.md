# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T100350Z`

Base accepted HEAD: `9f1f2346a7dba9e945aa136b3a22616c0fc812cc`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset so folded block
  scalars (`>`) preserve line breaks around more-indented lines.
- Covers source-review metadata such as folded reviewer logs that contain
  nested list bullets, exported source config snippets, or HTML-like audit
  lines.
- Updated the WordPress YAML metadata handoff smoke so the source-review log
  keeps nested reviewer lines available for import audit tooling.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats metadata blocks as YAML before
converting values into document metadata. YAML folded block scalars fold
ordinary adjacent lines, but preserve line breaks around more-indented content.
This slice ports only that bounded folded-scalar line preservation behavior for
native metadata handoff.

## Verification

- No current-base rework note was present:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  - Result: no matching file.
- Red-first direct behavior probe:
  `php -r 'require "tools/bootstrap.php"; $doc=(new PortLibs\Pandoc\MarkdownReader())->read("---\nsummary: >-\n  Review steps:\n    - preserve front matter\n    - import blocks\n  Done.\nreview:\n  note: >-\n    Queue log:\n      source: wp-export.xml\n      status: pending\n    Ready.\n...\n\n# Body\n"); var_export($doc->attr("meta")); echo "\n";'`
  - Result before edit: `summary` flattened to
    `Review steps: - preserve front matter - import blocks Done.` and
    `review.note` flattened to
    `Queue log: source: wp-export.xml status: pending Ready.`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result after implementation: `1 test files, 2742 assertions, 0 failures`.
  - Delta: `+13` focused assertions and `+1` focused PASS case from the
    previous accepted-base reader run at `2729` assertions.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1280 -> 1281`.
- Lane status `phpPass`: `820 -> 821`.
- Added `mappedYamlMetadataFoldedMoreIndentedBlockScalarCases: 1`.
- Added `yamlMetadataFoldedMoreIndentedBlockScalarAssertions: 13`.

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
timestamp/binary tags, comments outside flow collections, scalar block-scalar
chomping, quoted scalar folding, empty scalar null semantics, sequence
block-scalar metadata, scalar explicit mapping-key parsing, explicit
sequence-key parsing in mappings, block-form explicit map-key parsing,
explicit keys inside flow maps, explicit mapping keys inside sequence items, or
plain spaced mapping-key parsing. It owns only folded block scalars with
more-indented lines inside YAML metadata.

## Follow-Up

Keep multi-document YAML streams, writer-side YAML emission, full YAML schema
validation, custom application tag semantics, alias-cycle diagnostics, and full
upstream runner dependency planning as separate bounded slices.
