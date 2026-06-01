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
    'preserves empty unpack failure status like send-pack' => static function (TestRunner $t) use ($packet, $flush): void {
        $direct = PushResponse::fromReportStatusPacketLines(
            $packet("unpack \n")
            . $packet("ng refs/heads/main index-pack died\n")
            . $flush
        );
        $sideband = PushResponse::fromSidebandPacketLines(
            $packet("\x02remote: unpack failed without status text\n")
            . $packet("\x01" . $packet("unpack \n"))
            . $packet("\x01" . $packet("ng refs/heads/main index-pack died\n"))
            . $packet("\x01" . $flush)
            . $flush
        );

        $t->same('', $direct->unpackStatus());
        $t->same(false, $direct->unpackOk());
        $t->same('', $direct->unpackError());
        $t->same(false, $direct->isSuccessful());
        $t->same('refs/heads/main', $direct->rejectedRefs()[0]->refName);
        $t->same('index-pack died', $direct->rejectedRefs()[0]->message);
        $t->same('', $sideband->unpackStatus());
        $t->same(false, $sideband->unpackOk());
        $t->same(['remote: unpack failed without status text'], $sideband->progressMessages());
        $t->same('index-pack died', $sideband->rejectedRefs()[0]->message);
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
            . $packet("option old-oid {$staleOld} stale hook diagnostic\n")
            . $packet("option old-oid {$currentOld}\r\n")
            . $packet("option new-oid {$new} accepted by deployment hook\n")
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
    'preserves empty rejected ref status text after trailing space like send-pack' => static function (TestRunner $t) use ($packet, $flush): void {
        $direct = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ng refs/heads/main \n")
            . $flush
        );
        $sideband = PushResponse::fromSidebandPacketLines(
            $packet("\x02remote: deployment hook rejected without text\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ng refs/heads/main \n"))
            . $packet("\x01" . $flush)
            . $flush
        )->forExpectedRefNames(['refs/heads/main']);

        $t->same(false, $direct->isSuccessful());
        $t->same(PushRefStatus::REJECTED, $direct->refStatuses()[0]->status);
        $t->same('refs/heads/main', $direct->rejectedRefs()[0]->refName);
        $t->same('', $direct->rejectedRefs()[0]->message);
        $t->same(false, $sideband->isSuccessful());
        $t->same(['remote: deployment hook rejected without text'], $sideband->progressMessages());
        $t->same('', $sideband->rejectedRefs()[0]->message);
    },
    'parses report-status-v2 object-id options before trailing diagnostics' => static function (TestRunner $t) use ($packet, $flush): void {
        $oldSha1 = str_repeat('A', 40);
        $newSha256 = str_repeat('B', 64);
        $response = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/for/wp-release\n")
            . $packet("option refname refs/heads/deploy/wp-release\n")
            . $packet("option old-oid {$oldSha1} old object kept by proc-receive\n")
            . $packet("option new-oid {$newSha256}\r\n")
            . $flush
        );

        $status = $response->refStatuses()[0];

        $t->same('refs/heads/deploy/wp-release', $status->effectiveRefName());
        $t->same(strtolower($oldSha1), $status->oldObject);
        $t->same(strtolower($newSha256), $status->newObject);
        $t->same(true, $status->hasReportOption());
    },
    'ignores malformed report-status-v2 object-id option values like send-pack' => static function (TestRunner $t) use ($packet, $flush): void {
        $validOld = str_repeat('C', 40);
        $validNew = str_repeat('D', 64);
        $response = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/for/wp-release accepted by proc-receive\n")
            . $packet("option old-oid {$validOld}\n")
            . $packet("option new-oid not-a-hex-object deployment hook diagnostic\n")
            . $packet('option old-oid ' . str_repeat('f', 63) . "\n")
            . $packet("option new-oid {$validNew} accepted by deployment hook\n")
            . $flush
        );
        $invalidOnly = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/for/wp-preview accepted without object details\n")
            . $packet("option old-oid not-a-hex-object\n")
            . $packet('option new-oid ' . str_repeat('e', 63) . "\n")
            . $flush
        )->forExpectedRefNames(['refs/for/wp-preview']);
        $status = $response->refStatuses()[0];
        $invalidOnlyStatus = $invalidOnly->refStatuses()[0];

        $t->same(true, $response->isSuccessful());
        $t->same('refs/for/wp-release', $status->refName);
        $t->same(strtolower($validOld), $status->oldObject);
        $t->same(strtolower($validNew), $status->newObject);
        $t->same(true, $status->hasReportOption());
        $t->same(true, $invalidOnly->isSuccessful());
        $t->same(null, $invalidOnlyStatus->oldObject);
        $t->same(null, $invalidOnlyStatus->newObject);
        $t->same(true, $invalidOnlyStatus->hasReportOption());
    },
    'parses report-status-v2 object-id prefixes before non-hex diagnostics like send-pack' => static function (TestRunner $t) use ($packet, $flush): void {
        $old = str_repeat('A', 40);
        $new = str_repeat('B', 64);
        $direct = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/for/wp-release accepted after hook suffix diagnostics\n")
            . $packet("option old-oid {$old}#pre-receive-suffix\n")
            . $packet("option new-oid {$new}:accepted-by-hook\n")
            . $flush
        );
        $sideband = PushResponse::fromSidebandPacketLines(
            $packet("\x02remote: status suffix diagnostics\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/for/wp-release\n"))
            . $packet("\x01" . $packet("option old-oid {$old}#pre-receive-suffix\n"))
            . $packet("\x01" . $packet("option new-oid {$new}:accepted-by-hook\n"))
            . $packet("\x01" . $flush)
            . $flush
        )->forExpectedRefNames(['refs/for/wp-release']);
        $directStatus = $direct->refStatuses()[0];
        $sidebandStatus = $sideband->refStatuses()[0];

        $t->same(true, $direct->isSuccessful());
        $t->same('accepted after hook suffix diagnostics', $directStatus->message);
        $t->same(strtolower($old), $directStatus->oldObject);
        $t->same(strtolower($new), $directStatus->newObject);
        $t->same(true, $directStatus->hasReportOption());
        $t->same(true, $sideband->isSuccessful());
        $t->same(['remote: status suffix diagnostics'], $sideband->progressMessages());
        $t->same(strtolower($old), $sidebandStatus->oldObject);
        $t->same(strtolower($new), $sidebandStatus->newObject);
    },
    'parses report-status-v2 object ids with negotiated sha1 prefix length like send-pack' => static function (TestRunner $t) use ($packet, $flush): void {
        $oldPrefix = str_repeat('a', 40);
        $newPrefix = str_repeat('b', 40);
        $oldOverlong = $oldPrefix . 'feed';
        $newOverlong = $newPrefix . 'cafe';
        $raw = $packet("unpack ok\n")
            . $packet("ok refs/for/wp-release accepted with sha1 status prefix\n")
            . $packet("option old-oid {$oldOverlong}\n")
            . $packet("option new-oid {$newOverlong}\n")
            . $flush;
        $generic = PushResponse::fromReportStatusPacketLines($raw);
        $direct = PushResponse::fromReportStatusPacketLines($raw, 'sha1');
        $sideband = PushResponse::fromSidebandPacketLines(
            $packet("\x02remote: sha1 status object prefix applied\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/for/wp-release accepted with sha1 status prefix\n"))
            . $packet("\x01" . $packet("option old-oid {$oldOverlong}\n"))
            . $packet("\x01" . $packet("option new-oid {$newOverlong}\n"))
            . $packet("\x01" . $flush)
            . $flush,
            'sha1'
        );

        $t->same(null, $generic->refStatuses()[0]->oldObject);
        $t->same(null, $generic->refStatuses()[0]->newObject);
        $t->same($oldPrefix, $direct->refStatuses()[0]->oldObject);
        $t->same($newPrefix, $direct->refStatuses()[0]->newObject);
        $t->same(true, $direct->refStatuses()[0]->hasReportOption());
        $t->same(['remote: sha1 status object prefix applied'], $sideband->progressMessages());
        $t->same($oldPrefix, $sideband->refStatuses()[0]->oldObject);
        $t->same($newPrefix, $sideband->refStatuses()[0]->newObject);
    },
    'accepts valueless report-status-v2 options like send-pack' => static function (TestRunner $t) use ($packet, $flush): void {
        $response = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/for/wp-release accepted without rewrite details\n")
            . $packet("option refname\n")
            . $packet("option old-oid\n")
            . $packet("option new-oid \n")
            . $packet("option unknown-future-extension\n")
            . $flush
        );
        $filtered = $response->forExpectedRefNames(['refs/for/wp-release']);
        $status = $filtered->refStatuses()[0];

        $t->same(true, $response->isSuccessful());
        $t->same(true, $filtered->isSuccessful());
        $t->same(1, count($filtered->refStatuses()));
        $t->same('refs/for/wp-release', $status->refName);
        $t->same('refs/for/wp-release', $status->effectiveRefName());
        $t->same('accepted without rewrite details', $status->message);
        $t->same(null, $status->reportedRefName);
        $t->same(null, $status->oldObject);
        $t->same(null, $status->newObject);
        $t->same(false, $status->forcedUpdate);
        $t->same(true, $status->hasReportOption());
    },
    'preserves empty refname report-status-v2 option values like send-pack' => static function (TestRunner $t) use ($packet, $flush): void {
        $old = str_repeat('a', 40);
        $new = str_repeat('b', 40);
        $direct = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/for/wp-preview accepted with blank remote refname\n")
            . $packet("option refname \n")
            . $packet("option old-oid {$old}\n")
            . $packet("option new-oid {$new}\n")
            . $flush
        )->forExpectedRefNames(['refs/for/wp-preview']);
        $sideband = PushResponse::fromSidebandPacketLines(
            $packet("\x02remote: proc-receive returned an empty rewrite refname\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/for/wp-preview accepted with blank remote refname\n"))
            . $packet("\x01" . $packet("option refname \n"))
            . $packet("\x01" . $packet("option old-oid {$old}\n"))
            . $packet("\x01" . $packet("option new-oid {$new}\n"))
            . $packet("\x01" . $flush)
            . $flush
        )->forExpectedRefNames(['refs/for/wp-preview']);
        $directStatus = $direct->refStatuses()[0];
        $sidebandStatus = $sideband->refStatuses()[0];

        $t->same(true, $direct->isSuccessful());
        $t->same('refs/for/wp-preview', $directStatus->refName);
        $t->same('', $directStatus->reportedRefName);
        $t->same('', $directStatus->effectiveRefName());
        $t->same('accepted with blank remote refname', $directStatus->message);
        $t->same($old, $directStatus->oldObject);
        $t->same($new, $directStatus->newObject);
        $t->same(true, $directStatus->hasReportOption());
        $t->same(true, $sideband->isSuccessful());
        $t->same(['remote: proc-receive returned an empty rewrite refname'], $sideband->progressMessages());
        $t->same('', $sidebandStatus->reportedRefName);
        $t->same('', $sidebandStatus->effectiveRefName());
        $t->same($old, $sidebandStatus->oldObject);
        $t->same($new, $sidebandStatus->newObject);
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
    'preserves repeated proc-receive report-status-v2 reports for requested refs' => static function (TestRunner $t) use ($packet, $flush): void {
        $siteAOld = str_repeat('6', 40);
        $siteANew = str_repeat('7', 40);
        $siteBOld = str_repeat('8', 40);
        $siteBNew = str_repeat('9', 40);
        $response = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/for/wp-deploy\n")
            . $packet("option refname refs/heads/site-a\n")
            . $packet("option old-oid {$siteAOld}\n")
            . $packet("option new-oid {$siteANew}\n")
            . $packet("ok refs/for/wp-deploy\n")
            . $packet("option refname refs/heads/site-b\n")
            . $packet("option old-oid {$siteBOld}\n")
            . $packet("option new-oid {$siteBNew}\n")
            . $packet("ok refs/heads/main post-update hook accepted\n")
            . $flush
        );
        $filtered = $response->forExpectedRefNames(['refs/for/wp-deploy', 'refs/heads/main']);

        $t->same(3, count($response->refStatuses()));
        $t->same(3, count($filtered->refStatuses()));
        $t->same(true, $filtered->isSuccessful());
        $t->same('refs/for/wp-deploy', $filtered->refStatuses()[0]->refName);
        $t->same('refs/heads/site-a', $filtered->refStatuses()[0]->effectiveRefName());
        $t->same($siteAOld, $filtered->refStatuses()[0]->oldObject);
        $t->same($siteANew, $filtered->refStatuses()[0]->newObject);
        $t->same('refs/for/wp-deploy', $filtered->refStatuses()[1]->refName);
        $t->same('refs/heads/site-b', $filtered->refStatuses()[1]->effectiveRefName());
        $t->same($siteBOld, $filtered->refStatuses()[1]->oldObject);
        $t->same($siteBNew, $filtered->refStatuses()[1]->newObject);
        $t->same('refs/heads/main', $filtered->refStatuses()[2]->refName);
        $t->same('post-update hook accepted', $filtered->refStatuses()[2]->message);
    },
    'preserves proc-receive rewrites without refname options like upstream send-pack' => static function (TestRunner $t) use ($packet, $flush): void {
        $zero = str_repeat('0', 40);
        $old = str_repeat('a', 40);
        $new = str_repeat('b', 40);
        $changeOld = str_repeat('c', 40);
        $changeNew = str_repeat('d', 40);
        $response = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/for/main/topic\n")
            . $packet("option old-oid {$old}\n")
            . $packet("option new-oid {$new}\n")
            . $packet("ok refs/for/main/topic\n")
            . $packet("option refname refs/changes/24/124/1\n")
            . $packet("option old-oid {$zero}\n")
            . $packet("option new-oid {$changeOld}\n")
            . $packet("ok refs/for/main/topic\n")
            . $packet("option refname refs/changes/25/125/1\n")
            . $packet("option old-oid {$changeOld}\n")
            . $packet("option new-oid {$changeNew}\n")
            . $packet("option forced-update\n")
            . $flush
        );
        $sideband = PushResponse::fromSidebandPacketLines(
            $packet("\x02remote: proc-receive reported three rewrite records\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/for/main/topic\n"))
            . $packet("\x01" . $packet("option old-oid {$old}\n"))
            . $packet("\x01" . $packet("option new-oid {$new}\n"))
            . $packet("\x01" . $packet("ok refs/for/main/topic\n"))
            . $packet("\x01" . $packet("option refname refs/changes/24/124/1\n"))
            . $packet("\x01" . $packet("option old-oid {$zero}\n"))
            . $packet("\x01" . $packet("option new-oid {$changeOld}\n"))
            . $packet("\x01" . $packet("ok refs/for/main/topic\n"))
            . $packet("\x01" . $packet("option refname refs/changes/25/125/1\n"))
            . $packet("\x01" . $packet("option old-oid {$changeOld}\n"))
            . $packet("\x01" . $packet("option new-oid {$changeNew}\n"))
            . $packet("\x01" . $packet("option forced-update\n"))
            . $packet("\x01" . $flush)
            . $flush
        )->forExpectedRefNames(['refs/for/main/topic']);
        $filtered = $response->forExpectedRefNames(['refs/for/main/topic']);
        $first = $filtered->refStatuses()[0];
        $second = $filtered->refStatuses()[1];
        $third = $filtered->refStatuses()[2];

        $t->same(3, count($filtered->refStatuses()));
        $t->same(true, $filtered->isSuccessful());
        $t->same('refs/for/main/topic', $first->refName);
        $t->same('refs/for/main/topic', $first->effectiveRefName());
        $t->same($old, $first->oldObject);
        $t->same($new, $first->newObject);
        $t->same(false, $first->forcedUpdate);
        $t->same('refs/changes/24/124/1', $second->effectiveRefName());
        $t->same($zero, $second->oldObject);
        $t->same($changeOld, $second->newObject);
        $t->same(false, $second->forcedUpdate);
        $t->same('refs/changes/25/125/1', $third->effectiveRefName());
        $t->same($changeOld, $third->oldObject);
        $t->same($changeNew, $third->newObject);
        $t->same(true, $third->forcedUpdate);
        $t->same(true, $sideband->isSuccessful());
        $t->same(['remote: proc-receive reported three rewrite records'], $sideband->progressMessages());
        $t->same(
            ['refs/for/main/topic', 'refs/changes/24/124/1', 'refs/changes/25/125/1'],
            array_map(static fn (PushRefStatus $status): string => $status->effectiveRefName(), $sideband->refStatuses())
        );
    },
    'preserves proc-receive rewrite reports when a later duplicate status rejects like send-pack' => static function (TestRunner $t) use ($packet, $flush): void {
        $siteAOld = str_repeat('6', 40);
        $siteANew = str_repeat('7', 40);
        $siteBOld = str_repeat('8', 40);
        $siteBNew = str_repeat('9', 40);
        $direct = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/for/wp-deploy accepted by proc-receive\n")
            . $packet("option refname refs/heads/site-a\n")
            . $packet("option old-oid {$siteAOld}\n")
            . $packet("option new-oid {$siteANew}\n")
            . $packet("ok refs/for/wp-deploy accepted by proc-receive\n")
            . $packet("option refname refs/heads/site-b\n")
            . $packet("option old-oid {$siteBOld}\n")
            . $packet("option new-oid {$siteBNew}\n")
            . $packet("ng refs/for/wp-deploy post-receive hook declined\n")
            . $flush
        )->forExpectedRefNames(['refs/for/wp-deploy']);
        $sideband = PushResponse::fromSidebandPacketLines(
            $packet("\x02remote: proc-receive later rejected deployment refs\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/for/wp-deploy\n"))
            . $packet("\x01" . $packet("option refname refs/heads/site-a\n"))
            . $packet("\x01" . $packet("option old-oid {$siteAOld}\n"))
            . $packet("\x01" . $packet("option new-oid {$siteANew}\n"))
            . $packet("\x01" . $packet("ng refs/for/wp-deploy post-receive hook declined\n"))
            . $packet("\x01" . $flush)
            . $flush
        )->forExpectedRefNames(['refs/for/wp-deploy']);
        $first = $direct->refStatuses()[0];
        $second = $direct->refStatuses()[1];

        $t->same(false, $direct->isSuccessful());
        $t->same(2, count($direct->refStatuses()));
        $t->same(['refs/heads/site-a', 'refs/heads/site-b'], array_map(
            static fn (PushRefStatus $status): string => $status->effectiveRefName(),
            $direct->refStatuses()
        ));
        $t->same([PushRefStatus::REJECTED, PushRefStatus::REJECTED], array_map(
            static fn (PushRefStatus $status): string => $status->status,
            $direct->refStatuses()
        ));
        $t->same(['post-receive hook declined', 'post-receive hook declined'], array_map(
            static fn (PushRefStatus $status): ?string => $status->message,
            $direct->refStatuses()
        ));
        $t->same($siteAOld, $first->oldObject);
        $t->same($siteANew, $first->newObject);
        $t->same($siteBOld, $second->oldObject);
        $t->same($siteBNew, $second->newObject);
        $t->same(true, $first->hasReportOption());
        $t->same(true, $second->hasReportOption());
        $t->same(false, $sideband->isSuccessful());
        $t->same(['remote: proc-receive later rejected deployment refs'], $sideband->progressMessages());
        $t->same('refs/heads/site-a', $sideband->rejectedRefs()[0]->effectiveRefName());
        $t->same('post-receive hook declined', $sideband->rejectedRefs()[0]->message);
    },
    'marks missing requested receive-status refs as remote failures like send-pack' => static function (TestRunner $t) use ($packet, $flush): void {
        $response = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/heads/main post-update hook accepted\n")
            . $packet("ok refs/heads/ghost ignored by send-pack\n")
            . $flush
        );
        $filtered = $response->forExpectedRefNames(['refs/heads/main', 'refs/tags/wp-release']);
        $missingOnly = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/heads/ghost ignored by send-pack\n")
            . $flush
        )->forExpectedRefNames(['refs/heads/main']);

        $t->same(false, $filtered->isSuccessful());
        $t->same(2, count($filtered->refStatuses()));
        $t->same('refs/heads/main', $filtered->refStatuses()[0]->refName);
        $t->same(PushRefStatus::OK, $filtered->refStatuses()[0]->status);
        $t->same('refs/tags/wp-release', $filtered->refStatuses()[1]->refName);
        $t->same(PushRefStatus::REJECTED, $filtered->refStatuses()[1]->status);
        $t->same('remote failed to report status', $filtered->refStatuses()[1]->message);
        $t->same(false, $missingOnly->isSuccessful());
        $t->same('refs/heads/main', $missingOnly->rejectedRefs()[0]->refName);
        $t->same('remote failed to report status', $missingOnly->rejectedRefs()[0]->message);
    },
    'marks unpack-only receive-status refs as remote failures like send-pack' => static function (TestRunner $t) use ($packet, $flush): void {
        $raw = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $flush
        );
        $filtered = $raw->forExpectedRefNames(['refs/heads/main', 'refs/tags/wp-release']);
        $sideband = PushResponse::fromSidebandPacketLines(
            $packet("\x02Checking connectivity: 100% (2/2)\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $flush)
            . $flush
        )->forExpectedRefNames(['refs/heads/main']);

        $t->same(true, $raw->unpackOk());
        $t->same('ok', $raw->unpackStatus());
        $t->same([], $raw->refStatuses());
        $t->same(false, $raw->isSuccessful());
        $t->same(false, $filtered->isSuccessful());
        $t->same(2, count($filtered->refStatuses()));
        $t->same('refs/heads/main', $filtered->refStatuses()[0]->refName);
        $t->same(PushRefStatus::REJECTED, $filtered->refStatuses()[0]->status);
        $t->same('remote failed to report status', $filtered->refStatuses()[0]->message);
        $t->same('refs/tags/wp-release', $filtered->refStatuses()[1]->refName);
        $t->same(PushRefStatus::REJECTED, $filtered->refStatuses()[1]->status);
        $t->same('remote failed to report status', $filtered->refStatuses()[1]->message);
        $t->same(false, $sideband->isSuccessful());
        $t->same(['Checking connectivity: 100% (2/2)'], $sideband->progressMessages());
        $t->same('refs/heads/main', $sideband->rejectedRefs()[0]->refName);
        $t->same('remote failed to report status', $sideband->rejectedRefs()[0]->message);
    },
    'rejects report-status-v2 options after unrequested refs like send-pack' => static function (TestRunner $t) use ($packet, $flush): void {
        $unknownWithKnownOption = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/heads/main\n")
            . $packet("ok refs/heads/ghost ignored by send-pack\n")
            . $packet("option refname refs/heads/other\n")
            . $flush
        );
        $unknownWithFutureOption = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/heads/main\n")
            . $packet("ok refs/heads/ghost ignored by send-pack\n")
            . $packet("option future-extension ignored\n")
            . $flush
        );

        $t->same('refs/heads/other', $unknownWithKnownOption->refStatuses()[1]->effectiveRefName());
        $t->same(true, $unknownWithFutureOption->refStatuses()[1]->hasReportOption());
        $t->throws(InvalidArgumentException::class, static fn () => $unknownWithKnownOption->forExpectedRefNames(['refs/heads/main']));
        $t->throws(InvalidArgumentException::class, static fn () => $unknownWithFutureOption->forExpectedRefNames(['refs/heads/main']));
        $t->same(true, PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/heads/main\n")
            . $packet("ok refs/heads/ghost ignored by send-pack\n")
            . $flush
        )->forExpectedRefNames(['refs/heads/main'])->isSuccessful());
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
    'ignores empty progress sideband keepalives around receive-status bytes' => static function (TestRunner $t) use ($packet, $flush): void {
        $direct = PushResponse::fromSidebandPacketLines(
            $packet("\x02")
            . $packet("\x01" . substr($packet("unpack ok\n"), 0, 6))
            . $packet("\x02remote: validating deployment\n")
            . $packet("\x01" . substr($packet("unpack ok\n"), 6))
            . $packet("\x02")
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $packet("\x02")
            . $flush
        );

        $t->same(true, $direct->isSuccessful());
        $t->same(['remote: validating deployment'], $direct->progressMessages());
        $t->same('refs/heads/main', $direct->refStatuses()[0]->refName);
        $t->same([], $direct->errorMessages());
    },
    'accepts response-end terminated receive-status streams' => static function (TestRunner $t) use ($packet, $flush): void {
        $old = str_repeat('a', 40);
        $new = str_repeat('b', 40);
        $direct = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/for/wp-release accepted by proc-receive\n")
            . $packet("option refname refs/heads/wp-release\n")
            . $packet("option old-oid {$old}\n")
            . $packet("option new-oid {$new}\n")
            . '0002'
        );
        $sideband = PushResponse::fromSidebandPacketLines(
            $packet("\x02Checking connectivity: 100% (1/1)\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . '0002')
            . $flush
        );
        $outerResponseEnd = PushResponse::fromSidebandPacketLines(
            $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . '0002'
        );

        $t->same(true, $direct->isSuccessful());
        $t->same('refs/for/wp-release', $direct->refStatuses()[0]->refName);
        $t->same('refs/heads/wp-release', $direct->refStatuses()[0]->effectiveRefName());
        $t->same('accepted by proc-receive', $direct->refStatuses()[0]->message);
        $t->same($old, $direct->refStatuses()[0]->oldObject);
        $t->same($new, $direct->refStatuses()[0]->newObject);
        $t->same(true, $sideband->isSuccessful());
        $t->same(['Checking connectivity: 100% (1/1)'], $sideband->progressMessages());
        $t->same('refs/heads/main', $sideband->refStatuses()[0]->refName);
        $t->same(true, $outerResponseEnd->isSuccessful());
        $t->same('refs/heads/main', $outerResponseEnd->refStatuses()[0]->refName);
    },
    'accepts delimiter terminated receive-status streams like upstream packet-line readers' => static function (TestRunner $t) use ($packet, $flush): void {
        $direct = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . $packet("ok refs/heads/wp-preview deployed by hook\n")
            . '0001'
        );
        $sideband = PushResponse::fromSidebandPacketLines(
            $packet("\x02Checking connectivity: 100% (1/1)\n")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . '0001')
            . $flush
        );
        $outerDelimiter = PushResponse::fromSidebandPacketLines(
            $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . '0001'
        );

        $t->same(true, $direct->isSuccessful());
        $t->same('refs/heads/wp-preview', $direct->refStatuses()[0]->refName);
        $t->same('deployed by hook', $direct->refStatuses()[0]->message);
        $t->same(true, $sideband->isSuccessful());
        $t->same(['Checking connectivity: 100% (1/1)'], $sideband->progressMessages());
        $t->same('refs/heads/main', $sideband->refStatuses()[0]->refName);
        $t->same(true, $outerDelimiter->isSuccessful());
        $t->same('refs/heads/main', $outerDelimiter->refStatuses()[0]->refName);
        $delimiterOnly = PushResponse::fromReportStatusPacketLines(
            $packet("unpack ok\n")
            . '0001'
        )->forExpectedRefNames(['refs/heads/main']);
        $t->same(false, $delimiterOnly->isSuccessful());
        $t->same('refs/heads/main', $delimiterOnly->rejectedRefs()[0]->refName);
        $t->same('remote failed to report status', $delimiterOnly->rejectedRefs()[0]->message);
    },
    'guards malformed push response packet streams' => static function (TestRunner $t) use ($packet, $flush): void {
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromSidebandPacketLines($packet("\x09bad band") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromReportStatusPacketLines($flush));
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromReportStatusPacketLines($packet("ok refs/heads/main\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromReportStatusPacketLines($packet("unpack ok\n") . $packet("option refname refs/heads/main\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => PushResponse::fromReportStatusPacketLines($packet("unpack ok\n") . $packet("ok main\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => new PushRefStatus(PushRefStatus::OK, 'refs/heads/main', null, null, str_repeat('f', 63)));
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
        $t->contains('sideband error pre-receive hook declined after status', $runtimeMessage(
            static fn () => PushResponse::fromSidebandPacketLines(
                $packet("\x01" . $packet("unpack ok\n"))
                . $packet("\x01" . $packet("ok refs/heads/main\n"))
                . $packet("\x03pre-receive hook declined after status\n")
                . $packet("\x01" . $flush)
                . $flush
            )
        ));
        $t->contains('missing report-status flush packet', $invalidArgumentMessage(
            static fn () => PushResponse::fromSidebandPacketLines($packet("\x01" . $packet("unpack ok\n")) . $flush)
        ));
        $keepalive = PushResponse::fromSidebandPacketLines(
            $packet("\x03")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $flush
        );
        $t->same(true, $keepalive->isSuccessful());
        $t->same([], $keepalive->errorMessages());
        $emptyProgressKeepalive = PushResponse::fromSidebandPacketLines(
            $packet("\x02")
            . $packet("\x01" . $packet("unpack ok\n"))
            . $packet("\x01" . $packet("ok refs/heads/main\n"))
            . $packet("\x01" . $flush)
            . $flush
        );
        $t->same(true, $emptyProgressKeepalive->isSuccessful());
        $t->same([], $emptyProgressKeepalive->progressMessages());
    },
    'wordpress fixture parses deployment branch and tag push status' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-protocol-v1-push-response.php';
        $response = PushResponse::fromSidebandPacketLines($fixture['response']);
        $rewritten = PushResponse::fromReportStatusPacketLines($fixture['rewrittenResponse'])->refStatuses()[0];
        $fallThrough = PushResponse::fromReportStatusPacketLines($fixture['fallThroughResponse'])->refStatuses()[0];
        $compatibility = PushResponse::fromReportStatusPacketLines($fixture['compatibilityResponse']);
        $compatibilityAccepted = $compatibility->refStatuses()[0];
        $compatibilityRejected = $compatibility->refStatuses()[1];
        $emptyRejection = PushResponse::fromReportStatusPacketLines($fixture['emptyRejectionResponse'])->refStatuses()[0];
        $valuelessOption = PushResponse::fromReportStatusPacketLines($fixture['valuelessOptionResponse'])
            ->forExpectedRefNames([$fixture['valuelessOptionRef']['requested']])
            ->refStatuses()[0];
        $emptyRefnameOption = PushResponse::fromReportStatusPacketLines($fixture['emptyRefnameOptionResponse'])
            ->forExpectedRefNames([$fixture['emptyRefnameOptionRef']['requested']])
            ->refStatuses()[0];
        $malformedObjectOption = PushResponse::fromReportStatusPacketLines($fixture['malformedObjectOptionResponse'])->refStatuses()[0];
        $objectPrefixDiagnostic = PushResponse::fromReportStatusPacketLines($fixture['objectPrefixDiagnosticResponse'])->refStatuses()[0];
        $sha1ObjectPrefix = PushResponse::fromReportStatusPacketLines($fixture['sha1ObjectPrefixResponse'], 'sha1')->refStatuses()[0];
        $emptyUnpackStatus = PushResponse::fromReportStatusPacketLines($fixture['emptyUnpackStatusResponse']);

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
        $t->same($fixture['emptyRejectionRef']['requested'], $emptyRejection->refName);
        $t->same(PushRefStatus::REJECTED, $emptyRejection->status);
        $t->same($fixture['emptyRejectionRef']['message'], $emptyRejection->message);
        $t->same($fixture['valuelessOptionRef']['requested'], $valuelessOption->refName);
        $t->same($fixture['valuelessOptionRef']['requested'], $valuelessOption->effectiveRefName());
        $t->same($fixture['valuelessOptionRef']['message'], $valuelessOption->message);
        $t->same(null, $valuelessOption->oldObject);
        $t->same(null, $valuelessOption->newObject);
        $t->same(true, $valuelessOption->hasReportOption());
        $t->same($fixture['emptyRefnameOptionRef']['requested'], $emptyRefnameOption->refName);
        $t->same($fixture['emptyRefnameOptionRef']['actual'], $emptyRefnameOption->reportedRefName);
        $t->same($fixture['emptyRefnameOptionRef']['actual'], $emptyRefnameOption->effectiveRefName());
        $t->same($fixture['emptyRefnameOptionRef']['message'], $emptyRefnameOption->message);
        $t->same($fixture['emptyRefnameOptionRef']['oldObject'], $emptyRefnameOption->oldObject);
        $t->same($fixture['emptyRefnameOptionRef']['newObject'], $emptyRefnameOption->newObject);
        $t->same(true, $emptyRefnameOption->hasReportOption());
        $t->same($fixture['malformedObjectOptionRef']['requested'], $malformedObjectOption->refName);
        $t->same($fixture['malformedObjectOptionRef']['oldObject'], $malformedObjectOption->oldObject);
        $t->same($fixture['malformedObjectOptionRef']['newObject'], $malformedObjectOption->newObject);
        $t->same(true, $malformedObjectOption->hasReportOption());
        $t->same($fixture['objectPrefixDiagnosticRef']['requested'], $objectPrefixDiagnostic->refName);
        $t->same($fixture['objectPrefixDiagnosticRef']['oldObject'], $objectPrefixDiagnostic->oldObject);
        $t->same($fixture['objectPrefixDiagnosticRef']['newObject'], $objectPrefixDiagnostic->newObject);
        $t->same(true, $objectPrefixDiagnostic->hasReportOption());
        $t->same($fixture['sha1ObjectPrefixRef']['requested'], $sha1ObjectPrefix->refName);
        $t->same($fixture['sha1ObjectPrefixRef']['oldObject'], $sha1ObjectPrefix->oldObject);
        $t->same($fixture['sha1ObjectPrefixRef']['newObject'], $sha1ObjectPrefix->newObject);
        $t->same(true, $sha1ObjectPrefix->hasReportOption());
        $t->same('', $emptyUnpackStatus->unpackStatus());
        $t->same(false, $emptyUnpackStatus->unpackOk());
        $t->same($fixture['emptyUnpackStatusRef']['message'], $emptyUnpackStatus->rejectedRefs()[0]->message);

        $summary = require dirname(__DIR__) . '/examples/wordpress-protocol-v1-push-response.php';
        $t->same($fixture['expectedFilteredRefs'], array_map(
            static fn (array $status): string => $status['effectiveRef'],
            $summary['expectedFilteredRefs']
        ));
        $t->same($fixture['multiReportRef']['actual'], array_map(
            static fn (array $status): string => $status['effectiveRef'],
            $summary['multiReportRefs']
        ));
        $t->same($fixture['multiReportRef']['oldObjects'], array_map(
            static fn (array $status): string => $status['oldObject'],
            $summary['multiReportRefs']
        ));
        $t->same($fixture['multiReportRef']['newObjects'], array_map(
            static fn (array $status): string => $status['newObject'],
            $summary['multiReportRefs']
        ));
        $t->same($fixture['noRefnameMultiReportRef']['actual'], array_map(
            static fn (array $status): string => $status['effectiveRef'],
            $summary['noRefnameMultiReportRefs']
        ));
        $t->same($fixture['noRefnameMultiReportRef']['oldObjects'], array_map(
            static fn (array $status): string => $status['oldObject'],
            $summary['noRefnameMultiReportRefs']
        ));
        $t->same($fixture['noRefnameMultiReportRef']['newObjects'], array_map(
            static fn (array $status): string => $status['newObject'],
            $summary['noRefnameMultiReportRefs']
        ));
        $t->same([false, false, true], array_map(
            static fn (array $status): bool => $status['forcedUpdate'],
            $summary['noRefnameMultiReportRefs']
        ));
        $t->same($fixture['rejectedReportRef']['actual'], array_map(
            static fn (array $status): string => $status['effectiveRef'],
            $summary['rejectedReportRefs']
        ));
        $t->same($fixture['rejectedReportRef']['message'], $summary['rejectedReportRefs'][0]['message']);
        $t->same([PushRefStatus::REJECTED, PushRefStatus::REJECTED], array_map(
            static fn (array $status): string => $status['status'],
            $summary['rejectedReportRefs']
        ));
        $t->same(true, $summary['oversizedReportStatusRejected']);
        $t->same(true, $summary['fatalSidebandRejected']);
        $t->same(true, $summary['fallThroughAccepted']);
        $t->same(true, $summary['compatibilityOptionExtensionsIgnored']);
        $t->same(true, $summary['compatibilityTrailingObjectDiagnosticsIgnored']);
        $t->same(true, $summary['compatibilityBareRejectionDefaulted']);
        $t->same(true, $summary['emptyRejectionMessagePreserved']);
        $t->same(true, $summary['expectedUnknownStatusIgnored']);
        $t->same(true, $summary['expectedLastStatusWon']);
        $t->same(true, $summary['multiReportStatusPreserved']);
        $t->same(true, $summary['noRefnameMultiReportStatusPreserved']);
        $t->same(true, $summary['rejectedReportStatusPreserved']);
        $t->same(true, $summary['carriageReturnStatusRejected']);
        $t->same(true, $summary['emptyPacketLineRejected']);
        $t->same(true, $summary['unrequestedOptionRejected']);
        $t->same(true, $summary['missingExpectedStatusRejected']);
        $t->same($fixture['unpackOnlyExpectedRefs'], array_map(
            static fn (array $status): string => $status['requestedRef'],
            $summary['unpackOnlyExpectedRefs']
        ));
        $t->same(true, $summary['unpackOnlyExpectedRefsRejected']);
        $t->same(true, $summary['fatalAfterStatusRejected']);
        $t->same(true, $summary['emptyErrorSidebandAccepted']);
        $t->same(true, $summary['emptyProgressSidebandIgnored']);
        $t->same(true, $summary['responseEndTerminatedAccepted']);
        $t->same(true, $summary['delimiterTerminatedAccepted']);
        $t->same(true, $summary['valuelessReportStatusOptionsAccepted']);
        $t->same(true, $summary['emptyRefnameOptionValuePreserved']);
        $t->same($fixture['emptyRefnameOptionRef']['actual'], $summary['emptyRefnameOptionRefs'][0]['reportedRef']);
        $t->same($fixture['emptyRefnameOptionRef']['actual'], $summary['emptyRefnameOptionRefs'][0]['effectiveRef']);
        $t->same(true, $summary['malformedObjectOptionsIgnored']);
        $t->same(true, $summary['objectPrefixDiagnosticSuffixesParsed']);
        $t->same(true, $summary['sha1ObjectPrefixOptionsParsed']);
        $t->same(true, $summary['emptyUnpackStatusRejected']);
    },
];
