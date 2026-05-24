# Isolate Syncthing BEP Native-Path Slice

Timestamp: 2026-05-24T22:01Z

## Result

Accepted as an isolated patch candidate. Ready marker created.

## Worktree

- Clean detached worktree: `/tmp/port-isolate-syncthing-bep-path-20260524T215758Z`
- Base commit: `30f1c09c9059e8b6ac4fbf5b295cda3060ec298c`
- Patch path: `.tmux-team/tmp/isolate-syncthing-bep-path-20260524T215758Z.patch`
- Ready marker: `.tmux-team/tmp/handoff-candidates/port-isolate-syncthing-bep-path.ready`

## Touched Files

- `lanes/syncthing/src/BepSession.php`
- `lanes/syncthing/src/ProtocolValidation.php`
- `lanes/syncthing/src/Index.php`
- `lanes/syncthing/src/IndexUpdate.php`
- `lanes/syncthing/src/Request.php`
- `lanes/syncthing/tests/BepSessionTest.php`
- `lanes/syncthing/examples/wordpress-bep-session.php`

Patch stat from the clean worktree:

```text
7 files changed, 260 insertions(+), 2 deletions(-)
```

## Scope

Rebuilt only the BEP inbound native-model path slice identified by `audits/preflight-syncthing-bep-native-path-20260524T2210Z.md`:

- `BepSession` accepts `nativeDirectorySeparator`.
- Inbound `Index` and `IndexUpdate` file names are converted to native model paths before handler dispatch.
- Inbound Windows-invalid model names containing the native separator are filtered from `Index` and `IndexUpdate`.
- Inbound valid `Request` names are converted before request handler invocation.
- Inbound Windows-invalid `Request` names return `NO_SUCH_FILE`, skip the handler, and keep the session open.
- The WordPress example reports aggregate booleans for native inbound path conversion and filtering.

## Excluded Dirty-Main Changes

The patch intentionally excludes dirty-main Syncthing changes outside this slice, including:

- Folder scan/watch services and scheduler work.
- Folder completion/errors/statistics/summary/database API changes.
- Request exchange, dispatcher close-handler, coordinator, and connection lifecycle changes.
- REST/config/debug/system/discovery/GUI examples, sources, and tests.
- Syncthing manifest, lane status, notes, and dashboard metadata changes.
- Support-library backlog changes; no support-library row was activated.

## Verification

All commands below were run from `/tmp/port-isolate-syncthing-bep-path-20260524T215758Z`.

| Command | Exit | Evidence |
| --- | ---: | --- |
| `jq empty lanes/syncthing/UPSTREAM_TEST_MANIFEST.json lanes/syncthing/lane-status.json` | 0 | JSON valid; neither JSON file is touched by the patch. |
| `php -l lanes/syncthing/src/BepSession.php` | 0 | No syntax errors. |
| `php -l lanes/syncthing/src/ProtocolValidation.php` | 0 | No syntax errors. |
| `php -l lanes/syncthing/src/Index.php` | 0 | No syntax errors. |
| `php -l lanes/syncthing/src/IndexUpdate.php` | 0 | No syntax errors. |
| `php -l lanes/syncthing/src/Request.php` | 0 | No syntax errors. |
| `php -l lanes/syncthing/tests/BepSessionTest.php` | 0 | No syntax errors. |
| `php -l lanes/syncthing/examples/wordpress-bep-session.php` | 0 | No syntax errors. |
| `php tools/run-tests.php lanes/syncthing/tests/BepSessionTest.php` | 0 | 1 selected test file, 75 assertions, 0 failures. |
| `php lanes/syncthing/examples/wordpress-bep-session.php \| jq empty` | 0 | Example emitted valid JSON. |
| `git diff --check -- lanes/syncthing` | 0 | No whitespace errors. |
| `git diff --binary > /home/claude/port-libs/.tmux-team/tmp/isolate-syncthing-bep-path-20260524T215758Z.patch` | 0 | Patch written from the clean worktree. |
| `git -C /tmp/port-isolate-syncthing-bep-path-applycheck apply --check /home/claude/port-libs/.tmux-team/tmp/isolate-syncthing-bep-path-20260524T215758Z.patch` | 0 | Patch applies cleanly to the recorded base `30f1c09c9059e8b6ac4fbf5b295cda3060ec298c`. |

Note: a first apply check against the then-current moving main `HEAD` (`cd6a6b69`) failed because `HEAD` advanced after the slice worktree was created. The candidate is therefore explicitly based on `30f1c09c9059e8b6ac4fbf5b295cda3060ec298c`.

## Decision

Integrator should accept this isolated patch if it applies to the intended clean base. It is lane-local BEP protocol behavior and does not include the broad dirty Syncthing handoff surface rejected earlier.
