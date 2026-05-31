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
        $t->throws(RuntimeException::class, static fn () => PushResponse::fromReportStatusPacketLines($packet("ERR hook failed\n") . $flush));
    },
    'wordpress fixture parses deployment branch and tag push status' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-protocol-v1-push-response.php';
        $response = PushResponse::fromSidebandPacketLines($fixture['response']);
        $rewritten = PushResponse::fromReportStatusPacketLines($fixture['rewrittenResponse'])->refStatuses()[0];

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

        $summary = require dirname(__DIR__) . '/examples/wordpress-protocol-v1-push-response.php';
        $t->same(true, $summary['oversizedReportStatusRejected']);
    },
];
