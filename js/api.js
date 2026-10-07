let FINGERPRINT = '';
let FRAME_RATE = 0;
let IS_LOAD = {}; // готовность всех модулей
let CHECK_BOT_IN_FLIGHT = false;
let CHECK_BOT_DONE = false;


function refresh() {
	flagCloseWindow = false;

	const currentUrl = new URL(window.location.href);
	const ref = document.referrer || 'direct';
	let needReload = false;

	if (SAVE_REFERER) {
		localStorage.setItem('originalReferrer', ref);
	}

	// Передаем referrer через utm_referrer
	if (UTM_REFERRER && ref != 'direct') {
		// Удаляем служебные параметры антибота (если есть)
		//currentUrl.searchParams.delete('awaf_checked');

		// Добавляем ref, только если его ещё нет
		if (!currentUrl.searchParams.has('utm_referrer')) {
			currentUrl.searchParams.set('utm_referrer', encodeURIComponent(ref));
			needReload = true;
		}
	}

	if (needReload) {
		window.location.href = currentUrl.toString();
		// Фолбэк: если перезагрузки не произошло (из-за якоря)
		setTimeout(() => {
			if (window.location.href === currentUrl.toString()) {
				window.location.reload();
			}
		}, 100);
	} else
		window.location.reload();
}

function initFingerPrint() {
	if (typeof FingerprintJS !== 'undefined') {
		FingerprintJS.load()
			.then(fp => fp.get())
			.then(result => {
				FINGERPRINT = result.visitorId;
				IS_LOAD["initFingerPrint"] = true;

				if (METRIKA_ID != '') {
					ym(METRIKA_ID, 'params', { fp: FINGERPRINT }); // отправляем доп. параметры
				}
			});

	} else {
		console.error(callback + 'FingerprintJS no load');
	}
}

function callbackFrameRate() {
	if (typeof FrameRateJS !== 'undefined') {
		FrameRateJS.load()
			.then(result => {
				FRAME_RATE = result;
				IS_LOAD["callbackFrameRate"] = true;
			});
	} else {
		console.error(callback + 'FrameRateJS no load');
	}

}

function benchmark() {
	loadScript('js/benchmark.js', function () { startBenchmark(); });
}

function getObjectBrowser(obj, options = {}) {
	const {
		includeNull = false,
		includeEmpty = false
	} = options;

	// Явно исключаем HTMLAllCollection
	if (obj instanceof HTMLAllCollection) return undefined;

	// Проверка на другие нежелательные объекты
	const forbiddenTypes = [
		'[object HTMLAllCollection]',
		'[object HTMLCollection]',
		'[object NodeList]'
	];

	if (forbiddenTypes.includes(Object.prototype.toString.call(obj))) {
		return undefined;
	}

	if (obj === null) return includeNull ? null : undefined;
	if (typeof obj !== 'object') return obj;

	if (obj instanceof Date) return obj.toISOString();
	if (obj instanceof RegExp) return obj.toString();
	if (obj instanceof HTMLElement || obj instanceof Function) return undefined;

	const result = {};
	let hasValidProperties = false;

	// Создаем массив для хранения всех ключей
	const keys = [];

	// Собираем все перечисляемые свойства (включая унаследованные)
	for (const key in obj) {
		keys.push(key);
	}

	// Добавляем символьные свойства
	const symbols = Object.getOwnPropertySymbols(obj);
	for (const sym of symbols) {
		keys.push(sym);
	}

	for (const key of keys) {
		try {
			// Пропускаем специальные свойства
			if (key === '__proto__' || key === 'constructor') continue;

			// Безопасное получение значения свойства
			const value = (typeof key === 'symbol')
				? obj[key]
				: obj[key];

			// Пропускаем функции и DOM-элементы
			if (typeof value === 'function' || value instanceof HTMLElement) continue;

			// Обрабатываем только примитивы
			if (value !== null && typeof value === 'object') continue;

			if (value !== undefined && (includeNull || value !== null)) {
				// Для символов используем строковое представление
				const resultKey = (typeof key === 'symbol')
					? `Symbol(${key.description || ''})`
					: key;

				result[resultKey] = value;
				hasValidProperties = true;
			}
		} catch (e) {
			continue;
		}
	}

	return hasValidProperties ? result : (includeEmpty ? {} : undefined);
}

