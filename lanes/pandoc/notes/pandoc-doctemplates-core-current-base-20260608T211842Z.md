# Pandoc doctemplates core current-base HTML4 default resource

Slice: `pandoc-doctemplates-core-current-base-20260608T211842Z`
Lane: `pandoc`
Base accepted HEAD: `68d7c32c04f00c8830ab48c497321c0c06937915`

## Source truth

- Pinned Pandoc template inventory at `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83` includes `data/templates/default.html4`.
- `Text.Pandoc.Templates.getDefaultTemplate` maps `html` to `html5`, but does not map `html4`; unmatched writer names read `templates/default.<format>`, so `html4` resolves to `templates/default.html4`.
- Source files checked:
  - `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.html4`
  - `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Templates.hs`

No Pandoc command, Cabal solver/build/test command, Haskell runner, external template engine, browser renderer, online converter, live provider test, or live-service provider test was executed.

## Implementation

- `DocTemplate` now exposes a bounded native `templates/default.html4` resource with the upstream XHTML 1.0 Transitional doctype, legacy `http-equiv` metadata, HTML4-style CSS/link attributes, div-based title block, TOC div, include-before/body/include-after hooks, and shared `styles.html` partial fallback.
- `canonicalDefaultTemplateFormat()` no longer aliases `html4` to `html5`; `html` still aliases to `html5`.
- `defaultTemplateResourceBasenames()` and direct basename lookup now include `default.html4`.
- The WordPress doctemplate review-packet smoke now checks `html4+smart` for the XHTML/HTML4 default fallback instead of the old HTML5 alias.

## Evidence

- Rework note check:
  - `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print | sort | tail -20`
  - Result: no files.
- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 965 assertions, 0 failures`.
- Red-first direct checks before implementation:
  - `php -r 'require "tools/bootstrap.php"; $r = new \PortLibs\Pandoc\DocTemplate(); $out = $r->renderResource("templates/default", [], ["pandoc-version"=>"3.8.3", "pagetitle"=>"HTML4 Drift", "body"=>"<p>Body.</p>"], null, "html4"); if (!str_contains($out, "XHTML 1.0 Transitional")) { fwrite(STDERR, "missing html4 doctype\n"); exit(7); }'`
  - Result: `missing html4 doctype`
  - `php -r 'require "tools/bootstrap.php"; $r = new \PortLibs\Pandoc\DocTemplate(); try { $r->renderResource("templates/default.html4", [], ["pandoc-version"=>"3.8.3", "pagetitle"=>"Direct HTML4", "body"=>"<p>Body.</p>"]); } catch (\Throwable $e) { fwrite(STDERR, get_class($e) . ": " . $e->getMessage() . "\n"); exit(7); }'`
  - Result: `UnexpectedValueException: Missing doctemplate resource templates/default.html4`
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 991 assertions, 0 failures`.
  - Focused delta: `+26` assertions in the existing doctemplate test file; this slice does not change `phpPass`.
- Final example smoke:
  - `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.
- PHP lint:
  - `php -l lanes/pandoc/src/DocTemplate.php`
  - `php -l lanes/pandoc/tests/DocTemplateTest.php`
  - `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  - Result: no syntax errors.
- JSON validation:
  - `php -r '$files=["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'`
  - Result: both files decoded successfully.
- Whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.
- Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted doctemplate comments, delimiter whitespace, map-pairs, applied-partial rebinding, breakable-space wrapping, braced separators, digit/control-key metadata, user-data default partial precedence, custom extension-qualified fallback, Markdown/CommonMark defaults, chunked HTML defaults, legacy slide defaults, Beamer/man/ms defaults, or HTML5 default rendering. It only owns the pinned `default.html4` resource and the `html4` default-template lookup correction.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP `DocTemplate` parser/resource renderer, default resource registry, shared HTML styles partial, focused doctemplate tests, and the lane-local WordPress review-packet example. Full Pandoc/Haskell runner parity and external template engines remain out of scope for this bounded support-library slice.

## Follow-up

A useful non-overlapping follow-up is another source-backed default-template drift check or remaining doctemplate parser/resource diagnostic gap that can be covered through native PHP tests without running external converters.
