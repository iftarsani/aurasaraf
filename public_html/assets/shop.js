// Troli ringkas: kiraan di sini untuk paparan sahaja. Harga sebenar dikira semula di server.
(function () {
  // Sorok butang terapung bila bahagian pesanan sudah kelihatan
  var cta = document.querySelector('.sticky-cta'), sec = document.getElementById('pesan');
  if (cta && sec && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (en) { cta.style.display = en[0].isIntersecting ? 'none' : ''; }, { threshold: 0.05 }).observe(sec);
  }

  var form = document.getElementById('orderForm');
  if (!form) return;
  var cfg = window.SHOP || { shipSM: 8, shipSS: 12, freeMin: 0 };
  var items = [].slice.call(form.querySelectorAll('.pitem'));
  var state = document.getElementById('state');
  var rm = function (n) { return 'RM' + n.toFixed(2); };

  function recalc() {
    var sub = 0, html = '';
    items.forEach(function (el) {
      var input = el.querySelector('input[type=number]');
      var max = parseInt(el.dataset.stock, 10) || 0;
      var q = Math.max(0, Math.min(max, parseInt(input.value, 10) || 0));
      if (String(q) !== input.value) input.value = q;
      el.classList.toggle('has-qty', q > 0);
      if (q > 0) {
        var line = q * parseFloat(el.dataset.price);
        sub += line;
        html += '<p><span>' + q + ' × ' + el.dataset.name.replace(/</g, '&lt;') + '</span><b>' + rm(line) + '</b></p>';
      }
    });
    var opt = state.options[state.selectedIndex];
    var region = opt ? opt.getAttribute('data-region') : null;
    var ship = null;
    if (region) ship = (cfg.freeMin > 0 && sub >= cfg.freeMin) ? 0 : (region === 'SS' ? cfg.shipSS : cfg.shipSM);
    document.getElementById('sumItems').innerHTML = html || '<p class="muted">Belum ada produk dipilih.</p>';
    document.getElementById('sumSub').textContent = rm(sub);
    lastSub = sub;
    // Kadar kurier utama: angka dari server (shipping-quote.php), dikira semula semasa hantar.
    if (cfg.liveRates && region && ship !== 0 && sub > 0) { showShip(null, 'Mengira…'); fetchQuote(); }
    else showShip(ship, ship === null ? 'Pilih negeri' : '');
    document.getElementById('submitBtn').disabled = sub <= 0;
  }

  var lastSub = 0, quoteTimer = null, quoteSeq = 0;
  function showShip(ship, text) {
    document.getElementById('sumShip').textContent = text || (ship === 0 ? 'PERCUMA' : rm(ship));
    var total = lastSub + (ship || 0);
    document.getElementById('sumTotal').textContent = rm(total);
    [].forEach.call(document.querySelectorAll('.js-total'), function (n) { n.textContent = rm(total); });
  }
  function fetchQuote() {
    clearTimeout(quoteTimer);
    quoteTimer = setTimeout(function () {
      var seq = ++quoteSeq, q = new URLSearchParams();
      q.set('state', state.value);
      q.set('postcode', (form.elements.postcode || {}).value || '');
      q.set('city', (form.elements.city || {}).value || '');
      items.forEach(function (el) {
        var input = el.querySelector('input[type=number]'), n = parseInt(input.value, 10) || 0;
        if (n > 0) q.set(input.name, n);
      });
      fetch(cfg.quoteUrl + '?' + q.toString(), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (seq !== quoteSeq) return;
          if (d.fee === null || d.fee === undefined) return showShip(null, 'Pilih negeri');
          showShip(d.fee, d.needPostcode ? rm(d.fee) + ' (anggaran — isi poskod)' : '');
        })
        .catch(function () { if (seq === quoteSeq) showShip(null, 'Dikira semasa hantar'); });
    }, 350);
  }

  items.forEach(function (el) {
    var input = el.querySelector('input[type=number]');
    el.querySelector('.qminus').addEventListener('click', function () { input.value = (parseInt(input.value, 10) || 0) - 1; recalc(); });
    el.querySelector('.qplus').addEventListener('click', function () { input.value = (parseInt(input.value, 10) || 0) + 1; recalc(); });
    input.addEventListener('input', recalc);
  });
  state.addEventListener('change', recalc);
  if (cfg.liveRates) ['postcode', 'city'].forEach(function (n) { if (form.elements[n]) form.elements[n].addEventListener('change', recalc); });

  var box = document.getElementById('transferBox');
  function togglePay() {
    var m = form.querySelector('input[name=payment_method]:checked');
    if (box) box.hidden = !(m && m.value === 'transfer');
  }
  [].forEach.call(form.querySelectorAll('input[name=payment_method]'), function (r) { r.addEventListener('change', togglePay); });

  form.addEventListener('submit', function (ev) {
    var btn = document.getElementById('submitBtn');
    if (!form.checkValidity()) { ev.preventDefault(); form.reportValidity(); return; }
    btn.disabled = true; btn.textContent = 'Menghantar…';
  });

  // Jika hanya satu produk dan belum dipilih, mula dengan 1 unit
  if (items.length === 1) {
    var only = items[0].querySelector('input[type=number]');
    if (!only.disabled && (parseInt(only.value, 10) || 0) === 0) only.value = 1;
  }
  togglePay(); recalc();
})();
