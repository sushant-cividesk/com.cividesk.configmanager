/* Configuration Manager vanilla JavaScript. */
/*
 * Configuration Manager UI interactions.
 * Uses vanilla JavaScript only for CiviCRM 5.x/6.x compatibility.
 */
(function() {
  function ready(fn) { if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }
  ready(function() {
    document.querySelectorAll('.crm-configmanager-block [data-civicfg-open]').forEach(function(btn) {
      btn.addEventListener('click', function(ev) {
        ev.preventDefault();
        var modal = document.getElementById(btn.getAttribute('data-civicfg-open'));
        if (modal) { modal.hidden = false; modal.setAttribute('aria-hidden', 'false'); modal.classList.add('is-open'); }
      });
    });
    document.querySelectorAll('.crm-configmanager-block [data-civicfg-close]').forEach(function(btn) {
      btn.addEventListener('click', function(ev) {
        ev.preventDefault();
        var modal = btn.closest('.civicfg-modal');
        if (modal) { modal.classList.remove('is-open'); modal.setAttribute('aria-hidden', 'true'); modal.hidden = true; }
      });
    });
    document.querySelectorAll('.crm-configmanager-block .civicfg-modal').forEach(function(modal) {
      modal.addEventListener('click', function(ev) { if (ev.target === modal) { modal.classList.remove('is-open'); modal.setAttribute('aria-hidden', 'true'); modal.hidden = true; } });
    });
    document.addEventListener('keydown', function(ev) {
      if (ev.key === 'Escape') {
        document.querySelectorAll('.crm-configmanager-block .civicfg-modal.is-open').forEach(function(modal) { modal.classList.remove('is-open'); modal.setAttribute('aria-hidden', 'true'); modal.hidden = true; });
      }
    });


    document.querySelectorAll('.crm-configmanager-block form[data-civicfg-confirm]').forEach(function(form) {
      form.addEventListener('submit', function(ev) {
        var message = form.getAttribute('data-civicfg-confirm') || 'Import will update active CiviCRM configuration from YAML. Continue?';
        if (!window.confirm(message)) {
          ev.preventDefault();
        }
      });
    });

    var exportSelect = document.getElementById('export_item');
    if (exportSelect) {
      var endpoint = exportSelect.getAttribute('data-civicfg-single-url');
      var empty = document.getElementById('civicfg-single-export-empty');
      var preview = document.getElementById('civicfg-single-export-preview');
      var error = document.getElementById('civicfg-single-export-error');
      var path = document.getElementById('civicfg-single-export-path');
      var label = document.getElementById('civicfg-single-export-label');
      var yaml = document.getElementById('civicfg-single-export-yaml');
      var download = document.getElementById('civicfg-single-export-download');

      function show(el, state) { if (el) { el.hidden = !state; } }
      function setText(el, value) { if (el) { el.textContent = value || ''; } }

      function loadSingleExport() {
        var key = exportSelect.value || '';
        show(error, false);
        if (!key) {
          show(preview, false);
          show(empty, true);
          return;
        }
        if (!endpoint) { return; }
        show(empty, false);
        show(preview, true);
        if (preview) { preview.classList.add('civicfg-loading'); }
        setText(path, 'Loading...');
        setText(label, '');
        if (yaml) { yaml.value = ''; }
        if (download) { download.removeAttribute('href'); download.setAttribute('aria-disabled', 'true'); }

        fetch(endpoint + '&export_item=' + encodeURIComponent(key), {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
          .then(function(response) { return response.json(); })
          .then(function(data) {
            if (!data || !data.ok) { throw new Error((data && data.error) ? data.error : 'Could not load YAML preview.'); }
            setText(path, data.path || '');
            setText(label, data.label || '');
            if (yaml) { yaml.value = data.yaml || ''; }
            if (download && data.download_url) { download.setAttribute('href', data.download_url); download.removeAttribute('aria-disabled'); }
          })
          .catch(function(err) {
            show(preview, false);
            show(error, true);
            setText(error, err.message || 'Could not load YAML preview.');
          })
          .finally(function() {
            if (preview) { preview.classList.remove('civicfg-loading'); }
          });
      }

      exportSelect.addEventListener('change', loadSingleExport);
      if (exportSelect.value) { loadSingleExport(); }
    }
  });
})();
