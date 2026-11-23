<?php

declare(strict_types=1);

namespace App\Builder;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Keeps the settings of a map that is not saved, for the preview in the
 * builder. The settings are in the session of the editor, under a random
 * token, so other editors and the embed codes never see them. The session
 * keeps the last MAX_PREVIEWS previews.
 *
 * Example:
 *
 *     $token = $store->add($settings);
 *     // The frame of the preview loads /builder/preview/{token}.
 *     $settings = $store->get($token);
 */
final readonly class PreviewStore
{
    public const int MAX_PREVIEWS = 5;

    private const string SESSION_KEY = 'builder_previews';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return string the token of the preview: 32 hexadecimal characters
     */
    public function add(\stdClass $settings): string
    {
        $session = $this->requestStack->getSession();
        $token = bin2hex(random_bytes(16));

        /** @var array<string, string> $previews */
        $previews = $session->get(self::SESSION_KEY, []);
        $previews[$token] = json_encode($settings, \JSON_THROW_ON_ERROR);
        $session->set(self::SESSION_KEY, \array_slice($previews, -self::MAX_PREVIEWS, preserve_keys: true));

        return $token;
    }

    /**
     * @return \stdClass|null the settings, or null if the session does not have the token
     */
    public function get(string $token): ?\stdClass
    {
        /** @var array<string, string> $previews */
        $previews = $this->requestStack->getSession()->get(self::SESSION_KEY, []);
        if (!isset($previews[$token])) {
            return null;
        }

        $settings = json_decode($previews[$token], false, 512, \JSON_THROW_ON_ERROR);

        return $settings instanceof \stdClass ? $settings : null;
    }
}
