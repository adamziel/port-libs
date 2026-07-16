# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T124523Z`

Base accepted HEAD: `470fdd2506765a05e3ef8e7c8bfb9b771027c4b6`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata flow scanners so YAML
  verbatim tag directives such as `!<tag:example.test,2026:label>` are treated
  as atomic while scanning flow comments, balance, item commas, and flow-map
  mapping colons.
- Preserves tagged flow-map values, flow-sequence entries, flow `!!set` keys,
  tagged explicit flow-map keys, and nested reference metadata values without
  leaking raw `!<...>` tag text into WordPress review packets.
- Updated the WordPress YAML metadata handoff smoke so user-visible import
  review metadata exercises verbatim flow tags with comma/colon-bearing tag
  URIs.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats front matter as YAML before
metadata is converted into the document. YAML verbatim tags use `!<...>` and
the URI payload may contain punctuation that is meaningful to flow collection
scanners, including commas and colons. This slice ports only the bounded
verbatim-tag flow scanning behavior needed by native metadata handoff. It does
not claim full YAML schema parity or custom application tag semantics beyond
preserving the tagged value.

The local hydrated upstream Pandoc checkout was not present in this worktree or
the `.upstream-cache/pandoc` path, matching the lane's current upstream-runner
dependency blocker. No Pandoc binary, Cabal build, Haskell runner, or external
YAML parser was executed.

## Verification

- No current-base rework note was present:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print | sort | tail -20`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2830 assertions, 0 failures`.
- Red-first after adding the focused verbatim-tag case:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2833 assertions, 1 failures`.
  - Failure: tagged flow value leaked as `!<tag:example.test` because the flow
    scanner split inside the verbatim tag URI.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2845 assertions, 0 failures`.
  - Delta: `+15` focused assertions and `+1` focused PASS case.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- JSON metadata validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1358 -> 1359`.
- Lane status `phpPass`: `900 -> 901`.
- Added `mappedYamlMetadataVerbatimFlowTagCases: 1`.
- Added `yamlMetadataVerbatimFlowTagAssertions: 15`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries or parsers, Word, LibreOffice, `zip`,
`unzip`, external template engines, TeX/PDF engines, browser renderers, roff,
Typst, MathJax, KaTeX, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, fenced-code
exclusion, JSON-object metadata, ordinary flow-map metadata, multiline flow
collection balancing, flow comments outside verbatim tag directives, flow
quoted scalars, block-style nested sequence metadata, compact sequence maps,
anchors, aliases, ordinary merge keys, merge-sequence precedence, explicit
scalar tags, explicit integer base tags, non-specific tags, explicit set tags,
ordered `!!omap` / `!!pairs` metadata handoff, timestamp/binary tags, comments
outside flow collections, scalar block-scalar chomping, quoted scalar folding,
empty scalar null semantics, sequence block-scalar metadata, scalar explicit
mapping-key parsing, explicit sequence-key parsing in mappings, block-form
explicit map-key parsing, explicit key/value entries inside flow maps,
explicit key-only entries inside flow maps, explicit mapping keys inside
sequence items, plain spaced mapping-key parsing, or folded block scalars with
more-indented lines. It owns only verbatim `!<...>` tag handling while scanning
YAML flow metadata collections.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep YAML alias-cycle diagnostics, custom application tag provenance,
multi-document YAML stream handling, writer-side YAML emission, full YAML
schema validation, and full upstream runner dependency planning as separate
bounded slices.
