<?php

namespace ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Contracts\MiddlewareInterface;

class IpFilter implements MiddlewareInterface
{
    const MODE_ALLOW = 'allow';

    const MODE_DENY = 'deny';

    /** @var string[] */
    private $ips;

    /** @var string */
    private $mode;

    /**
     * @param  string[]  $ips
     */
    public function __construct(array $ips, string $mode = self::MODE_ALLOW)
    {
        $this->ips = $ips;
        $this->mode = $mode;
    }

    /**
     * 建立白名單過濾器
     *
     * @param  string[]  $ips
     */
    public static function allow(array $ips): self
    {
        return new self($ips, self::MODE_ALLOW);
    }

    /**
     * 建立黑名單過濾器
     *
     * @param  string[]  $ips
     */
    public static function deny(array $ips): self
    {
        return new self($ips, self::MODE_DENY);
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

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
        if (strpos($pattern, '/') !== false) {
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
