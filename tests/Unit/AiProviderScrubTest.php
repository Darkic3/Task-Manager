<?php

namespace Tests\Unit;

use App\Exceptions\AiProviderException;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4: the scrubber is the single choke point keeping URLs, keys and
 * tokens out of user responses and server logs.
 */
class AiProviderScrubTest extends TestCase
{
    public function test_urls_become_host_only(): void
    {
        $out = AiProviderException::scrub(
            'cURL error 7: Failed to connect for https://router.example/v1/chat/completions, errno 7'
        );

        $this->assertStringNotContainsString('https://', $out);
        $this->assertStringNotContainsString('/v1/chat', $out);
        $this->assertStringContainsString('[provider router.example]', $out);
    }

    public function test_query_keys_and_bearer_tokens_redacted(): void
    {
        $out = AiProviderException::scrub(
            'GET https://api.example/v1?key=sk-live-123&model=x with Bearer sk-live-123 failed'
        );

        $this->assertStringNotContainsString('sk-live-123', $out);
        $this->assertStringContainsString('[provider api.example]', $out);
        $this->assertStringContainsString('Bearer [redacted]', $out);
        $this->assertSame('?key=[redacted]', AiProviderException::scrub('?key=abc'));
    }

    public function test_plain_text_passes_through(): void
    {
        $this->assertSame(
            'HTTP 500: internal error',
            AiProviderException::scrub('HTTP 500: internal error')
        );
    }

    public function test_constructor_stores_scrubbed_message(): void
    {
        $e = new AiProviderException(
            'boom for https://secret.example/x?token=abc', 'prov', 'https://secret.example/x', 500
        );

        $this->assertStringNotContainsString('https://', $e->getMessage());
        $this->assertStringNotContainsString('abc', $e->getMessage());
        $this->assertSame(500, $e->httpStatus);
        $this->assertTrue($e->isRetryable());
    }
}