function ymc(metrika, ip) {
	if (typeof ym === 'function') return;

	try {
		(function (m, e, t, r, i, k, a) {
			m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments) };
			m[i].l = 1 * new Date();
			for (var j = 0; j < document.scripts.length; j++) { if (document.scripts[j].src === r) { return; } }
			k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a)
		})
			(window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

		ym(metrika, "init", {
			clickmap: true,
			trackLinks: true,
			accurateTrackBounce: true,
			webvisor: true,
			params: { ip: ip }
		});
	} catch (e) { }
}

/**
 * Проверяет на включенные куки
 * @returns 
 */
function сheckCookie() {
	document.cookie = "testcookie=1; SameSite=Lax; path=/";
	const cookiesEnabled = document.cookie.includes("testcookie=");
	document.cookie = "testcookie=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/";

	return cookiesEnabled;
}
/**
 * Возвращает true если это старый движок Mozilla или BAS-браузер
 */
function isBas() {
	Object.create(location.reload);
	return Reflect.ownKeys(location.reload).length === 3;
}

// Убирает индикатор проверки
function spinnerNone() {
	lspinner.style.display = "none";
	blockHTTPSecurity.style.display = "none";
}

function spinnerShow() {
	lspinner.style.display = "";
	blockHTTPSecurity.style.display = "none";
}

/**
 * Подключает js скрипт к странице
 * @param pathFile Относительный путь к файлу js
 */
function loadScript(pathFile, callback = null) {
	if (callback != null) {
		const callbackName = callback.name || 'anonymous';
		IS_LOAD[callbackName] = false;
	}

	var script = document.createElement('script');
	script.src = HTTP_ANTIBOT_PATH + pathFile;
	script.async = true;
	if (callback !== null) script.onload = callback;
	script.onerror = function () {
		console.error('Error load: ' + pathFile);
	};
	document.head.appendChild(script);
}

// Действие после успешной проверки
function allow() {
	if (METRIKA_ID != '') {
		try {
			ym(METRIKA_ID, 'reachGoal', 'onclickcapcha');
		} catch (e) { }
	}
	form.style.display = "none";
	lspinner.style.display = "none";
	blockHTTPSecurity.style.display = "none";
	blockSuccessful.style.display = "block";
	setTimeout(refresh, 1000);
}

// Действие после блокировки
function block() {
	flagCloseWindow = false;
	const urlString = window.location.href;
	const url = new URL(urlString);
	const protocol = url.protocol; // "https:"
	const domain = url.hostname; // "example.com"
	window.location.href = protocol + '//' + domain + '/?awafblock';
}




