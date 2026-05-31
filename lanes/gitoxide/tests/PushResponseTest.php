<?php

declare(strict_types=1);

use PortLibs\Gitoxide\PushRefStatus;
use PortLibs\Gitoxide\PushResponse;

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$flush = '0000';
$invalidArgumentMessage = static function (callable $callback): string {
    try {
        $callback();
    } catch (InvalidArgumentException $error) {
        return $error->getMessage();
    }

    throw new RuntimeException('Expected InvalidArgumentException was not thrown');
};
$runtimeMessage = static function (callable $callback): string {
    try {
        $callback();
    } catch (RuntimeException $error) {
        return $error->getMessage();
    }

    throw new RuntimeException('Expected RuntimeException was not thrown');
};

return [
    'parses receive-pack report status without sideband' => static function (TestRunner $t) use ($packet, $flush): void {
        $response = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/heads/main\n")
            . $flush
        );

        $t->same(true, $response->unpackOk());
        $t->same(true, $response->isSuccessful());
        $t->same('ok', $response->unpackStatus());
        $t->same(PushRefStatus::OK, $response->refStatuses()[0]->status);
        $t->same('refs/heads/main', $response->refStatuses()[0]->refName);
    },
    'parses rejected refs and unpack errors' => static function (TestRunner $t) use ($packet, $flush): void {
        $rejected = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ng refs/heads/main non-fast-forward\n")
            . $flush
        );
        $unpackFailed = PushResponse::fromReportStatusPacketLines(
            $packet("unpack index-pack failed\n")
            . $packet("ng refs/heads/main unpacker error\n")
            . $flush
        );

        $t->same(false, $rejected->isSuccessful());
        $t->same(PushRefStatus::REJECTED, $rejected->refStatuses()[0]->status);
        $t->same('non-fast-forward', $rejected->refStatuses()[0]->message);
        $t->same('refs/heads/main', $rejected->rejectedRefs()[0]->refName);
        $t->same(false, $unpackFailed->unpackOk());
        $t->same('index-pack failed', $unpackFailed->unpackError());
    },
    'parses report-status-v2 rewritten ref options' => static function (TestRunner $t) use ($packet, $flush): void {
        $old = '58F4F2BE1F149A49F7234F4BBD3B1B8C92A6D61A';
        $new = '7B333369DE1221F9BFBBE03A3A13E9A09BC1C907';
        $response = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/for/main\n")
            . $packet("option refname refs/heads/main\n")
            . $packet("option old-oid {$old}\n")
            . $packet("option new-oid {$new}\n")
            . $packet("option forced-update\n")
            . $flush
        );
        $status = $response->refStatuses()[0];

        $t->same('refs/for/main', $status->refName);
        $t->same('refs/heads/main', $status->reportedRefName);
        $t->same('refs/heads/main', $status->effectiveRefName());
        $t->same(strtolower($old), $status->oldObject);
        $t->same(strtolower($new), $status->newObject);
        $t->same(true, $status->forcedUpdate);
    },
    'parses report-status-v2 sha256 proc-receive rewritten refs' => static function (TestRunner $t) use ($packet, $flush): void {
        $old = str_repeat('A', 64);
        $new = str_repeat('B', 64);
        $response = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/for/wp-release\n")
            . $packet("option refname refs/heads/deploy/wp-release\n")
            . $packet("option old-oid {$old}\n")
            . $packet("option new-oid {$new}\n")
            . $packet("option forced-update\n")
            . $packet("ok refs/heads/main\n")
            . $flush
        );

        $rewritten = $response->refStatuses()[0];
        $unchanged = $response->refStatuses()[1];

        $t->same(true, $response->isSuccessful());
        $t->same('refs/for/wp-release', $rewritten->refName);
        $t->same('refs/heads/deploy/wp-release', $rewritten->effectiveRefName());
        $t->same(strtolower($old), $rewritten->oldObject);
        $t->same(strtolower($new), $rewritten->newObject);
        $t->same(true, $rewritten->forcedUpdate);
        $t->same('refs/heads/main', $unchanged->effectiveRefName());
        $t->same(null, $unchanged->oldObject);
        $t->same(null, $unchanged->newObject);
    },
    'parses report-status-v2 proc-receive fall-through refs' => static function (TestRunner $t) use ($packet, $flush): void {
        $response = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/for/wp-release\n")
            . $packet("option fall-through\n")
            . $packet("ok refs/heads/main\n")
            . $flush
        );

        $fallThrough = $response->refStatuses()[0];
        $ordinary = $response->refStatuses()[1];

        $t->same(true, $response->isSuccessful());
        $t->same('refs/for/wp-release', $fallThrough->refName);
        $t->same('refs/for/wp-release', $fallThrough->effectiveRefName());
        $t->same(true, $fallThrough->fallThrough);
        $t->same(false, $fallThrough->forcedUpdate);
        $t->same(null, $fallThrough->oldObject);
        $t->same(null, $fallThrough->newObject);
        $t->same(false, $ordinary->fallThrough);
    },
    'parses send-pack receive-status compatibility extensions like upstream Git' => static function (TestRunner $t) use ($packet, $flush): void {
        $staleOld = str_repeat('1', 40);
        $currentOld = str_repeat('2', 40);
        $new = str_repeat('3', 64);
        $response = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/for/wp-release accepted by proc-receive\n")
            . $packet("option refname refs/heads/stale-wp-release\n")
            . $packet("option refname refs/heads/deploy/wp-release\n")
            . $packet("option unknown-future-extension ignored\n")
            . $packet("option old-oid {$staleOld}\n")
            . $packet("option old-oid {$currentOld}\n")
            . $packet("option new-oid {$new}\n")
            . $packet("option forced-update true\n")
            . $packet("ng refs/heads/protected\n")
            . $flush
        );

        $accepted = $response->refStatuses()[0];
        $rejected = $response->refStatuses()[1];

        $t->same(true, $response->unpackOk());
        $t->same(false, $response->isSuccessful());
        $t->same('refs/for/wp-release', $accepted->refName);
        $t->same('accepted by proc-receive', $accepted->message);
        $t->same('refs/heads/deploy/wp-release', $accepted->effectiveRefName());
        $t->same($currentOld, $accepted->oldObject);
        $t->same($new, $accepted->newObject);
        $t->same(true, $accepted->forcedUpdate);
        $t->same(false, $accepted->fallThrough);
        $t->same(PushRefStatus::REJECTED, $rejected->status);
        $t->same('refs/heads/protected', $rejected->refName);
        $t->same('failed', $rejected->message);
    },
    'filters send-pack receive-status reports to requested refs like upstream Git' => static function (TestRunner $t) use ($packet, $flush): void {
        $old = str_repeat('4', 40);
        $new = str_repeat('5', 40);
        $response = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/heads/ghost ignored by send-pack\n")
            . $packet("ng refs/heads/main stale lock\n")
            . $packet("ok refs/for/wp-release accepted by proc-receive\n")
            . $packet("option refname refs/heads/deploy/wp-release\n")
            . $packet("option old-oid {$old}\n")
            . $packet("option new-oid {$new}\n")
            . $packet("ok refs/heads/main post-update hook accepted\n")
            . $flush
        );
        $filtered = $response->forExpectedRefNames(['refs/heads/main', 'refs/for/wp-release']);

        $t->same(4, count($response->refStatuses()));
        $t->same(2, count($filtered->refStatuses()));
        $t->same(true, $filtered->isSuccessful());
        $t->same('refs/heads/main', $filtered->refStatuses()[0]->refName);
        $t->same(PushRefStatus::OK, $filtered->refStatuses()[0]->status);
        $t->same('post-update hook accepted', $filtered->refStatuses()[0]->message);
        $t->same('refs/for/wp-release', $filtered->refStatuses()[1]->refName);
        $t->same('refs/heads/deploy/wp-release', $filtered->refStatuses()[1]->effectiveRefName());
        $t->same($old, $filtered->refStatuses()[1]->oldObject);
        $t->same($new, $filtered->refStatuses()[1]->newObject);
        $t->same([], $filtered->rejectedRefs());
    },
    'enforces upstream packet-line length bounds for receive-pack status' => static function (TestRunner $t) use ($packet, $flush, $invalidArgumentMessage): void {
        $maxPacketLineLength = 65520;
        $statusPrefix = 'ng refs/heads/main ';
        $reason = str_repeat('x', $maxPacketLineLength - 4 - strlen($statusPrefix) - 1);
        $bounded = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . sprintf('%04x', $maxPacketLineLength) . $statusPrefix . $reason . "\n"
            . $flush
        );

        $t->same($reason, $bounded->refStatuses()[0]->message);
        $t->contains('packet line exceeds maximum length', $invalidArgumentMessage(static fn () => PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . 'ffff' . str_repeat('x', 0xffff - 4)
            . $flush
        )));
        $t->contains('packet line exceeds maximum length', $invalidArgumentMessage(static fn () => PushResponse::fromSidebandPacketLines(
            'ffff' . "\x02" . str_repeat('x', 0xffff - 5)
            . $flush
        )));
        $t->contains('invalid empty packet line', $invalidArgumentMessage(static fn () => PushResponse::fromReportStatusPacketLines(
            '0004'
            . $flush
        )));
        $t->contains('invalid empty packet line', $invalidArgumentMessage(static fn () => PushResponse::fromSidebandPacketLines(
            $packet("\x01" . '0004')
            . $flush
        )));
    },
    'preserves upstream line-feed-only receive-status text trimming' => static function (TestRunner $t) use ($packet, $flush, $runtimeMessage): void {
        $rejected = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ng refs/heads/main hook declined\r\n")
            . $flush
        );
        $errorMessage = $runtimeMessage(static fn () => PushResponse::fromReportStatusPacketLines(
            $packet("ERR hook failed\r\n")
            . $flush
        ));

        $t->same("hook declined\r", $rejected->refStatuses()[0]->message);
        $t->same(true, str_contains($errorMessage, "hook failed\r"));
        $t->same(false, str_contains($errorMessage, "\n"));
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/heads/main\r\n")
            . $flush
        ));
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/for/main\n")
            . $packet("option refname refs/heads/main\r\n")
            . $flush
        ));
    },
    'parses upstream-shaped sideband push response' => static function (TestRunner $t) use ($packet, $flush): void {
        $advisory = "\nGitHub found 1 vulnerability on the-lean-crate/criner's default branch (1 high). To find out more, visit:\n"
            . "     https://github.com/the-lean-crate/criner/security/dependabot/1\n\n";
        $response = PushResponse::fromSidebandPacketLines(
            $packet("\x02Resolving deltas:   0% (0/2)\r")
            . $packet("\x02Resolving deltas:  50% (1/2)\r")
            . $packet("\x02Resolving deltas: 100% (2/2)\r")
            . $packet("\x02Resolving deltas: 100% (2/2), completed with 2 local objects.\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x02" . $advisory)
            . $packet("\x01" . $flush)
            . $flush
        );

        $t->same(true, $response->isSuccessful());
        $t->same(5, count($response->progressMessages()));
        $t->same("Resolving deltas:   0% (0/2)\r", $response->progressMessages()[0]);
        $t->same("Resolving deltas: 100% (2/2), completed with 2 local objects.", $response->progressMessages()[3]);
        $t->same(true, str_contains($response->progressMessages()[4], 'GitHub found 1 vulnerability'));
        $t->same('refs/heads/main', $response->refStatuses()[0]->refName);
    },
    'guards malformed push response packet streams' => static function (TestRunner $t) use ($packet, $flush): void {
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromSidebandPacketLines($packet("\x09bad band") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromReportStatusPacketLines($packet("unpack ok\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromReportStatusPacketLines($packet("ok refs/heads/main\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromReportStatusPacketLines($packet("unpack ok\n") . $packet("option refname refs/heads/main\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromReportStatusPacketLines($packet("unpack ok\n") . $packet("ok main\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromReportStatusPacketLines($packet("unpack ok\n") . $packet('ok refs/heads/main' . "\n") . $packet('option old-oid ' . str_repeat('f', 63) . "\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromReportStatusPacketLines($packet("unpack ok\n") . $packet("ok refs/heads/main\n") . $packet("option fall-through true\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromReportStatusPacketLines($packet("unpack ok\n") . $packet("ok refs/heads/main\n") . $packet("option fall-through\n") . $packet("option fall-through\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromReportStatusPacketLines($packet("unpack ok\n") . $packet("ng refs/heads/main rejected\n") . $packet("option fall-through\n") . $flush));
        $t->throws(RuntimeException::class, static fn () => PushResponse::fromReportStatusPacketLines($packet("ERR hook failed\n") . $flush));
    },
    'surfaces receive-pack fatal errors from sideband responses' => static function (TestRunner $t) use ($packet, $flush, $invalidArgumentMessage, $runtimeMessage): void {
        $t->contains('receive-pack error repository disabled', $runtimeMessage(
            static fn () => PushResponse::fromSidebandPacketLines($packet("ERR repository disabled\n") . $flush)
        ));
        $t->contains('sideband error pre-receive hook declined', $runtimeMessage(
            static fn () => PushResponse::fromSidebandPacketLines($packet("\x03pre-receive hook declined\n") . $flush)
        ));
        $t->contains('missing report-status flush packet', $invalidArgumentMessage(
            static fn () => PushResponse::fromSidebandPacketLines($packet("\x01" . $packet("unpack ok\n")) . $flush)
        ));
    },
    'wordpress fixture parses deployment branch and tag push status' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-protocol-v1-push-response.php';
        $response = PushResponse::fromSidebandPacketLines($fixture['response']);
        $rewritten = PushResponse::fromReportStatusPacketLines($fixture['rewrittenResponse'])->refStatuses()[0];
        $fallThrough = PushResponse::fromReportStatusPacketLines($fixture['fallThroughResponse'])->refStatuses()[0];
        $compatibility = PushResponse::fromReportStatusPacketLines($fixture['compatibilityResponse']);
        $compatibilityAccepted = $compatibility->refStatuses()[0];
        $compatibilityRejected = $compatibility->refStatuses()[1];

        $t->same(true, $response->isSuccessful());
        $t->same('ok', $response->unpackStatus());
        $t->same($fixture['refs'], array_map(
            static fn (PushRefStatus $status): string => $status->effectiveRefName(),
            $response->refStatuses()
        ));
        $t->same($fixture['progress'], $response->progressMessages());
        $t->same($fixture['rewrittenRef']['requested'], $rewritten->refName);
        $t->same($fixture['rewrittenRef']['actual'], $rewritten->effectiveRefName());
        $t->same($fixture['rewrittenRef']['oldObject'], $rewritten->oldObject);
        $t->same($fixture['rewrittenRef']['newObject'], $rewritten->newObject);
        $t->same(true, $rewritten->forcedUpdate);
        $t->same($fixture['fallThroughRef']['requested'], $fallThrough->refName);
        $t->same($fixture['fallThroughRef']['requested'], $fallThrough->effectiveRefName());
        $t->same(true, $fallThrough->fallThrough);
        $t->same($fixture['compatibilityRef']['requested'], $compatibilityAccepted->refName);
        $t->same($fixture['compatibilityRef']['actual'], $compatibilityAccepted->effectiveRefName());
        $t->same($fixture['compatibilityRef']['message'], $compatibilityAccepted->message);
        $t->same($fixture['compatibilityRef']['oldObject'], $compatibilityAccepted->oldObject);
        $t->same($fixture['compatibilityRef']['newObject'], $compatibilityAccepted->newObject);
        $t->same(true, $compatibilityAccepted->forcedUpdate);
        $t->same('refs/heads/protected', $compatibilityRejected->refName);
        $t->same('failed', $compatibilityRejected->message);

        $summary = require dirname(__DIR__) . '/examples/wordpress-protocol-v1-push-response.php';
        $t->same($fixture['expectedFilteredRefs'], array_map(
            static fn (array $status): string => $status['effectiveRef'],
            $summary['expectedFilteredRefs']
        ));
        $t->same(true, $summary['oversizedReportStatusRejected']);
        $t->same(true, $summary['fatalSidebandRejected']);
        $t->same(true, $summary['fallThroughAccepted']);
        $t->same(true, $summary['compatibilityOptionExtensionsIgnored']);
        $t->same(true, $summary['compatibilityBareRejectionDefaulted']);
        $t->same(true, $summary['expectedUnknownStatusIgnored']);
        $t->same(true, $summary['expectedLastStatusWon']);
        $t->same(true, $summary['carriageReturnStatusRejected']);
        $t->same(true, $summary['emptyPacketLineRejected']);
    },
];
