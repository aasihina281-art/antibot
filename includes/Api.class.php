<?php

namespace WAFSystem;

use Exception;

// ... Реализация работы с капчей
class Api
{
    private static $_instances = null;
    private $WAFSystem;
    private $CSRF;
    private $data; // хранит массив данных из php://input
    private $maxData = 10000; // ограничение на размер входящего объекта
    private $maxKeys = 64;
    private $rateWindow = 60;
    private $rateLimit = 30;

    private function __construct(WAFSystem $wafsystem)
    {
        $this->WAFSystem = $wafsystem;
        $this->CSRF = CSRF::getInstance($this->WAFSystem);
        $client_ip = $this->WAFSystem->Profile->IP;

        if (!$this->checkRateLimit($client_ip)) {
            $message = "Error: xhr rate limit exceeded";
            $this->WAFSystem->Logger->log($message);
            $this->WAFSystem->GrayList->add($client_ip, $message);
            $this->endJSON('fail');
        }

        // Блокировка плохих запросов
        if (!$this->isPost()) {
            $this->WAFSystem->Logger->log("Not a POST request");
            $this->endJSON('block');
        }

        $input = file_get_contents('php://input');
        if (($len = strlen($input)) > $this->maxData) {
            $message = "Error: Input data size exceeded (size: $len, max: $this->maxData)";
            $this->WAFSystem->Logger->log($message);
            $this->WAFSystem->GrayList->add($client_ip, $message);
            $this->endJSON('fail');
        }

        $this->data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($this->data)) {
            $message = "Error: Invalid JSON payload";
            $this->WAFSystem->Logger->log($message);
            $this->WAFSystem->GrayList->add($client_ip, $message);
            $this->endJSON('fail');
        }

        if (count($this->data) > $this->maxKeys) {
            $message = "Error: Too many JSON fields";
            $this->WAFSystem->Logger->log($message);
            $this->WAFSystem->GrayList->add($client_ip, $message);
            $this->endJSON('fail');
        }

        if (empty($this->data)) {
            $message = "Error: JSON-data is empty";
            $this->WAFSystem->Logger->log($message, [get_class($this)]);
            $this->WAFSystem->GrayList->add($client_ip, $message);
            $this->endJSON('fail');
        }

        if (!isset($this->data['func'])) {
            $message = "Error: Param 'func' not found";
            $this->WAFSystem->Logger->log($message, [get_class($this)]);
            $this->WAFSystem->GrayList->add($client_ip, $message);
            $this->endJSON('fail');
        }

        if (!isset($this->data['csrf_token'])) {
            $this->WAFSystem->Logger->log("Error: Param 'csrf_token' not found");
            $this->endJSON('fail');
        }

        if ($this->data['func'] == "load_module") {
            $this->endJSON("no_csrf", [ // доступ без csrf
                "modules" => ["metrika"]
            ]);
        }

        if ($this->data['func'] == "get_param") {
            $this->endJSON("no_csrf", [ // доступ без csrf
                "metrika" => \WAFSystem\Metrika::getInstance($this->WAFSystem)->get("ID"),
                "ip" => $this->WAFSystem->Profile->IP,
                "fp" => $this->WAFSystem->FingerPrint->enabled
            ]);
        }

        try {
            $this->CSRF->validCSRF($this->data['csrf_token']);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $this->WAFSystem->Logger->log($message, [get_class($this), $this->data]);
            $this->WAFSystem->GrayList->add($client_ip, $message);
            $this->endJSON('fail', ['message' => $message]);
        }
    }

    public static function getInstance(WAFSystem $wafsystem)
    {
        if (is_null(self::$_instances))
            self::$_instances = new self($wafsystem);

        return self::$_instances;
    }

    public function endJSON($status, $data = [])
    {
        header('Content-type: application/json; charset=utf-8');

        $res = ['status' => $status];
        if (!session_id()) {
            $res = "Critical error: Session session_start() not started.";
            $this->WAFSystem->Logger->log($res, [get_class($this)]);
            echo json_encode($res);
            exit;
        }

        if ($status == 'captcha') {
            $this->WAFSystem->Logger->log("Show captcha");
            $this->setHiddenValue();
            $this->WAFSystem->CaptchaChallenge->issue();
        }

        if ($status == 'allow') {
            $this->removeHiddenValue();
        }

        if ($status == 'block') {
            // Если расскомментировать, то редирект не сработает
           // header("HTTP/1.0 403 Forbidden");
        }

        if ($status != 'fail' && $status != 'no_csrf') { // не выдавать ключь для ошибки или данных без ключа
            $csrf_token = $this->CSRF->createCSRF();
        }

        if (isset($this->data['func'])) {
            $res = array_merge([
                'func' => $this->data['func'],
                'csrf_token' => isset($csrf_token) ? $csrf_token : ""
            ], $res);
        }

        if (sizeof($data) > 0)
            $res = array_merge($res, $data);

        echo json_encode($res);
        exit;
    }

    /**
     * Устанавливает ключ, при котором разблокируется запрос на установку метки
     */
    private function setHiddenValue()
    {
        $_SESSION['rndname'] = true;
    }

    private function removeHiddenValue()
    {
        unset($_SESSION['rndname']);
    }

    /**
     * Проверяет наличие ключа разблокировки
     */
    public function isHiddenValue($clientData = [])
    {
        if (!isset($_SESSION['rndname'])) {
            return false;
        }

        $result = $this->WAFSystem->CaptchaChallenge->consumeSuccess($clientData);
        if (!$result['ok']) {
            $this->WAFSystem->Logger->log('CAPTCHA challenge rejected: ' . $result['reason']);
            $this->WAFSystem->GrayList->add($this->WAFSystem->Profile->IP, $result['reason']);
            return false;
        }

        return true;
    }

    /**
     * Проверяем метод отправки запроса
     */
    public function isPOST()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return true;
        }
        return false;
    }

    /**
     * Получает JSON данные из запроса
     */
    public function getData()
    {
        return $this->data;
    }

    private function checkRateLimit($clientIp)
    {
        $dir = rtrim($this->WAFSystem->Config->CachePath, '/\\') . '/api_rate/';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return true;
        }

        $file = $dir . sha1($clientIp) . '.json';
        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return true;
        }

        $allowed = true;
        if (@flock($fp, LOCK_EX)) {
            $raw = stream_get_contents($fp);
            $state = json_decode($raw, true);
            $now = time();

            if (!is_array($state) || !isset($state['started'], $state['count'])
                || ($now - (int)$state['started']) >= $this->rateWindow) {
                $state = ['started' => $now, 'count' => 0];
            }

            $state['count']++;
            if ($state['count'] > $this->rateLimit) {
                $allowed = false;
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($state));
            fflush($fp);
            flock($fp, LOCK_UN);
        }

        fclose($fp);
        return $allowed;
    }

    /**
     * Блокирует айпи, если его нет в белых списках и других правилах исключения
     */
    public function BlockIP($client_ip, $message)
    {
        if (
            !($this->WAFSystem->WhiteListIP->isListed($client_ip)
                || ($this->WAFSystem->IndexBot->enabled && $this->WAFSystem->IndexBot->Checking($client_ip))
            )
        ) {
            $this->WAFSystem->BlackListIP->add($this->WAFSystem->Profile->IP, $message);
        }
    }
}
