<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class CredentialHelperExchange
{
    /**
     * @param iterable<string> $args
     * @param callable(string, CredentialContext): (?CredentialContext) $credentials
     */
    public static function invoke(iterable $args, string $stdin, callable $credentials): string
    {
        $action = self::actionFromArgs($args);
        $context = CredentialContext::fromBytes($stdin);

        if ($context->url === null && ($context->protocol === null || $context->host === null)) {
            throw new \InvalidArgumentException("Either 'url' field or both 'protocol' and 'host' fields must be provided");
        }

        $result = $credentials($action, $context);
        if ($action !== 'get') {
            if ($result !== null) {
                throw new \LogicException('Credential helper callback must not return a context for store or erase actions');
            }

            return '';
        }

        if ($result === null) {
            $url = $context->url ?? $context->toUrl();
            throw new \RuntimeException("Credentials for {$url} could not be obtained");
        }

        return $result->storageBytes();
    }

    /**
     * @param iterable<string> $args
     */
    private static function actionFromArgs(iterable $args): string
    {
        foreach ($args as $arg) {
            return match ($arg) {
                'fill', 'get' => 'get',
                'approve', 'store' => 'store',
                'reject', 'erase' => 'erase',
                default => throw new \InvalidArgumentException(
                    "Credential helper action must be get, store, erase, fill, approve, or reject: {$arg}",
                ),
            };
        }

        throw new \InvalidArgumentException('Credential helper action is missing');
    }
}
