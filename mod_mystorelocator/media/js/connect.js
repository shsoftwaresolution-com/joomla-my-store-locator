/**
 * My Store Locator – map token field (module edit form).
 * Shows whether the input contains a valid map token and runs the optional connect flow:
 * popup to app.my-store-locator.com/connect → static return page of this module → postMessage / BroadcastChannel
 * (same origin only) → input filled.
 * No data is sent anywhere by this script except opening the connect page the user clicked.
 *
 * @copyright  (C) 2026 Internet am Dornbusch, Sajjad Hanifa
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
(function () {
  'use strict';

  var PATTERN = /^[A-Za-z0-9_-]{6,64}$/;
  var STATE_KEY = 'msl_connect_state';

  function extract(value) {
    var v = String(value || '').trim(), m;
    if (!v) return '';
    if ((m = v.match(/data-token\s*=\s*["']?([A-Za-z0-9_-]+)/i))) v = m[1];
    else if ((m = v.match(/#\/m\/([A-Za-z0-9_-]+)/))) v = m[1];
    else if ((m = v.match(/\/m\/([A-Za-z0-9_-]+)/))) v = m[1];
    return PATTERN.test(v) ? v : '';
  }

  function randomState() {
    var a = new Uint8Array(16);
    (window.crypto || window.msCrypto).getRandomValues(a);
    return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
  }

  function init(box) {
    if (box.dataset.mslInit) return;
    box.dataset.mslInit = '1';

    var input = document.getElementById(box.dataset.inputId);
    var status = box.querySelector('.msl-token-status');
    var button = box.querySelector('.msl-connect');
    if (!input) return;

    function show() {
      var raw = input.value.trim(), token = extract(raw);
      status.className = 'msl-token-status form-text';
      if (!raw) { status.textContent = ''; return; }
      if (token) {
        status.textContent = box.dataset.textOk.replace('%s', token);
        status.classList.add('text-success');
      } else {
        status.textContent = box.dataset.textBad;
        status.classList.add('text-danger');
      }
    }

    input.addEventListener('input', show);
    input.addEventListener('change', function () {
      var token = extract(input.value);
      if (token && token !== input.value.trim()) input.value = token;
      show();
    });
    show();

    function accept(data) {
      if (!data || data.type !== 'msl:connect') return false;
      var expected = '';
      try { expected = sessionStorage.getItem(STATE_KEY) || ''; } catch (err) { expected = ''; }
      if (!expected || data.state !== expected || !PATTERN.test(String(data.token || ''))) return false;
      try { sessionStorage.removeItem(STATE_KEY); } catch (err) { /* ignore */ }
      input.value = data.token;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      status.className = 'msl-token-status form-text text-success';
      status.textContent = box.dataset.textConnected.replace('%s', data.map ? String(data.map) : data.token);
      return true;
    }

    // popup with window.opener intact
    window.addEventListener('message', function (e) {
      if (e.origin === window.location.origin) accept(e.data);
    });

    // Joomla sends "Cross-Origin-Opener-Policy: same-origin", so after the popup visited the app window.opener is gone:
    // the return page then uses a same-origin BroadcastChannel and waits for our ack.
    if ('BroadcastChannel' in window) {
      var bc = new BroadcastChannel('msl-connect');
      bc.onmessage = function (e) {
        var state = e.data && e.data.state;
        if (accept(e.data)) bc.postMessage({ type: 'msl:connect:ack', state: state });
      };
    }

    if (!button || button.disabled) return;

    button.addEventListener('click', function () {
      var state = randomState();
      try { sessionStorage.setItem(STATE_KEY, state); } catch (e) { /* storage blocked: state check fails safely */ }
      var url = box.dataset.connectUrl + '&state=' + encodeURIComponent(state);
      var w = 560, h = 760;
      var left = Math.max(0, (window.screenX || 0) + (window.outerWidth - w) / 2);
      var top = Math.max(0, (window.screenY || 0) + (window.outerHeight - h) / 2);
      var popup = window.open(url, 'msl-connect', 'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top);
      if (!popup) {
        status.className = 'msl-token-status form-text text-danger';
        status.textContent = box.dataset.textBlocked;
      }
    });
  }

  function run() {
    var boxes = document.querySelectorAll('[data-msl-token-field]');
    for (var i = 0; i < boxes.length; i++) init(boxes[i]);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();
