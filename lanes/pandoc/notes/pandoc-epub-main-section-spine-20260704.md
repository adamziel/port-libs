# EPUB main section spine fixture

This increment adds a checked-in EPUB 3 package/native pair for `main-section-spine.epub`. The fixture keeps the reading order simple while wrapping the spine body in XHTML `<main id="reader-main" epub:type="bodymatter">`, covering a sectioning boundary not present in the prior EPUB reader fixture snapshot.

## Count deltas

- Checked-in EPUB package inputs: `63 -> 64`.
- Checked-in same-basename `.native` goldens: `63 -> 64`.
- Checked-in fixture identity files: `126 -> 128`.
- Package/native/executable parity: `63 -> 64`.
- Package feature coverage: `fixtureCount=64`, `navFixtures=59`, `metadataCreators=59`, `manifestItems=227`, `readingOrderItems=95`, `xhtmlAssets=151`, `navigationEntries=154`.
- Package feature signature: `157f63a2070a1437698fb331f3d621c7fa0ee9de184544793a0f6a74973cba70`.
- Normalized native AST signature: `4fa93f1c1184f45af171b365d91f979d51d20e9140fb8f5e3035ab1ab1282cfc`.

## Fixture hashes

```text
99f8c2afa52f3cb97bed7466fdff1bcb9a94f795ba27d306cabc70314cab40dc  main-section-spine.epub
9f9d405ee278ca5a586aa3a9cd1020c90e6b66d2c8019e985d7c779a1347e5a5  main-section-spine.native
```

## Gates

```sh
php tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-package-parity=64 --require-native-readiness=64 --require-mapped-parity=64 --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan
php tools/pandoc-epub-executable-native-ast.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc summary --require-executable-parity=64 --require-pandoc-version='pandoc 3.10'
php tools/pandoc-epub-reader-evidence.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc --require-native-ast-package-parity --require-executable-native-ast-parity --require-pandoc-version='pandoc 3.10' --require-runner-plan --require-no-validation-issues
```

Expected summaries:

```text
packages: total=64 compared=64 packageParsed=64 readerParsed=64 packageFailures=0 readerFailures=0
normalizedAst: matches=64 (100.00%) mismatches=0
fixtureIdentity: status=valid-checked-in-current-epub-fixture-identity expected=128 observed=128
nativeAstPackageParity: totalEpubCount=64 normalizedAstMatchCount=64
executableNativeAstParity: totalEpubCount=64 normalizedAstMatchCount=64 pandocNativeFixtureMatchCount=64
```
