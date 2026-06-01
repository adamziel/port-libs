<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class SendPackSession
{
    private readonly PushCommand $command;

    public function __construct(
        private readonly ReceivePackAdvertisement $advertisement,
        ?string $agent = null,
    ) {
        $this->command = PushCommand::create($advertisement->capabilities(), $agent);
    }

    public static function create(ReceivePackAdvertisement $advertisement, ?string $agent = null): self
    {
        return new self($advertisement, $agent);
    }

    public function advertisement(): ReceivePackAdvertisement
    {
        return $this->advertisement;
    }

    public function command(): PushCommand
    {
        return $this->command;
    }

    public function createOrUpdate(string $refName, string $newObject): bool
    {
        $oldObject = $this->advertisement->objectFor($refName);
        if ($oldObject === null) {
            $this->command->createRef($newObject, $refName);

            return true;
        }
        if (strtolower($oldObject) === strtolower($newObject)) {
            return false;
        }

        $this->command->updateRef($oldObject, $newObject, $refName);

        return true;
    }

    public function delete(string $refName): bool
    {
        $oldObject = $this->advertisement->objectFor($refName);
        if ($oldObject === null) {
            return false;
        }

        $this->command->deleteRef($oldObject, $refName);

        return true;
    }

    /**
     * @param list<GitObject> $objects
     */
    public function buildRequest(array $objects): SendPackRequest
    {
        return $this->buildRequestWithPack($objects, []);
    }

    /**
     * @param list<GitObject> $objects
     * @param list<GitObject> $remoteBases Objects the receiver is expected to already have.
     */
    public function buildThinRequest(array $objects, array $remoteBases): SendPackRequest
    {
        return $this->buildRequestWithPack($objects, $remoteBases);
    }

    /**
     * @param list<GitObject> $objects
     * @param list<GitObject> $remoteBases
     */
    private function buildRequestWithPack(array $objects, array $remoteBases): SendPackRequest
    {
        $pack = null;
        if ($this->needsPack()) {
            $pack = $remoteBases === []
                ? PackBuilder::build($objects)
                : PackBuilder::buildWithRefDeltas($objects, $remoteBases);

            return new SendPackRequest($this->command, $this->command->requestWithPack($pack), $pack);
        }

        if ($objects !== []) {
            throw new \InvalidArgumentException('send-pack: object pack was provided for a delete-only request');
        }

        return new SendPackRequest($this->command, $this->command->requestBytes(), null);
    }

    public function parseSidebandResponse(string $bytes): PushResponse
    {
        return PushResponse::fromSidebandPacketLines($bytes, $this->objectFormatFromFeatures());
    }

    public function parseReportStatusResponse(string $bytes): PushResponse
    {
        return PushResponse::fromReportStatusPacketLines($bytes, $this->objectFormatFromFeatures());
    }

    private function needsPack(): bool
    {
        foreach ($this->command->updates() as $update) {
            if (!$update->isDelete()) {
                return true;
            }
        }

        return false;
    }

    private function objectFormatFromFeatures(): string
    {
        foreach ($this->command->features() as $feature) {
            [$name, $value] = array_pad(explode('=', $feature, 2), 2, null);
            if ($name !== 'object-format') {
                continue;
            }

            return $value === 'sha256' ? 'sha256' : 'sha1';
        }

        return 'sha1';
    }
}
