// JOTECH — lädt Produkte aus der Datenbank (api/products.php) und rendert
// die Produktkarten für die Shop-Seite und den "Aktuell im Shop"-Ausschnitt
// auf der Startseite. Ersetzt die vormals hart codierten Produktkarten.
(function () {
  var ICONS = {
    pc: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="4" width="18" height="12" rx="1"/><path d="M2 20h20M9 20l1-4h4l1 4"/></svg>',
    laptop: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="3" width="18" height="13" rx="1"/><path d="M2 19h20"/></svg>',
    komponente: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="4" y="6" width="16" height="8" rx="1"/><path d="M8 18h8M12 14v4"/></svg>',
    zubehoer: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="6" width="18" height="12" rx="1"/><circle cx="8" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="16" cy="12" r="1.5"/></svg>'
  };

  var CATEGORY_LABELS = {
    pc: 'Desktop-PC',
    laptop: 'Laptop',
    komponente: 'Komponente',
    zubehoer: 'Zubehör'
  };

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function renderCard(p) {
    var media = p.image
      ? '<img src="' + escapeHtml(p.image) + '" alt="' + escapeHtml(p.title) + '" loading="lazy">'
      : (ICONS[p.category] || ICONS.pc);
    var specsHtml = escapeHtml(p.specs || '').replace(/\n/g, '<br>');
    var query = 'produkt=' + encodeURIComponent(p.title);
    return (
      '<div class="product-card" data-cat="' + escapeHtml(p.category) + '">' +
        '<div class="product-media"><span class="stock">' + escapeHtml(p.stock_label) + '</span>' + media + '</div>' +
        '<div class="product-body">' +
          '<span class="product-cat">' + escapeHtml(CATEGORY_LABELS[p.category] || p.category) + '</span>' +
          '<h3>' + escapeHtml(p.title) + '</h3>' +
          '<div class="product-specs">' + specsHtml + '</div>' +
          '<div class="product-footer">' +
            '<span class="price">' + p.price_label + '&nbsp;€<small>' + escapeHtml(p.price_note) + '</small></span>' +
            '<a href="kontakt.html?' + query + '" class="icon-btn">Anfragen</a>' +
          '</div>' +
        '</div>' +
      '</div>'
    );
  }

  document.addEventListener('DOMContentLoaded', function () {
    var shopGrid = document.getElementById('productGrid');
    var featuredGrid = document.getElementById('featuredGrid');

    if (shopGrid) {
      fetch('api/products.php')
        .then(function (r) { return r.json(); })
        .then(function (products) {
          shopGrid.innerHTML = products.map(renderCard).join('');
          var empty = document.getElementById('emptyState');
          if (empty) empty.style.display = products.length === 0 ? 'block' : 'none';
        })
        .catch(function (err) {
          shopGrid.innerHTML = '<p style="grid-column:1/-1; color:var(--white-faint);">Produkte konnten nicht geladen werden.</p>';
          console.error('JOTECH: Shop-Produkte konnten nicht geladen werden', err);
        });
    }

    if (featuredGrid) {
      fetch('api/products.php?featured=1')
        .then(function (r) { return r.json(); })
        .then(function (products) {
          featuredGrid.innerHTML = products.map(renderCard).join('');
        })
        .catch(function (err) {
          console.error('JOTECH: Empfohlene Produkte konnten nicht geladen werden', err);
        });
    }
  });
})();
