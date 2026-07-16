# pandoc-epub3-package-core-current-base-20260605T164243Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-epub3-package-core-current-base-20260605T164243Z`
- Base accepted HEAD: `d11e64ae6f006601d89d9cc168745be63321b45d`
- Upstream contract: EPUB3 package handoff under `lanes/pandoc/**`, bounded to OCF/OPF/spine/nav/XHTML asset metadata without invoking Pandoc, zip/unzip, browsers, Haskell runners, or online services.

## Implemented Behavior

`EpubReader` now detects OPS namespace `epub:switch` elements while scanning XHTML spine assets. The content-resource scan reports:

- per-asset `flags.switch`;
- per-asset `reviewFlags` containing `switch`;
- aggregate `xhtmlResourceReport.switchAssetCount`;
- the same review flag through `importReport.xhtmlResourceReport`, document-level `xhtmlResourceReport`, and WordPress handoff block `contentResourceReviewFlags`.

This is intentionally separate from the already accepted OPF manifest `properties="switch"` path. Manifest resource-property flags still describe package-declared resource metadata, while this slice flags actual XHTML content requiring reviewer attention.

## Evidence

Red-first check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL flags EPUB switch XHTML content for package review
Values are not identical
Expected: 1
Actual: NULL
1 test files, 995 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1005 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Final verification:

```text
php -l lanes/pandoc/src/EpubReader.php
No syntax errors detected in lanes/pandoc/src/EpubReader.php

php -l lanes/pandoc/tests/EpubReaderTest.php
No syntax errors detected in lanes/pandoc/tests/EpubReaderTest.php

php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-epub3-package-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok

git diff --check -- lanes/pandoc
```

`git diff --check -- lanes/pandoc` produced no output.

## Status Delta

- `lane-status.json` `phpPass`: `1002 -> 1003`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1457 -> 1458`
- `epub3PackageCoreCases`: `4 -> 5`
- `epub3PackageCoreAssertions`: `62 -> 73`

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage` plus `EpubReader` DOM-based XHTML content scanning and package reporting. Full upstream Pandoc runner parity remains gated on hydrating the pinned Pandoc checkout and Cabal/Tasty runner dependencies; no Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip, ZipArchive, browser renderer, online sanitizer, or online conversion service was executed.

## Non-Overlap

This patch does not repeat OCF mimetype/container validation, OPF metadata/manifest/spine parsing, manifest resource-property `switch` flags, nav/NCX target resolution, guide/collection handling, fallback chains, remote-resource reconciliation, SMIL media overlays, encryption/obfuscated font preflight, OCF rights/signatures, EPUB CFI fragments, or ZIP comment-policy work. Follow-up EPUB3 work remains separate for actual `epub:switch` fallback branch selection, XHTML-to-AST conversion, CSS cascade, media export policy, and reading-system capability evaluation.
