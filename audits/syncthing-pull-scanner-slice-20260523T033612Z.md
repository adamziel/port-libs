# Syncthing Pull Scanner Slice - 2026-05-23T03:36:12Z

## Scope

Implemented one bounded native PHP Syncthing `pullScannerRoutine` slice for
scan aggregation and deferred post-pull scan scheduling. The slice is local
state-machine/data-structure behavior only.

Owned paths touched:

- `lanes/syncthing/src/PullScanner.php`
- `lanes/syncthing/src/PullScanScheduleResult.php`
- `lanes/syncthing/src/PullFinisher.php`
- `lanes/syncthing/tests/PullScannerTest.php`
- `lanes/syncthing/examples/wordpress-post-pull-scan-scheduler.php`
- `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`
- `lanes/syncthing/lane-status.json`
- `lanes/syncthing/notes/wordpress-scenarios.md`
- `audits/syncthing-pull-scanner-slice-20260523T033612Z.md`

No other lanes, `progress.md`, `porting.html`, or `porting-summary.json` were
edited by this worker.

## Upstream Evidence Used

Static targeted source reads from upstream Syncthing commit
`3962a237232473c20a44945a6c8ce8c930375360`:

- `lib/model/folder_sendrecv.go` `pullScannerRoutine`: scan paths are collected
  into a set and one `Scan(scanList)` is called after `scanChan` closes.
- `finisherRoutine`, `performFinish`, and `scanIfItemChanged`: finalization can
  enqueue scan names before returning errors such as `file modified but not
  rescanned`.
- `checkToBeDeleted` and `deleteDirOnDiskHandleChildren`: delete paths can
  enqueue file or directory scan names, including receive-only directory
  resurrection.

No full upstream `go test ./...` runner was attempted for this slice.

## Native Behavior Added

- `PullScanner` queues scan paths while a pull is open, de-duplicates by path,
  tracks file/directory/unknown/mixed classifications for PHP bookkeeping, and
  invokes the scan callback once on `close()`.
- `PullScanner` keeps scans deferred until close, treats repeated close as
  idempotent, rejects queueing after close, and records callback errors in the
  schedule result.
- `PullFinisher` now hands finalization `scanNames` to an optional
  `PullScanner`, so failed finalization paths still schedule a deferred scan
  without running the scan during finalization.
- `wordpress-post-pull-scan-scheduler.php` demonstrates local-first media and
  stale Playground folder scan requests remaining pending until the post-pull
  scanner closes.

## Verification

Commands run:

```sh
php -l lanes/syncthing/src/PullScanScheduleResult.php && php -l lanes/syncthing/src/PullScanner.php && php -l lanes/syncthing/src/PullFinisher.php && php -l lanes/syncthing/tests/PullScannerTest.php && php -l lanes/syncthing/examples/wordpress-post-pull-scan-scheduler.php
```

Result: all five files reported `No syntax errors detected`.

```sh
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $root=getcwd(); $files=glob($root . "/lanes/syncthing/tests/*Test.php") ?: []; sort($files); $runner=new TestRunner(); foreach ($files as $file) { $tests=require $file; if (!is_array($tests)) { throw new RuntimeException("Test file did not return an array: {$file}"); } $runner->runTests($tests, str_replace($root . "/", "", $file)); } fwrite(STDOUT, "\n" . count($files) . " test files, " . $runner->assertions() . " assertions, " . $runner->failures() . " failures\n"); exit($runner->failures() === 0 ? 0 : 1);'
```

Result: `36 test files, 1766 assertions, 0 failures`.

```sh
php lanes/syncthing/examples/wordpress-post-pull-scan-scheduler.php
```

Result: exited 0 and emitted one scheduled batch containing:

- `wp-content/uploads/2026/local-first-hero.jpg`
- `wp-content/uploads/2026/playground-export-cache`
- `wp-content/uploads/2026/playground-export-cache/local-crops`

```sh
php tools/run-tests.php
```

Result: `179 test files, 17287 assertions, 0 failures`.

```sh
php -r 'foreach (["lanes/syncthing/UPSTREAM_TEST_MANIFEST.json", "lanes/syncthing/lane-status.json"] as $file) { json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'
```

Result: both Syncthing JSON metadata files decoded successfully.

```sh
git diff --check -- lanes/syncthing
git diff --check -- audits/syncthing-pull-scanner-slice-20260523T033612Z.md
```

Result: both commands exited 0 with no whitespace errors.

## Blockers

None for the native PHP slice. Full upstream Syncthing runner parity remains
out of scope for this worker run because the shared upstream cache is a
blob-filtered/no-checkout checkout with mass tracked deletions, and broad Go
runner hydration would require the full module graph and 141 upstream Go test
files plus integration paths.
