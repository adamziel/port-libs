# EPUB inline abbr subscript superscript fixture

This increment adds a checked-in EPUB 3 package/native pair for `inline-abbr-subscript-superscript.epub`. The fixture keeps the package structure narrow while exercising Pandoc EPUB reader parity for inline XHTML `abbr` title metadata plus `sub` and `sup` content in the reading-order document.

## Count deltas

- Checked-in EPUB package inputs: `60 -> 61`.
- Checked-in same-basename `.native` goldens: `60 -> 61`.
- Checked-in fixture identity files: `120 -> 122`.
- Package/native/executable parity: `60 -> 61`.
- Package feature coverage: `fixtureCount=61`, `navFixtures=56`, `metadataCreators=57`, `manifestItems=220`, `xhtmlAssets=144`, `navigationEntries=151`.
- Package feature signature: `e02e0d91e96a76ca6350a54488fb72145df06b4bbf6deaf0bfd122b227a2e471`.
- Normalized native AST signature: `122e15910acdbd620979938993f09fe6b87ce4497ab1c8ad17f7148e233accba`.

## Fixture hashes

```text
60d188945fb302e0e658afdc5be5843422f94b2edb344b392703dae00f1d1409  inline-abbr-subscript-superscript.epub
3d9b3e8d736bcb4f233b70228b0bebce47e5716334cb949adb80977716999e53  inline-abbr-subscript-superscript.native
```

## Gates

```sh
php tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-package-parity=61 --require-native-readiness=61 --require-mapped-parity=61 --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan
php tools/pandoc-epub-executable-native-ast.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc summary --require-executable-parity=61 --require-pandoc-version='pandoc 3.10'
php tools/pandoc-epub-reader-evidence.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc --require-native-ast-package-parity --require-executable-native-ast-parity --require-pandoc-version='pandoc 3.10' --require-runner-plan --require-no-validation-issues
```

Expected summaries:

```text
packages: total=61 compared=61 packageParsed=61 readerParsed=61 packageFailures=0 readerFailures=0
normalizedAst: matches=61 (100.00%) mismatches=0
fixtureIdentity: status=valid-checked-in-current-epub-fixture-identity expected=122 observed=122
nativeAstPackageParity: totalEpubCount=61 normalizedAstMatchCount=61
executableNativeAstParity: totalEpubCount=61 normalizedAstMatchCount=61 pandocNativeFixtureMatchCount=61
```
