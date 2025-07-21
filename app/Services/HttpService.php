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

        if (strtolower($method) === 'get') {
            $request = $request->withOptions(['query' => $params]);

            return $request->get($url)->throw();
        }

        $request = $request->asForm();

        return $request->throw();
    }

    public function get(string $endpoint, array $params = []): array
    {
        return $this->request('get', $endpoint, $params)->json();

    }

    public function post(string $endpoint, array $params = []): array
    {
        return $this->request('post', $endpoint, $params)->json();
    }
}
