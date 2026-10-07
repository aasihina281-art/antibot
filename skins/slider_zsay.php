<?php
$nonce = \Utility\GenerateRandomName::genKey(17);
$funcName = \Utility\GenerateRandomName::genFuncName();
$funcNameSucc = \Utility\GenerateRandomName::genFuncName();
// $tagCheckbox = Utility\GenerateRandomName::genFuncName(4, 6);
?><html lang="<?php echo $antiBot->Profile->LangAttr ?>" dir="ltr">

<head>
  <meta http-equiv="x-ua-compatible" content="IE=Edge,chrome=1">
  <meta http-equiv="content-security-policy"
    content="default-src 'none'; script-src 'nonce-<?php echo $nonce; ?>' 'unsafe-eval'; script-src-attr 'none'; worker-src blob:; style-src 'unsafe-inline'; img-src 'self'; connect-src 'self'; frame-src 'self' blob:; child-src 'self' blob:; form-action 'none'; base-uri 'self'">
  <title>Checking your Browser…</title>
  <meta name="robots" content="noindex,nofollow">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif;
      min-height: 100vh;
      display: flex;
      justify-content: left;
      transition: background 0.3s;
    }

    /* контейнер */
    .slider-captcha {
      width: 92%;
      max-width: 560px;
    }

    /* полоса */
    .bar {
      position: relative;
      height: 68px;
      background: #2b2b2b;
      border-radius: 34px;
      margin: 6px 0;
      padding: 0 14px;
      overflow: hidden;
      transition: background 0.3s;
    }

    @media (prefers-color-scheme: light) {
      .bar {
        background: #e0e0e0;
      }
    }

    /* фон полосы с градиентом */
    .bar-bg {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      border-radius: 34px;
      transition: background 0.3s;
    }

    /* круг */
    .dot {
      position: absolute;
      top: 50%;
      left: 14px;
      width: 44px;
      height: 44px;
      background: #3c3c3d;
      border-radius: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      touch-action: none;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-weight: bold;
      user-select: none;
      z-index: 2;
      transition: background 0.3s, color 0.3s;
    }

    .dot.ok {
      background: #28ff5a;
      color: #000;
    }

    /* проценты */
    .percent {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      min-width: 72px;
      height: 40px;
      padding: 0 12px;
      background: #1e1e1e;
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: #fff;
      border: 2px solid transparent;
      transition: 0.2s;
      pointer-events: none;
      z-index: 2;
    }

    @media (prefers-color-scheme: light) {
      .dot {
        background: #9b9a9a;
      }

      .percent {
        background: #fff;
        color: #333;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      }
    }

    .percent.hint {
      border-color: #4CAF50;
      color: #4CAF50;
    }

    /* индикатор цели */
    .target-indicator {
      position: absolute;
      top: 50%;
      left: -10px;
      transform: translateY(-50%);
      width: 4px;
      height: 50px;
      background: #246526;
      z-index: 1;
      border-radius: 2px;
      transition: background 0.3s;
    }

    @media (prefers-color-scheme: light) {
      .target-indicator {
        background: #4caf50;
      }
    }
  </style>
</head>

<body>

  <div class="slider-captcha">
    <div class="bar">
      <div class="bar-bg"></div>
      <div class="target-indicator"></div>
      <div class="dot">●</div>
      <div class="percent">0%</div>
    </div>
  </div>

  <script type="text/javascript" nonce="<?php echo $nonce ?>">
    const bar = document.querySelector('.bar');
    const barBg = document.querySelector('.bar-bg');
    const dot = document.querySelector('.dot');
    const percentBox = document.querySelector('.percent');
    const targetIndicator = document.querySelector('.target-indicator');

    let dragging = false;
    let barRect;
    let holdStart = null;

    /* рандомная зона */
    const unlockFrom = Math.floor(40 + Math.random() * 35);
    const unlockTo = unlockFrom + 6;
    const targetCenter = (unlockFrom + unlockTo) / 2;

    // Проверяем тему устройства
    const prefersLightScheme = window.matchMedia('(prefers-color-scheme: light)');

    // Функция для вычисления цвета на основе расстояния до цели
    function getColorForDistance(distance) {
      // distance - абсолютное значение разницы в процентах от цели
      // 0% - на цели, 50% - максимально далеко

      // Преобразуем расстояние в значение от 0 до 1
      let normalized = Math.min(distance / 50, 1);

      // Холодные цвета (синий) при удалении, теплые (красный/оранжевый) при приближении
      // и зеленый при нахождении в зоне

      if (distance < 3) {
        // В зоне - зеленый (одинаковый для обеих тем)
        return '#4CAF50';
      } else if (distance < 10) {
        // Очень близко - оранжево-желтый
        return '#ffd700';
      } else if (distance < 20) {
        // Близко - оранжевый
        return '#ff8c00';
      } else if (distance < 30) {
        // Средне - красный
        return '#ff4500';
      } else {
        // Далеко - синий
        if (prefersLightScheme.matches) {
          // Для светлой темы - более светлый синий
          const blueIntensity = Math.max(100, 255 - Math.floor(normalized * 120));
          return `rgb(70, 130, ${blueIntensity})`;
        } else {
          // Для темной темы - темный синий
          const blueIntensity = Math.max(50, 255 - Math.floor(normalized * 150));
          return `rgb(0, 100, ${blueIntensity})`;
        }
      }
    }

    function updateBarColor(percent) {
      const distance = Math.abs(percent - targetCenter);
      const color = getColorForDistance(distance);

      // Определяем цвет фона для незаполненной части
      const bgColor = prefersLightScheme.matches ? '#e0e0e0' : '#2b2b2b';

      // Обновляем цвет фона полоски
      barBg.style.background = `linear-gradient(90deg, 
    ${color} 0%, 
    ${color} ${percent}%, 
    ${bgColor} ${percent}%, 
    ${bgColor} 100%)`;

      // Обновляем цвет точки
      if (distance < 3) {
        dot.style.background = '#4CAF50';
        dot.style.color = '#000';
      } else {
        dot.style.background = color;
        dot.style.color = '#fff';
      }
    }

    function move(clientX) {
      const min = 14;
      const max = barRect.width - 58 - percentBox.offsetWidth;

      let x = clientX - barRect.left;
      x = Math.max(min, Math.min(max, x));
      dot.style.left = x + 'px';

      let percent = Math.round((x - min) / (max - min) * 100);
      percentBox.textContent = percent + '%';

      // Обновляем цвет полоски
      updateBarColor(percent);

      /* проверка зоны */
      if (percent >= unlockFrom && percent <= unlockTo) {
        percentBox.classList.add('hint');

        if (!holdStart) holdStart = Date.now();
        if (Date.now() - holdStart > 500) <?php echo $funcNameSucc ?>();
      } else {
        percentBox.classList.remove('hint');
        holdStart = null;
      }
    }

    // Инициализация - позиционируем индикатор цели
    function initTargetIndicator() {
      setTimeout(() => {
        const barWidth = bar.offsetWidth - 28; // учитываем padding
        const targetPosition = (targetCenter / 100) * barWidth + 14;
        targetIndicator.style.left = targetPosition + 'px';
        // Устанавливаем начальный цвет
        updateBarColor(0);
      }, 300);
    }

    /* мышь */
    dot.addEventListener('mousedown', e => {
      dragging = true;
      barRect = bar.getBoundingClientRect();
      e.preventDefault();
    });

    document.addEventListener('mousemove', e => {
      if (dragging) move(e.clientX);
    });

    document.addEventListener('mouseup', () => {
      dragging = false;
      holdStart = null;
    });

    /* тач */
    dot.addEventListener('touchstart', () => {
      dragging = true;
      barRect = bar.getBoundingClientRect();
    });

    document.addEventListener('touchmove', e => {
      if (dragging) move(e.touches[0].clientX);
    });

    document.addEventListener('touchend', () => {
      dragging = false;
      holdStart = null;
    });

    /* успех */
    function <?php echo $funcNameSucc ?>() {
      dragging = false;
      dot.classList.add('ok');
      dot.textContent = 'OK';
      percentBox.classList.remove('hint');

      // Фиксируем зеленый цвет при успехе
      barBg.style.background = '#28ff5a';
      dot.style.background = '#28ff5a';

      <?php echo $funcName ?>('<?php echo $antiBot->Marker->getNameMarker() ?>');
    }

    // Инициализируем после загрузки страницы
    window.addEventListener('load', initTargetIndicator);

    // Слушаем изменение темы устройства
    prefersLightScheme.addEventListener('change', () => {
      // Перерисовываем текущее состояние
      if (barRect) {
        // Если слайдер уже был инициализирован, обновляем цвета
        const percent = parseInt(percentBox.textContent);
        updateBarColor(percent);
      } else {
        // Иначе просто инициализируем
        initTargetIndicator();
      }
    });

    let CSRF = "<?php echo $_REQUEST["csrf"] ?>";
        const CAPTCHA_CHALLENGE_NONCE = "<?php echo htmlspecialchars($antiBot->CaptchaChallenge->getToken(), ENT_QUOTES, 'UTF-8'); ?>";
        const CAPTCHA_STARTED_AT = (window.performance && performance.now) ? performance.now() : Date.now();
        let CAPTCHA_EVENTS = 0;
        ['pointerdown', 'touchstart', 'mousedown', 'keydown', 'click'].forEach(function(type) {
            document.addEventListener(type, function() { CAPTCHA_EVENTS++; }, {passive: true});
        });
    var HTTP_ANTIBOT_PATH = '<?php echo $antiBot->Config->ANTIBOT_PATH; ?>';

    function <?php echo $funcName ?>(func) {
      var xhr = new XMLHttpRequest();
      var visitortime = new Date();

      let obj = {
        func: func == undefined ? 'csrf_token' : func,
        csrf_token: CSRF,
        mainFrame: window.top === window.self,
                challenge_nonce: CAPTCHA_CHALLENGE_NONCE,
                challenge_elapsed: Math.max(0, Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - CAPTCHA_STARTED_AT)),
                events: CAPTCHA_EVENTS,
      };

      let data = null;
      try {
        data = JSON.stringify(obj);
      } catch (e) {
        console.error('Failed to stringify data:', e);
      }

      xhr.open('POST', HTTP_ANTIBOT_PATH + 'xhr.php', true);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.onload = async function() {
        if (xhr.status >= 200 && xhr.status < 300) {
          var data = JSON.parse(xhr.responseText);
          CSRF = data.csrf_token;

          // Перезагружает капчу
          if (data.status == 'captcha') {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('csrf', CSRF);
            window.location.href = currentUrl.toString();
          }
          // Успешно прошел капчу
          else if (data.status == 'allow') {
            parent.allow();
          }
          // НЕ прошел капчу, пользователь заблокирован
          else if (data.status == 'block') {
            setTimeout(parent.block, 1000);
          }
          // Технические ошибки, когда требуется вмешательство тех. специалиста
          else if (data.status == 'fail') {
            console.log(data);
          } 
          // Обновить страницу
          else if (data.status == 'refresh') {
            parent.refresh();
          } else {
            console.log(data);
          }
        }
      };
      xhr.onerror = function() {
        console.error('Network error occurred');
      };
      xhr.send(data);
    }
  </script>

</body>

</html>