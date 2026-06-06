# Pandoc YAML Metadata Writer Slice 2026-06-06 01:35 UTC

## Behavior

- Added opt-in `MarkdownWriter(['yamlMetadata' => true])` support for emitting
  Pandoc-style YAML metadata blocks before the Markdown body.
- Emits supported document metadata scalars, lists, and mappings, including
  nested CSL-like metadata such as `references[*].issued.date-parts`.
- Quotes ambiguous YAML keys/scalars such as `yes`, fields containing `:`, and
  strings that would otherwise be parsed as booleans or numbers.
- Filters derived inline companion metadata such as `titleInlines`, duplicate
  `authors` when `author` already carries the same list, internal YAML
  diagnostics, and scratch fields ending in `_`.
- Reuses the native `MarkdownReader` YAML front-matter parser for round-trip
  validation; no external YAML parser or Pandoc process is involved.

## Evidence

- Red-first probe before the edit:
  `php -r 'require "tools/bootstrap.php"; use PortLibs\Pandoc\AstNode; use PortLibs\Pandoc\MarkdownWriter; $doc=new AstNode("document", ["meta"=>["title"=>"Round Trip", "keywords"=>["migration"]]], [new AstNode("paragraph", [], [new AstNode("text", ["text"=>"Body."])])]); echo (new MarkdownWriter(["yamlMetadata"=>true]))->write($doc), "\n";'`
  emitted only `Body.`, proving writer metadata emission was absent.
- `php -l lanes/pandoc/src/MarkdownWriter.php` passed.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed
  with `1 test files, 3057 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  passed with `yaml metadata handoff self-test ok`.
- `git diff --check -- lanes/pandoc` passed.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`MarkdownWriter`, `MarkdownReader`, the focused `MarkdownReaderTest` fixture
set, and the WordPress YAML metadata handoff example. No Pandoc binary, Cabal
solver/build/test command, Haskell runner, external YAML parser, browser
renderer, online sanitizer, online service, or live provider test was executed.

## Blocker / Next

- Blocker unchanged: full upstream Pandoc runner parity still requires the
  hydrated Pandoc checkout, Cabal project/package files, exact source-repository
  pins, and Haskell Tasty executable builds recorded in the lane status.
- Next: keep multi-document metadata emission, richer Pandoc `MetaValue` type
  fidelity, citation bibliography writer handoff, and non-initial metadata
  blocks as separate bounded YAML metadata slices.
