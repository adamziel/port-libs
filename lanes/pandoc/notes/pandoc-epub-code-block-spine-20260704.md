# EPUB code block spine fixture

This increment adds a checked-in EPUB/native pair for `code-block-spine.epub`. The fixture keeps the package small while exercising a spine XHTML document that Pandoc 3.10 reads as a `CodeBlock`, preserving normalized native AST parity against both the local reader and the pandoc executable.

## Count deltas

- Checked-in EPUB package inputs: `57 -> 58`.
- Checked-in same-basename `.native` goldens: `57 -> 58`.
- Checked-in fixture identity files: `114 -> 116`.
- Package feature coverage: `fixtureCount=58`, `navFixtures=53`, `metadataCreators=54`, `manifestItems=214`, `readingOrderItems=89`, `resourceKinds` includes `navigation=59` and `xhtml=85`.
- Package feature signature: `3229cd8ed2d76f05a234fdbb51f2c55db520bc747d5040f3b878bf48c92c033c`.
- Normalized native AST signature: `9623d8fb5ac615eddd82449372bbc79214dcb9a1b5e7578489b13c5d800e6fe0`.

## Fixture hashes

```text
edce77261e123eb3aa3ef2614978a0854900a24ae02834d68f9625d70e5f5f3b  code-block-spine.epub
996f70134a1e706fb7525e5fbac1d2d7d7c2ae4d95bfa52a99f1cfee5e3137f8  code-block-spine.native
```

## Gates

```sh
php tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-package-parity=58 --require-native-readiness=58 --require-mapped-parity=58 --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan
php tools/pandoc-epub-executable-native-ast.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc summary --require-executable-parity=58 --require-pandoc-version='pandoc 3.10'
```

Expected summaries:

```text
packages: total=58 compared=58 packageParsed=58 readerParsed=58 packageFailures=0 readerFailures=0
normalizedAst: matches=58 (100.00%) mismatches=0
fixtureIdentity: status=valid-checked-in-current-epub-fixture-identity expected=116 observed=116
packageFeatureCoverage: fixtures=58 nav=53 ncx=4 covers=5 landmarks=20 pageLists=11 manifestItems=214
nativeAstPackageParity: totalEpubCount=58 normalizedAstMatchCount=58
executableNativeAstParity: totalEpubCount=58 normalizedAstMatchCount=58 pandocNativeFixtureMatchCount=58
```
