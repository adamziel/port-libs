# Pandoc doctemplates current-base child control-key metadata

Slice: `pandoc-doctemplates-core-current-base-20260608T194104Z`
Base accepted HEAD: `62c06d3a4b24281f99ac1aedb30fe965d1d82ed8`

## Source truth

- Prior doctemplate lane notes record the Hackage doctemplates contract for variable names beginning with a letter and continuing with letters, digits, `_`, `-`, and `.`.
- The earlier digit-leading child-key slice explicitly kept top-level directive names letter-led; this slice preserves that top-level guard and narrows the change to child metadata path segments.
- The hydrated upstream Pandoc checkout was not present locally for this worker, so this patch uses the existing lane doctemplate implementation/tests/notes as the bounded source-truth surface. No Pandoc, Cabal, Haskell, or external template runner was executed.

## Implementation

- `DocTemplate::validateVariableName()` now continues to reject reserved top-level directive words such as `if`, `for`, `elseif`, `else`, `endif`, `endfor`, and `sep`, while still allowing the top-level loop value `it`.
- Child path segments now allow reserved-looking metadata keys, so `$control.if$`, `$control.elseif.sep$`, `$it.it$`, and applied partial paths such as `$control.for.it$` can render real metadata instead of being rejected as control directives.
- The WordPress review-packet doctemplate example now covers child metadata keys named `if`, `for`, `it`, and `else`.

## Evidence

- Baseline before this slice: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` -> `1 test files, 891 assertions, 0 failures`.
- Red-first after adding only the focused test: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` -> `1 test files, 891 assertions, 1 failures`; the new case failed with `Unsupported doctemplate directive control.if at packets/review.html:2:7`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` -> `1 test files, 892 assertions, 0 failures`.
- Final example smoke: `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` -> `OK wordpress doctemplate review packet`.
- PHP lint: `php -l lanes/pandoc/src/DocTemplate.php`, `php -l lanes/pandoc/tests/DocTemplateTest.php`, and `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php` -> no syntax errors.
- JSON validation: `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` -> OK.
- Whitespace check: `git diff --check -- lanes/pandoc` -> passed.
- Root harness: not run - isolated micro-slice.

## Non-overlap

This is not another default-template, extension-qualified writer lookup, breakable-space wrapping, braced separator, map-pairs, applied-partial rebinding, or digit-leading child-key slice. It only changes reserved/control-word handling for child metadata path segments in the native doctemplate renderer.

## Dependency closure

No new support component is needed. The patch reuses the bounded native PHP `DocTemplate` parser/resource renderer and the existing WordPress review-packet example. External Pandoc execution, Cabal solver/build/test planning, Haskell runners, external template engines, browser renderers, online services, live provider tests, and live-service provider tests remain out of scope.

## Next

A useful follow-up would be another non-overlapping doctemplate parser/rendering edge, such as resource lookup precedence, indentation/wrapping parity, or a remaining default-template alias, with the same native PHP and no-external-runner constraints.
