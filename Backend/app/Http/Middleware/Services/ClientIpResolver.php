<?php

namespace App\Http\Middleware\Services;

use Illuminate\Http\Request;

class ClientIpResolver
{
    /**
     * Resolve the actual public visitor IP.
     */
    public function resolve(Request $request): ?string
    {
        $remoteAddress = $request->server->get('REMOTE_ADDR');

        // Only trust CF headers if the direct connection came
        // from an official Cloudflare proxy address.
        if (
            is_string($remoteAddress)
            && filter_var($remoteAddress, FILTER_VALIDATE_IP)
            && $this->isCloudflareIp($remoteAddress)
        ) {
            // If Cloudflare Pseudo IPv4 overwrites the IPv4 header,
            // this preserves the visitor's original IPv6 address.
            $candidate = $request->header('CF-Connecting-IPv6')
                ?: $request->header('CF-Connecting-IP');

            return is_string($candidate)
                && filter_var(trim($candidate), FILTER_VALIDATE_IP)
                    ? trim($candidate)
                    : null;
        }

        // If the server already restored the original IP,
        // or this is not a Cloudflare connection, use Laravel's
        // trusted-proxy-aware IP resolution.
        $candidate = $request->ip();

        return is_string($candidate)
            && filter_var($candidate, FILTER_VALIDATE_IP)
                ? $candidate
                : null;
    }

    private function isCloudflareIp(string $ip): bool
    {
        foreach (config('cloudflare.proxies', []) as $cidr) {
            if ($this->matchesCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function matchesCidr(string $ip, string $cidr): bool
    {
        [$network, $prefix] = array_pad(
            explode('/', $cidr, 2),
            2,
            null
        );

        $ipBinary = inet_pton($ip);
        $networkBinary = inet_pton($network);

        if (
            $ipBinary === false
            || $networkBinary === false
            || strlen($ipBinary) !== strlen($networkBinary)
        ) {
            return false;
        }

        $prefix = $prefix === null
            ? strlen($networkBinary) * 8
            : (int) $prefix;

        $maximumBits = strlen($networkBinary) * 8;

        if ($prefix < 0 || $prefix > $maximumBits) {
            return false;
        }

        $wholeBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if (
            $wholeBytes > 0
            && substr($ipBinary, 0, $wholeBytes)
                !== substr($networkBinary, 0, $wholeBytes)
        ) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ipBinary[$wholeBytes]) & $mask)
            === (ord($networkBinary[$wholeBytes]) & $mask);
    }
}