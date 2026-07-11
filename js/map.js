// JOTECH — lädt Leaflet + OpenStreetMap-Kacheln erst nach Klick auf "Karte laden".
// Bis dahin wird keine Verbindung zu externen Kartendiensten aufgebaut (kein Tracking
// ohne Nutzerinteraktion, daher kein Cookie-Consent-Banner nötig).
(function () {
  var LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
  var LEAFLET_CSS_INTEGRITY = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
  var LEAFLET_JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
  var LEAFLET_JS_INTEGRITY = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';

  function loadCss(href, integrity) {
    if (document.querySelector('link[href="' + href + '"]')) return;
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    link.integrity = integrity;
    link.crossOrigin = '';
    document.head.appendChild(link);
  }

  function loadScript(src, integrity) {
    return new Promise(function (resolve, reject) {
      if (window.L) {
        resolve();
        return;
      }
      var script = document.createElement('script');
      script.src = src;
      script.integrity = integrity;
      script.crossOrigin = '';
      script.onload = resolve;
      script.onerror = reject;
      document.body.appendChild(script);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('mapLoadBtn');
    var preview = document.getElementById('mapPreview');
    var mapContainer = document.getElementById('mapContainer');
    if (!btn || !preview || !mapContainer) return;

    btn.addEventListener('click', function () {
      btn.disabled = true;
      btn.textContent = 'Karte wird geladen …';

      loadCss(LEAFLET_CSS, LEAFLET_CSS_INTEGRITY);
      loadScript(LEAFLET_JS, LEAFLET_JS_INTEGRITY)
        .then(function () {
          preview.hidden = true;
          mapContainer.hidden = false;
          void mapContainer.offsetWidth; // Reflow erzwingen, damit Leaflet sofort die finale Containergröße sieht

          var lat = parseFloat(mapContainer.getAttribute('data-lat'));
          var lon = parseFloat(mapContainer.getAttribute('data-lon'));
          var label = mapContainer.getAttribute('data-label') || '';

          var map = L.map(mapContainer, { scrollWheelZoom: false }).setView([lat, lon], 15);
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>-Mitwirkende',
          }).addTo(map);
          // maxWidth eng begrenzt, damit das Popup auch in schmalen mobilen
          // Kartenausschnitten umbricht statt über den Rand hinauszuragen
          // (Leaflet-Container hat overflow:hidden, das Popup würde sonst abgeschnitten).
          var marker = L.marker([lat, lon]).addTo(map).bindPopup(label, { maxWidth: 200 });

          // Popup erst nach invalidateSize() öffnen, sonst berechnet Leaflets
          // Auto-Pan die Position anhand einer noch veralteten Containergröße
          // und das Popup wird am Rand abgeschnitten.
          setTimeout(function () {
            map.invalidateSize();
            marker.openPopup();
          }, 100);
        })
        .catch(function () {
          btn.disabled = false;
          btn.textContent = 'Karte konnte nicht geladen werden — erneut versuchen';
        });
    });
  });
})();
