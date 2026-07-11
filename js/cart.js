// JOTECH — Warenkorb (localStorage) + Checkout-Submit für shop.html.
// Der hier angezeigte Gesamtpreis ist nur eine Schätzung fürs UI; die verbindliche
// Preisbildung passiert serverseitig in api/checkout.php anhand der DB-Preise.
(function () {
  var CART_KEY = 'jotech_cart';

  function readCart() {
    try {
      var raw = localStorage.getItem(CART_KEY);
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function writeCart(items) {
    localStorage.setItem(CART_KEY, JSON.stringify(items));
  }

  function addItem(id, title, priceRaw) {
    var items = readCart();
    var existing = items.find(function (i) { return i.id === id; });
    if (existing) {
      existing.qty += 1;
    } else {
      items.push({ id: id, title: title, priceRaw: priceRaw, qty: 1 });
    }
    writeCart(items);
    render();
  }

  function setQty(id, qty) {
    var items = readCart();
    items = items.map(function (i) {
      if (i.id === id) i.qty = qty;
      return i;
    }).filter(function (i) { return i.qty > 0; });
    writeCart(items);
    render();
  }

  function removeItem(id) {
    writeCart(readCart().filter(function (i) { return i.id !== id; }));
    render();
  }

  function clearCart() {
    writeCart([]);
    render();
  }

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function formatEuro(value) {
    return value.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function render() {
    var items = readCart();
    var countBadge = document.getElementById('cartCount');
    var listEl = document.getElementById('cartItems');
    var emptyEl = document.getElementById('cartEmpty');
    var totalRow = document.getElementById('cartTotalRow');
    var totalEl = document.getElementById('cartTotal');
    var checkoutBtn = document.getElementById('cartCheckoutBtn');
    if (!listEl) return;

    var totalQty = items.reduce(function (sum, i) { return sum + i.qty; }, 0);
    if (countBadge) {
      countBadge.textContent = String(totalQty);
      countBadge.hidden = totalQty === 0;
    }

    if (!items.length) {
      listEl.innerHTML = '';
      if (emptyEl) emptyEl.hidden = false;
      if (totalRow) totalRow.hidden = true;
      if (checkoutBtn) checkoutBtn.hidden = true;
      return;
    }

    if (emptyEl) emptyEl.hidden = true;
    if (totalRow) totalRow.hidden = false;
    if (checkoutBtn) checkoutBtn.hidden = false;

    var total = 0;
    listEl.innerHTML = items.map(function (item) {
      var lineTotal = item.priceRaw * item.qty;
      total += lineTotal;
      return (
        '<div class="cart-item" data-id="' + item.id + '">' +
          '<div class="cart-item-info">' +
            '<strong>' + escapeHtml(item.title) + '</strong>' +
            '<span>' + formatEuro(item.priceRaw) + '&nbsp;€ / Stück</span>' +
          '</div>' +
          '<div class="cart-item-controls">' +
            '<button type="button" class="cart-qty-btn" data-qty-decrease>−</button>' +
            '<span class="cart-qty-value">' + item.qty + '</span>' +
            '<button type="button" class="cart-qty-btn" data-qty-increase>+</button>' +
            '<button type="button" class="cart-remove-btn" data-remove-item aria-label="Entfernen">✕</button>' +
          '</div>' +
        '</div>'
      );
    }).join('');
    totalEl.textContent = formatEuro(total) + ' €';
  }

  document.addEventListener('DOMContentLoaded', function () {
    var overlay = document.getElementById('cartOverlay');
    var drawer = document.getElementById('cartDrawer');
    var toggleBtn = document.getElementById('cartToggle');
    var closeBtn = document.getElementById('cartClose');
    var itemsStep = document.getElementById('cartStep-items');
    var checkoutForm = document.getElementById('checkoutForm');
    var checkoutBtn = document.getElementById('cartCheckoutBtn');
    var backBtn = document.getElementById('cartBackBtn');
    var errorEl = document.getElementById('checkoutError');
    var submitBtn = document.getElementById('checkoutSubmitBtn');

    if (!drawer) return; // Seite ohne Warenkorb-Markup (nicht shop.html)

    render();

    function openDrawer() {
      drawer.classList.add('is-open');
      drawer.setAttribute('aria-hidden', 'false');
      if (overlay) overlay.hidden = false;
    }

    function closeDrawer() {
      drawer.classList.remove('is-open');
      drawer.setAttribute('aria-hidden', 'true');
      if (overlay) overlay.hidden = true;
    }

    function showItemsStep() {
      itemsStep.hidden = false;
      checkoutForm.hidden = true;
    }

    function showCheckoutStep() {
      itemsStep.hidden = true;
      checkoutForm.hidden = false;
      errorEl.hidden = true;
    }

    if (toggleBtn) toggleBtn.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (overlay) overlay.addEventListener('click', closeDrawer);
    if (checkoutBtn) checkoutBtn.addEventListener('click', showCheckoutStep);
    if (backBtn) backBtn.addEventListener('click', showItemsStep);

    // Event-Delegation: "In den Warenkorb"-Buttons werden erst nach dem
    // asynchronen Produkt-Fetch (js/products.js) ins DOM eingefügt.
    document.addEventListener('click', function (e) {
      var addBtn = e.target.closest('[data-add-to-cart]');
      if (addBtn) {
        var id = parseInt(addBtn.getAttribute('data-product-id'), 10);
        var title = addBtn.getAttribute('data-product-title');
        var price = parseFloat(addBtn.getAttribute('data-product-price'));
        if (id && !isNaN(price)) {
          addItem(id, title, price);
          openDrawer();
        }
        return;
      }

      var decreaseBtn = e.target.closest('[data-qty-decrease]');
      var increaseBtn = e.target.closest('[data-qty-increase]');
      var removeBtn = e.target.closest('[data-remove-item]');
      if (decreaseBtn || increaseBtn || removeBtn) {
        var row = e.target.closest('.cart-item');
        var id2 = parseInt(row.getAttribute('data-id'), 10);
        if (removeBtn) {
          removeItem(id2);
        } else {
          var items = readCart();
          var current = items.find(function (i) { return i.id === id2; });
          if (current) {
            var nextQty = current.qty + (increaseBtn ? 1 : -1);
            setQty(id2, nextQty);
          }
        }
      }
    });

    if (checkoutForm) {
      checkoutForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var items = readCart();
        if (!items.length) return;

        var formData = new FormData(checkoutForm);
        var payload = {
          website: formData.get('website') || '',
          form_loaded_at: formData.get('form_loaded_at') || 0,
          customer: {
            first_name: formData.get('first_name') || '',
            last_name: formData.get('last_name') || '',
            email: formData.get('email') || '',
            phone: formData.get('phone') || '',
            street: formData.get('street') || '',
            zip: formData.get('zip') || '',
            city: formData.get('city') || '',
            country_code: formData.get('country_code') || 'DE',
            consent: formData.get('consent') ? true : false,
          },
          items: items.map(function (i) { return { product_id: i.id, quantity: i.qty }; }),
        };

        submitBtn.disabled = true;
        submitBtn.textContent = 'Wird gesendet …';
        errorEl.hidden = true;

        fetch('api/checkout.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        })
          .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
          .then(function (result) {
            if (result.ok && result.body && result.body.status === 'success') {
              clearCart();
              window.location.href = 'danke.html?status=success';
              return;
            }
            throw new Error((result.body && result.body.message) || 'Bestellung fehlgeschlagen.');
          })
          .catch(function (err) {
            errorEl.textContent = err.message || 'Bestellung fehlgeschlagen. Bitte versuche es erneut.';
            errorEl.hidden = false;
            submitBtn.disabled = false;
            submitBtn.textContent = 'Kostenpflichtig bestellen →';
          });
      });
    }
  });
})();
