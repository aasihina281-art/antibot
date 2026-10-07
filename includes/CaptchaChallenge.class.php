<?php

namespace WAFSystem;

class CaptchaChallenge
{
    private $Config;
    private $ttl;
    private $minSeconds;
    private $maxAttempts;

    public function __construct(Config $config)
    {
        $this->Config = $config;
        $this->ttl = (int)$config->init('captcha_security', 'challenge_ttl', 120, 'секунд жизни серверного challenge');
        $this->minSeconds = (float)$config->init('captcha_security', 'challenge_min_seconds', 0.8, 'минимальное время до принятия решения CAPTCHA');
        $this->maxAttempts = (int)$config->init('captcha_security', 'challenge_max_attempts', 5, 'максимум попыток завершения CAPTCHA');
    }

    private function now()
    {
        return microtime(true);
    }

    public function issue()
    {
        $bytes = function_exists('random_bytes') ? random_bytes(24) : openssl_random_pseudo_bytes(24);
        $nonce = bin2hex($bytes);
        $_SESSION['aw_captcha_challenge'] = [
            'nonce' => $nonce,
            'created' => $this->now(),
            'expires' => time() + max(10, $this->ttl),
            'attempts' => 0,
            'solved' => false
        ];

        return $nonce;
    }

    public function get()
    {
        return isset($_SESSION['aw_captcha_challenge'])
            && is_array($_SESSION['aw_captcha_challenge'])
            ? $_SESSION['aw_captcha_challenge']
            : null;
    }

    public function getToken()
    {
        $state = $this->get();
        return $state && isset($state['nonce']) ? $state['nonce'] : '';
    }

    public function isActive()
    {
        $state = $this->get();
        if (!$state || !isset($state['expires'], $state['solved'])) {
            return false;
        }

        if ($state['solved'] || time() > (int)$state['expires']) {
            return false;
        }

        return true;
    }

    public function consumeSuccess($clientData = [])
    {
        $state = $this->get();

        if (!$state || !$this->isActive()) {
            return ['ok' => false, 'reason' => 'captcha_challenge_missing_or_expired'];
        }

        $state['attempts'] = isset($state['attempts']) ? ((int)$state['attempts'] + 1) : 1;
        $_SESSION['aw_captcha_challenge'] = $state;

        if ($state['attempts'] > max(1, $this->maxAttempts)) {
            return ['ok' => false, 'reason' => 'captcha_attempt_limit'];
        }

        $elapsed = $this->now() - (float)$state['created'];
        if ($elapsed < $this->minSeconds) {
            return ['ok' => false, 'reason' => 'captcha_completed_too_fast'];
        }

        if (!isset($clientData['challenge_nonce']) || !is_string($clientData['challenge_nonce'])
            || !hash_equals((string)$state['nonce'], $clientData['challenge_nonce'])) {
            return ['ok' => false, 'reason' => 'captcha_challenge_mismatch'];
        }

        if (!isset($clientData['events'])) {
            return ['ok' => false, 'reason' => 'captcha_interaction_missing'];
        }
        $events = filter_var($clientData['events'], FILTER_VALIDATE_INT);
        if ($events === false || $events < 1 || $events > 100000) {
            return ['ok' => false, 'reason' => 'captcha_invalid_interaction'];
        }

        if (isset($clientData['challenge_elapsed'])) {
            $clientElapsed = filter_var($clientData['challenge_elapsed'], FILTER_VALIDATE_INT);
            if ($clientElapsed === false || $clientElapsed < 0 || $clientElapsed > ($this->ttl * 1000 + 5000)) {
                return ['ok' => false, 'reason' => 'captcha_invalid_elapsed'];
            }
        }

        $state['solved'] = true;
        $_SESSION['aw_captcha_challenge'] = $state;

        return ['ok' => true, 'reason' => 'ok'];
    }

    public function clear()
    {
        unset($_SESSION['aw_captcha_challenge']);
    }
}
