# EPUB blockquote list spine fixture

This increment adds a checked-in EPUB/native pair for `blockquote-list-spine.epub`.
The fixture stays small while exercising EPUB spine XHTML with a section id,
blockquote, ordered list start/style attributes, a bullet list, and a nav TOC
target. The `.native` golden was generated with `/opt/homebrew/bin/pandoc -f
epub -t native` and matches the local EPUB reader under the normalized AST
policy.

## Count deltas

- Checked-in EPUB package inputs: `58 -> 59`.
- Checked-in same-basename `.native` goldens: `58 -> 59`.
- Checked-in fixture identity files: `116 -> 118`.
- Package feature coverage: `fixtureCount=59`, `navFixtures=54`, `metadataCreators=55`, `manifestItems=216`, `readingOrderItems=90`, `resourceKinds` includes `navigation=60` and `xhtml=86`.
- Package feature signature: `c659ed7e76d699fb5297121c45c97974b8cb692414b2db47c450cacc7c2371f4`.
- Normalized native AST signature: `0e7f538f725944c268ee3f0c10b2111f1292a868e9a4b2b875c974e1668bde93`.

## Fixture hashes

```text
74fbf6f8f030e88a866ba652f72d0ec6864149c38ae8df45b9efd0bb4b8d0746  blockquote-list-spine.epub
dc459413755a1c07b599fb45cbb3cbd4c26bcdf8cf2caef31bdeaa1029bdfbc0  blockquote-list-spine.native
```

## Gates

```sh
php tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-package-parity=59 --require-native-readiness=59 --require-mapped-parity=59 --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan
php tools/pandoc-epub-executable-native-ast.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc summary --require-executable-parity=59 --require-pandoc-version='pandoc 3.10'
php tools/pandoc-epub-reader-evidence.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc --require-native-ast-package-parity --require-executable-native-ast-parity --require-pandoc-version='pandoc 3.10' --require-runner-plan --require-no-validation-issues
```

Expected summaries:

```text
packages: total=59 compared=59 packageParsed=59 readerParsed=59 packageFailures=0 readerFailures=0
normalizedAst: matches=59 (100.00%) mismatches=0
fixtureIdentity: status=valid-checked-in-current-epub-fixture-identity expected=118 observed=118
nativeAstPackageParity: totalEpubCount=59 normalizedAstMatchCount=59
executableNativeAstParity: totalEpubCount=59 normalizedAstMatchCount=59 pandocNativeFixtureMatchCount=59
```

The upstream Haskell/Tasty EPUB runner remains planned-not-run; this is
checked-in package/native/executable evidence, not a full upstream runner claim.
