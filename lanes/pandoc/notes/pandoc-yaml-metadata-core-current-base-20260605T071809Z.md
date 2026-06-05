# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T071809Z`

Base accepted HEAD: `8bd450f4c90e33ec348f2657c7aae831ed20a4df`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to normalize
  explicit sequence keys such as `? [source, uri]` into stable bracketed
  metadata keys like `[source, uri]`.
- Covers same-line explicit sequence keys, block-form explicit sequence keys,
  nested mapping keys, sequence keys inside explicit `!!set` values, and
  sequence keys inside reference-entry maps.
- Updated the WordPress YAML metadata handoff smoke so source-audit packets can
  preserve sequence-keyed reviewer metadata without external YAML libraries.
- Map-valued complex YAML keys remain intentionally out of scope for a later
  bounded slice.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats metadata blocks as YAML before
converting values into document metadata. This slice ports only the bounded
explicit sequence-key behavior needed by native front-matter metadata handoff.
The source-truth contract remains the Pandoc User's Guide
`yaml_metadata_block` behavior recorded by earlier YAML lane notes.

## Verification

- No current-base rework note was present:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2651 assertions, 0 failures`.
- Direct pre-edit behavior check:
  `php -r 'require "tools/bootstrap.php"; $doc=(new PortLibs\Pandoc\MarkdownReader())->read("---\n? [source, uri]\n: https://example.test/import#seq\nreview:\n  ? [owner, desk]\n  : Import Desk\n...\n\n# Body\n"); var_export($doc->attr("meta")); echo "\n";'`
  - Result before edit: top-level `[source, uri]` was absent and nested
    `review` metadata was empty.
- Red-first check after adding the focused expectation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: failed as expected with `1 test files, 2655 assertions,
    1 failures`; `[source, uri]` was missing from metadata.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2665 assertions, 0 failures`.
  - Delta: `+14` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1198 -> 1199`.
- Lane status `phpPass`: `739 -> 740` for the new focused PASS case.
- `mappedYamlMetadataExplicitSequenceKeyCases`: `0 -> 1`.
- `yamlMetadataExplicitSequenceKeyAssertions`: `0 -> 14`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries or parsers, Word, LibreOffice, `zip`,
`unzip`, external template engines, TeX/PDF engines, browser renderers, roff,
Typst, MathJax, KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, fenced-code
exclusion, JSON-object metadata, flow-map metadata, multiline flow collection
balancing, flow comments, flow quoted scalars, block-style nested sequence
metadata, compact sequence maps, anchors, aliases, ordinary merge keys,
merge-sequence precedence, explicit scalar tags, explicit set tags,
timestamp/binary tags, comments outside flow collections, block-scalar mapping
values, quoted scalar folding, empty scalar null semantics, sequence
block-scalar metadata, or scalar explicit mapping-key parsing. It owns only
bounded explicit sequence-key handling for YAML metadata.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep map-valued complex YAML keys, multi-document YAML streams, writer-side
YAML emission, full YAML schema validation, and full upstream runner dependency
planning as separate bounded slices.
