<?php
$nonce = \Utility\GenerateRandomName::genKey(17);
$funcName = \Utility\GenerateRandomName::genFuncName();
$funcNameSucc = \Utility\GenerateRandomName::genFuncName();

$langMap = [
  "ru" => [
      "title" => "Проверяем ваш Браузер...",
      "exercise" => "Найдите такой же рисунок:",
  ],
  "en" => [
      "title" => "Checking your Browser...",
      "exercise" => "Find the same picture:",
  ],
  "zh" => [
      "title" => "正在检查您的浏览器...",
      "exercise" => "找到相同的图片",
  ],
];

// Путь к папке с картинками
$scriptDir = dirname($_SERVER['SCRIPT_FILENAME']);
$skinPathRelative = '/skins/animals/';
$skinPathAbsolute = $scriptDir . $skinPathRelative;

$availableImages = [
  'bear.png',
  'cat.png',
  'cow.png',
  'crab.png',
  'dog.png',
  'elephant.png',
  'fish.png',
  'fox.png',
  'frog.png',
  'lion.png',
  'panda.png',
  'rabbit.png'
];

$emojisMap = [
  'bear.png' => '🐻',
  'cat.png' => '🐱',
  'cow.png' => '🐮',
  'crab.png' => '🦀',
  'dog.png' => '🐶',
  'elephant.png' => '🐘',
  'fish.png' => '🐟',
  'fox.png' => '🦊',
  'frog.png' => '🐸',
  'lion.png' => '🦁',
  'panda.png' => '🐼',
  'rabbit.png' => '🐰'
];

// Проверяем существующие картинки
$existingImages = [];
foreach ($availableImages as $img) {
  if (file_exists($skinPathAbsolute . $img)) {
    $existingImages[] = $img;
  }
}

if (empty($existingImages)) {
  $useImages = false;
  $emojis = array_values($emojisMap);
  $targetEmoji = $emojis[array_rand($emojis)];
  $otherEmojis = array_diff($emojis, [$targetEmoji]);
  shuffle($otherEmojis);
  $options = array_merge([$targetEmoji], array_slice($otherEmojis, 0, 5));
  shuffle($options);
  $targetForJs = $targetEmoji;
} else {
  $useImages = true;
  // Выбираем целевую картинку
  $targetImage = $existingImages[array_rand($existingImages)];
  $targetEmoji = $emojisMap[$targetImage];

  // Получаем эмодзи для вариантов
  $otherEmojis = [];
  foreach ($existingImages as $img) {
    if ($img !== $targetImage) {
      $otherEmojis[] = $emojisMap[$img];
    }
  }
  shuffle($otherEmojis);
  $options = array_merge([$targetEmoji], array_slice($otherEmojis, 0, 5));
  shuffle($options);

  // Сохраняем правильный эмодзи для проверки
  $targetForJs = $targetEmoji;
}

$imageBaseUrl = rtrim($antiBot->Config->ANTIBOT_PATH, '/') . $skinPathRelative;
?><html lang="<?php echo $antiBot->Profile->LangAttr ?>" dir="ltr">

