<?php

use App\Services\WhatsApp\WebhookSignatureVerifier;

beforeEach(function (): void {
    config(['services.whatsapp.app_secret' => 'test-app-secret']);
});

it('accepts a correctly signed payload', function (): void {
    $body = json_encode(['object' => 'whatsapp_business_account']);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-app-secret');

    expect(WebhookSignatureVerifier::verify($body, $signature))->toBeTrue();
});

it('rejects a payload signed with the wrong secret', function (): void {
    $body = json_encode(['object' => 'whatsapp_business_account']);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'wrong-secret');

    expect(WebhookSignatureVerifier::verify($body, $signature))->toBeFalse();
});

it('rejects a payload whose body was tampered with after signing', function (): void {
    $originalBody = json_encode(['object' => 'whatsapp_business_account']);
    $signature = 'sha256='.hash_hmac('sha256', $originalBody, 'test-app-secret');

    $tamperedBody = json_encode(['object' => 'tampered']);

    expect(WebhookSignatureVerifier::verify($tamperedBody, $signature))->toBeFalse();
});

it('rejects a missing signature header', function (): void {
    $body = json_encode(['object' => 'whatsapp_business_account']);

    expect(WebhookSignatureVerifier::verify($body, null))->toBeFalse();
});

it('rejects a signature header without the sha256= prefix', function (): void {
    $body = json_encode(['object' => 'whatsapp_business_account']);
    $signature = hash_hmac('sha256', $body, 'test-app-secret');

    expect(WebhookSignatureVerifier::verify($body, $signature))->toBeFalse();
});

it('rejects when no app secret is configured', function (): void {
    config(['services.whatsapp.app_secret' => null]);

    $body = json_encode(['object' => 'whatsapp_business_account']);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-app-secret');

    expect(WebhookSignatureVerifier::verify($body, $signature))->toBeFalse();
});
