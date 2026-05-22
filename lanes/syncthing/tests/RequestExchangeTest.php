<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\ProtocolValidation;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\RequestExchange;
use PortLibs\Syncthing\Response;

return [
    'maps upstream response error code conversion' => static function (TestRunner $t): void {
        $t->same(null, Response::codeToError(Response::CODE_NO_ERROR));
        $t->same(Response::ERROR_NO_SUCH_FILE, Response::codeToError(Response::CODE_NO_SUCH_FILE));
        $t->same(Response::ERROR_INVALID_FILE, Response::codeToError(Response::CODE_INVALID_FILE));
        $t->same(Response::ERROR_GENERIC, Response::codeToError(Response::CODE_GENERIC));
        $t->same(Response::ERROR_GENERIC, Response::codeToError(99));

        $t->same(Response::CODE_NO_ERROR, Response::errorToCode(null));
        $t->same(Response::CODE_NO_SUCH_FILE, Response::errorToCode(Response::ERROR_NO_SUCH_FILE));
        $t->same(Response::CODE_INVALID_FILE, Response::errorToCode(Response::ERROR_INVALID_FILE));
        $t->same(Response::CODE_GENERIC, Response::errorToCode(Response::ERROR_GENERIC));
        $t->same(Response::CODE_GENERIC, Response::errorToCode(new RuntimeException('unexpected disk error')));

        $response = Response::errorResponse(9, Response::ERROR_NO_SUCH_FILE);
        $t->same(9, $response->id);
        $t->same('', $response->data);
        $t->same(Response::CODE_NO_SUCH_FILE, $response->code);
        $t->true(!$response->successful());
        $t->same(Response::ERROR_NO_SUCH_FILE, $response->error());
    },
    'maps rawConnection outbound ids and response completions' => static function (TestRunner $t): void {
        $exchange = new RequestExchange();
        $first = $exchange->queue(new Request(
            folder: 'wordpress-media',
            name: 'wp-content/uploads/2026/hero.jpg',
            offset: 0,
            size: 1024,
            hashHex: hash('sha256', 'first requested block'),
            fromTemporary: true,
            blockNo: 0,
        ));
        $second = $exchange->queue(new Request(
            folder: 'wordpress-media',
            name: 'wp-content/uploads/2026/poster.jpg',
            offset: 1024,
            size: 2048,
            hashHex: hash('sha256', 'second requested block'),
            blockNo: 1,
        ));

        $t->same(0, $first->id);
        $t->same(1, $second->id);
        $t->same(2, $exchange->nextRequestId());
        $t->same([0, 1], $exchange->pendingIds());

        $decoded = BepWire::decodeRequestMessage(BepWire::encodeRequestMessage($second));
        $t->same(1, $decoded->id);
        $t->same('wp-content/uploads/2026/poster.jpg', $decoded->name);
        $t->same(1, $decoded->blockNo);

        $miss = $exchange->complete(new Response(id: 1, code: Response::CODE_NO_SUCH_FILE));
        $t->same(1, $miss?->id);
        $t->same(Response::ERROR_NO_SUCH_FILE, $miss?->error);
        $t->true(!$miss?->successful());
        $t->same([0], $exchange->pendingIds());

        $hit = $exchange->complete(new Response(id: 0, data: 'media bytes'));
        $t->same(0, $hit?->id);
        $t->same('media bytes', $hit?->data);
        $t->same(null, $hit?->error);
        $t->true($hit?->successful() ?? false);
        $t->same([], $exchange->pendingIds());

        $t->same(null, $exchange->complete(new Response(id: 99, data: 'stale late response')));
    },
    'maps rawConnection close draining awaiting requests' => static function (TestRunner $t): void {
        $exchange = new RequestExchange(10);
        $exchange->queue(new Request(folder: 'wordpress-media', name: 'a.jpg', size: 1024));
        $exchange->queue(new Request(folder: 'wordpress-media', name: 'b.jpg', size: 1024));

        $closed = $exchange->close();
        $t->true($exchange->isClosed());
        $t->same([], $exchange->pendingIds());
        $t->same(10, $closed[0]->id);
        $t->same(11, $closed[1]->id);
        $t->same(Response::ERROR_CLOSED, $closed[0]->error);
        $t->same(Response::ERROR_CLOSED, $closed[1]->error);
        $t->true(!$closed[0]->successful());
        $t->same([], $exchange->close());
        $t->throws(RuntimeException::class, static fn () => $exchange->queue(new Request(folder: 'wordpress-media', name: 'c.jpg', size: 1024)));
    },
    'maps dispatcher request validation boundaries before request handling' => static function (TestRunner $t): void {
        ProtocolValidation::checkRequest(new Request(
            folder: 'wordpress-media',
            name: 'wp-content/uploads/2026/valid.jpg',
            size: ProtocolValidation::MAX_REQUEST_SIZE,
        ));
        $t->true(true);

        foreach ([0, -1, ProtocolValidation::MAX_REQUEST_SIZE + 1] as $size) {
            $t->throws(InvalidArgumentException::class, static fn () => ProtocolValidation::checkRequest(new Request(
                folder: 'wordpress-media',
                name: 'wp-content/uploads/2026/invalid.jpg',
                size: $size,
            )));
        }

        $t->throws(InvalidArgumentException::class, static fn () => ProtocolValidation::checkRequest(new Request(
            folder: 'wordpress-media',
            name: '../wp-config.php',
            size: 1024,
        )));
    },
];
