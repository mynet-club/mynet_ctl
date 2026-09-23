/* MyNet 产品形态分析 — 运行模式切换 */

(function () {
  'use strict';

  var buttons = Array.prototype.slice.call(document.querySelectorAll('.mbtn'));
  var panels = {
    node: document.getElementById('panel-node'),
    console: document.getElementById('panel-console')
  };

  if (!buttons.length || !panels.node || !panels.console) return;

  function show(mode) {
    buttons.forEach(function (btn) {
      var on = btn.dataset.mode === mode;
      btn.classList.toggle('active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });

    Object.keys(panels).forEach(function (key) {
      var panel = panels[key];
      var on = key === mode;
      panel.hidden = !on;
      if (on) {
        // 重放淡入动画
        panel.style.animation = 'none';
        void panel.offsetWidth;
        panel.style.animation = '';
      }
    });
  }

  buttons.forEach(function (btn) {
    btn.setAttribute('aria-pressed', btn.classList.contains('active') ? 'true' : 'false');
    btn.addEventListener('click', function () {
      show(btn.dataset.mode);
    });
  });

  // 键盘左右键在两个模式间切换
  document.querySelector('.mode-switch').addEventListener('keydown', function (e) {
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
    var i = buttons.findIndex(function (b) { return b.classList.contains('active'); });
    if (i < 0) return;
    var next = e.key === 'ArrowRight'
      ? (i + 1) % buttons.length
      : (i - 1 + buttons.length) % buttons.length;
    buttons[next].focus();
    show(buttons[next].dataset.mode);
    e.preventDefault();
  });
})();