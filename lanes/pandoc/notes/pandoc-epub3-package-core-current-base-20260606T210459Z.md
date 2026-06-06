# pandoc-epub3-package-core-current-base-20260606T210459Z

## Behavior

This slice adds bounded EPUB3 stylesheet dependency handoff to the native PHP
EpubReader. Manifest `text/css` assets are scanned in-package for `@import`
and `url(...)` references, with comments stripped and `data:` payloads ignored.
References are resolved through the existing package-reference path so local
package parts, missing resources, remote URLs, fragments, manifest ids, media
types, byte metadata, and encrypted-resource exposure policy use the same
shape as XHTML resource diagnostics.

The reader now returns `cssResourceReport` at the top level, inside
`importReport`, and on the AST document attributes. The existing
`remoteResources` report now also folds stylesheet remote references into
remote-resource declaration review, preserving `xhtmlExternalReferenceCount`
and adding `cssExternalReferenceCount` plus `undeclared-css-remote-resources`
diagnostics when a stylesheet references remote resources without declaring
`remote-resources` in OPF.

The WordPress EPUB3 handoff example now includes a stylesheet that references
a cover image and an encrypted font package part, and its self-test asserts the
CSS dependency report, encrypted-reference diagnostic, import-report handoff,
and AST attribute propagation.

## Evidence

- Baseline focused test before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  -> `1 test files, 1608 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  -> `1 test files, 1652 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  -> `epub3 package handoff self-test ok`.
- Syntax checks:
  `php -l lanes/pandoc/src/EpubReader.php`;
  `php -l lanes/pandoc/tests/EpubReaderTest.php`;
  `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`.
- Diff hygiene:
  `git diff --check -- lanes/pandoc`.
- Root harness: not run - isolated micro-slice.

Focused delta: +1 PHP PASS case and +44 net focused assertions in
`EpubReaderTest.php`. The manifest mapped denominator is updated from 1813 to
1814 for this native support-library case.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`EpubReader`, OPF manifest metadata, `OpcPackagePath` package-reference
resolution, and a bounded in-process CSS token scanner. No Pandoc, Cabal,
Haskell runner, external CSS parser, browser renderer, `zip`/`unzip`,
EPUBCheck, online service, live provider test, or live-service provider test
was run.

## Non-Overlap

This does not repeat accepted EPUB3 OCF container/rootfile parsing, OPF
metadata/manifest/spine handling, nav/NCX resolution, XHTML spine raw-block
handoff, XHTML `srcset` and content-reference scanning, switch/trigger review,
remote OPF/XHTML resource handling, OCF sidecar metadata, or encrypted font
preflight. It owns only stylesheet package dependency discovery and remote
resource declaration reconciliation for EPUB3 imports.

## Follow-Up

Keep CSS cascade/media query semantics, full stylesheet import graph handling,
XHTML-to-AST conversion, resource export policy, remote fetch policy,
EPUBCheck parity, and encrypted resource decryption as separate bounded slices.
