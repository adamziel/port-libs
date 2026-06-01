<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ReceivePackClient
{
    private ?SendPackSession $session = null;

    public function __construct(
        private readonly ReceivePackTransport $transport,
        private readonly ?string $agent = null,
    ) {
    }

    public function handshake(): SendPackSession
    {
        if ($this->session !== null) {
            return $this->session;
        }

        $advertisement = ReceivePackAdvertisement::fromV1PacketLines($this->transport->readAdvertisement());
        $this->session = SendPackSession::create($advertisement, $this->agent);

        return $this->session;
    }

    public function send(SendPackRequest $request): PushResponse
    {
        $features = $request->command()->features();
        if (!self::hasFeature($features, 'report-status') && !self::hasFeature($features, 'report-status-v2')) {
            throw new \LogicException('receive-pack client cannot parse a response without report-status');
        }

        $this->transport->writeRequest($request->requestBytes());
        $responseBytes = $this->transport->readResponse();

        $objectFormat = self::objectFormatFromFeatures($features);
        $expectedRefNames = array_map(
            static fn (PushUpdate $update): string => $update->refName,
            $request->command()->updates()
        );
        $response = self::hasFeature($features, 'side-band') || self::hasFeature($features, 'side-band-64k')
            ? PushResponse::fromSidebandPacketLinesForExpectedRefNames($responseBytes, $expectedRefNames, $objectFormat)
            : PushResponse::fromReportStatusPacketLinesForExpectedRefNames($responseBytes, $expectedRefNames, $objectFormat);

        return $response->forExpectedUpdates($request->command()->updates());
    }

    public function run(callable $plan): PushResponse
    {
        $request = $plan($this->handshake());
        if (!$request instanceof SendPackRequest) {
            throw new \InvalidArgumentException('receive-pack client planner must return a SendPackRequest');
        }

        return $this->send($request);
    }

    /**
     * @param list<string> $features
     */
    private static function hasFeature(array $features, string $name): bool
    {
        foreach ($features as $feature) {
            [$featureName] = explode('=', $feature, 2);
            if ($featureName === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $features
     */
    private static function objectFormatFromFeatures(array $features): string
    {
        foreach ($features as $feature) {
            [$name, $value] = array_pad(explode('=', $feature, 2), 2, null);
            if ($name !== 'object-format') {
                continue;
            }

            return $value === 'sha256' ? 'sha256' : 'sha1';
        }

        return 'sha1';
    }

}
