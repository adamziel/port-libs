# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T142521Z`

Base accepted HEAD: `6c126186066ceb7460fca9cb3fcff42503b6c891`

## Behavior Added

- Extended the native `MarkdownReader` YAML flow-map scanner so unquoted
  plain keys containing colons stay intact when the mapping separator is the
  later colon followed by whitespace.
- Covers source/review metadata keys such as `source:key`, `dc:title`,
  `urn:source:id`, and `source:uri` inside top-level and nested YAML flow maps.
- Preserves quoted JSON-style flow keys with adjacent colons, such as
  `{"source:key":"json-compatible"}`, so YAML-as-JSON review packets keep
  working.
- Updated the WordPress YAML metadata handoff smoke so user-visible import
  review metadata preserves colon-bearing audit keys without external YAML
  libraries.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats front matter as YAML before it
is converted into document metadata. YAML plain scalars may contain colons when
the colon is not followed by separation whitespace, which keeps keys such as
`source:key` and `dc:title` distinct from the later `: value` mapping
separator. This slice ports only that bounded flow-mapping scanner behavior
needed by native metadata handoff and does not claim full YAML schema parity.

The local hydrated upstream Pandoc checkout was not present in this worktree or
the `.upstream-cache/pandoc` path, matching the lane's current upstream-runner
dependency blocker. No Pandoc binary, Cabal build, Haskell runner, or external
YAML parser was executed.

## Verification

- No current-base rework note was present:
  `ls /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  - Result: no matching file.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2845 assertions, 0 failures`.
- Baseline example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- Red-first behavior probe before edit:
  `php -r 'require "tools/bootstrap.php"; $doc=(new PortLibs\Pandoc\MarkdownReader())->read("---\nflow-colon-review: {source:key: metadata value, dc:title: Source Title, urn:source:id: packet-7, status: approved}\n...\n\n# Body\n"); var_export($doc->attr("meta")["flow-colon-review"] ?? null); echo "\n";'`
  - Result before edit: `source:key`, `dc:title`, and `urn:source:id` split
    at the first colon into `source`, `dc`, and `urn` keys.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2864 assertions, 0 failures`.
  - Delta: `+19` focused assertions and `+1` focused PASS case.
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
- `git diff --check -- lanes/pandoc`
  - Result: no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1399 -> 1400`.
- Lane status `phpPass`: `943 -> 944`.
- Added `mappedYamlMetadataFlowPlainColonKeyCases: 1`.
- Added `yamlMetadataFlowPlainColonKeyAssertions: 19`.

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
collection balancing, flow comments, flow quoted scalars, verbatim flow tag
scanner handling, block-style nested sequence metadata, compact sequence maps,
anchors, aliases, ordinary merge keys, merge-sequence precedence, explicit
scalar tags, explicit integer base tags, non-specific tags, explicit set tags,
ordered `!!omap` / `!!pairs` metadata handoff, timestamp/binary tags, comments
outside flow collections, scalar block-scalar chomping, quoted scalar folding,
empty scalar null semantics, sequence block-scalar metadata, scalar explicit
mapping-key parsing, explicit sequence-key parsing in mappings, block-form
explicit map-key parsing, explicit key/value entries inside flow maps,
explicit key-only entries inside flow maps, explicit mapping keys inside
sequence items, plain spaced mapping-key parsing, or folded block scalars with
more-indented lines. It owns only unquoted colon-bearing plain keys inside YAML
flow maps.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep YAML alias-cycle diagnostics, custom application tag provenance,
multi-document YAML stream handling, writer-side YAML emission, full YAML
schema validation, and full upstream runner dependency planning as separate
bounded slices.
