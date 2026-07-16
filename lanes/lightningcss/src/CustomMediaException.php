<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CustomMediaException extends \InvalidArgumentException
{
    /**
     * @param array{line:int,column:int}|null $mediaLocation
     * @param array{line:int,column:int}|null $customMediaLocation
     */
    public function __construct(
        public readonly string $kind,
        public readonly ?string $name,
        public readonly ?array $mediaLocation,
        public readonly ?array $customMediaLocation,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * @param array{line:int,column:int}|null $mediaLocation
     * @param array{line:int,column:int}|null $customMediaLocation
     */
    public static function unsupportedBooleanLogic(?string $name, ?array $mediaLocation, ?array $customMediaLocation): self
    {
        $message = 'Unsupported custom media boolean logic';
        if ($name !== null) {
            $message .= " involving {$name}";
        }

        return new self('unsupported-custom-media-boolean-logic', $name, $mediaLocation, $customMediaLocation, self::withLocations($message, $mediaLocation, $customMediaLocation));
    }

    /**
     * @param array{line:int,column:int}|null $mediaLocation
     */
    public static function notDefined(string $name, ?array $mediaLocation): self
    {
        return new self('custom-media-not-defined', $name, $mediaLocation, null, self::withLocations("Custom media {$name} is not defined", $mediaLocation, null));
    }

    /**
     * @param array{line:int,column:int}|null $mediaLocation
     */
    public static function circular(string $name, ?array $mediaLocation): self
    {
        return new self('circular-custom-media', $name, $mediaLocation, null, self::withLocations("Circular custom media reference involving {$name}", $mediaLocation, null));
    }

    /**
     * @param array{line:int,column:int}|null $mediaLocation
     * @param array{line:int,column:int}|null $customMediaLocation
     */
    private static function withLocations(string $message, ?array $mediaLocation, ?array $customMediaLocation): string
    {
        if ($mediaLocation !== null) {
            $message .= " at @media line {$mediaLocation['line']}, column {$mediaLocation['column']}";
        }
        if ($customMediaLocation !== null) {
            $message .= "; @custom-media line {$customMediaLocation['line']}, column {$customMediaLocation['column']}";
        }

        return $message;
    }
}
