/* My Store Locator – strict two-click consent (shared by the PHP CMS integrations; each platform ships a copy).
   With consent mode on, the plugin renders the map container WITHOUT the widget's mount markers:
     <div class="msl-map-consent" data-msl-gate id="…" data-token="…" data-height="…" data-lang="…" [data-lazy="true"] …>
       <div class="msl-consent" …><p>…privacy text…</p><button type="button" class="msl-consent__load">…</button></div>
     </div>
   Nothing is requested from app.my-store-locator.com until the visitor clicks the button. The click counts as consent
   for this page: every gated map becomes a normal widget container (class "msl-map" + data-mystorelocator, no data-consent)
   and widget.js is injected, which then mounts them. Without the click no external request is made. */
(function () {
  if (window.__mslConsentReady) return;
  window.__mslConsentReady = true;

  var SRC = 'https://app.my-store-locator.com/widget.js';

  function loadWidget() {
    var s = document.createElement('script');
    // The first copy keeps the id that the non-consent loaders check; if the widget already runs on this page
    // (another map without consent mode), a second copy is needed to mount the newly released containers.
    if (!document.getElementById('msl-widget-js')) s.id = 'msl-widget-js';
    s.async = true;
    s.src = SRC;
    document.head.appendChild(s);
  }

  function release() {
    var gates = document.querySelectorAll('[data-msl-gate]');
    if (!gates.length) return;
    for (var i = 0; i < gates.length; i++) {
      var g = gates[i];
      g.removeAttribute('data-msl-gate');
      g.removeAttribute('data-consent');
      g.classList.remove('msl-map-consent');
      g.classList.add('msl-map');
      g.setAttribute('data-mystorelocator', '');
      g.innerHTML = '';
    }
    loadWidget();
  }

  document.addEventListener('click', function (e) {
    var b = e.target && e.target.closest ? e.target.closest('[data-msl-gate] .msl-consent__load') : null;
    if (!b) return;
    e.preventDefault();
    release();
  });
})();