<head>
  <meta http-equiv="x-ua-compatible" content="IE=Edge,chrome=1">
  <meta http-equiv="content-security-policy"
    content="default-src 'none'; script-src 'nonce-<?php echo $nonce; ?>' 'unsafe-eval'; script-src-attr 'none'; worker-src blob:; style-src 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-src 'self' blob:; child-src 'self' blob:; form-action 'none'; base-uri 'self'">
  <title><?php echo $langMap[$antiBot->Profile->Language]['title'] ?></title>
  <meta name="robots" content="noindex,nofollow">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <style>
    * {
      box-sizing: border-box;
      user-select: none;
    }

    body {
      margin: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif;
      min-height: 100vh;
      justify-content: center;
      align-items: center;
    }

    .captcha-container {
      background: #e0e0e0;
      border-radius: 24px;
      padding: 20px;
      width: 100%;
      animation: slideIn 0.5s ease;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-30px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (prefers-color-scheme: dark) {
      .captcha-container {
        background: #1a1a1a;
        color: #fff;
      }
    }

    .title {
      font-size: 20px;
      font-weight: 600;
      margin-bottom: 8px;
      text-align: center;
    }

    .subtitle {
      font-size: 14px;
      color: #666;
      text-align: center;
      margin-bottom: 24px;
    }

    @media (prefers-color-scheme: dark) {
      .subtitle {
        color: #aaa;
      }
    }

    .question {
      background: #f5f5f5;
      border-radius: 16px;
      padding: 24px;
      text-align: center;
      margin-bottom: 24px;
    }

    @media (prefers-color-scheme: dark) {
      .question {
        background: #2a2a2a;
      }
    }

    .question-text {
      font-size: 16px;
      font-weight: 500;
      margin-bottom: 16px;
      color: #333;
    }

    @media (prefers-color-scheme: dark) {
      .question-text {
        color: #ddd;
      }
    }

    .target-emoji {
      font-size: 64px;
      display: inline-block;
      animation: bounce 1s infinite;
    }

    .target-image {
      display: inline-block;
      position: relative;
    }

    .target-image img {
      width: 80px;
      height: 80px;
      object-fit: contain;
    }

    /* Canvas шум поверх картинки */
    .noise-canvas {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      opacity: 0.15;
    }

    @keyframes bounce {

      0%,
      100% {
        transform: translateY(0);
      }

      50% {
        transform: translateY(-10px);
      }
    }

    .options-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
    }

    .option {
      background: #f0f0f0;
      border: 2px solid transparent;
      border-radius: 16px;
      padding: 20px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      min-height: 100px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .emoji-canvas {
      width: 60px;
      height: 60px;
      display: block;
      margin: 0 auto;
    }

    @media (prefers-color-scheme: dark) {
      .option {
        background: #2a2a2a;
      }
    }

    .option:hover {
      transform: scale(1.05);
      background: #e8e8e8;
    }

    .option:active {
      transform: scale(0.98);
    }

    .option.correct {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      animation: correctPulse 0.5s ease;
    }

    @keyframes correctPulse {

      0%,
      100% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.1);
      }
    }

    .option.wrong {
      background: #ff4757;
      animation: shake 0.3s ease;
    }

    @keyframes shake {

      0%,
      100% {
        transform: translateX(0);
      }

      25% {
        transform: translateX(-5px);
      }

      75% {
        transform: translateX(5px);
      }
    }

    .message {
      text-align: center;
      padding: 12px;
      border-radius: 12px;
      margin-top: 16px;
      font-size: 14px;
      display: none;
    }

    .message.error {
      background: #ffe0e0;
      color: #d63031;
      display: block;
    }

    .message.success {
      background: #e0ffe0;
      color: #00b894;
      display: block;
    }

    @media (prefers-color-scheme: dark) {
      .message.error {
        background: #2d1a1a;
      }

      .message.success {
        background: #1a2d1a;
      }
    }

    .loading {
      display: inline-block;
      width: 20px;
      height: 20px;
      border: 3px solid #f3f3f3;
      border-top: 3px solid #667eea;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin-left: 10px;
      vertical-align: middle;
    }

    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    .disabled {
      pointer-events: none;
      opacity: 0.6;
    }
  </style>
</head>

