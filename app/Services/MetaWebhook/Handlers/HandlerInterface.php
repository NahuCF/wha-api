<?php

namespace App\Services\MetaWebhook\Handlers;

interface HandlerInterface
{
    public function handle(array $data): void;
}
