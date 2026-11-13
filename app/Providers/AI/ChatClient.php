<?php

namespace App\Providers\AI;

interface ChatClient
{
    public function chat(array $messages, array $tools, ?string $model): array;
}
