<?php

use Illuminate\Support\Facades\Route;

describe('health', function () {
    test('reports ok without caching and without touching the database', function () {
        $response = $this->getJson('/api/v1/health')->assertOk();

        expect($response->json('status'))->toBe('ok')
            ->and($response->json())->toHaveKeys(['status', 'time'])
            ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    });
});

describe('frontend API key', function () {
    beforeEach(function () {
        config(['services.frontend.api_key' => 'test-key-123']);

        // A stand-in for the write endpoints that will sit behind this middleware.
        Route::middleware(['api', 'frontend.key'])->post('/api/v1/_guarded', fn () => response()->json(['ok' => true]));
    });

    test('rejects a request without the key', function () {
        $this->postJson('/api/v1/_guarded')->assertUnauthorized()->assertExactJson(['message' => 'Unauthorized.']);
    });

    test('rejects a wrong key', function () {
        $this->postJson('/api/v1/_guarded', [], ['X-Api-Key' => 'nope'])->assertUnauthorized();
    });

    test('rejects a key that only shares a prefix with the real one', function () {
        $this->postJson('/api/v1/_guarded', [], ['X-Api-Key' => 'test-key-12'])->assertUnauthorized();
    });

    test('accepts the right key', function () {
        $this->postJson('/api/v1/_guarded', [], ['X-Api-Key' => 'test-key-123'])->assertOk()->assertJson(['ok' => true]);
    });

    test('fails closed when no key is configured, even for an empty header', function () {
        config(['services.frontend.api_key' => null]);

        $this->postJson('/api/v1/_guarded')->assertUnauthorized();
        $this->postJson('/api/v1/_guarded', [], ['X-Api-Key' => ''])->assertUnauthorized();
    });
});

describe('rate limiting', function () {
    test('anonymous callers are limited per IP and told when to retry', function () {
        foreach (range(1, 120) as $i) {
            $this->getJson('/api/v1/health')->assertOk();
        }

        $this->getJson('/api/v1/health')
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    });

    test('the frontend server, identified by its key, is not throttled at the anonymous limit', function () {
        config(['services.frontend.api_key' => 'test-key-123']);

        foreach (range(1, 150) as $i) {
            $this->getJson('/api/v1/health', ['X-Api-Key' => 'test-key-123'])->assertOk();
        }
    });

    test('a wrong key gets no special treatment', function () {
        config(['services.frontend.api_key' => 'test-key-123']);

        foreach (range(1, 120) as $i) {
            $this->getJson('/api/v1/health', ['X-Api-Key' => 'wrong'])->assertOk();
        }

        $this->getJson('/api/v1/health', ['X-Api-Key' => 'wrong'])->assertStatus(429);
    });
});
