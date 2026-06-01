# Gitoxide Receive-Pack Transport Header Boundary Parity

Micro-slice: `gitoxide-receive-pack-transport-boundary-parity-20260601T052702Z`

Source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/mod.rs`
  - `Transport::new_http()` initializes smart HTTP with `User-Agent: git/oxide-*`.
  - discovery and request headers carry the transport user-agent across receive-pack GET and POST boundaries.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs`
  - curl setup appends a blank `Expect:` header before upload to suppress `100-continue`.

Implemented behavior:

- `SmartHttpReceivePackTransport` now sends a Gitoxide-style default `User-Agent` on both discovery and receive-pack POST requests.
- Caller-provided `User-Agent` headers still override the default on both boundaries.
- Receive-pack POST requests always set a blank `Expect` header after caller header merging, suppressing `100-continue` while preserving the request body.
- The WordPress receive-pack fixture and example expose the default/override header boundary and POST body preservation.

Focused evidence:

- Baseline before this slice:
  - `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  - `1 test files, 795 assertions, 0 failures`
- Red-first check after adding the header-boundary assertions only:
  - `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  - failed in `smart http receive-pack transport strips service advertisement and posts request`: expected `git/oxide-port-libs`, actual `NULL` for the discovery `User-Agent`.
- Green focused check after implementation and fixture/example coverage:
  - `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  - `1 test files, 811 assertions, 0 failures`

Final verification:

- `php -l lanes/gitoxide/src/SmartHttpReceivePackTransport.php` - no syntax errors.
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php` - no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php` - no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php` - no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` - `json ok`.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php` - `1 test files, 811 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` - exit 0.
- `git diff --check -- lanes/gitoxide` - exit 0.

Non-overlap:

- This does not revisit accepted smart HTTP redirect, cookie, proxy/noProxy, credential, content-type, packet-line, send-pack, SSH, git-daemon, ref transaction, object database, pathspec, merge-base, or sparse-checkout behavior.
- It maps one bounded receive-pack smart HTTP transport header boundary from upstream gix-transport.

Dependency closure:

- No new support component is needed.
- The slice reuses the existing native smart HTTP requester/header normalization, receive-pack client, fixture packet helpers, and example smoke path.
- Live provider/network tests and the upstream Cargo workspace were not executed for this isolated micro-slice.
