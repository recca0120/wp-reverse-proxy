<?php

namespace Recca0120\ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Support\Arr;
use Recca0120\ReverseProxy\Support\Str;

/**
 * @UILabel("IP Filter")
 * @UIDescription("Filter requests by IP address")
 */
class IpFilter implements MiddlewareInterface
{
    public const MODE_ALLOW = 'allow';

    public const MODE_DENY = 'deny';

    /** @var string[] */
    private $ips;

    /** @var string */
    private $mode;

    /**
     * @param  string  $modeOrIp  模式 (allow/deny) 或第一個 IP
     * @param  string|string[]  ...$ips  IP 列表
     *
     * @UIField(name="modeOrIp", type="select", label="Mode", options="allow:Allow List,deny:Deny List", default="allow")
     * @UIField(name="ips", type="repeater", label="IP Addresses", placeholder="e.g. 192.168.1.0/24")
     */
    public function __construct(string $modeOrIp = self::MODE_ALLOW, ...$ips)
    {
        // Support both: new IpFilter('allow', '1.2.3.4', '5.6.7.8') and new IpFilter('allow', ['1.2.3.4', '5.6.7.8'])
        if (count($ips) === 1 && is_array($ips[0])) {
            $ips = $ips[0];
        }

        if (Arr::contains([self::MODE_ALLOW, self::MODE_DENY], $modeOrIp)) {
            $this->mode = $modeOrIp;
        } else {
            $this->mode = self::MODE_ALLOW;
            array_unshift($ips, $modeOrIp);
        }
        $this->ips = $ips;
    }

    /**
     * 建立白名單過濾器
     */
    public static function allow(string ...$ips): self
    {
        return new self(self::MODE_ALLOW, ...$ips);
    }

    /**
     * 建立黑名單過濾器
     */
    public static function deny(string ...$ips): self
    {
        return new self(self::MODE_DENY, ...$ips);
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $serverParams = $request->getServerParams();
        $clientIp = $serverParams['REMOTE_ADDR'] ?? '';

        if (! $this->isAllowed($clientIp)) {
            return $this->createForbiddenResponse();
        }

        return $next($request);
    }

    private function isAllowed(string $clientIp): bool
    {
        $matchesAnyIp = $this->matchesAnyIp($clientIp);

        if ($this->mode === self::MODE_ALLOW) {
            // 白名單模式：IP 必須在清單中
            return $matchesAnyIp;
        }

        // 黑名單模式：IP 不能在清單中
        return ! $matchesAnyIp;
    }

    private function matchesAnyIp(string $clientIp): bool
    {
        foreach ($this->ips as $ip) {
            if ($this->matchesIp($clientIp, $ip)) {
                return true;
            }
        }

        return false;
    }

    private function matchesIp(string $clientIp, string $pattern): bool
    {
        // CIDR 格式
        if (Str::contains($pattern, '/')) {
            return $this->matchesCidr($clientIp, $pattern);
        }

        // 精確匹配
        return $clientIp === $pattern;
    }

    private function matchesCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = -1 << (32 - (int) $bits);

        $subnetLong &= $mask;

        return ($ipLong & $mask) === $subnetLong;
    }

    private function createForbiddenResponse(): ResponseInterface
    {
        $body = json_encode([
            'error' => 'Forbidden',
            'status' => 403,
        ]);

        return new Response(
            403,
            ['Content-Type' => 'application/json'],
            $body
        );
    }
}
