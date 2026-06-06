# Pandoc doctemplates core current-base 2026-06-06T03:11:50Z

## Slice

- Added bounded rooted filesystem template discovery to
  `PortLibs\Pandoc\DocTemplate`.
- New `renderFilesystemResource()` and `renderFilesystemResourceWrapped()`
  methods load template resources from a caller-supplied root directory, then
  reuse the existing native resource renderer.
- The rooted loader preserves extensionless output-format fallback, sibling
  partial lookup, user-data `templates/` partial fallback, and breakable-space
  wrapped rendering.
- Filesystem discovery rejects absolute and traversal template/user-data
  paths, rejects filesystem root directories that are too broad, skips symlink
  resources, and enforces bounded file-count, per-file byte, and total-byte
  limits.
- Updated the WordPress doctemplate review-packet smoke with a temporary
  filesystem-backed review packet.

## Source Truth

- Upstream `Text.DocTemplates.Parser` resolves partial names relative to the
  current template path and reads partials through `getPartial`:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- Upstream `Text.DocTemplates.Internal` defines `TemplateMonad` with `IO`
  reading partials from the filesystem via `TIO.readFile`:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Internal.hs
- Hackage/Stackage identify `doctemplates` as Pandoc-style document templates
  and document the same parser/internal modules:
  https://hackage.haskell.org/package/doctemplates
  https://www.stackage.org/package/doctemplates
- This slice used temporary local fixture files only. No Pandoc binary, Cabal
  build/solver/test command, Haskell runner, Word, LibreOffice, `zip`/`unzip`,
  external template engine, browser renderer, online sanitizer, online service,
  or live provider test was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before edits: `1 test files, 187 assertions, 0 failures`.
- Red-first focused command after adding the filesystem-resource expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before implementation: `1 test files, 187 assertions, 1 failures`;
    failure was `Call to undefined method PortLibs\Pandoc\DocTemplate::renderFilesystemResource()`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 192 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.
- PHP lint:
  - `php -l lanes/pandoc/src/DocTemplate.php`
  - `php -l lanes/pandoc/tests/DocTemplateTest.php`
  - `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  - Result: no syntax errors.
- Lane JSON validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both files valid.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, dollar delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, parameter-free pipes, deterministic map-pairs ordering,
parameterized block pipes, Unicode display-width padding, missing/null pipe
handling, in-memory resource-map partial discovery, path-style partial lookup,
applied partial variable rebinding, partial recursion guards, braced pipe
quoted-string braces, braced separator parsing, alpha overflow labels, boolean
false output, Unicode identifier parsing, multiline control boundary newline
swallowing, empty standalone partial line swallowing, `chomp` traversal,
breakable-space rendering/wrapping, dedented nesting termination, final newline
stripping for included partials, extensionless custom-template output-format
fallback, unclosed ordinary-dollar diagnostics, built-in default-template
fallbacks, default HTML style partials, unclosed `$~$` breakable-space
rejection, or default HTML5 void tag serialization.

It only adds bounded rooted filesystem resource discovery around the existing
native renderer. It does not touch ZIP/OPC package primitives, YAML metadata,
Citation/CSL, BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers,
DOCX/ODT/EPUB package parsing, legacy-DOC parsing, table geometry, math
conversion, PDF handoff planning, archive compression, syntax highlighting,
XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency audit behavior.

## Dependency Closure

No external support component is needed. This reuses the accepted native PHP
`DocTemplate` renderer and adds a bounded filesystem resource loader for local
template trees. HTTP-backed template discovery, richer source-location
diagnostics, fuller upstream default-template data-file parity, full doclayout
value modeling, and full upstream Pandoc runner parity remain separate bounded
slices.

Root harness: not run - isolated micro-slice.
