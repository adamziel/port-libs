# Pandoc doctemplates core current-base 2026-06-06T23:07:07Z

## Change

- Added bounded Pandoc default data-file fallback resources to
  `PortLibs\Pandoc\DocTemplate` partial discovery.
- Custom resource templates can now include known built-in Pandoc default
  templates and their dependent partials, for example `${ default() }` from a
  custom `templates/wrapper.typst`, and `${ styles.html() }` plus
  `${ default.html5() }` from a custom HTML wrapper.
- Caller-provided resources still win over built-in fallback resources.
- Updated the WordPress doctemplate review-packet smoke with a nested Typst
  default-template wrapper path.

## Source Truth

- Upstream doctemplates `TemplateMonad` retrieves partials with `getPartial`,
  computes partial paths from the original template path, supports nested
  partials, and omits final newlines from included partials:
  https://raw.githubusercontent.com/jgm/doctemplates/master/README.md
- Upstream `Text.DocTemplates.Parser.pPartial` resolves extensionless partials
  against the original template extension and parses nested partial source
  through the same template parser:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- Pinned Pandoc `Text.Pandoc.Templates.WithPartials.getPartial` falls back to
  `readDataFile ("templates" </> takeFileName fp)` when a local partial is not
  found, while `WithDefaultPartials` reads from Pandoc data files directly:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Templates.hs

## Red-First

- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 316 assertions, 0 failures`.
- Red-first probe:
  `php -r 'require "tools/bootstrap.php"; $r = new PortLibs\Pandoc\DocTemplate(); echo $r->renderResource("templates/wrapper", ["templates/wrapper.typst" => "#let wrapper = [\n\${ default() }\n]"], ["body" => "#heading[Wrapped body]"], null, "typst");'`
  failed with `Missing doctemplate partial default at templates/wrapper.typst:2:1`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 327 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- `php -l lanes/pandoc/src/DocTemplate.php`
  passed.
- `php -l lanes/pandoc/tests/DocTemplateTest.php`
  passed.
- `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  passed.
- `git diff --check -- lanes/pandoc`
  passed with no output.
- Root harness not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, dollar delimiters,
variable lookup, truthiness, loops, separators, pipes, Unicode display-width
padding, partial parsing, path-style partial lookup, applied partial rebinding,
partial recursion guards, final newline stripping, filesystem resource loading,
HTML5 style default resources for top-level defaults, Markdown/CommonMark,
LaTeX, Beamer, office, EPUB, or direct Typst default-template fallback. It only
adds bounded fallback discovery for built-in Pandoc default resources when they
are used as partials inside custom resource templates.

## Dependency Closure

No new support component is needed. This reuses the native PHP `DocTemplate`
resource map, built-in bounded Pandoc default-template resources, partial
discovery, and the existing WordPress doctemplate review-packet example. Full
Pandoc runner parity, Cabal/Haskell runner execution, external template
engines, TeX/PDF engines, Typst execution, browser renderers, online services,
live provider tests, and live-service provider tests remain out of scope for
this isolated micro-slice.

## Next

Continue doctemplate closure with a non-overlapping default-template/resource
behavior, such as another bounded Pandoc data-template alias or partial
fallback edge, while preserving caller resource override precedence and avoiding
external Pandoc/template-engine execution.
