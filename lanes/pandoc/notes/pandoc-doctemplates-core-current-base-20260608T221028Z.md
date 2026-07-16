# Pandoc doctemplates core current-base block pipe reboxing

Slice: `pandoc-doctemplates-core-current-base-20260608T221028Z`
Lane: `pandoc`
Base accepted HEAD: `b5a355b21ceda7875c2975dde96ac65abe5fde9b`

## Source truth

- `jgm/doctemplates` `0.11.0.1` parses block-pipe widths with `Text.Read.decimal`, so `0` is accepted syntax for `left`, `right`, and `center`.
- `Text.DocTemplates.Internal.applyPipe (Block ...)` delegates to `Text.DocLayout.lblock`, `rblock`, and `cblock`.
- `Text.DocLayout.block` reboxes rendered text with `chop width`; when width is below `1` and the document is non-empty, it coerces the block width to `1` instead of rejecting it.
- Source files checked:
  - `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/src/Text/DocTemplates/Parser.hs`
  - `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/src/Text/DocTemplates/Internal.hs`
  - `https://raw.githubusercontent.com/jgm/doclayout/0.5.0.1/src/Text/DocLayout.hs`

No Pandoc command, Cabal solver/build/test command, Haskell runner, external template engine, TeX/PDF engine, browser renderer, online converter, live provider test, or live-service provider test was executed.

## Implementation

- `DocTemplate` now accepts block pipe width `0` instead of rejecting it.
- Overlong `left`, `right`, and `center` block-pipe text is split into bounded display-width chunks before padding and borders are applied.
- Non-empty zero-width block-pipe content uses an effective width of `1`, matching the upstream doclayout block guard.
- The WordPress doctemplate review-packet smoke now includes a narrow review-code field that exercises the reboxed block-pipe output.

## Evidence

- Rework note check:
  - `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print | sort | tail -20`
  - Result: no files.
- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 1055 assertions, 0 failures`.
- Red-first direct checks before implementation:
  - `php -r 'require "tools/bootstrap.php"; $r=new PortLibs\Pandoc\DocTemplate(); foreach (["left"=>"Title: \$title/left 0\$","border"=>"Title: \$title/center 0 \"[\" \"]\"\$"] as $k=>$tpl) { try { echo $r->render($tpl,["title"=>"Review"]); } catch (Throwable $e) { echo $e->getMessage(), "\n"; } }'`
  - Result included `Expected positive integer parameter for doctemplate pipe left at <template>:1:8` and `Expected positive integer parameter for doctemplate pipe center at <template>:1:8`.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 1060 assertions, 0 failures`.
  - Focused delta: `+1` doctemplate PASS case and `+5` assertions; this slice should move `phpPass` by `+1`.
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

This does not repeat accepted doctemplate comments, delimiter whitespace, map-pairs, applied-partial rebinding, breakable-space wrapping, braced separators, digit/control-key metadata, user-data default partial precedence, extension-qualified fallback, default-template fallback, or existing Unicode padding tests. It only owns upstream block-pipe reboxing and zero-width block-pipe acceptance for the native PHP doctemplate renderer.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP `DocTemplate` parser/resource renderer and `UnicodeText::displayWidth()`/`padDisplay()` helpers. Full Pandoc/Haskell/doclayout runner parity remains outside this isolated support-library slice.

## Follow-up

A useful non-overlapping follow-up is to tighten another source-backed doctemplate parser or doclayout rendering edge that can be covered by native PHP tests without running Pandoc, Cabal, Haskell runners, or external template engines.
