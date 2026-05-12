document.addEventListener('DOMContentLoaded', function () {
  var DURATION = 4500;

  function getContainer() {
    var c = document.getElementById('flash-toast-container');
    if (!c) {
      c = document.createElement('div');
      c.id = 'flash-toast-container';
      c.className = 'flash-toast-container';
      document.body.appendChild(c);
    }
    return c;
  }

  function dismissToast(toast) {
    if (toast.dataset.dismissed) return;
    toast.dataset.dismissed = '1';
    toast.classList.add('flash-toast-hiding');
    setTimeout(function () { toast.remove(); }, 300);
  }

  function showToast(html, type) {
    var container = getContainer();
    var isSuccess = type === 'success';
    var icon = isSuccess ? '✓' : '✕';
    var typeClass = isSuccess ? 'flash-toast-success' : 'flash-toast-error';

    var toast = document.createElement('div');
    toast.className = 'flash-toast ' + typeClass;
    toast.innerHTML =
      '<span class="flash-toast-icon">' + icon + '</span>' +
      '<div class="flash-toast-body">' + html + '</div>' +
      '<button class="flash-toast-close" aria-label="Dismiss">&times;</button>' +
      '<div class="flash-toast-bar"></div>';

    var bar = toast.querySelector('.flash-toast-bar');
    bar.style.animation = 'flashBarShrink ' + (DURATION / 1000) + 's linear forwards';

    toast.querySelector('.flash-toast-close').addEventListener('click', function () {
      dismissToast(toast);
    });

    container.appendChild(toast);
    setTimeout(function () { dismissToast(toast); }, DURATION);
  }

  document.querySelectorAll('.alert').forEach(function (el) {
    var type = el.classList.contains('alert-success') ? 'success' : 'error';
    showToast(el.innerHTML.trim(), type);
  });
});
