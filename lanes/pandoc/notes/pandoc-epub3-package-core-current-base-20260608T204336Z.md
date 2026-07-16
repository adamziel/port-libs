Pandoc EPUB3 package current-base CSS @page handoff

Micro-slice: pandoc-epub3-package-core-current-base-20260608T204336Z
Accepted base: 6479f65c1465d77f871d7146aaaa2d022aa27e3f

Scope:
- Added native EPUB stylesheet paged-media package review support under EpubReader.
- The CSS resource report now records @page rule counts, named page selectors, page pseudo-classes, page descriptors, page margin boxes, and paged-media review flags.
- The WordPress EPUB3 package handoff example now includes an @page source:left rule and verifies the page selector, descriptor, and margin-box metadata in its self-test.

Evidence:
- Baseline before implementation: php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php -> 1 test files, 2693 assertions, 0 failures.
- Red-first probe before implementation: php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php -> 1 test files, 2695 assertions, 1 failures because cssResourceReport lacked pageRuleCount/pageRules metadata.
- Final focused test: php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php -> 1 test files, 2731 assertions, 0 failures.
- Example smoke: php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test -> epub3 package handoff self-test ok.
- PHP lint passed for EpubReader.php, EpubReaderTest.php, and wordpress-epub3-package-handoff.php.

Status delta:
- +1 mapped EPUB3 package core native support case.
- +1 PHP PASS case in EpubReaderTest.php.
- +38 focused assertions.
- lane-status phpPass should move from 1824 to 1825.
- benchmarkDenominator.mapped should move from 2248 to 2249.
- mappedEpub3PackageCoreCases should move from 6 to 7.
- epub3PackageCoreAssertions should move from 112 to 150.

Dependency closure:
- No new support component is needed. This slice reuses the native ZipPackage fixture builder, EpubReader CSS reference scanning, CSS declaration parsing, CSS brace matching, import-report/document attribute handoff, and the lane-local WordPress EPUB example.
- No Pandoc, Cabal/Haskell runner, browser/CSS engine, EPUBCheck, zip/unzip, external validator, online service, live provider test, or live-service provider test was executed.

Non-overlap:
- This is distinct from accepted EPUB OCF/container/rootfile, OPF metadata/manifest/spine, nav/NCX, page-break, fallback/bindings, media-overlay, CFI, scripted/switch/trigger, XHTML semantic metadata, CSS resource-reference, CSS font-face, image-set, and CSS conditional-at-rule slices.
- It does not implement CSS cascade/layout, browser rendering, EPUBCheck validation, encrypted resource decryption, or full CSS parsing.

Next:
- Continue EPUB3 package work on a non-overlapping bounded support gap such as rendition layout conflicts, fixed-layout viewport inheritance, collection/navigation edge metadata, or active-media review policy.
