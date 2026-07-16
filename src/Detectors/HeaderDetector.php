<?php

namespace Bale\Gupa\Detectors;

use Illuminate\Http\Request;

class HeaderDetector implements DetectorInterface
{
    private const DEFAULT_SCORE = 20;

    private const BOT_USER_AGENTS = [
        'scrapy',
        'curl',
        'wget',
        'python-requests',
        'python-urllib',
        'go-http-client',
        'php/',
        'java/',
        'perl',
        'ruby',
        'nikto',
        'sqlmap',
        'masscan',
        'nmap',
        'zgrab',
        'httpx',
    ];

    public function detect(Request $request): int
    {
        $score = 0;

        if ($this->hasBotUserAgent($request)) {
            $score += config('gupa.detectors.header.bot_user_agent_score', self::DEFAULT_SCORE);
        }

        if ($this->hasMissingAcceptHeader($request)) {
            $score += config('gupa.detectors.header.missing_accept_score', 10);
        }

        if ($this->hasMissingAcceptLanguage($request)) {
            $score += config('gupa.detectors.header.missing_accept_language_score', 10);
        }

        if ($this->hasMissingRefererOnPost($request)) {
            $score += config('gupa.detectors.header.missing_referer_post_score', 5);
        }

        return $score;
    }

    private function hasBotUserAgent(Request $request): bool
    {
        $userAgent = strtolower($request->userAgent() ?? '');

        if ($userAgent === '') {
            return true;
        }

        foreach (self::BOT_USER_AGENTS as $bot) {
            if (str_contains($userAgent, $bot)) {
                return true;
            }
        }

        return false;
    }

    private function hasMissingAcceptHeader(Request $request): bool
    {
        return $request->header('Accept') === null;
    }

    private function hasMissingAcceptLanguage(Request $request): bool
    {
        return $request->header('Accept-Language') === null;
    }

    private function hasMissingRefererOnPost(Request $request): bool
    {
        if ($request->method() !== 'POST') {
            return false;
        }

        return $request->header('Referer') === null;
    }

    public function isEnabled(): bool
    {
        return (bool) config('gupa.detectors.header.enabled', true);
    }

    public function getName(): string
    {
        return 'header';
    }
}
