# EPUB appendix navigation guide fixture

This increment adds a checked-in EPUB 3 package/native pair for `appendix-navigation-guide.epub`. The fixture keeps the appendix target outside the spine while declaring both an OPF guide reference with `type="appendix"` and an auxiliary `epub:type="appendix"` navigation section, so package/navigation coverage grows without changing the simple reading-order AST.

## Count deltas

- Checked-in EPUB package inputs: `62 -> 63`.
- Checked-in same-basename `.native` goldens: `62 -> 63`.
- Checked-in fixture identity files: `124 -> 126`.
- Package/native/executable parity: `62 -> 63`.
- Package feature coverage: `fixtureCount=63`, `navFixtures=58`, `auxiliaryNavigationEntries=8`, `manifestItems=225`, `readingOrderItems=94`, `xhtmlAssets=149`, `navigationEntries=153`, `guideReferences=23`.
- Package feature signature: `887f6304e118128f437624f3774797965b4f00172cc49ea785ca58469246653f`.
- Normalized native AST signature: `fe96d0491a8d06ab3aac19083cf39580995f5b25dda35ca4ef8049834c884578`.

## Fixture hashes

```text
2c7b9ca20d38dcda15b63a1bf4aa210a3b11b132dc0a8fe6bedec13d24675c4f  appendix-navigation-guide.epub
8e9029119f437f20c1ba3fa8055beb80087bf9230c083e297ac91f3115453bed  appendix-navigation-guide.native
```

## Gates

```sh
php tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-package-parity=63 --require-native-readiness=63 --require-mapped-parity=63 --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan
php tools/pandoc-epub-executable-native-ast.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc summary --require-executable-parity=63 --require-pandoc-version='pandoc 3.10'
php tools/pandoc-epub-reader-evidence.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc --require-native-ast-package-parity --require-executable-native-ast-parity --require-pandoc-version='pandoc 3.10' --require-runner-plan --require-no-validation-issues
```

Expected summaries:

```text
packages: total=63 compared=63 packageParsed=63 readerParsed=63 packageFailures=0 readerFailures=0
normalizedAst: matches=63 (100.00%) mismatches=0
fixtureIdentity: status=valid-checked-in-current-epub-fixture-identity expected=126 observed=126
nativeAstPackageParity: totalEpubCount=63 normalizedAstMatchCount=63
executableNativeAstParity: totalEpubCount=63 normalizedAstMatchCount=63 pandocNativeFixtureMatchCount=63
```
