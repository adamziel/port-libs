# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260606T132441Z`
Base accepted HEAD: `d6a0bec32b014e5f17cc2c14a29189f43b11e877`

## Behavior Added

- `MarkdownReader` now applies YAML's default block-scalar `clip` chomping to
  front-matter metadata.
- Literal `|` and folded `>` block scalars without an explicit `+` or `-`
  chomping indicator now retain exactly one final newline after trailing empty
  scalar lines are stripped.
- Covered positions include top-level metadata fields and sequence item
  metadata values.
- Existing explicit strip and keep cases remain intact: `|-`, `>-`, `|+`, and
  explicit indentation handling still use their existing accepted behavior.
- `wordpress-yaml-metadata-handoff.php --self-test` now verifies default
  literal and folded clip metadata in the native WordPress review packet.

## Source Truth Boundary

Pandoc's `yaml_metadata_block` contract treats front matter as YAML before
converting it into document metadata. YAML block scalars with no chomping
indicator use the default `clip` behavior, keeping one final line break while
stripping extra trailing empty lines. This slice ports only that bounded scalar
handoff behavior.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
YAML parser, browser renderer, online sanitizer, online service, live provider
test, or live-service provider test was executed.

## Evidence

- Rework-note check:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print | sort | tail -20`
  - Result: no current Pandoc rework note was present.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3165 assertions, 0 failures`.
- Red-first probe before implementation:
  `php -r 'require "tools/bootstrap.php"; $doc=(new PortLibs\Pandoc\MarkdownReader())->read("---\nsummary: |\n  Line one\n  Line two\n...\n\n# Body\n"); var_export($doc->attr("meta")); echo "\n";'`
  - Result: `summary` was `Line one\nLine two` without the default clipped
    final newline.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3175 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+10` assertions.
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

- `lane-status.json` `phpPass`: `1334 -> 1335`.
- `UPSTREAM_TEST_MANIFEST.json` mapped native checks: `1748 -> 1749`.
- Added manifest inventory keys:
  - `mappedYamlMetadataDefaultBlockScalarClipCases: 1`
  - `yamlMetadataDefaultBlockScalarClipAssertions: 10`

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses existing AST metadata,
`MarkdownWriter`, `WordPressBlockWriter`, and the WordPress YAML metadata
handoff example. It does not require Pandoc, Cabal, Haskell runners, external
YAML libraries, external template engines, browser renderers, online
sanitizers, online services, live provider tests, or live-service provider
tests.

## Non-Overlap

This patch does not repeat accepted YAML block placement, omitted opening
markers, document marker comments, fenced-code exclusion, JSON-object
metadata, top-level flow mapping documents, flow-map parsing, multiline flow
collections, flow comments, quoted flow scalars, block sequences, compact
sequence maps, anchors, aliases, alias diagnostics, alias path diagnostics,
merge keys, merge-sequence precedence, explicit scalar/core tags, explicit
integer base or sexagesimal parsing, special float coercion, non-specific
tags, custom tag provenance paths, tag directives, `!!set`, `!!omap`/`!!pairs`,
timestamp/binary tags, explicit strip/keep block-scalar chomping, invalid
block-scalar indentation rejection, explicit mapping key normalization,
explicit sequence keys, flow explicit null keys, duplicate-key diagnostics,
plain multiline scalars, ambiguous top-level field diagnostics, quoted
ambiguous field preservation, or writer metadata emission. It owns only default
block-scalar clip chomping for YAML metadata values.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep true multi-document YAML streams, writer-side directive/comment emission,
richer source-location diagnostics, full YAML schema validation, richer Pandoc
`MetaValue` fidelity, and full upstream runner dependency planning as separate
bounded slices.
