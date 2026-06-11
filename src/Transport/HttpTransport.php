<?php

namespace VictorStochero\Warden\Transport;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use VictorStochero\Warden\Config\ConfigCache;
use VictorStochero\Warden\Contracts\Transport;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Warden;

/**
 * Ships event batches to the parent's /ingest endpoint. Never throws: any
 * network or HTTP failure returns false so the daemon keeps the batch in the
 * outbox and retries later (RNF-2). Always runs suppressed so the POST itself
 * is not observed (§18.3).
 */
class HttpTransport implements Transport
{
    /** @var array<string, mixed> Directives from the last successful ingest response. */
    protected array $directives = [];

    /** Guards the one-time insecure-parent_url warning per process. */
    protected static bool $insecureWarned = false;

    public function __construct(
        protected Warden $observer,
        protected Repository $config,
        protected Http $http,
    ) {}

    /** @return array<string, mixed> */
    public function lastDirectives(): array
    {
        return $this->directives;
    }

    public function ship(array $batch): bool
    {
        return $this->observer->withoutRecording(function () use ($batch) {
            try {
                $this->warnIfInsecure();

                $body = json_encode([
                    'schema_version' => 2,
                    'project' => $this->config->get('warden.child.project'),
                    'sent_at' => time(),
                    'app_timezone' => Cast::str($this->config->get('app.timezone'), 'UTC'),
                    'config_version' => ConfigCache::version(),
                    'batches' => array_values($batch),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                if ($body === false) {
                    return false;
                }

                $signer = new Signer(Cast::str($this->config->get('warden.child.secret')));

                $response = $this->http
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-Warden-Token' => Cast::str($this->config->get('warden.child.token')),
                        'X-Warden-Signature' => $signer->sign($body),
                    ])
                    ->withBody($body, 'application/json')
                    ->timeout(10)
                    ->post($this->ingestUrl());

                if ($response->successful()) {
                    $json = $response->json();
                    $directives = [];
                    if (is_array($json)) {
                        foreach ($json as $key => $value) {
                            $directives[(string) $key] = $value;
                        }
                    }
                    $this->directives = $directives;

                    return true;
                }

                return false;
            } catch (Throwable) {
                return false; // resilience: swallow and retry later
            }
        });
    }

    public function reportDeadLetter(string $batchId, string $reason, int $attempts): bool
    {
        return $this->observer->withoutRecording(function () use ($batchId, $reason, $attempts) {
            try {
                $body = json_encode([
                    'batch_id' => $batchId,
                    'reason' => $reason,
                    'attempts' => $attempts,
                    'sent_at' => time(),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                if ($body === false) {
                    return false;
                }

                $signer = new Signer(Cast::str($this->config->get('warden.child.secret')));

                $response = $this->http
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-Warden-Token' => Cast::str($this->config->get('warden.child.token')),
                        'X-Warden-Signature' => $signer->sign($body),
                    ])
                    ->withBody($body, 'application/json')
                    ->timeout(10)
                    ->post($this->deadLetterUrl());

                return $response->successful();
            } catch (Throwable) {
                return false;
            }
        });
    }

    /**
     * Warn once if the parent URL is not HTTPS. The channel is authenticated and
     * replay-protected, but only TLS keeps the secret and payload confidential
     * on the wire — a plaintext parent_url is almost always a misconfiguration.
     */
    protected function warnIfInsecure(): void
    {
        if (static::$insecureWarned) {
            return;
        }

        $url = Cast::str($this->config->get('warden.child.parent_url'));

        if ($url !== '' && ! str_starts_with(strtolower($url), 'https://')) {
            static::$insecureWarned = true;
            Log::warning('Warden: WARDEN_PARENT_URL is not HTTPS; event batches (and the signing secret) travel in plaintext. Use https:// in production.');
        }
    }

    protected function ingestUrl(): string
    {
        $base = rtrim(Cast::str($this->config->get('warden.child.parent_url')), '/');
        $path = trim(Cast::str($this->config->get('warden.child.ingest_path', 'warden/ingest'), 'warden/ingest'), '/');

        return "{$base}/{$path}";
    }

    protected function deadLetterUrl(): string
    {
        $base = rtrim(Cast::str($this->config->get('warden.child.parent_url')), '/');
        $ingestPath = trim(Cast::str($this->config->get('warden.child.ingest_path', 'warden/ingest'), 'warden/ingest'), '/');
        // Derive by replacing the trailing "ingest" segment with "dead-letter".
        $dlPath = preg_replace('/\bingest$/', 'dead-letter', $ingestPath) ?? 'warden/dead-letter';

        return "{$base}/{$dlPath}";
    }
}
