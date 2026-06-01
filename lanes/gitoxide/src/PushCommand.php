<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PushCommand
{
    private const MAX_PACKET_LINE_LENGTH = 65520;

    private const FEATURE_ORDER = [
        'report-status-v2',
        'report-status',
        'side-band-64k',
        'side-band',
    ];

    /**
     * @param list<string> $features
     * @param list<PushUpdate> $updates
     * @param list<string> $pushOptions
     */
    private function __construct(
        private readonly ProtocolCapabilities $capabilities,
        private readonly string $objectFormat,
        private array $features,
        private array $updates = [],
        private array $pushOptions = [],
    ) {
    }

    public static function create(ProtocolCapabilities $capabilities, ?string $agent = null, string $objectFormat = 'sha1'): self
    {
        $objectFormat = self::normalizeObjectFormat($objectFormat);
        $features = self::defaultFeatures($capabilities, $objectFormat);
        if ($agent !== null) {
            self::assertFeatureValue($agent, 'agent');
            $features[] = 'agent=' . $agent;
        }

        return new self($capabilities, $objectFormat, $features);
    }

    /**
     * @return list<string>
     */
    public static function defaultFeatures(ProtocolCapabilities $capabilities, string $objectFormat = 'sha1'): array
    {
        $objectFormat = self::normalizeObjectFormat($objectFormat);
        $features = [];
        foreach (self::FEATURE_ORDER as $feature) {
            if ($feature === 'report-status' && in_array('report-status-v2', $features, true)) {
                continue;
            }
            if ($feature === 'side-band' && in_array('side-band-64k', $features, true)) {
                continue;
            }
            if ($capabilities->contains($feature)) {
                $features[] = $feature;
            }
        }

        $objectFormatCapability = $capabilities->capability('object-format');
        if ($objectFormatCapability === null) {
            if ($objectFormat !== 'sha1') {
                throw new \InvalidArgumentException("push: object format {$objectFormat} is not supported");
            }
        } else {
            if (!$objectFormatCapability->supports($objectFormat)) {
                throw new \InvalidArgumentException("push: object format {$objectFormat} is not supported");
            }
            $features[] = 'object-format=' . $objectFormat;
        }

        return $features;
    }

    /**
     * @return list<string>
     */
    public function features(): array
    {
        return $this->features;
    }

    /**
     * @return list<PushUpdate>
     */
    public function updates(): array
    {
        return $this->updates;
    }

    public function objectFormat(): string
    {
        return $this->objectFormat;
    }

    /**
     * @return list<string>
     */
    public function pushOptions(): array
    {
        return $this->pushOptions;
    }

    public function isEmpty(): bool
    {
        return $this->updates === [];
    }

    public function addUpdate(PushUpdate $update): void
    {
        if ($update->objectFormat() !== $this->objectFormat) {
            throw new \InvalidArgumentException('push: update object format does not match command object format');
        }

        $this->updates[] = $update;
    }

    public function createRef(string $newObject, string $refName): void
    {
        $this->addUpdate(PushUpdate::create($newObject, $refName, $this->objectFormat));
    }

    public function updateRef(string $oldObject, string $newObject, string $refName): void
    {
        $this->addUpdate(PushUpdate::update($oldObject, $newObject, $refName, $this->objectFormat));
    }

    public function deleteRef(string $oldObject, string $refName): void
    {
        $this->addUpdate(PushUpdate::delete($oldObject, $refName, $this->objectFormat));
    }

    public function useAtomic(): void
    {
        $this->requireCapability('atomic');
        $this->addFeature('atomic');
    }

    public function addPushOption(string $option): void
    {
        $this->requireCapability('push-options');
        self::assertFeatureValue($option, 'push option');
        $this->addFeature('push-options');
        $this->pushOptions[] = $option;
    }

    /**
     * @return list<string>
     */
    public function commandLines(): array
    {
        $lines = [];
        foreach ($this->updates as $index => $update) {
            $line = $update->commandLine();
            if ($index === 0 && $this->features !== []) {
                $line .= "\0 " . implode(' ', $this->features);
            }
            $lines[] = $line;
        }

        return $lines;
    }

    public function requestBytes(string $packBytes = ''): string
    {
        $this->validate();

        $bytes = '';
        foreach ($this->commandLines() as $line) {
            $bytes .= self::packetLine($line);
        }
        $bytes .= '0000';

        if ($this->pushOptions !== []) {
            foreach ($this->pushOptions as $option) {
                $bytes .= self::packetLine($option);
            }
            $bytes .= '0000';
        }

        return $bytes . $packBytes;
    }

    public function requestWithPack(PackBuildResult $pack): string
    {
        return $this->requestBytes($pack->packBytes());
    }

    public function validate(): void
    {
        if ($this->updates === []) {
            throw new \InvalidArgumentException('push: at least one ref update is required');
        }
        if ($this->pushOptions !== [] && !in_array('push-options', $this->features, true)) {
            throw new \InvalidArgumentException('push: push-options capability was not requested');
        }
        foreach ($this->features as $feature) {
            [$name] = explode('=', $feature, 2);
            if ($name === 'agent') {
                continue;
            }
            $this->requireCapability($name);
        }
    }

    private function addFeature(string $feature): void
    {
        if (!in_array($feature, $this->features, true)) {
            $this->features[] = $feature;
        }
    }

    private function requireCapability(string $feature): void
    {
        if (!$this->capabilities->contains($feature)) {
            throw new \LogicException("push: {$feature} is not supported");
        }
    }

    private static function packetLine(string $payload): string
    {
        $length = strlen($payload) + 4;
        if ($length > self::MAX_PACKET_LINE_LENGTH) {
            throw new \InvalidArgumentException('push: packet line exceeds maximum length');
        }

        return sprintf('%04x', $length) . $payload;
    }

    private static function assertFeatureValue(string $value, string $label): void
    {
        if ($value === '' || str_contains($value, "\0") || str_contains($value, "\n") || str_contains($value, "\r")) {
            throw new \InvalidArgumentException("push: {$label} is not valid");
        }
    }

    private static function normalizeObjectFormat(string $objectFormat): string
    {
        if (!in_array($objectFormat, ['sha1', 'sha256'], true)) {
            throw new \InvalidArgumentException("push: object format {$objectFormat} is not supported");
        }

        return $objectFormat;
    }
}
