<?php

namespace WAFSystem;

class BehaviorChecker
{
    public $enabled = false;
    public $action = 'CAPTCHA';
    public $riskCaptcha = 35;
    public $riskBlock = 80;

    private $moduleName = 'behavior_checker';
    private $Config;
    private $Logger;

    public function __construct(Config $config, Logger $logger)
    {
        $this->Config = $config;
        $this->Logger = $logger;

        $this->enabled = $config->init($this->moduleName, 'enabled', $this->enabled, 'On - включить BehaviorChecker, Off - выключить');
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

        $behavior = isset($data['behavior']) && is_array($data['behavior']) ? $data['behavior'] : array();
        $events = isset($behavior['events']) ? max(0, (int)$behavior['events']) : 0;
        $pointer = isset($behavior['pointer']) ? max(0, (int)$behavior['pointer']) : 0;
        $touch = isset($behavior['touch']) ? max(0, (int)$behavior['touch']) : 0;
        $keyboard = isset($behavior['keyboard']) ? max(0, (int)$behavior['keyboard']) : 0;
        $moveIntervals = isset($behavior['moveIntervals']) && is_array($behavior['moveIntervals']) ? $behavior['moveIntervals'] : array();
        $clicks = isset($behavior['clicks']) ? max(0, (int)$behavior['clicks']) : 0;
        $scroll = isset($behavior['scroll']) ? max(0, (int)$behavior['scroll']) : 0;
        $elapsed = isset($behavior['elapsedMs']) ? max(0, (int)$behavior['elapsedMs']) : 0;
        $firstAction = isset($behavior['firstActionMs']) ? max(0, (int)$behavior['firstActionMs']) : 0;
        $mobile = isset($behavior['mobile']) ? (bool)$behavior['mobile'] : ($profile->isMobile === true);

        // На touch/mobile отсутствие pointer совершенно нормально.
        if (!$mobile && $pointer === 0 && $touch === 0 && $events >= 3) {
            $add('no_pointer', 10);
        }

        // Desktop-клиент, который уже пробыл на странице, но не дал ни одного
        // пользовательского события — заметно подозрительнее, чем просто отсутствие scroll.
        // Сам по себе сигнал не вызывает CAPTCHA, чтобы не наказывать человека,
        // который просто читает страницу; он усиливает другие признаки.
        if (!$mobile && $events === 0 && $elapsed >= 1500) {
            $add('no_user_activity_after_delay', 25);
        }

        // Длинная desktop-сессия без pointer/click/scroll.
        if (!$mobile && $events >= 15 && $elapsed >= 4000 && $scroll === 0 && $pointer === 0 && $clicks === 0) {
            $add('no_interaction', 10);
        }

        // Очень быстрый автоматизированный сценарий.
        if (!$mobile && $firstAction > 0 && $firstAction < 350 && $events <= 4) {
            $add('too_fast', 20);
        }

        // Клик без предшествующего pointer/touch — сильнее, чем просто отсутствие pointer.
        if ($clicks > 0 && $pointer === 0 && $touch === 0 && $keyboard === 0 && $mobile === false) {
            $add('click_without_pointer', 20);
        }

        // Роботы часто генерируют события с почти одинаковым интервалом.
        // Это только слабый сигнал и никогда не является самостоятельным блоком.
        if (!$mobile && count($moveIntervals) >= 8) {
            $validIntervals = array();
            foreach ($moveIntervals as $interval) {
                if (is_numeric($interval) && $interval >= 1 && $interval <= 5000) {
                    $validIntervals[] = (int)$interval;
                }
            }
            if (count($validIntervals) >= 8) {
                $mean = array_sum($validIntervals) / count($validIntervals);
                $variance = 0.0;
                foreach ($validIntervals as $interval) {
                    $variance += ($interval - $mean) * ($interval - $mean);
                }
                $variance /= count($validIntervals);
                if ($mean >= 8 && sqrt($variance) < 1.5) {
                    $add('regular_event_timing', 10);
                }
            }
        }

        if (!empty($behavior['honeypot'])) {
            $add('honeypot', 60);
        }
        if (!empty($behavior['hiddenLink'])) {
            $add('hidden_link', 70);
        }
        if (!empty($behavior['hiddenForm'])) {
            $add('hidden_form', 80);
        }

        // Данные ограничиваем разумными значениями, чтобы не принять мусор за реальное поведение.
        if ($events > 5000 || $pointer > 5000 || $clicks > 500 || $scroll > 500 || count($moveIntervals) > 100) {
            $add('telemetry_overflow', 15);
        }

        if ($score >= $this->riskBlock) {
            $action = 'BLOCK';
        } elseif ($score >= $this->riskCaptcha) {
            $action = 'CAPTCHA';
        } else {
            $action = 'SKIP';
        }

        $this->Logger->log('BehaviorChecker: score=' . $score . ' action=' . $action .
            ' events=' . $events .
            (empty($signals) ? '' : ' signals=' . implode(',', $signals)));

        return array('score' => $score, 'signals' => $signals, 'action' => $action);
    }
}
