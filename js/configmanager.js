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


    function ensureConfirmModal() {
      var existing = document.getElementById('civicfg-confirm-modal');
      if (existing) { return existing; }
      var modal = document.createElement('div');
      modal.id = 'civicfg-confirm-modal';
      modal.className = 'civicfg-modal';
      modal.hidden = true;
      modal.setAttribute('aria-hidden', 'true');
      modal.innerHTML = '' +
        '<div class="civicfg-modal-box civicfg-confirm-box" role="dialog" aria-modal="true" aria-labelledby="civicfg-confirm-title">' +
          '<div class="civicfg-modal-header"><strong id="civicfg-confirm-title">Confirm import</strong><button type="button" class="civicfg-close" data-civicfg-confirm-cancel="1" aria-label="Close">×</button></div>' +
          '<div class="civicfg-modal-body">' +
            '<p id="civicfg-confirm-message"></p>' +
            '<div id="civicfg-confirm-warning" class="messages warning no-popup"></div>' +
            '<label class="civicfg-confirm-check"><input type="checkbox" id="civicfg-confirm-reviewed" /> I reviewed the changed files, dependency notes, and understand this action can change active configuration.</label>' +
            '<label class="civicfg-confirm-label" for="civicfg-confirm-text">Type the confirmation word to continue</label>' +
            '<input type="text" id="civicfg-confirm-text" autocomplete="off" />' +
            '<div class="civicfg-actions"><button type="button" class="button" data-civicfg-confirm-apply="1" disabled><span>Continue</span></button><button type="button" class="button" data-civicfg-confirm-cancel="1"><span>Cancel</span></button></div>' +
          '</div>' +
        '</div>';
      var host = document.querySelector('.crm-configmanager-block') || document.body;
      host.appendChild(modal);
      return modal;
    }

    function closeConfirmModal(modal) {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      modal.hidden = true;
      modal._civicfgForm = null;
    }

    document.querySelectorAll('.crm-configmanager-block form[data-civicfg-confirm-modal]').forEach(function(form) {
      form.addEventListener('submit', function(ev) {
        if (form.getAttribute('data-civicfg-confirmed') === '1') {
          form.removeAttribute('data-civicfg-confirmed');
          return;
        }
        ev.preventDefault();
        var modal = ensureConfirmModal();
        var title = form.getAttribute('data-civicfg-confirm-title') || 'Confirm action';
        var message = form.getAttribute('data-civicfg-confirm-message') || 'This action will update configuration.';
        var word = form.getAttribute('data-civicfg-confirm-word') || 'IMPORT';
        var buttonText = form.getAttribute('data-civicfg-confirm-button') || 'Continue';
        var warning = form.getAttribute('data-civicfg-confirm-warning') || 'This action changes the YAML/CiviCRM sync state. Review the details before continuing.';
        modal._civicfgForm = form;
        modal.querySelector('#civicfg-confirm-title').textContent = title;
        modal.querySelector('#civicfg-confirm-message').textContent = message;
        modal.querySelector('#civicfg-confirm-warning').textContent = warning;
        var reviewed = modal.querySelector('#civicfg-confirm-reviewed');
        var text = modal.querySelector('#civicfg-confirm-text');
        var apply = modal.querySelector('[data-civicfg-confirm-apply]');
        reviewed.checked = false;
        text.value = '';
        apply.disabled = true;
        modal.querySelector('.civicfg-confirm-label').textContent = 'Type ' + word + ' to continue';
        apply.querySelector('span').textContent = buttonText;
        function refresh() { apply.disabled = !(reviewed.checked && text.value === word); }
        reviewed.onchange = refresh;
        text.oninput = refresh;
        modal.querySelectorAll('[data-civicfg-confirm-cancel]').forEach(function(btn) { btn.onclick = function() { closeConfirmModal(modal); }; });
        apply.onclick = function() {
          var target = modal._civicfgForm;
          closeConfirmModal(modal);
          if (target) {
            target.setAttribute('data-civicfg-confirmed', '1');
            target.submit();
          }
        };
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-open');
        text.focus();
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
