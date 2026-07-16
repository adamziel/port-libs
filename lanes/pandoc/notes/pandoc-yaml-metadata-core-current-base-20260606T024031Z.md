# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260606T024031Z`
Base accepted HEAD: `ad9601f72ab7b5ca72b674992cdf319945bef6ec`

## Behavior Added

- `MarkdownReader` now retargets custom YAML tag provenance emitted while
  parsing explicit mapping keys to the normalized metadata key path.
- Covered positions are block explicit mapping keys, nested explicit mapping
  keys, flow explicit mapping keys, flow key-only entries, and YAML set keys.
- Metadata values stay unchanged: custom tags remain review provenance only and
  do not leak raw tag syntax into document metadata or WordPress handoff output.
- `wordpress-yaml-metadata-handoff.php --self-test` now verifies flow and block
  explicit-key tag provenance paths in the native review packet.

## Source Truth Boundary

Pandoc's `yaml_metadata_block` contract treats front matter as YAML metadata
before converting it into document metadata. This slice ports only bounded
review provenance for tags attached to YAML mapping keys; it does not claim full
YAML parser parity, multi-document stream handling, or upstream runner parity.

No local hydrated Pandoc checkout was present under the worktree or upstream
cache for this slice. No Pandoc binary, Cabal solver/build/test command,
Haskell runner, external YAML parser, online sanitizer, online service, or live
provider test was executed.

## Evidence

- Rework-note check:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - Result: no current Pandoc rework note was present.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3071 assertions, 0 failures`.
- Red-first after adding the focused explicit-key tag test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3080 assertions, 1 failures`.
  - Failure: key tags reported parent paths such as `/review` and
    `/flow-review` instead of normalized key paths such as
    `/review/[owner, desk]`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3084 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+13` assertions.
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

- `lane-status.json` `phpPass`: `1160 -> 1161`.
- `UPSTREAM_TEST_MANIFEST.json` mapped native checks: `1610 -> 1611`.
- Added manifest inventory keys:
  - `mappedYamlMetadataExplicitKeyTagPathCases: 1`
  - `yamlMetadataExplicitKeyTagPathAssertions: 13`

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset in `MarkdownReader` and reuses existing AST metadata,
`MarkdownWriter`, `WordPressBlockWriter`, and the WordPress YAML metadata
handoff example. No Pandoc, Cabal, Haskell runner, external YAML parser,
external template engine, browser renderer, online sanitizer, online service,
or live provider test was executed.

## Non-Overlap

This patch does not repeat accepted YAML block placement, omitted opening
markers, fenced-code exclusion, JSON-object metadata, top-level flow mapping
documents, flow-map parsing, multiline flow collections, flow comments, quoted
flow scalars, block sequences, compact sequence maps, anchors, aliases, alias
diagnostics, merge keys, merge-sequence precedence, explicit scalar/core tags,
non-specific tags, custom tag value provenance, custom tag value paths, tag
directives, `!!set` value parsing, `!!omap`/`!!pairs`, timestamp/binary tags,
block-scalar handling, explicit mapping key normalization, flow explicit null
keys, plain multiline scalars, ambiguous top-level field diagnostics, quoted
ambiguous field preservation, or writer metadata emission. It owns only custom
tag provenance paths for explicit mapping keys and set keys.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep multi-document YAML streams, richer Pandoc `MetaValue` fidelity,
non-initial metadata writer emission, full YAML schema validation, and full
upstream runner dependency planning as separate bounded slices.
