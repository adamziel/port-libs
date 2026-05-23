# Gitoxide Lane Root Harness Verification Note - 2026-05-23

Current verification after the prepared loose-reference delete slice:

- Focused gitoxide run: `32` test files, `2,646` assertions, `0` failures.
- Required root run: `php tools/run-tests.php` exited `0` with `183` test files, `18,198` assertions, and `0` failures.
- No current Gitoxide PHP blocker. Full upstream Cargo workspace parity remains unexecuted because the workspace is large and feature-heavy enough to hydrate/build beyond the current VM cap.

This Gitoxide slice maps prepared delete locks for loose references: no-deref symbolic reflog-only deletion, dereferenced reflog-only deletion, and reflog-delete failure before reference deletion. The WordPress reference transaction example now prunes a stale tenant review ref through a prepared delete lock.

Current verification after the prepared loose-reference reflog slice:

- Focused gitoxide run: `32` test files, `2,619` assertions, `0` failures.
- Required root run: `php tools/run-tests.php` exited `1` with `183` test files, `17,951` assertions, and `7` failures outside Gitoxide.
- Root failures observed outside this lane:
  - `lanes/lightningcss/tests/CssMinifierTest.php`: no-target nested parent-reference spaces, implicit nested selectors, attached nested selectors, and WordPress conditional block stylesheet imports.
  - `lanes/lightningcss/tests/NestingTransformerTest.php`: namespace-attached selector lowering and explicit nesting include/exclude targets.
  - `lanes/quadrable/tests/QuadbStoreTest.php`: upstream full-head LMDB cursor slice restore witness rejection.

This Gitoxide slice maps prepared transaction reflog behavior: object-update reflogs are appended before prepared locks are published, missing committers fail before lock publication when a reflog would be written, same-object updates do not append a new reflog entry, empty reflog directory blockers are recovered, and namespaced tenant branch refs auto-create audit reflogs. The WordPress reference transaction example now commits prepared tenant review refs with audit reflog lines.

Current verification after the loose-reference directory blocker recovery slice:

- Focused gitoxide run: `32` test files, `2,537` assertions, `0` failures.
- Required root run: `php tools/run-tests.php` passed with `178` test files, `17,211` assertions, and `0` failures.

This slice maps the exact upstream gix-ref directory-blocker recovery test: an empty directory tree at the loose-ref path is removed before the ref file is written, while non-empty directory blockers remain errors. The WordPress reference transaction example now covers an interrupted tenant deploy that left an empty `HEAD` directory before the symbolic HEAD update.

Historical multi-head merge-base context is retained below. That slice added `MergeBaseFinder::mergeBasesMany()` and `mergeBaseMany()` for upstream-shaped multi-head/octopus merge-base selection, plus a WordPress plugin/theme/content review-branch release-baseline example. Its root run was red because of unrelated Pandoc lane failures from missing `PortLibs\Pandoc\MarkdownReader::collectSpannedGridTableLines()`.

Prior external merge-driver shell-demotion context is retained below. That slice removed the default `proc_open()` execution fallback from `ExternalMergeDriverCommand::run()`. Native progress covers command preparation, caller-injected runner status handling, and `%A` readback only; shell-backed process launch remains outside the native lane and is not counted.

Historical failure sample from the previous external merge-driver readback slice is retained below for audit context.

Gitoxide lane verification after the external merge-driver readback slice passed:

- `32` gitoxide test files
- `2,498` assertions
- `0` failures

Required root verification was red outside the gitoxide lane:

- First full run: `php tools/run-tests.php` exited `1` with `174` test files, `15,849` assertions, and `83` failures.
- Failure-only rerun: `php tools/run-tests.php 2>&1 | rg '^(FAIL|[0-9]+ test files)'` reported `174` test files, `15,861` assertions, and `83` failures.

Exact failing tests from the failure-only rerun:

- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream typescript export equals assignments
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream top level import equals declarations
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream exported import equals declarations
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream typescript runtime enum declarations
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: folds upstream enum member constants and split enum blocks
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: inlines upstream same file enum member references
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: erases non exported const enums while inlining same file accesses
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream typescript type annotation erasure subset
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream typescript using declarations
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: maps upstream using nullish initializer optimization boundaries
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream using declarations through explicit resource helpers
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: renames upstream for using helper symbols when source names collide
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream block scoped using declarations through explicit resource helpers
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream control flow block scoped using declarations through explicit resource helpers
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: erases upstream class async generator method await using types
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream class async generator method await using cleanup
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: erases upstream object async generator method await using types
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream object async generator method await using cleanup
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream function scoped using declarations through explicit resource helpers
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: erases upstream function scoped typescript using declarations
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream for using declarations with erased types
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream for using loops through explicit resource helpers
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: erases upstream ambient typescript declarations
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: erases upstream decorated abstract ambient class declarations
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: erases upstream class member declare fields
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream abstract class members and headers
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: erases upstream class method type parameters and optional markers
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream typescript auto accessor markers and types
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream class fields in assign semantics mode
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream static class fields in assign semantics mode
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: caches upstream computed class field keys in assign semantics mode
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: preserves upstream computed class field key order in derived assign semantics classes
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: preserves upstream computed class key side effect order in assign semantics mode
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream constructor parameter properties
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream constructor parameter properties in assign semantics mode
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: inserts upstream derived constructor parameter properties after super
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: wraps upstream multiple derived super calls for parameter properties
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: wraps upstream conditional derived super calls for parameter properties
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: wraps upstream logical assignment derived super calls for assign semantics fields
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: keeps upstream dead false super branches outside the helper path
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: injects upstream assign semantics fields into one line derived constructors
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: splits upstream comma expression derived super calls before assignment injection
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: splits upstream return and throw comma expression derived super calls before assignment injection
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: splits upstream switch tests and for initializers around derived super calls
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers upstream private class fields in assign semantics super insertion
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: keeps non ambient declare line breaks and rejects malformed export as namespace
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress commonjs block export without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress typed block callbacks without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress runtime enum config without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress const enum config without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress enum alias config without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: erases wordpress ambient type declarations without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: erases wordpress ambient exported class declarations without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: erases wordpress declared class fields without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress constructor property controller without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress constructor properties in assign semantics without field declarations
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress class field assign semantics without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress computed class field asset keys without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress computed super controller without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress conditional super constructor controller without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress lazy super controller without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress comma super controller without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress return super controller without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress control statement super controllers without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress private settings controller without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress auto accessor controller without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress using disposable asset handles without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress function scoped disposable asset handles without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress function scoped disposable asset cleanup without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress block scoped disposable asset cleanup without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress for using asset loops without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress for using asset cleanup without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress for using asset cleanup with colliding helper names without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress async asset queue using cleanup without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress async generator asset queue class cleanup without node
- `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`: lowers wordpress object async generator asset queue cleanup without node
- `lanes/esbuild/tests/TypeScriptNamespaceLowererTest.php`: renames upstream namespace using helper symbols when source names collide
- `lanes/esbuild/tests/TypeScriptNamespaceLowererTest.php`: maps upstream namespace value merge declaration rules
- `lanes/esbuild/tests/TypeScriptNamespaceLowererTest.php`: lowers wordpress destructured namespace settings without node
- `lanes/esbuild/tests/TypeScriptNamespaceLowererTest.php`: lowers wordpress function namespace merge settings without node
- `lanes/esbuild/tests/TypeScriptNamespaceLowererTest.php`: lowers wordpress namespace scoped disposable preview asset without node
- `lanes/esbuild/tests/TypeScriptNamespaceLowererTest.php`: lowers wordpress namespace disposable preview with colliding helper names without node
- `lanes/quadrable/tests/QuadbStoreTest.php`: native quadb store matches upstream LMDB cursor oracle for binary proof heads
