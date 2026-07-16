# EPUB3 Package Trigger Handoff

Slice: `pandoc-epub3-package-core-current-base-20260605T213430Z`

Accepted base: `b321f6888e03ba16f542328dfc7cccbdbb2ef4a8`

## Source Truth

- W3C EPUB 3.3 package/content-document scope: https://www.w3.org/TR/epub-33/
- IDPF EPUB 3.0.1 content documents define `epub:trigger` as legacy/deprecated multimedia trigger markup with action/ref and XML Events observer/event attributes: https://idpf.org/epub/301/spec/epub-contentdocs-20140626.html

## Behavior

- `EpubReader` now detects XHTML `epub:trigger` elements during package review.
- Trigger reports preserve `action`, `ref`, `ev:observer`, `ev:event`, `ev:defaultAction`, `ev:phase`, `ev:propagate`, and raw attributes.
- Same-document IDREFs are resolved for `ref` and `ev:observer`, with `refElement` / `observerElement` handoff metadata.
- Invalid actions, missing required attributes, and unresolved trigger targets become flattened XHTML resource diagnostics.
- WordPress raw HTML block handoff now carries `contentTriggers` alongside existing XHTML content resource metadata.

## Verification Evidence

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL preserves EPUB trigger XHTML controls for static review
1 test files, 1007 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1052 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Final checks:

```text
php -l lanes/pandoc/src/EpubReader.php
No syntax errors detected in lanes/pandoc/src/EpubReader.php

php -l lanes/pandoc/tests/EpubReaderTest.php
No syntax errors detected in lanes/pandoc/tests/EpubReaderTest.php

php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-epub3-package-handoff.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'
lanes/pandoc/lane-status.json OK
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json OK

git diff --check -- lanes/pandoc
passed with no output
```

Focused delta: `+1` PHP PASS case and `+45` focused assertions.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage` fixtures, DOM parsing, `EpubReader` XHTML package reporting, and the existing WordPress EPUB package handoff example. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, browser renderer, JavaScript/media execution, online sanitizer, online service, or live provider test was executed.

## Follow-Up

Keep active trigger execution, SMIL playback mapping, XHTML-to-AST conversion, media extraction/export policy, remote-resource policy, encrypted/obfuscated font preflight, and CSS cascade behavior as separate bounded slices.
