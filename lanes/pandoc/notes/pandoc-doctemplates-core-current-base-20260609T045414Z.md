# pandoc-doctemplates-core-current-base-20260609T045414Z

## Scope

Lane: `pandoc`

Micro-slice: `pandoc-doctemplates-core-current-base-20260609T045414Z`

Accepted base: `e3e201377d66d62da0039dedbb153200e0a6e366`

This patch keeps the bounded native PHP doctemplate renderer focused on parser diagnostics. It does not run Pandoc, Cabal/Haskell test binaries, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF engines, browser renderers, online services, live provider tests, or live-service provider tests.

## Behavior

Unsupported doctemplate pipe errors now report the source location of the unsupported pipe token instead of falling back to the enclosing directive start. The tokenizer records the trimmed directive body location, and unsupported-pipe parser errors carry a directive-relative byte offset that is converted back to resource line/column coordinates.

Covered cases:

- direct variable pipe diagnostics after Unicode-prefix text;
- braced directive pipe diagnostics with leading directive whitespace;
- partial-call pipe diagnostics;
- resource template diagnostics;
- inactive branch parser validation.

Red-first probe before the patch:

```text
Bad: $title/unknown$ -> Unsupported doctemplate pipe unknown at <template>:1:6
```

After the patch, the same class of error points at the pipe name, for example:

```text
Unsupported doctemplate pipe unknown at review-packets/broken.html:2:8
```

## Evidence

Focused PHP test delta:

- Before: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 1135 assertions, 0 failures`.
- After: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 1138 assertions, 0 failures`.

Commands run:

```text
php -l lanes/pandoc/src/DocTemplate.php
php -l lanes/pandoc/tests/DocTemplateTest.php
php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php
php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php
php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test
php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
git diff --check -- lanes/pandoc
```

Results:

- Syntax checks reported no errors.
- Focused doctemplate suite: `1 test files, 1138 assertions, 0 failures`.
- WordPress doctemplate review-packet smoke: `OK wordpress doctemplate review packet`.
- Lane JSON parse check: `json ok`.
- `git diff --check -- lanes/pandoc`: passed with no output.

## Mapping

`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` records:

- `benchmarkDenominator.mapped`: `2728`
- `mappedDoctemplatePipeDiagnosticCases`: `1`
- `doctemplatePipeDiagnosticAssertions`: `3`

`lanes/pandoc/lane-status.json` records:

- `phpPass`: `2332`
- `phpFail`: `0`

## Dependency Closure

No new support component is needed. The slice reuses native PHP `DocTemplate` tokenization/parsing, the existing focused PHP test runner, and the lane-local WordPress doctemplate review-packet example. Full upstream Pandoc runner parity remains a separate upstream-runner dependency task requiring hydrated pinned upstream sources and Haskell test executables.

## Non-Overlap

This slice avoids the recent accepted EPUB3 content-feature reconciliation and prior doctemplate clusters for block pipes, explicit nesting, partial rendering, default resources, and pipe quote/separator diagnostics. It only changes unsupported-pipe source locations.
