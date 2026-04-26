(function() {
  var timer = null;
  var minChars = 2;
  var debounceMs = 300;

  document.querySelectorAll('#header-search-input, #mobile-search input[name="search"]').forEach(function(input) {
    var resultsEl = input.closest('.header-search')
      ? input.closest('.header-search').querySelector('.header-search__results')
      : null;

    if (!resultsEl) return;

    input.addEventListener('input', function() {
      clearTimeout(timer);
      var val = this.value.trim();
      if (val.length < minChars) {
        resultsEl.classList.remove('active');
        resultsEl.innerHTML = '';
        return;
      }
      timer = setTimeout(function() {
        fetch('index.php?route=product/autocomplete&language=' + document.documentElement.lang + '&term=' + encodeURIComponent(val))
          .then(function(r) { return r.json(); })
          .then(function(data) { renderResults(data, resultsEl); })
          .catch(function() { resultsEl.classList.remove('active'); });
      }, debounceMs);
    });

    input.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        resultsEl.classList.remove('active');
      }
    });

    document.addEventListener('click', function(e) {
      if (!input.contains(e.target) && !resultsEl.contains(e.target)) {
        resultsEl.classList.remove('active');
      }
    });
  });

  function renderResults(data, el) {
    var html = '';
    if (data.categories && data.categories.length) {
      html += '<div class="search-ac__section">';
      html += '<div class="search-ac__label">Категорії</div>';
      data.categories.forEach(function(cat) {
        html += '<a href="' + cat.href + '" class="search-ac__cat"><i class="fa-solid fa-folder me-2"></i>' + escHtml(cat.name) + '</a>';
      });
      html += '</div>';
    }
    if (data.products && data.products.length) {
      html += '<div class="search-ac__section">';
      html += '<div class="search-ac__label">Товари</div>';
      data.products.forEach(function(p) {
        html += '<a href="' + p.href + '" class="search-ac__product">';
        html += '<img src="' + p.thumb + '" alt="" class="search-ac__img" loading="lazy"/>';
        html += '<span class="search-ac__name">' + escHtml(p.name) + '</span>';
        if (p.price) html += '<span class="search-ac__price">' + escHtml(p.price) + '</span>';
        html += '</a>';
      });
      html += '</div>';
    }
    if (!html) {
      html = '<div class="search-ac__empty">Нічого не знайдено</div>';
    }
    el.innerHTML = html;
    el.classList.add('active');
  }

  function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }
})();
