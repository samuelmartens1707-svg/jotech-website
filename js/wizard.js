// JOTECH — generic multi-step wizard engine (used by Ankauf & Reparatur forms)
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.wizard-form');
    if (!form) return;

    var steps = Array.prototype.slice.call(form.querySelectorAll('.step'));
    var stepsNav = form.querySelector('#wizardSteps');
    var prevBtn = form.querySelector('[data-action="prev"]');
    var nextBtn = form.querySelector('[data-action="next"]');
    var submitBtn = form.querySelector('[data-action="submit"]');
    var current = 0;

    function buildNav() {
      if (!stepsNav) return;
      stepsNav.innerHTML = '';
      steps.forEach(function (step, i) {
        var label = step.getAttribute('data-label') || ('Schritt ' + (i + 1));
        var el = document.createElement('span');
        el.className = 'ws';
        el.innerHTML = '<span class="b">' + (i + 1) + '</span><span>' + label + '</span>';
        stepsNav.appendChild(el);
      });
    }

    function updateNavState() {
      if (!stepsNav) return;
      var items = stepsNav.querySelectorAll('.ws');
      items.forEach(function (el, i) {
        el.classList.toggle('active', i === current);
        el.classList.toggle('done', i < current);
      });
    }

    function showStep(index) {
      steps.forEach(function (step, i) {
        step.classList.toggle('active', i === index);
      });
      updateNavState();
      prevBtn.disabled = index === 0;
      var isLast = index === steps.length - 1;
      nextBtn.hidden = isLast;
      submitBtn.hidden = !isLast;
      if (isLast) buildSummary();
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function clearErrors(step) {
      step.querySelectorAll('.option-grid').forEach(function (g) { g.classList.remove('error-shake'); });
    }

    function validateStep(step) {
      clearErrors(step);
      var valid = true;

      // radio / checkbox groups grouped inside .option-grid
      var groups = step.querySelectorAll('.option-grid[data-required="true"]');
      groups.forEach(function (grid) {
        var checked = grid.querySelectorAll('input:checked');
        if (checked.length === 0) {
          valid = false;
          grid.classList.add('error-shake');
          setTimeout(function () { grid.classList.remove('error-shake'); }, 500);
        }
      });

      // native fields
      var fields = step.querySelectorAll('input[required], textarea[required], select[required]');
      for (var i = 0; i < fields.length; i++) {
        var f = fields[i];
        if (f.type === 'radio' || f.type === 'checkbox') continue; // handled via option-grid
        if (!f.checkValidity()) {
          f.reportValidity();
          valid = false;
          break;
        }
      }

      return valid;
    }

    function buildSummary() {
      var box = form.querySelector('.summary-box');
      if (!box) return;
      var rows = [];
      steps.forEach(function (step) {
        if (step.hasAttribute('data-summary-skip')) return;
        var groups = step.querySelectorAll('.option-grid');
        groups.forEach(function (grid) {
          var checked = Array.prototype.slice.call(grid.querySelectorAll('input:checked'));
          if (!checked.length) return;
          var label = grid.getAttribute('data-summary-label') || 'Auswahl';
          var vals = checked.map(function (c) {
            return c.closest('.option-card').querySelector('.t').textContent.trim();
          }).join(', ');
          rows.push({ k: label, v: vals });
        });
        var namedFields = step.querySelectorAll('[data-summary]');
        namedFields.forEach(function (f) {
          if (!f.value) return;
          rows.push({ k: f.getAttribute('data-summary'), v: f.value });
        });
      });

      box.innerHTML = '<h4>Zusammenfassung deiner Angaben</h4>' + rows.map(function (r) {
        return '<div class="summary-row"><span class="k">' + r.k + '</span><span class="v">' + escapeHtml(r.v) + '</span></div>';
      }).join('');
    }

    function escapeHtml(str) {
      var div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    }

    nextBtn.addEventListener('click', function () {
      if (!validateStep(steps[current])) return;
      if (current < steps.length - 1) {
        current++;
        showStep(current);
      }
    });

    prevBtn.addEventListener('click', function () {
      if (current > 0) {
        current--;
        showStep(current);
      }
    });

    buildNav();
    showStep(current);
  });
})();
