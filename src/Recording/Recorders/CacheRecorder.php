<?php

namespace VictorStochero\Warden\Recording\Recorders;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use VictorStochero\Warden\Recording\AbstractRecorder;

class CacheRecorder extends AbstractRecorder
{
    public function type(): string
    {
        return 'cache';
    }

    public function register(): void
    {
        $this->events->listen(CacheHit::class, fn (CacheHit $e) => $this->log('hit', $e->key, $e->storeName ?? null));
        $this->events->listen(CacheMissed::class, fn (CacheMissed $e) => $this->log('miss', $e->key, $e->storeName ?? null));
        $this->events->listen(KeyWritten::class, fn (KeyWritten $e) => $this->log('write', $e->key, $e->storeName ?? null));
        $this->events->listen(KeyForgotten::class, fn (KeyForgotten $e) => $this->log('forget', $e->key, $e->storeName ?? null));
    }

    protected function log(string $action, string $key, ?string $store): void
    {
        $this->record([
            'action' => $action,
            'key' => $key,
            'store' => $store,
            'hit' => $action === 'hit',
        ]);
    }
}
