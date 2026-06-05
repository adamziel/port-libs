# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T061734Z`
Base: `c6ac5df0374dd36163d5c0e76bc3d26f21646bd2`

## Behavior

- Extended the native `MarkdownReader` YAML metadata subset to preserve
  explicit `!!set` metadata values.
- Flow sets, block sets, set values inside flow maps, and set values inside
  block sequences now become PHP maps whose scalar members are keys with
  `null` values.
- This keeps source-review label sets available to WordPress import tooling
  instead of silently exposing them as empty maps.
- Updated the WordPress YAML metadata handoff smoke so flow, block, and
  sequence set tags are exercised on the import-audit path.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats metadata blocks as YAML before
converting values into document metadata. This slice ports only the bounded YAML
set-tag behavior needed by native front-matter metadata handoff. YAML's set tag
represents set members as mapping keys with null values, so the PHP handoff
keeps that map shape. Source-truth reference remains the Pandoc User's Guide
`yaml_metadata_block` contract recorded by earlier YAML notes:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- No current-base rework note was present:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2622 assertions, 0 failures`.
- Direct pre-edit behavior check:
  `php -r 'require "tools/bootstrap.php"; $doc=(new PortLibs\Pandoc\MarkdownReader())->read("---\nreview-labels: !!set {front-matter, wordpress, \"source:key\"}\nblock-labels: !!set\n  ? migration\n  ? qa\nreview: {required-labels: !!set {import, approved}}\n...\n\n# Body\n"); var_export($doc->attr("meta")); echo "\n";'`
  - Result before edit: explicit `!!set` values were exposed as empty maps.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2636 assertions, 0 failures`.
  - Delta: `+14` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1161 -> 1162`.
- Lane status `phpPass`: `683 -> 684` for the new focused PASS case.
- `mappedYamlMetadataExplicitSetTagCases`: `0 -> 1`.
- `yamlMetadataExplicitSetTagAssertions`: `0 -> 14`.

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
balancing, flow comments, flow quoted scalars, block-style nested sequence
metadata, compact sequence maps, anchors, aliases, ordinary merge keys,
merge-sequence precedence, explicit scalar tags, comments outside flow
collections, block-scalar mapping values, quoted scalar folding, empty scalar
null semantics, sequence block-scalar metadata, or explicit mapping-key
parsing. It owns only bounded explicit `!!set` tag handling for YAML metadata.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep timestamp and binary tag families, complex non-scalar YAML mapping keys,
multi-document YAML streams, writer-side YAML emission, full YAML schema
validation, and full upstream runner dependency planning as separate bounded
slices.
