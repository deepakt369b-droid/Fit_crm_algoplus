<?php

use App\Services\WhatsApp\Support\DnsUrlSafetyChecker;

/**
 * Deliberately exercised only via literal-IP URLs (plus one hostname,
 * "localhost") so these tests never depend on real network/DNS access —
 * a hostname's resolve() path is otherwise hard to test deterministically
 * without mocking PHP's global dns_get_record(). "localhost" always
 * resolves via the local hosts file, which every environment (including
 * CI containers) has by default, so it exercises that path safely.
 */
it('rejects loopback IPv4', function (): void {
    expect((new DnsUrlSafetyChecker)->isSafe('http://127.0.0.1/'))->toBeFalse();
});

it('rejects loopback IPv6', function (): void {
    expect((new DnsUrlSafetyChecker)->isSafe('http://[::1]/'))->toBeFalse();
});

it('rejects the cloud metadata address', function (): void {
    expect((new DnsUrlSafetyChecker)->isSafe('http://169.254.169.254/latest/meta-data/'))->toBeFalse();
});

it('rejects RFC1918 private ranges', function (): void {
    expect((new DnsUrlSafetyChecker)->isSafe('http://10.0.0.5/'))->toBeFalse()
        ->and((new DnsUrlSafetyChecker)->isSafe('http://172.16.0.5/'))->toBeFalse()
        ->and((new DnsUrlSafetyChecker)->isSafe('http://192.168.1.5/'))->toBeFalse();
});

it('rejects the "localhost" hostname', function (): void {
    expect((new DnsUrlSafetyChecker)->isSafe('http://localhost/'))->toBeFalse();
});

it('rejects a non-http(s) scheme even against a public IP', function (): void {
    expect((new DnsUrlSafetyChecker)->isSafe('ftp://8.8.8.8/'))->toBeFalse()
        ->and((new DnsUrlSafetyChecker)->isSafe('file:///etc/passwd'))->toBeFalse();
});

it('rejects an empty or unparseable URL', function (): void {
    expect((new DnsUrlSafetyChecker)->isSafe(''))->toBeFalse()
        ->and((new DnsUrlSafetyChecker)->isSafe('not a url'))->toBeFalse();
});

it('accepts a public IPv4 address', function (): void {
    expect((new DnsUrlSafetyChecker)->isSafe('https://8.8.8.8/hook'))->toBeTrue();
});
