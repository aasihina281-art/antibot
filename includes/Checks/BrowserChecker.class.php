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
        $ch = isset($data['clientHints']) && is_array($data['clientHints']) ? $data['clientHints'] : array();
        $secFetch = isset($data['secFetch']) && is_array($data['secFetch']) ? $data['secFetch'] : array();

        if (isset($nav['webdriver']) && $nav['webdriver'] === true) {
            $add('webdriver', 35);
        }

        if (isset($data['missingFeatures']) && is_array($data['missingFeatures']) && count($data['missingFeatures']) > 0) {
            $add('missing_features', 15);
        }

        if (isset($data['canvas']) && $data['canvas'] === false) {
            $add('canvas', 10);
        }

        if (isset($data['webgl']) && $data['webgl'] === false) {
            $add('webgl', 10);
        }

        if (!empty($ch['mismatch'])) {
            $add('client_hints_mismatch', 20);
        }

        // Sec-Fetch отсутствующие не блокируют сами по себе.
        $expectedFetch = isset($secFetch['expected']) ? (int)$secFetch['expected'] : 0;
        $presentFetch = isset($secFetch['present']) ? (int)$secFetch['present'] : 0;
        if ($expectedFetch >= 2 && $presentFetch === 0) {
            $add('sec_fetch_missing', 10);
        }

        if (!empty($data['protocolMismatch'])) {
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
}