/* Browser / Behavior telemetry */
var BEHAVIOR_TELEMETRY = { events: 0, pointer: 0, touch: 0, clicks: 0, keyboard: 0, scroll: 0, startedAt: Date.now(), firstActionMs: 0, honeypot: false, hiddenLink: false, hiddenForm: false, moveIntervals: [] };
var BROWSER_TELEMETRY = { canvas: null, webgl: null, missingFeatures: [], clientHints: {} };
function initAntiBotTelemetry() {
	var lastPointerAt = 0;
	var action = function(type) {
		BEHAVIOR_TELEMETRY.events++;
		if (!BEHAVIOR_TELEMETRY.firstActionMs) BEHAVIOR_TELEMETRY.firstActionMs = Date.now() - BEHAVIOR_TELEMETRY.startedAt;
		if (type === 'pointer') BEHAVIOR_TELEMETRY.pointer++;
		if (type === 'touch') BEHAVIOR_TELEMETRY.touch++;
		if (type === 'click') BEHAVIOR_TELEMETRY.clicks++;
		if (type === 'keyboard') BEHAVIOR_TELEMETRY.keyboard++;
		if (type === 'scroll') BEHAVIOR_TELEMETRY.scroll++;
	};
	['pointermove','pointerdown','pointerup'].forEach(function(e){ window.addEventListener(e,function(){
			action('pointer');
			if (e === 'pointermove') {
				var now = Date.now();
				if (lastPointerAt > 0) {
					var delta = now - lastPointerAt;
					if (delta >= 1 && delta <= 5000 && BEHAVIOR_TELEMETRY.moveIntervals.length < 100) BEHAVIOR_TELEMETRY.moveIntervals.push(delta);
				}
				lastPointerAt = now;
			}
		},{passive:true}); });
	['touchstart','touchend','touchmove'].forEach(function(e){ window.addEventListener(e,function(){action('touch');},{passive:true}); });
	window.addEventListener('click',function(){action('click');},{passive:true});
	window.addEventListener('scroll',function(){action('scroll');},{passive:true});
	window.addEventListener('keydown',function(){action('keyboard');},{passive:true});
	var hp=document.createElement('input'); hp.type='text'; hp.name='awaf_hp_website'; hp.autocomplete='off'; hp.tabIndex=-1; hp.setAttribute('aria-hidden','true'); hp.style.cssText='position:absolute;left:-10000px;top:-10000px;width:1px;height:1px;opacity:0;pointer-events:none;'; hp.addEventListener('input',function(){BEHAVIOR_TELEMETRY.honeypot=hp.value!=='';}); document.body.appendChild(hp);
	var link=document.createElement('a'); link.href=HTTP_ANTIBOT_PATH+'xhr.php?awaf_hl=1'; link.tabIndex=-1; link.setAttribute('aria-hidden','true'); link.textContent='continue'; link.style.cssText='position:absolute;left:-10000px;top:-10000px;width:1px;height:1px;overflow:hidden;opacity:0;'; link.addEventListener('click',function(){BEHAVIOR_TELEMETRY.hiddenLink=true;}); document.body.appendChild(link);
	var hiddenForm=document.createElement('form'); hiddenForm.id='awaf_hidden_form'; hiddenForm.tabIndex=-1; hiddenForm.setAttribute('aria-hidden','true'); hiddenForm.style.cssText='position:absolute;left:-10000px;top:-10000px;width:1px;height:1px;overflow:hidden;opacity:0;'; hiddenForm.addEventListener('submit',function(e){e.preventDefault();BEHAVIOR_TELEMETRY.hiddenForm=true;}); document.body.appendChild(hiddenForm);
}
function collectBrowserTelemetry() {
	['Promise','Proxy','Map','Set','fetch','URL'].forEach(function(name){ if (!(name in window)) BROWSER_TELEMETRY.missingFeatures.push(name); });
	try { var c=document.createElement('canvas'),ctx=c.getContext('2d'); if(!ctx) BROWSER_TELEMETRY.canvas=false; else {ctx.font='14px Arial';ctx.fillText('awaf',2,2);BROWSER_TELEMETRY.canvas=true;} } catch(e){BROWSER_TELEMETRY.canvas=false;}
	try { var c2=document.createElement('canvas'),gl=c2.getContext('webgl')||c2.getContext('experimental-webgl'); BROWSER_TELEMETRY.webgl=!!gl; } catch(e){BROWSER_TELEMETRY.webgl=false;}
	try { var ua=navigator.userAgentData; BROWSER_TELEMETRY.clientHints={available:!!ua,mobile:ua?!!ua.mobile:null,platform:ua?String(ua.platform||''):'',brands:ua&&ua.brands?ua.brands.map(function(x){return String(x.brand)+'/'+String(x.version);}):[]}; } catch(e){BROWSER_TELEMETRY.clientHints={available:false};}
}
initAntiBotTelemetry();
collectBrowserTelemetry();
function checkBot(func) {
	if (func == 'checks') {
		// Only one checks request is allowed per verification page.
		// CSRF tokens are single-use; duplicate requests can invalidate the token.
		if (CHECK_BOT_IN_FLIGHT || CHECK_BOT_DONE) return;
		CHECK_BOT_IN_FLIGHT = true;
	}

	var xhr = new XMLHttpRequest();
	var visitortime = new Date();

	let obj = {
		func: func,
		csrf_token: CSRF,
	};

	if (func == 'checks') {
		obj2 = { // Данные для отправки
			datetime: {
				now: visitortime.toISOString(),
				timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'Unknown',
				offsetHours: -(visitortime.getTimezoneOffset() / 60),
			},
			clientWidth: document.documentElement.clientWidth,
			clientHeight: document.documentElement.clientHeight,
			screenWidth: window.screen.width,
			screenHeight: window.screen.height,
			pixelRatio: window.devicePixelRatio || 1,
			colorDepth: window.screen.colorDepth,
			pixelDepth: window.screen.pixelDepth,
			java: window.java ? 1 : 0,
			referer: document.referrer,
			document: getObjectBrowser(document),
			window: getObjectBrowser(window),
			navigator: getObjectBrowser(navigator),
			screen: getObjectBrowser(window.screen),
			location: getObjectBrowser(window.location),
			fingerPrint: FINGERPRINT,
			isBas: isBas(),
			isFrame: window.top === window.self,
			frameRate: FRAME_RATE,
			browserTelemetry: BROWSER_TELEMETRY,
			behavior: Object.assign({}, BEHAVIOR_TELEMETRY, {
				mobile: ('ontouchstart' in window) || (navigator.maxTouchPoints > 0)
			}),

		};
		Object.assign(obj, obj2);
	}
	// console.log(obj);

	let data = null;
	try {
		data = JSON.stringify(obj);
	} catch (e) {
		console.error('Failed to stringify data:', e);
	}

	xhr.open('POST', HTTP_ANTIBOT_PATH + 'xhr.php', true);
	xhr.setRequestHeader('Content-Type', 'application/json');

	xhr.onload = async function () {
		if (xhr.status >= 200 && xhr.status < 300) {
			var data = JSON.parse(xhr.responseText);
			if (func == 'checks') {
				CHECK_BOT_IN_FLIGHT = false;
				CHECK_BOT_DONE = true;
			}
			CSRF = data.csrf_token;

			if (data.status == 'captcha') {
				displayCaptcha();
			} else if (data.status == 'allow') {
				allow();
			} else if (data.status == 'block') {
				setTimeout(block, 1000);
			} else if (data.status == 'refresh') {
				setTimeout(refresh, 1000);
			} else if (data.status == 'fail') {
				console.log(data);
			} else if (data.status == 'benchmark') {
				setTimeout(benchmark, 1000);
			}
			else {
				console.log(data);
			}
		} else {
			console.error('Request failed with status:', xhr.status, xhr.statusText);
		}
	};

	xhr.onerror = function () {
		if (func == 'checks') CHECK_BOT_IN_FLIGHT = false;
		console.error('Network error occurred');
	};

	xhr.send(data);
}

/* MAIN */

if (METRIKA_ID != '') {
	ymc(METRIKA_ID, REMOTE_ADDR);
}

if (!сheckCookie()) {
	spinnerNone();
	noscript = document.querySelectorAll('noscript');
	const div = document.createElement('div');
	div.innerHTML = noscript[0].innerHTML;
	noscript[0].replaceWith(div);
} else {
	// Проверяет готовность всех модулей
	const intervalId = setInterval(async () => {
		try {
			if (Object.keys(IS_LOAD).length > 0) {
				let found = true;
				for (const value of Object.values(IS_LOAD)) {
					if (!value) {
						found = false;
						break;
					}
				}
				if (found) {
					clearInterval(intervalId);
					checkBot('checks');
				}
			}
		} catch (error) {
			console.error('Ошибка:', error);
		}
	}, 500);

	loadModules();
}