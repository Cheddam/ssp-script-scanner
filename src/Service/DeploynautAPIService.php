<?php

namespace App\Service;

use ErrorException;
use GuzzleHttp\Client;

/**
 * Handles interactions with the Deploynaut API
 *
 * @package App\Service
 */
class DeploynautAPIService
{
    /**
     * DeploynautAPIService constructor.
     *
     * @throws ErrorException
     */
    public function __construct()
    {
        if (empty($_ENV['API_URL']) || empty($_ENV['API_USER']) || empty($_ENV['API_KEY'])) {
            throw new ErrorException('API_URL, API_USER and API_KEY must be set in .env file!');
        }

        $this->client = new Client([
            'base_uri' => $_ENV['API_URL'],
            'timeout' => 5.0,
            'headers' => [
                'Content-Type' => 'application/vnd.api+json',
                'Accept' => 'application/vnd.api+json',
                'X-Api-Version' => '2.0',
            ],
            'auth' => [$_ENV['API_USER'], $_ENV['API_KEY']],
        ]);
    }

    public function getMeta(): array
    {
        return $this->makeRequestAndReceiveJSON('meta');
    }

    public function getStackList(): array
    {
        return $this->makeRequestAndReceiveJSON('projects');
    }

    public function getStack($stackID): array
    {
        return $this->makeRequestAndReceiveJSON('project/' . $stackID);
    }

    public function getEnvironment($environmentID): array
    {
        return $this->makeRequestAndReceiveJSON('environment/' . $environmentID);
    }

    public function getScriptsForEnvironment($stackID, $environmentID): array
    {
        return $this->makeRequestAndReceiveJSON('project/' . $stackID . '/environment/' . $environmentID . '/scripts');
    }

    private function makeRequestAndReceiveJSON($url): array
    {
        return json_decode($this->client->get($url)->getBody(), true);
    }
}