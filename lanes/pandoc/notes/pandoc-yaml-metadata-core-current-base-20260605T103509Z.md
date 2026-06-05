# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T103509Z`

Base accepted HEAD: `05e3db7e0ccb37bb704fa63dae3d9c01b791d492`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to consume bare
  non-specific YAML tag directives (`! value`) before scalar parsing.
- Covers scalar values, flow collections, anchors, aliases, sequence items, and
  the WordPress YAML metadata handoff example.
- Prevents bare `!` tags from leaking into reviewer metadata text such as
  `! "Import Desk"` or `! front-matter`.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats metadata blocks as YAML before
converting values into document metadata. YAML permits the bare non-specific
tag handle `!` before a node; for native metadata handoff this should behave as
a directive marker, not as literal scalar content.

`yaml_metadata_block` contract recorded by earlier YAML notes:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- No current-base rework note was present:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - Result: no matching file.
- Red-first direct behavior probe:
  `php -r 'require "tools/bootstrap.php"; $doc=(new \PortLibs\Pandoc\MarkdownReader())->read("---\nreview:\n  owner: ! \"Import Desk\"\n  status: ! queued\nlabels:\n  - ! front-matter\n  - ! \"WordPress #import\"\n...\n\n# Body\n"); var_export($doc->attr("meta")); echo "\n";'`
  - Result before edit: `review.owner` was `! "Import Desk"`,
    `review.status` was `! queued`, and labels were prefixed with `!`.
  - Result after edit: `review.owner` is `Import Desk`, `review.status` is
    `queued`, and labels are `front-matter` and `WordPress #import`.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result after implementation: `1 test files, 2763 assertions, 0 failures`.
  - Delta: `+21` focused assertions and `+1` focused PASS case from the
    previous accepted-base reader run at `2742` assertions.
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

- Manifest mapped native checks: `1298 -> 1299`.
- Lane status `phpPass`: `839 -> 840`.
- Added `mappedYamlMetadataNonSpecificTagCases: 1`.
- Added `yamlMetadataNonSpecificTagAssertions: 21`.

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
explicit keys inside flow maps, explicit mapping keys inside sequence items,
plain spaced mapping-key parsing, or folded block scalars with more-indented
lines. It owns only the bare non-specific `!` tag directive in YAML metadata
values.

## Follow-Up

Keep alias-cycle diagnostics, custom application tag semantics beyond
pass-through, multi-document YAML streams, writer-side YAML emission, full YAML
schema validation, and full upstream runner dependency planning as separate
bounded slices.
