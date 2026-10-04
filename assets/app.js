(function () {
  'use strict';

  // ---------- Mobile sidebar toggle ----------
  var menuBtn  = document.getElementById('mobileMenuBtn');
  var sidebar  = document.getElementById('sidebar');
  var backdrop = document.getElementById('sidebarBackdrop');

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (backdrop) backdrop.classList.remove('open');
  }

  if (menuBtn && sidebar && backdrop) {
    menuBtn.addEventListener('click', function () {
      sidebar.classList.add('open');
      backdrop.classList.add('open');
    });
    backdrop.addEventListener('click', closeSidebar);
  }

  // ---------- Drag-and-drop upload zone ----------
  var dropzone = document.getElementById('dropzone');
  var fileInput = document.getElementById('attendance');

  if (dropzone && fileInput) {
    var fileLabel = document.getElementById('dropzoneFile');

    function showFileName() {
      if (fileInput.files && fileInput.files.length) {
        dropzone.classList.add('has-file');
        if (fileLabel) fileLabel.textContent = fileInput.files[0].name;
      }
    }

    dropzone.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', showFileName);

    ['dragenter', 'dragover'].forEach(function (evt) {
      dropzone.addEventListener(evt, function (e) {
        e.preventDefault();
        dropzone.classList.add('drag-over');
      });
    });
    ['dragleave', 'drop'].forEach(function (evt) {
      dropzone.addEventListener(evt, function (e) {
        e.preventDefault();
        dropzone.classList.remove('drag-over');
      });
    });
    dropzone.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        showFileName();
      }
    });
  }

  // ---------- Loading overlay on slow form submits ----------
  var overlay = document.getElementById('loadingOverlay');
  var overlayText = document.getElementById('loadingText');

  document.querySelectorAll('form[data-loading]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (form.hasAttribute('data-confirm-checked') === false) {
        // let any inline onsubmit confirm() run first; if it returned false, submit is already cancelled
      }
      if (e.defaultPrevented) {
        return;
      }
      var msg = form.getAttribute('data-loading') || 'Working on it…';
      if (overlayText) overlayText.textContent = msg;
      if (overlay) overlay.classList.add('visible');

      var btn = form.querySelector('button[type=submit]');
      if (btn) btn.disabled = true;
    });
  });
})();
