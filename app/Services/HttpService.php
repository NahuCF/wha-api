<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class HttpService
{
    public function __construct(protected string $baseUrl) {}

    public function request(string $method, string $endpoint, array $params = [], array $headers = []): Response
    {
        $url = "{$this->baseUrl}/{$endpoint}";

        $request = Http::withHeaders($headers)
            ->acceptJson()
            ->asForm();

        $response = $request->$method($url, $params);

        return $response->throw();
    }

    public function get(string $endpoint, array $params = []): array
    {
        return $this->request('get', $endpoint, $params)->json();
    }
}
