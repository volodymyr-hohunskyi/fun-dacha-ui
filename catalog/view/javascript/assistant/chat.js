/**
 * Fun Dacha garden assistant — chat UI, IndexedDB transcript, jQuery AJAX.
 */
(function ($) {
  'use strict';

  var cfg = window.aiAssistantConfig || {};
  var IDB_NAME = 'FunDachaAssistant';
  var IDB_STORE = 'transcript';
  var IDB_KEY = 'current';
  var idbDb = null;
  var useLs = typeof window.indexedDB === 'undefined';

  var state = { isOpen: false };

  var $panel = null;
  var $toggle = null;
  var $close = null;
  var $messages = null;
  var $scroll = null;
  var $input = null;
  var $send = null;
  var $replies = null;
  var $welcome = null;
  var $loading = null;
  var $reset = null;

  function idbOpen(cb) {
    if (useLs) {
      cb(null);
      return;
    }
    if (idbDb) {
      cb(idbDb);
      return;
    }
    var req = indexedDB.open(IDB_NAME, 1);
    req.onupgradeneeded = function () {
      var db = req.result;
      if (!db.objectStoreNames.contains(IDB_STORE)) {
        db.createObjectStore(IDB_STORE);
      }
    };
    req.onsuccess = function () {
      idbDb = req.result;
      cb(idbDb);
    };
    req.onerror = function () {
      cb(null);
    };
  }

  function idbGet(cb) {
    if (useLs) {
      try {
        var raw = localStorage.getItem('fun_dacha_assistant_v1');
        cb(raw ? JSON.parse(raw) : null);
      } catch (e) {
        cb(null);
      }
      return;
    }
    idbOpen(function (db) {
      if (!db) {
        cb(null);
        return;
      }
      try {
        var tx = db.transaction(IDB_STORE, 'readonly');
        var st = tx.objectStore(IDB_STORE);
        var g = st.get(IDB_KEY);
        g.onsuccess = function () {
          cb(g.result || null);
        };
        g.onerror = function () {
          cb(null);
        };
      } catch (e) {
        cb(null);
      }
    });
  }

  function idbSet(data, cb) {
    if (useLs) {
      try {
        localStorage.setItem('fun_dacha_assistant_v1', JSON.stringify(data));
      } catch (e) {}
      if (cb) {
        cb();
      }
      return;
    }
    idbOpen(function (db) {
      if (!db) {
        if (cb) {
          cb();
        }
        return;
      }
      try {
        var tx = db.transaction(IDB_STORE, 'readwrite');
        tx.objectStore(IDB_STORE).put(data, IDB_KEY);
        tx.oncomplete = function () {
          if (cb) {
            cb();
          }
        };
        tx.onerror = function () {
          if (cb) {
            cb();
          }
        };
      } catch (e) {
        if (cb) {
          cb();
        }
      }
    });
  }

  function idbClear(cb) {
    idbSet({ v: 1, items: [] }, cb);
  }

  function saveItem(item) {
    idbGet(function (data) {
      var d = data && data.items ? data : { v: 1, items: [] };
      d.items.push(item);
      if (d.items.length > 50) {
        d.items = d.items.slice(-50);
      }
      idbSet(d);
    });
  }

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /** OC url->link() already uses &amp;; avoid double-encoding in href. */
  function escHref(url) {
    var u = String(url || '').replace(/&amp;/g, '&');
    return escHtml(u);
  }

  function renderMarkdown(text) {
    return escHtml(text)
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.+?)\*/g, '<em>$1</em>')
      .replace(/\n/g, '<br>');
  }

  function scrollBottom() {
    if ($scroll && $scroll.length) {
      $scroll[0].scrollTop = $scroll[0].scrollHeight;
    }
  }

  function setPanelLoading(on) {
    if (!$loading || !$loading.length) {
      return;
    }
    $loading.attr('aria-hidden', on ? 'false' : 'true');
    $panel.toggleClass('ai-assistant-panel--busy', !!on);
  }

  function appendUserMessage(text, skipSave) {
    var $msg = $('<div class="ai-message ai-message--user"></div>');
    $msg.text(text);
    $messages.append($msg);
    scrollBottom();
    if (!skipSave) {
      saveItem({ role: 'user', text: text });
    }
  }

  function appendAiMessage(text, actions, skipSave) {
    var $bubble = $('<div class="ai-message ai-message--ai"></div>');
    var $avatar = $('<span class="ai-message__avatar" aria-hidden="true">🌱</span>');
    var $body = $('<div class="ai-message__body"></div>');
    $body.html(renderMarkdown(text || ''));

    (actions || []).forEach(function (action) {
      if (!action || !action.type) {
        return;
      }
      if (action.type === 'showProducts' && action.products) {
        $body.append(renderProductCarousel(action.products));
      }
      if (action.type === 'askFilter' && action.filters) {
        $body.append(renderFilterChips(action.question, action.filters));
      }
      if (action.type === 'showArticle' && action.article) {
        $body.append(renderArticleCard(action.article));
      }
      if (action.type === 'navigateTo' && action.url) {
        $body.append(renderNavigateCta(action.url, action.label));
      }
    });

    $bubble.append($avatar, $body);
    $messages.append($bubble);
    scrollBottom();
    if (!skipSave) {
      saveItem({ role: 'assistant', text: text, actions: actions || [] });
    }
  }

  function groupProductAttributes(attrs) {
    var byGroup = {};
    (attrs || []).forEach(function (a) {
      var g = (a.group && String(a.group).trim()) || '__default__';
      var n = (a.name && String(a.name).trim()) || '';
      if (!n) {
        return;
      }
      var v = a.value != null && a.value !== '' ? String(a.value) : '';
      if (!byGroup[g]) {
        byGroup[g] = {};
      }
      if (!byGroup[g][n]) {
        byGroup[g][n] = [];
      }
      if (v && byGroup[g][n].indexOf(v) === -1) {
        byGroup[g][n].push(v);
      }
    });
    return byGroup;
  }

  function renderGroupedProductAttributes(attrs) {
    var grouped = groupProductAttributes(attrs);
    var keys = Object.keys(grouped);
    if (!keys.length) {
      return '';
    }
    var showGroupTitles = keys.length > 1;
    var html = '';
    keys.forEach(function (gKey) {
      if (showGroupTitles && gKey !== '__default__') {
        html +=
          '<div class="ai-attr-group__heading">' + escHtml(gKey) + '</div>';
      }
      var rows = grouped[gKey];
      Object.keys(rows).forEach(function (name) {
        var vals = rows[name].join(', ');
        html +=
          '<div class="ai-attr-row"><b>' +
          escHtml(name) +
          ':</b> ' +
          escHtml(vals) +
          '</div>';
      });
    });
    return html;
  }

  function renderProductCarousel(products) {
    var $wrap = $('<div class="ai-product-carousel"></div>');
    products.forEach(function (p) {
      var price =
        p.priceFormatted ||
        (p.price > 0 ? p.price + ' ₴' : '—');
      var stock = p.inStock
        ? '<span class="ai-badge ai-badge--in">В наявності</span>'
        : '<span class="ai-badge ai-badge--out">Уточнюйте наявність</span>';
      var attrLines = renderGroupedProductAttributes(p.attributes || []);
      var imgSrc = p.image ? cfg.imageBase + p.image : '';
      var productUrl = cfg.productUrl + String(p.id);

      var html =
        '<div class="ai-product-card__img">' +
        (imgSrc ? '<img src="' + escHtml(imgSrc) + '" alt="" loading="lazy">' : '') +
        '</div>' +
        '<div class="ai-product-card__info">' +
        '<div class="ai-product-card__name">' +
        escHtml(p.name) +
        '</div>' +
        '<div class="ai-product-card__cat">' +
        escHtml(p.category || '') +
        '</div>' +
        '<div class="ai-product-card__attrs">' +
        (attrLines
          ? '<div class="ai-attr-groups">' + attrLines + '</div>'
          : '') +
        '</div>' +
        stock +
        '<div class="ai-product-card__price">' +
        escHtml(price) +
        '</div>' +
        '<a href="' +
        escHref(productUrl) +
        '" class="ai-product-card__cta">Детальніше →</a>' +
        '</div>';

      var $card = $('<article class="ai-product-card"></article>').html(html);
      $wrap.append($card);
    });
    return $('<div class="ai-carousel-wrap-inner"></div>').append($wrap);
  }

  function renderFilterChips(question, filters) {
    var $wrap = $('<div class="ai-filter-group"></div>');
    $wrap.append(
      $('<div class="ai-filter-group__label"></div>').text(question || 'Оберіть:')
    );
    var $chips = $('<div class="ai-filter-group__chips"></div>');
    (filters || []).forEach(function (f) {
      $('<button type="button" class="ai-chip"></button>')
        .text(f.name)
        .attr('data-filter-id', f.id)
        .on('click', function () {
          sendMessage(f.name);
        })
        .appendTo($chips);
    });
    $('<button type="button" class="ai-chip ai-chip--skip"></button>')
      .text('Не важливо')
      .on('click', function () {
        sendMessage('Не важливо');
      })
      .appendTo($chips);
    return $wrap.append($chips);
  }

  function renderArticleCard(article) {
    var href = article.href || cfg.callbackUrl || '#';
    return $('<div class="ai-article-card"></div>').html(
      '<div class="ai-article-card__icon">📖 Стаття</div>' +
        '<div class="ai-article-card__title">' +
        escHtml(article.title || '') +
        '</div>' +
        '<div class="ai-article-card__summary">' +
        escHtml((article.summary || '').substring(0, 220)) +
        (article.summary && article.summary.length > 220 ? '…' : '') +
        '</div>' +
        '<a href="' +
        escHref(href) +
        '" class="ai-article-card__cta">Блог →</a>'
    );
  }

  function renderNavigateCta(url, label) {
    return $('<div class="ai-navigate-cta"></div>').html(
      '<a href="' +
        escHref(url) +
        '" class="ai-navigate-cta__btn">' +
        escHtml(label || 'Перейти →') +
        '</a>'
    );
  }

  function renderQuickReplies(kind) {
    if (!$replies || !$replies.length) {
      return;
    }
    $replies.empty();
    var list = [];
    if (kind === 'start') {
      list = ['Томати', 'Огірки', 'Перець', 'Квіти'];
    } else if (kind === 'products') {
      list = ['Є щось дешевше?', 'Показати ще варіанти', 'Почати спочатку'];
    } else if (kind === 'info') {
      list = ['Консультація', 'Каталог', 'Акції'];
    } else {
      list = ['Каталог', 'Акції', 'Почати спочатку'];
    }
    list.forEach(function (s) {
      $('<button type="button" class="ai-quick-reply"></button>')
        .text(s)
        .on('click', function () {
          sendMessage(s);
        })
        .appendTo($replies);
    });
  }

  function handleActions(actions) {
    var types = (actions || []).map(function (a) {
      return a.type;
    });
    if (types.indexOf('showProducts') >= 0) {
      renderQuickReplies('products');
    } else if (types.indexOf('navigateTo') >= 0 || types.indexOf('showInfo') >= 0) {
      renderQuickReplies('info');
    } else {
      renderQuickReplies('general');
    }
  }

  function sendMessage(text) {
    if (!text) {
      return;
    }
    $welcome.hide();
    appendUserMessage(text);
    setPanelLoading(true);
    if ($replies) {
      $replies.empty();
    }

    if (text === 'Почати спочатку') {
      $.ajax({
        url: cfg.resetUrl,
        method: 'POST',
        dataType: 'json',
        complete: function () {
          idbClear();
          $welcome.show();
          $messages.find('.ai-message').remove();
          setPanelLoading(false);
          appendAiMessage(cfg.i18n ? cfg.i18n.resetDone : 'OK', [], true);
          renderQuickReplies('start');
        },
      });
      return;
    }

    $.ajax({
      url: cfg.ajaxUrl,
      method: 'POST',
      contentType: 'application/json; charset=utf-8',
      data: JSON.stringify({ message: text }),
      dataType: 'json',
      success: function (resp) {
        setPanelLoading(false);
        if (resp.error) {
          appendAiMessage(cfg.i18n ? cfg.i18n.error : 'Error', []);
          return;
        }
        appendAiMessage(resp.text || '', resp.actions || []);
        handleActions(resp.actions || []);
      },
      error: function () {
        setPanelLoading(false);
        appendAiMessage(cfg.i18n ? cfg.i18n.error : 'Error', []);
      },
    });
  }

  function doSend() {
    var text = ($input.val() || '').trim();
    if (!text) {
      return;
    }
    $input.val('');
    $input.css('height', 'auto');
    sendMessage(text);
  }

  function restoreTranscript() {
    idbGet(function (data) {
      if (!data || !data.items || !data.items.length) {
        renderQuickReplies('start');
        return;
      }
      $welcome.hide();
      data.items.forEach(function (item) {
        if (item.role === 'user') {
          appendUserMessage(item.text, true);
        } else {
          appendAiMessage(item.text, item.actions || [], true);
        }
      });
      scrollBottom();
      renderQuickReplies('general');
    });
  }

  function bindDom() {
    $panel = $('#ai-assistant-panel');
    $toggle = $('#ai-assistant-toggle');
    $close = $('#ai-assistant-close');
    $messages = $('#ai-chat-messages');
    $scroll = $('#ai-chat-scroll');
    $input = $('#ai-chat-input');
    $send = $('#ai-chat-send');
    $replies = $('#ai-quick-replies');
    $welcome = $('#ai-welcome-screen');
    $loading = $('#ai-assistant-loading');
    $reset = $('#ai-assistant-reset');

    if (cfg.embedMode === 'full') {
      state.isOpen = true;
      $panel.addClass('ai-assistant-panel--open').attr('aria-hidden', false);
    }

    if ($toggle.length) {
      $toggle.on('click', function () {
        state.isOpen = !state.isOpen;
        $panel.toggleClass('ai-assistant-panel--open', state.isOpen).attr('aria-hidden', !state.isOpen);
        if (state.isOpen) {
          $input.trigger('focus');
          scrollBottom();
        }
      });
    }

    if ($close.length) {
      $close.on('click', function () {
        state.isOpen = false;
        $panel.removeClass('ai-assistant-panel--open').attr('aria-hidden', true);
      });
    }

    $(document).on('click', '.ai-intent-btn', function () {
      var msg = $(this).data('message') || $(this).text();
      sendMessage(msg);
      if (($(this).data('intent') || '') === 'consult') {
        renderQuickReplies('start');
      }
    });

    $send.on('click', doSend);
    $input.on('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        doSend();
      }
    });
    $input.on('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    $reset.on('click', function () {
      setPanelLoading(true);
      $.ajax({
        url: cfg.resetUrl,
        method: 'POST',
        dataType: 'json',
        complete: function () {
          idbClear(function () {
            $welcome.show();
            $messages.find('.ai-message').remove();
            setPanelLoading(false);
            appendAiMessage(cfg.i18n ? cfg.i18n.resetDone : 'OK', [], true);
            renderQuickReplies('start');
          });
        },
      });
    });

    restoreTranscript();
  }

  $(document).ready(bindDom);
})(jQuery);
