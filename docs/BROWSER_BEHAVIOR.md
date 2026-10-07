# BrowserChecker и BehaviorChecker

Новые фильтры работают только на этапе клиентской проверки (xhr.php, func=checks) и не требуют Nginx-модулей, Lua, БД или внешнего proxy.

## По умолчанию

```ini
[browser_checker]
enabled = Off
action = CAPTCHA
risk_captcha = 35
risk_block = 80

[behavior_checker]
enabled = Off
action = CAPTCHA
risk_captcha = 35
risk_block = 80
```

config.ini создаётся и дополняется автоматически через Config::init().

## BrowserChecker

Проверяет navigator.webdriver, базовые JS API, Canvas, WebGL, User-Agent Client Hints, согласованность Client Hints с User-Agent, наличие Sec-Fetch-* и согласованность X-Client-Protocol с версией HTTP.

Отсутствие Sec-Fetch-* не является самостоятельным блокирующим признаком.

## BehaviorChecker

Собирает агрегированные pointer/touch/click/scroll/keyboard события, время до первого действия и длительность проверки. Дополнительно используются скрытое поле, скрытая ссылка и скрытая форма.

Отсутствие pointer на мобильном/touch устройстве не считается подозрительным. Отсутствие scroll само по себе также не повышает риск.

## Начальные веса

- webdriver: +35
- missing_features: +15
- Canvas unavailable: +10
- WebGL unavailable: +10
- Client Hints mismatch: +20
- Sec-Fetch missing: +10
- protocol mismatch: +15
- desktop без pointer при наличии событий: +10
- длительная desktop-сессия без взаимодействия: +10
- слишком быстрый сценарий: +20
- click без pointer/touch: +20
- honeypot: +60
- hidden link: +70
- hidden form: +80
- telemetry overflow: +15

Порог по умолчанию: 0–34 SKIP, 35–79 CAPTCHA, 80+ BLOCK.

action ограничивает максимальное действие модуля. При action = CAPTCHA риск 80 не превращается в BLOCK.

## Калибровка

Перед включением BLOCK рекомендуется сначала использовать action = CAPTCHA для обоих модулей и собрать реальные логи с desktop, Android и iOS. Особое внимание уделяйте sec_fetch_missing, потому что внешний proxy хостинга может не передавать эти заголовки PHP.

## Архитектура

Браузер отправляет telemetry вместе с существующим POST /xhr.php и func=checks. Новый endpoint не используется. Существующий CSRF и лимит размера JSON остаются в силе.

## Ограничение

BrowserChecker/BehaviorChecker не являются криптографическим доказательством того, что клиент — настоящий браузер. Все клиентские данные потенциально могут быть подделаны. Сильные решения должны основываться на совокупности независимых сигналов.


## Server-side CAPTCHA challenge

When CAPTCHA is shown, the server creates a one-time challenge in the PHP session. The challenge has a TTL, a minimum completion time, and an attempt limit. CAPTCHA skins return the challenge nonce and a bounded interaction counter; the server validates both before issuing the access marker.

The access marker is HMAC-signed and includes an expiry and the current RayID. A forged or expired `aw_marker` cookie is therefore not accepted.

POST requests to `xhr.php` are additionally rate-limited using files under the existing cache directory, so this works on shared hosting without external infrastructure.
