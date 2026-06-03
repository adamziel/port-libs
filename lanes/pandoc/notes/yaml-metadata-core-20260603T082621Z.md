# Pandoc YAML Metadata Core Slice 2026-06-03 08:26 UTC

## Behavior

- Added native MarkdownReader support for a leading Pandoc-style YAML metadata
  block delimited by `---` and closed by `---` or `...`.
- Mapped bounded YAML front matter values into the document `meta` attribute:
  scalar strings, booleans, nulls, numbers, inline lists, block lists, nested
  maps, literal block scalars, and folded block scalars.
- Reused the existing metadata inline parser for `title`, `author`/`authors`,
  and `date`, so inline Markdown in those metadata fields remains available as
  AST inline nodes.
- Preserved the Markdown body fallback for a leading thematic break when no
  closing YAML metadata fence exists.
- Added `examples/wordpress-yaml-metadata-handoff.php` to show title, authors,
  keywords, and nested review status metadata before rendering the WordPress
  block body.

## Evidence

- Baseline before edit: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 2315 assertions, 0 failures`.
- `php -l lanes/pandoc/src/MarkdownReader.php` passed.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed
  with `1 test files, 2344 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php` printed the
  parsed metadata handoff and WordPress heading/paragraph blocks.

## Dependency Closure

No external YAML library, Pandoc binary, Haskell runner, or online service is
needed for this slice. The active `pandoc-yaml-metadata-core` subset is
implemented natively inside `MarkdownReader`. No DOCX/OpenXML, PDF, EPUB, ODT,
citation, math, archive/compression, Unicode/charset, or template support row is
activated by this patch.

## Blocker / Next

- Blocker: full upstream Pandoc runner parity remains unexecuted. The local
  upstream cache path recorded in the manifest was unavailable in this isolated
  worker, and the prior lane manifest records that upstream Haskell test
  executables require a hydrated checkout and Cabal dependency build.
- Next: extend metadata behavior only if needed by a real consumer, such as
  non-initial metadata blocks, bibliography/citation metadata handoff, or
  writer-side metadata emission.