<body>
  <div class="captcha-container">
    <div class="question">
      <div class="question-text"><?php echo $langMap[$antiBot->Profile->Language]['exercise'] ?></div>
      <?php if (!$useImages): ?>
        <div class="target-emoji"><?php echo $targetEmoji ?></div>
      <?php else: ?>
        <div class="target-image" id="targetImageContainer">
          <img src="<?php echo $imageBaseUrl . $targetImage ?>" alt="target image" id="targetImg">
          <canvas class="noise-canvas" id="noiseCanvas"></canvas>
        </div>
      <?php endif; ?>
    </div>

    <div class="options-grid" id="optionsGrid"></div>

    <div class="message" id="message"></div>
  </div>

  <script type="text/javascript" nonce="<?php echo $nonce ?>">
    (function() {
      const optionsData = <?php echo json_encode($options) ?>;
      const useImages = <?php echo $useImages ? 'true' : 'false' ?>;
      const targetEmoji = '<?php echo $targetForJs ?>';
      const messageDiv = document.getElementById('message');
      const container = document.querySelector('.captcha-container');

      // Функция для отрисовки эмодзи на Canvas с шумом
      function drawEmojiOnCanvas(emoji, size = 60) {
        const canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        canvas.className = 'emoji-canvas';
        const ctx = canvas.getContext('2d');

        // Прозрачный фон
        ctx.clearRect(0, 0, size, size);

        // Рисуем эмодзи
        ctx.font = `${size * 0.7}px "Segoe UI Emoji", "Apple Color Emoji", "Noto Color Emoji", sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#000000';
        ctx.fillText(emoji, size / 2, size / 2);

        // Добавляем легкий шум
        const imageData = ctx.getImageData(0, 0, size, size);
        const data = imageData.data;
        for (let i = 0; i < data.length; i += 4) {
          if (Math.random() < 0.01) {
            data[i] = data[i] ^ Math.floor(Math.random() * 30);
            data[i + 1] = data[i + 1] ^ Math.floor(Math.random() * 30);
            data[i + 2] = data[i + 2] ^ Math.floor(Math.random() * 30);
          }
        }
        ctx.putImageData(imageData, 0, 0);

        return canvas;
      }

      // Создаем кнопки с Canvas
      const grid = document.getElementById('optionsGrid');
      const optionElements = [];

      optionsData.forEach((emoji, index) => {
        const optionDiv = document.createElement('div');
        optionDiv.className = 'option';
        optionDiv.setAttribute('data-emoji', emoji);

        // Рисуем эмодзи на Canvas
        const canvas = drawEmojiOnCanvas(emoji, 60);
        optionDiv.appendChild(canvas);

        // Добавляем обработчик клика
        optionDiv.addEventListener('click', function() {
          handleOptionClick(emoji, this);
        });

        grid.appendChild(optionDiv);
        optionElements.push(optionDiv);
      });

      // Функция для добавления шума на целевую картинку
      if (useImages) {
        function addNoiseToImage() {
          const img = document.getElementById('targetImg');
          const noiseCanvas = document.getElementById('noiseCanvas');
          if (!img || !noiseCanvas) return;

          // Устанавливаем размер canvas под размер картинки
          noiseCanvas.width = img.offsetWidth || 80;
          noiseCanvas.height = img.offsetHeight || 80;

          const ctx = noiseCanvas.getContext('2d');
          const imageData = ctx.getImageData(0, 0, noiseCanvas.width, noiseCanvas.height);
          const data = imageData.data;

          for (let i = 0; i < data.length; i += 4) {
            if (Math.random() < 0.1) {
              data[i] = Math.random() * 255;
              data[i + 1] = Math.random() * 255;
              data[i + 2] = Math.random() * 255;
              data[i + 3] = Math.random() * 100;
            }
          }
          ctx.putImageData(imageData, 0, 0);

          requestAnimationFrame(addNoiseToImage);
        }

        // Запускаем шум после загрузки картинки
        const targetImg = document.getElementById('targetImg');
        targetImg.onload = () => {
          addNoiseToImage();
        };

        // Также добавляем легкое движение картинке
        let angle = 0;
        setInterval(() => {
          if (!container.classList.contains('disabled')) {
            angle += 0.02;
            const offsetX = Math.sin(angle) * 1;
            const offsetY = Math.cos(angle * 1.3) * 1;
            targetImg.style.transform = `translate(${offsetX}px, ${offsetY}px)`;
          }
        }, 100);
      }

      async function handleOptionClick(selectedEmoji, element) {
        // Блокируем повторные клики
        if (container.classList.contains('disabled')) return;

        if (selectedEmoji === targetEmoji) {
          // Правильный ответ
          element.classList.add('correct');
          container.classList.add('disabled');

          // Отправляем успех
          await <?php echo $funcName ?>('<?php echo $antiBot->Marker->getNameMarker() ?>');
        } else {
          // Неправильный ответ
          element.classList.add('wrong');

          setTimeout(() => {
            element.classList.remove('wrong');
          }, 300);
        }
      }

      let CSRF = "<?php echo $_REQUEST["csrf"] ?>";
        const CAPTCHA_CHALLENGE_NONCE = "<?php echo htmlspecialchars($antiBot->CaptchaChallenge->getToken(), ENT_QUOTES, 'UTF-8'); ?>";
        const CAPTCHA_STARTED_AT = (window.performance && performance.now) ? performance.now() : Date.now();
        let CAPTCHA_EVENTS = 0;
        ['pointerdown', 'touchstart', 'mousedown', 'keydown', 'click'].forEach(function(type) {
            document.addEventListener(type, function() { CAPTCHA_EVENTS++; }, {passive: true});
        });
      var HTTP_ANTIBOT_PATH = '<?php echo $antiBot->Config->ANTIBOT_PATH; ?>';

      function <?php echo $funcName ?>(func) {
        return new Promise((resolve, reject) => {
          var xhr = new XMLHttpRequest();

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

              if (data.status == 'captcha') {
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('csrf', CSRF);
                window.location.href = currentUrl.toString();
              } else if (data.status == 'allow') {
                parent.allow();
                resolve();
              } else if (data.status == 'block') {
                setTimeout(parent.block, 1000);
                reject();
              } else if (data.status == 'fail') {
                console.log(data);
                reject();
              } else if (data.status == 'refresh') {
                parent.refresh();
                reject();
              } else {
                console.log(data);
                reject();
              }
            }
          };
          xhr.onerror = function() {
            console.error('Network error occurred');
            reject();
          };
          xhr.send(data);
        });
      }
    })();
  </script>
</body>

</html>