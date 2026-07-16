# Pandoc doctemplates core current-base 2026-06-05T00:07:56Z

## Slice

- Extended `PortLibs\Pandoc\DocTemplate` with a bounded `renderResource()`
  entry point for caller-supplied template resource maps.
- Resource rendering now resolves partials from the directory containing the
  main template, infers the main template extension for extensionless partial
  calls, and falls back to `templates/` under a supplied user-data directory
  only when the main template path is relative.
- Resource paths are normalized in-memory and reject NUL bytes,
  parent-directory traversal, empty paths, and non-string template contents.
- Updated the WordPress doctemplate review-packet smoke to render through the
  resource-map path, with header/list partials beside the main template and
  the body partial coming from the user-data templates fallback.

## Source Truth

- Pandoc User's Guide `Template syntax`, `Partials`
  (https://pandoc.org/demo/example33/6.1-template-syntax.html): partials are
  called with `name()`, are searched beside the main template, use the main
  template extension when the call lacks one, can fall back to user-data
  `templates/` for relative main template paths, can be applied to variables,
  and strip final newlines.
- Pandoc User's Guide `Template syntax`, `Interpolated variables`: variable
  names may contain letters, numbers, `_`, `-`, and `.`. The resource test
  covers underscore-bearing variables while preserving the existing parser
  rules.
- No Pandoc binary, Haskell test binary, Cabal build, Word, LibreOffice,
  `zip`/`unzip`, external template engine, TeX/PDF engine, browser renderer,
  roff, Typst, MathJax, KaTeX, or online service was executed.

## Evidence

- Rework note check:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print | sort | tail -20`
  returned no current Pandoc rework notes.
- Red-first probe before implementation:
  `php -r 'require "tools/bootstrap.php"; $r = new \PortLibs\Pandoc\DocTemplate(); echo $r->renderResource("templates/review.html", ["templates/review.html" => "\${ styles() }"], []);'`
  failed with `Call to undefined method PortLibs\Pandoc\DocTemplate::renderResource()`.
- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed:
  1 test file, 33 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- `php -l lanes/pandoc/src/DocTemplate.php`,
  `php -l lanes/pandoc/tests/DocTemplateTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  passed.
- `git diff --check -- lanes/pandoc` passed with no output.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
interpolated variables, conditionals, loops, separators, `$it$`, `$^$`,
automatic multiline nesting, `$~$` breakable-space markers, parameter-free or
parameterized pipes, inline partial arrays, or applied partial rendering. It
does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, or upstream-runner dependency audit
behavior.

## Dependency Closure

No new external support component is needed. This reuses the existing native
PHP `pandoc-doctemplates-core` renderer and adds an in-memory resource-map
policy for template files and partials. It intentionally does not read the
host filesystem or user-data directories itself, so filesystem-backed template
discovery remains a separate bounded activation slice. Full upstream Pandoc
runner parity remains out of scope for this isolated micro-slice because a
hydrated upstream checkout and Cabal dependency plan are still required.

Root harness: not run - isolated micro-slice.
