<?php

namespace App\Services\WhatsApp\Support;

use App\Contracts\UrlSafetyChecker;

/**
 * Blocks server-side request forgery (SSRF) on admin-configured webhook
 * URLs (see AutomationStepExecutor::callWebhook). Rejects anything that
 * isn't a plain http(s) URL whose host — resolved via DNS if it isn't
 * already a literal IP — lands only on public, non-reserved addresses.
 * FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE together cover
 * RFC1918 private ranges, loopback (127.0.0.0/8, ::1), and link-local
 * (169.254.0.0/16 — which is also where the AWS/GCP/Azure cloud
 * metadata address 169.254.169.254 lives) for both IPv4 and IPv6.
 *
 * Does not defend against DNS rebinding between this check and the
 * actual request — the caller should also disable HTTP redirects,
 * since a same-host-then-redirect-to-internal 3xx response is the
 * other classic bypass (see AutomationStepExecutor's Http call).
 */
class DnsUrlSafetyChecker implements UrlSafetyChecker
{
    public function isSafe(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'];

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->isPublicIp($host);
        }

        $ips = $this->resolve($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false) {
            return [];
        }

        $ips = [];

        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $ips[] = $record['ip'];
            }

            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return $ips;
    }
}
