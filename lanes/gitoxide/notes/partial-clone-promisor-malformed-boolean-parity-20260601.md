# Partial-Clone Promisor Malformed Boolean Parity

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T194354Z`
Base accepted HEAD: `717fdb296ffb612f8a5e6c844680b41c0b18437c`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config-value/src/boolean.rs`
  parses Git booleans as `yes/on/true`, `no/off/false`, empty false, and
  numeric zero/non-zero. Any other value produces the diagnostic:
  `Booleans need to be 'no', 'off', 'false', '' or 'yes', 'on', 'true' or any number`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/access/comfort.rs`
  preserves invalid boolean parse errors through boolean accessors instead of
  silently treating malformed values as false.

## Native Delta

- `ObjectDatabase::promisorRemotes()` now rejects malformed
  `remote.<name>.promisor` config booleans instead of silently treating the
  remote as non-promisor.
- `PartialCloneTest.php` adds a focused malformed boolean case and extends the
  WordPress lazy-promisor example assertions.
- `wordpress-lazy-promisor-fetch.php` now includes a local malformed
  promisor-config fixture so the example proves deployment config typos do not
  silently disable blobless hydration.
- `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` record phpPass
  `10693 -> 10699 (+6)` and mapped coverage `1817 -> 1818 / 2886`.

## Red-First Evidence

Before the source change, this probe accepted `promisor = sometimes` as a
non-promisor remote:

```sh
php -r 'require "tools/bootstrap.php"; $gitDir = sys_get_temp_dir() . "/port-libs-git-promisor-invalid-red-" . bin2hex(random_bytes(4)) . "/.git"; mkdir($gitDir . "/objects/pack", 0777, true); file_put_contents($gitDir . "/config", "[remote \"origin\"]\n\turl = https://git.example.test/wp-content.git\n\tpromisor = sometimes\n\tpartialCloneFilter = blob:none\n"); $db = new PortLibs\Gitoxide\ObjectDatabase($gitDir); try { var_export($db->promisorRemotes()); echo "\nred-first: invalid promisor boolean was accepted as non-promisor\n"; } catch (RuntimeException $e) { echo "already throws: " . $e->getMessage() . "\n"; }'
```

Observed output:

```text
array (
)
red-first: invalid promisor boolean was accepted as non-promisor
```

## Verification

- `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - `No syntax errors detected in lanes/gitoxide/src/ObjectDatabase.php`
- `php -l lanes/gitoxide/tests/PartialCloneTest.php`
  - `No syntax errors detected in lanes/gitoxide/tests/PartialCloneTest.php`
- `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - `No syntax errors detected in lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
- `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 454 assertions, 0 failures`
- `php -r '$summary = require "lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php"; $message = (string) ($summary["invalidPromisorConfigMessage"] ?? ""); if (!str_contains($message, "Invalid Git config boolean value for remote promisor: sometimes") || !str_contains($message, "Booleans need to be")) { fwrite(STDERR, "missing invalid promisor config guard\n"); exit(1); } echo "example ok: invalid promisor config rejected\n";'`
  - `example ok: invalid promisor config rejected`
- `php -r 'foreach (["lanes/gitoxide/lane-status.json", "lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"] as $path) { json_decode((string) file_get_contents($path), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $path . ": " . json_last_error_msg() . "\n"); exit(1); } } echo "json ok\n";'`
  - `json ok`
- `git diff --check -- lanes/gitoxide`
  - passed after adding this note.

Root harness: not run - isolated micro-slice.
Full upstream Cargo workspace: not run.

## Non-Overlap

This slice does not repeat accepted config-only promisor hydration, numeric
boolean parsing, refresh-never behavior, external promisor pack refresh,
pack-bundle writes, empty bundle handling, thin-pack repair, stale MIDX
filtering, recursion-bound checks, URL/refspec, reference, protocol,
transport, sparse-checkout, tree/pathspec, loose-object, or merge-base slices.
It is limited to malformed promisor config boolean rejection.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
config reader, `ObjectDatabase`, `PromisorObjectResolver`, focused test
harness, and local WordPress example fixtures. It does not invoke git, network
services, providers, credentials, or live auth state.

## Next Task

Continue partial-clone parity on non-overlapping gaps such as shallow/promisor
graph-object lookup interaction, promisor pack invalidation after explicit
garbage collection, or parser-level fetch filter validation that can be proven
with focused PHP tests.
