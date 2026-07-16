# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T085408Z`

Base accepted HEAD: `3e68afa3f934177d51659afb21146e8d69445f45`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to parse plain
  scalar mapping keys containing spaces.
- Covers top-level keys such as `source label`, nested review-map keys such as
  `source owner`, sequence item maps, compact sequence item maps, and flow-map
  keys.
- The plain-key splitter only treats a colon as the mapping delimiter when it
  is followed by whitespace or end-of-line, preserving URL-like scalars such as
  `https://example.test/export:443/path`.
- Updated the WordPress YAML metadata handoff smoke so source-audit packets can
  preserve unquoted reviewer metadata keys with spaces without external YAML
  libraries.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats metadata blocks as YAML before
converting values into document metadata. YAML permits plain scalar mapping
keys containing spaces when the mapping colon is followed by whitespace. This
slice ports only that bounded key-splitting behavior for native metadata
handoff.

## Verification

- No current-base rework note was present:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  - Result: no matching file.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2693 assertions, 0 failures`.
- Direct pre-edit behavior check:
  `php -r 'require "tools/bootstrap.php"; $doc=(new PortLibs\Pandoc\MarkdownReader())->read("---\nsource label: Migration review\nreview:\n  source owner: Import Desk\nreferences:\n  - id: plain-key\n    source title: Packet title\n...\n\n# Body\n"); var_export($doc->attr("meta")); echo "\n";'`
  - Result before edit: `source label` and `source title` were absent, while
    `review` collapsed to the scalar `source owner: Import Desk`.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2710 assertions, 0 failures`.
  - Delta: `+17` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1248 -> 1249`.
- Lane status `phpPass`: `789 -> 790`.
- Added `mappedYamlMetadataPlainKeyCases: 1`.
- Added `yamlMetadataPlainKeyAssertions: 17`.

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
sequence-key parsing, block-form explicit map-key parsing, or explicit keys
inside flow maps. It owns only bounded plain spaced mapping-key parsing for
YAML metadata.

## Follow-Up

Keep multi-document YAML streams, writer-side YAML emission, full YAML schema
validation, custom application tag semantics, alias-cycle diagnostics, and
full upstream runner dependency planning as separate bounded slices.
