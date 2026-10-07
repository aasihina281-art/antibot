<?php

namespace WAFSystem;

use Exception;

class Marker
{
    const COOKIE_KEY = 'aw_marker';

    private $Config;
    private $profile;
    private $logger;
    private $expireDays;
    private $nameMarker; // команда установки куки
    private $storageType = 'cookie'; // тип хранилища для метки

    public function __construct(Config $config, Profile $profile, Logger $logger)
    {
        $this->Config = $config;
        $this->profile = $profile;
        $this->logger = $logger;

        $config->init('cookie', 'cookie_name', substr($this->profile->genKey(), 0, rand(5, 11)), 'Изменение значения, позволяет сбросить метку всем пользователям');
        $config->init('cookie', 'expire_days', 30, 'дней, действия метки');
        $this->storageType = $config->init('cookie', 'storage_type', $this->storageType, 'тип хранилища для метки (cookie, awsession)');
        $this->markerSecret = $config->init('cookie', 'marker_secret', $this->profile->genKey(), 'секрет для криптографической подписи метки');

        $this->expireDays = (int)$config->get('cookie', 'expire_days', 30);
        $this->genNameMarker();
    }

    public function getNameMarker()
    {
        return $this->nameMarker;
    }

    // Устанавливаем имя маркера для каждой сессии
    private function genNameMarker()
    {
        if (isset($_SESSION['name_marker']) && !empty($_SESSION['name_marker']))
            $this->nameMarker = $_SESSION['name_marker'];
        else {
            $this->nameMarker = \Utility\GenerateRandomName::genFuncName();
            $_SESSION['name_marker'] = $this->nameMarker;
        }
    }

    function set($time = null)
    {
        if ($time == null)
            $time = time() + $this->expireDays * 86400;

        $payload = $this->profile->RayID . '.' . (int)$time . '.' . substr($this->profile->genKey(), 0, 16);
        $signature = hash_hmac('sha256', $payload, $this->markerSecret);
        $cookie_value = $payload . '.' . $signature;

        if ($this->storageType == "awsession") {
            $Session = \WAFSystem\WAFSystem::getInstance()->Session;
            $Session->set(self::COOKIE_KEY, $cookie_value, $time);

        } else {
            if (version_compare(PHP_VERSION, '7.3.0') >= 0) {
                setcookie(self::COOKIE_KEY, $cookie_value, [
                    'expires' => $time,
                    'path' => '/',
                    'httponly' => true,
                    'secure' => isset($_SERVER['HTTPS'])
                ]);
            } else {
                setcookie(self::COOKIE_KEY, $cookie_value, time() + $this->expireDays * 24 * 3600, "/");
            }
        }

        $this->logger->logMessage("Tag set");
        $this->genNameMarker();
    }

    function remove()
    {
        $this->set(time() - 3600);
    }

    function isValid()
    {
        if ($this->storageType == "awsession") {
            $Session = \WAFSystem\WAFSystem::getInstance()->Session;
            $value = $Session->get(self::COOKIE_KEY);
            if (!is_null($value) && $this->verifyValue($value)) {
                return true;
            }
                
        } else {
            if (isset($_COOKIE[self::COOKIE_KEY]) && $this->verifyValue($_COOKIE[self::COOKIE_KEY])) {
                return true;
            }
        }

        return false;
    }

    private function verifyValue($value)
    {
        if (!is_string($value) || strlen($value) > 512) {
            return false;
        }

        $parts = explode('.', $value);
        if (count($parts) !== 4) {
            return false;
        }

        list($rayId, $expires, $nonce, $signature) = $parts;
        if (!preg_match('/^[a-f0-9]{16}$/', $rayId)
            || !ctype_digit($expires)
            || !preg_match('/^[a-f0-9]{16}$/', $nonce)
            || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
            return false;
        }

        if ((int)$expires < time()) {
            return false;
        }

        if (!hash_equals($this->profile->RayID, $rayId)) {
            return false;
        }

        $payload = $rayId . '.' . (int)$expires . '.' . $nonce;
        $expected = hash_hmac('sha256', $payload, $this->markerSecret);

        return hash_equals($expected, $signature);
    }
}
