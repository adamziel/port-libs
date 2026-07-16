# Pandoc JSON/native mixed figure handoff

Slice: `plib-w2w18` JSON/native AST constructor completeness.

`PandocJsonWriter` and `NativeWriter` already flushed mixed Figure children
into block payloads, and the readers already reconstructed those blocks. The
remaining handoff gap was WordPress output: a Figure without a single image
body was still forced through the image-only block path, synthesizing an empty
`img` and dropping preserved link/raw/code child blocks.

`WordPressBlockWriter` now keeps image-only figures on `wp:image`, but emits
mixed-content figures as raw HTML with preserved figure attributes, paragraph
boundaries, code blocks, raw HTML inline payloads, and figcaption text. This
keeps JSON/native Figure constructor payloads reviewable after round-trip
without invoking Pandoc, browser engines, or external validators.

Focused validation:

- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeMixedFigureHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeMixedFigureHandoffTest.php lanes/pandoc/tests/PandocJsonNativeCollectionWrapperTest.php lanes/pandoc/tests/PandocNativeWriterJsonProvenanceTest.php`
  - Result: 3 files, 139 assertions, 0 failures.

`PandocJsonNativeAstTest.php` remains baseline-red outside this focused slice;
the mixed figure case now passes while seven unrelated existing failures remain.
