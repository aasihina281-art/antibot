<?php

namespace WAFSystem;

class BrowserChecker
{
    public $enabled = false;
    public $action = 'CAPTCHA';
    public $riskCaptcha = 35;
    public $riskBlock = 80;

    private $moduleName = 'browser_checker';
    private $Config;
    private $Logger;

    public function __construct(Config $config, Logger $logger)
    {
        $this->Config = $config;
        $this->Logger = $logger;

        $this->enabled = $config->init($this->moduleName, 'enabled', $this->enabled, 'On - включить BrowserChecker, Off - выключить');
        $this->action = $config->init($this->moduleName, 'action', $this->action, 'CAPTCHA - капча, BLOCK - блокировка, ALLOW - пропуск, SKIP - только анализ');
        $this->riskCaptcha = (int)$config->init($this->moduleName, 'risk_captcha', $this->riskCaptcha, 'Порог риска для CAPTCHA');
        $this->riskBlock = (int)$config->init($this->moduleName, 'risk_block', $this->riskBlock, 'Порог риска для BLOCK');
    }

    public function Checking($data, Profile $profile)
    {
        if (!$this->enabled || !is_array($data)) {
            return array('score' => 0, 'signals' => array(), 'action' => 'SKIP');
        }

        $score = 0;
        $signals = array();
        $add = function($name, $weight) use (&$score, &$signals) {
            $score += (int)$weight;
            $signals[] = $name . '+' . (int)$weight;
        };

        $nav = isset($data['navigator']) && is_array($data['navigator']) ? $data['navigator'] : array();
        $telemetry = isset($data['browserTelemetry']) && is_array($data['browserTelemetry']) ? $data['browserTelemetry'] : array();
        $ch = isset($telemetry['clientHints']) && is_array($telemetry['clientHints']) ? $telemetry['clientHints'] : array();

        if (isset($nav['webdriver']) && $nav['webdriver'] === true) {
            $add('webdriver', 35);
        }

        if (isset($telemetry['canvas']) && $telemetry['canvas'] === false) {
            $add('canvas', 10);
        }

        if (isset($telemetry['webgl']) && $telemetry['webgl'] === false) {
            $add('webgl', 10);
        }

        if (isset($telemetry['missingFeatures']) && is_array($telemetry['missingFeatures']) && count($telemetry['missingFeatures']) > 0) {
            $add('missing_features', 15);
        }

        $uaMobile = stripos($profile->UserAgent, 'Mobile') !== false;
        if (isset($ch['available']) && $ch['available']) {
            if (isset($ch['mobile']) && (bool)$ch['mobile'] !== $uaMobile) {
                $add('client_hints_mismatch', 20);
            }
            if (!empty($ch['platform'])) {
                $platform = strtolower($ch['platform']);
                if (stripos($profile->UserAgent, 'Windows') !== false && strpos($platform, 'windows') === false) $add('client_hints_mismatch', 20);
                if (stripos($profile->UserAgent, 'Android') !== false && strpos($platform, 'android') === false) $add('client_hints_mismatch', 20);
            }
        }

        // Клиентские Sec-Fetch заголовки доступны серверу, а не JS.
        $fetchPresent = 0;
        foreach (array('HTTP_SEC_FETCH_SITE','HTTP_SEC_FETCH_MODE','HTTP_SEC_FETCH_DEST','HTTP_SEC_FETCH_USER') as $header) {
            if (!empty($_SERVER[$header])) $fetchPresent++;
        }
        if ($fetchPresent === 0 && $this->looksLikeModernBrowser($profile->UserAgent)) {
            $add('sec_fetch_missing', 10);
        }

        $clientProtocol = isset($_SERVER['HTTP_X_CLIENT_PROTOCOL']) ? strtolower(trim($_SERVER['HTTP_X_CLIENT_PROTOCOL'])) : '';
        $httpVersion = strtolower(trim((string)$profile->HttpVersion));
        if ($clientProtocol !== '' && $httpVersion !== '' && $this->protocolMismatch($clientProtocol, $httpVersion)) {
            $add('protocol_mismatch', 15);
        }

        // Дополнительная защита от поддельного типа данных.
        if (isset($data['isMobile']) && !is_bool($data['isMobile']) && $data['isMobile'] !== null) {
            $add('mobile_type', 10);
        }

        if ($score >= $this->riskBlock) {
            $action = 'BLOCK';
        } elseif ($score >= $this->riskCaptcha) {
            $action = 'CAPTCHA';
        } else {
            $action = 'SKIP';
        }

        $this->Logger->log('BrowserChecker: score=' . $score . ' action=' . $action .
            (empty($signals) ? '' : ' signals=' . implode(',', $signals)));

        return array('score' => $score, 'signals' => $signals, 'action' => $action);
    }

    private function looksLikeModernBrowser($ua)
    {
        return (bool)preg_match('/Chrome\\/(9[0-9]|1[0-9]{2})|Firefox\\/(9[0-9]|1[0-9]{2})|Edg\\/(9[0-9]|1[0-9]{2})|Safari\\/6[0-9]+/i', (string)$ua);
    }

    private function protocolMismatch($clientProtocol, $httpVersion)
    {
        if (strpos($clientProtocol, 'http/1') !== false && strpos($httpVersion, '1.') !== false) return false;
        if ((strpos($clientProtocol, 'http/2') !== false || strpos($clientProtocol, 'h2') !== false) && strpos($httpVersion, '2') !== false) return false;
        if ((strpos($clientProtocol, 'http/3') !== false || strpos($clientProtocol, 'h3') !== false) && strpos($httpVersion, '3') !== false) return false;
        return true;
    }
}
