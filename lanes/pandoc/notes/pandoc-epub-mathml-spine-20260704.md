# EPUB MathML spine fixture

This increment adds a checked-in EPUB 3 package/native pair for `mathml-spine.epub`. The fixture exercises an XHTML reading-order document with embedded MathML plus the OPF `mathml` manifest property; the MathML includes TeX annotations so Pandoc 3.10 and the local reader normalize inline and display math the same way.

## Count deltas

- Checked-in EPUB package inputs: `61 -> 62`.
- Checked-in same-basename `.native` goldens: `61 -> 62`.
- Checked-in fixture identity files: `122 -> 124`.
- Package/native/executable parity: `61 -> 62`.
- Package feature coverage: `fixtureCount=62`, `navFixtures=57`, `metadataCreators=58`, `manifestItems=222`, `readingOrderItems=93`, `xhtmlAssets=146`, `navigationEntries=152`, `guideReferences=22`.
- Package feature signature: `26e64b9c392aaa0038ab6cf47f8bb1b156787082c880a07390b933b27b67e376`.
- Normalized native AST signature: `bcec018580b4bc21ed32c636b56e976ad9d04b99eb1288f97a0f9680fc81da0c`.

## Fixture hashes

```text
c89ff2507ce6ca380f20bdf0e4d2ca15f27baf0c9a68fac7f482587727a568b3  mathml-spine.epub
394d586dfd52a7717a6989f20d0e034d9ac1dbb0c904d43ed2ae598b91be81d0  mathml-spine.native
```

## Gates

```sh
php tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-package-parity=62 --require-native-readiness=62 --require-mapped-parity=62 --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan
php tools/pandoc-epub-executable-native-ast.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc summary --require-executable-parity=62 --require-pandoc-version='pandoc 3.10'
php tools/pandoc-epub-reader-evidence.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc --require-native-ast-package-parity --require-executable-native-ast-parity --require-pandoc-version='pandoc 3.10' --require-runner-plan --require-no-validation-issues
```

Expected summaries:

```text
packages: total=62 compared=62 packageParsed=62 readerParsed=62 packageFailures=0 readerFailures=0
normalizedAst: matches=62 (100.00%) mismatches=0
fixtureIdentity: status=valid-checked-in-current-epub-fixture-identity expected=124 observed=124
nativeAstPackageParity: totalEpubCount=62 normalizedAstMatchCount=62
executableNativeAstParity: totalEpubCount=62 normalizedAstMatchCount=62 pandocNativeFixtureMatchCount=62
```
