// Slide gambar produk (halaman produk) dan gambar hero yang bertukar sendiri (laman utama).
(function () {
  var still = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Halaman produk: geser/anak panah/titik/thumbnail. Main sendiri setiap 5 saat
  // sehingga pelawat menyentuh slide.
  [].forEach.call(document.querySelectorAll('[data-gal]'), function (gal) {
    var track = gal.querySelector('.gal-track');
    var n = track.children.length;
    var dots = [].slice.call(gal.querySelectorAll('.gal-dots button'));
    var thumbs = gal.nextElementSibling && gal.nextElementSibling.classList.contains('gal-thumbs')
      ? [].slice.call(gal.nextElementSibling.querySelectorAll('button')) : [];
    var cur = 0, timer = null;

    function go(i) { track.scrollTo({ left: ((i + n) % n) * track.clientWidth }); }
    function mark(i) {
      cur = i;
      dots.forEach(function (d, k) { d.classList.toggle('on', k === i); });
      thumbs.forEach(function (t, k) { t.classList.toggle('on', k === i); });
    }
    function stop() { clearInterval(timer); timer = null; }

    track.addEventListener('scroll', function () {
      var i = Math.round(track.scrollLeft / track.clientWidth);
      if (i !== cur && i >= 0 && i < n) mark(i);
    }, { passive: true });
    gal.querySelector('.gal-prev').addEventListener('click', function () { stop(); go(cur - 1); });
    gal.querySelector('.gal-next').addEventListener('click', function () { stop(); go(cur + 1); });
    dots.concat(thumbs).forEach(function (b) {
      b.addEventListener('click', function () {
        var i = dots.indexOf(b);
        stop(); go(i >= 0 ? i : thumbs.indexOf(b));
      });
    });
    track.addEventListener('keydown', function (ev) {
      if (ev.key === 'ArrowLeft') { stop(); go(cur - 1); ev.preventDefault(); }
      if (ev.key === 'ArrowRight') { stop(); go(cur + 1); ev.preventDefault(); }
    });
    ['pointerdown', 'touchstart', 'wheel'].forEach(function (t) { track.addEventListener(t, stop, { passive: true }); });
    if (!still) timer = setInterval(function () { if (!document.hidden) go(cur + 1); }, 5000);
  });

  // Laman utama: gambar hero bertukar dengan fade setiap 4 saat.
  if (still) return;
  [].forEach.call(document.querySelectorAll('[data-fade]'), function (box) {
    var imgs = box.querySelectorAll('img'), cur = 0;
    setInterval(function () {
      if (document.hidden) return;
      imgs[cur].classList.remove('on');
      cur = (cur + 1) % imgs.length;
      imgs[cur].classList.add('on');
    }, 4000);
  });
})();
