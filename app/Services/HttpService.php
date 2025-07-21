<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class HttpService
{
    public function __construct(protected string $baseUrl) {}

    public function request(string $method, string $endpoint, array $params = [], array $headers = [])
    {
        $url = "{$this->baseUrl}/{$endpoint}";

        $request = Http::withHeaders($headers)
            ->acceptJson();

        $response = match ($method) {
            'get' => $request->get($url, $params),
            'post' => $request->asForm()->post($url, $params),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        $response->throw();

        return $response;
    }

    public function get(string $endpoint, array $params = [], array $headers = [])
    {
        return $this->request('get', $endpoint, $params, $headers);
    }

    public function post(string $endpoint, array $params = [])
    {
        return $this->request('post', $endpoint, $params);
    }
}
