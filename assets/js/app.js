/**
 * assets/js/app.js
 * Interaksi global Davin's Gaming Space:
 * - Inisialisasi ikon Lucide
 * - Toggle sidebar mobile (dengan backdrop + Escape)
 * - Auto-trigger SweetAlert2 dari flash message (window.DGS_FLASH)
 * - Toggle show/hide password pada form login
 * - Pencarian & filter kartu PS
 * - Modal Mulai Main, Tambah Waktu, dan Bayar
 */
(function () {
  'use strict';

  function refreshIcons() {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  }

  function formatRupiah(value) {
    var amount = Math.round(Number(value) || 0);
    return 'Rp ' + amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function parseIntSafe(value, fallback) {
    var parsed = parseInt(value, 10);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  /* ------------------------------------------------------------
   * Sidebar
   * ------------------------------------------------------------ */
  function initSidebar() {
    var body = document.body;
    var toggle = document.getElementById('sidebarToggle');
    var backdrop = document.getElementById('sidebarBackdrop');
    var sidebar = document.getElementById('appSidebar');

    if (!toggle) {
      return;
    }

    function setSidebar(open) {
      body.classList.toggle('sidebar-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
      if (backdrop) {
        backdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
      }
    }

    toggle.addEventListener('click', function () {
      setSidebar(!body.classList.contains('sidebar-open'));
    });

    if (backdrop) {
      backdrop.addEventListener('click', function () {
        setSidebar(false);
      });
    }

    // Tutup drawer setelah memilih menu (khusus mode mobile).
    if (sidebar) {
      sidebar.addEventListener('click', function (event) {
        var link = event.target.closest('a.nav-link');
        if (link && window.matchMedia('(max-width: 900px)').matches) {
          setSidebar(false);
        }
      });
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && body.classList.contains('sidebar-open')) {
        setSidebar(false);
      }
    });

    window.addEventListener('resize', function () {
      if (!window.matchMedia('(max-width: 900px)').matches) {
        setSidebar(false);
      }
    });
  }

  /* ------------------------------------------------------------
   * Flash message
   * ------------------------------------------------------------ */
  function initFlash() {
    var flash = window.DGS_FLASH;

    if (!flash || typeof Swal === 'undefined') {
      return;
    }

    Swal.fire({
      icon: flash.type || 'info',
      title: flash.title || 'Informasi',
      text: flash.message || '',
      confirmButtonText: 'Mengerti',
      timer: 4600,
      timerProgressBar: true
    });
  }

  /* ------------------------------------------------------------
   * Show/hide password
   * ------------------------------------------------------------ */
  function initPasswordToggle() {
    var button = document.getElementById('togglePassword');
    var input = document.getElementById('password');

    if (!button || !input) {
      return;
    }

    button.addEventListener('click', function () {
      var isHidden = input.getAttribute('type') === 'password';

      input.setAttribute('type', isHidden ? 'text' : 'password');
      button.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
      button.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Tampilkan password');
      button.innerHTML = '<i data-lucide="' + (isHidden ? 'eye-off' : 'eye') + '"></i>';

      refreshIcons();
      input.focus({ preventScroll: true });
    });
  }

  /* ------------------------------------------------------------
   * Modal helper
   * ------------------------------------------------------------ */
  var modalState = {
    current: null,
    lastFocused: null
  };

  function openModal(modal) {
    if (!modal) {
      return;
    }

    if (modalState.current && modalState.current !== modal) {
      closeModal();
    }

    modalState.lastFocused = document.activeElement;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    modalState.current = modal;

    var focusTarget = modal.querySelector('input:not([type="hidden"]), select, textarea, button');
    if (focusTarget) {
      window.setTimeout(function () {
        focusTarget.focus({ preventScroll: true });
      }, 60);
    }
  }

  function closeModal() {
    if (!modalState.current) {
      return;
    }

    modalState.current.classList.remove('is-open');
    modalState.current.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    modalState.current = null;

    if (modalState.lastFocused && typeof modalState.lastFocused.focus === 'function') {
      modalState.lastFocused.focus({ preventScroll: true });
    }
  }

  function initModalBase() {
    document.addEventListener('click', function (event) {
      var closeTrigger = event.target.closest('[data-modal-close]');
      if (closeTrigger) {
        event.preventDefault();
        closeModal();
        return;
      }

      var modal = event.target.classList && event.target.classList.contains('modal') ? event.target : null;
      if (modal) {
        closeModal();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && modalState.current) {
        closeModal();
      }
    });
  }

  /* ------------------------------------------------------------
   * Pencarian & filter kartu PS
   * ------------------------------------------------------------ */
  function initBillingFilters() {
    var grid = document.getElementById('psGrid');
    if (!grid) {
      return;
    }

    var searchInput = document.getElementById('psSearch');
    var pills = Array.prototype.slice.call(document.querySelectorAll('[data-filter]'));
    var emptyNote = document.getElementById('psEmptyFilter');
    var activeFilter = 'all';

    function matchesFilter(card) {
      if (activeFilter === 'all') {
        return true;
      }
      return card.getAttribute('data-card-state') === activeFilter;
    }

    function matchesSearch(card) {
      if (!searchInput) {
        return true;
      }

      var term = searchInput.value.trim().toLowerCase();
      if (term === '') {
        return true;
      }

      var haystack = (card.getAttribute('data-search') || '') + ' ' +
        (card.getAttribute('data-station-name') || '') + ' ' +
        (card.getAttribute('data-customer-name') || '');

      return haystack.toLowerCase().indexOf(term) !== -1;
    }

    function applyFilters() {
      var visible = 0;
      var cards = grid.querySelectorAll('.ps-card');

      Array.prototype.forEach.call(cards, function (card) {
        var show = matchesFilter(card) && matchesSearch(card);
        card.classList.toggle('is-hidden', !show);
        if (show) {
          visible += 1;
        }
      });

      if (emptyNote) {
        emptyNote.hidden = visible !== 0;
      }
    }

    pills.forEach(function (pill) {
      pill.addEventListener('click', function () {
        activeFilter = pill.getAttribute('data-filter') || 'all';

        pills.forEach(function (other) {
          var isActive = other === pill;
          other.classList.toggle('is-active', isActive);
          other.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        applyFilters();
      });
    });

    if (searchInput) {
      searchInput.addEventListener('input', applyFilters);
    }

    document.addEventListener('dgs:card-state', applyFilters);

    applyFilters();
  }

  /* ------------------------------------------------------------
   * Modal Mulai Main
   * ------------------------------------------------------------ */
  function initStartModal() {
    var modal = document.getElementById('modalStart');
    var form = document.getElementById('startForm');

    if (!modal || !form) {
      return;
    }

    var stationIdEl = document.getElementById('startStationId');
    var stationMetaEl = document.getElementById('startStationMeta');
    var customerEl = document.getElementById('startCustomer');
    var durationInput = document.getElementById('startDurationMinutes');
    var packageFields = document.getElementById('startPackageFields');
    var customHours = document.getElementById('startCustomHours');
    var presets = Array.prototype.slice.call(document.querySelectorAll('#startDurationPresets input[name="duration_preset"]'));
    var modeRadios = Array.prototype.slice.call(form.querySelectorAll('[data-mode-radio]'));
    var previewCost = document.getElementById('startPreviewCost');
    var previewRate = document.getElementById('startPreviewRate');
    var previewLabel = document.getElementById('startPreviewLabel');
    var currentRate = 0;

    function currentMode() {
      var checked = modeRadios.filter(function (radio) {
        return radio.checked;
      });
      return checked.length ? checked[0].value : 'package';
    }

    function resolveMinutes() {
      var customValue = parseFloat(customHours && customHours.value ? customHours.value : '0');

      if (customValue > 0) {
        return Math.max(30, Math.round(customValue * 60));
      }

      var checkedPreset = presets.filter(function (radio) {
        return radio.checked;
      });

      if (checkedPreset.length) {
        return parseIntSafe(checkedPreset[0].value, 60);
      }

      return 60;
    }

    function updatePreview() {
      var isPackage = currentMode() === 'package';

      if (packageFields) {
        packageFields.hidden = !isPackage;
      }

      if (!isPackage) {
        if (previewLabel) {
          previewLabel.textContent = 'Open Billing dihitung saat pembayaran';
        }
        if (previewCost) {
          previewCost.textContent = 'Mengikuti durasi';
        }
        if (durationInput) {
          durationInput.value = '0';
        }
        return;
      }

      var minutes = resolveMinutes();
      var cost = Math.round((minutes / 60) * currentRate);

      if (previewLabel) {
        previewLabel.textContent = 'Estimasi biaya paket';
      }
      if (previewCost) {
        previewCost.textContent = formatRupiah(cost);
      }
      if (durationInput) {
        durationInput.value = String(minutes);
      }
    }

    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-action="start"]');
      if (!trigger) {
        return;
      }

      event.preventDefault();

      currentRate = parseIntSafe(trigger.getAttribute('data-hourly-rate'), 0);
      var stationName = trigger.getAttribute('data-station-name') || '-';
      var consoleType = trigger.getAttribute('data-console') || 'PS';

      if (stationIdEl) {
        stationIdEl.value = trigger.getAttribute('data-station-id') || '';
      }
      if (stationMetaEl) {
        stationMetaEl.textContent = stationName + ' · ' + consoleType + ' · ' + formatRupiah(currentRate) + '/jam';
      }
      if (customerEl) {
        customerEl.value = '';
      }
      var notesEl = document.getElementById('startNotes');
      if (notesEl) {
        notesEl.value = '';
      }
      if (customHours) {
        customHours.value = '';
      }
      if (previewRate) {
        previewRate.textContent = formatRupiah(currentRate);
      }

      // Default ke mode paket 1 jam setiap kali modal dibuka.
      modeRadios.forEach(function (radio) {
        radio.checked = radio.value === 'package';
      });
      presets.forEach(function (radio) {
        radio.checked = radio.value === '60';
      });

      updatePreview();
      openModal(modal);
    });

    modeRadios.forEach(function (radio) {
      radio.addEventListener('change', updatePreview);
    });

    presets.forEach(function (radio) {
      radio.addEventListener('change', function () {
        if (customHours) {
          customHours.value = '';
        }
        updatePreview();
      });
    });

    if (customHours) {
      customHours.addEventListener('input', function () {
        if (customHours.value !== '') {
          presets.forEach(function (radio) {
            radio.checked = false;
          });
        }
        updatePreview();
      });
    }
  }

  /* ------------------------------------------------------------
   * Modal Tambah Waktu
   * ------------------------------------------------------------ */
  function initExtendModal() {
    var modal = document.getElementById('modalExtend');
    var form = document.getElementById('extendForm');

    if (!modal || !form || !document.querySelector('[data-action="extend"]')) {
      return;
    }

    var sessionIdEl = document.getElementById('extendSessionId');
    var metaEl = document.getElementById('extendStationMeta');
    var minutesInput = document.getElementById('extendMinutes');
    var previewCost = document.getElementById('extendPreviewCost');
    var previewRate = document.getElementById('extendPreviewRate');
    var presets = Array.prototype.slice.call(form.querySelectorAll('#extendPresets input[name="extend_preset"]'));
    var currentRate = 0;

    function resolveMinutes() {
      var checked = presets.filter(function (radio) {
        return radio.checked;
      });
      return checked.length ? parseIntSafe(checked[0].value, 60) : 60;
    }

    function updatePreview() {
      var minutes = resolveMinutes();
      if (minutesInput) {
        minutesInput.value = String(minutes);
      }
      if (previewCost) {
        previewCost.textContent = formatRupiah(Math.round((minutes / 60) * currentRate));
      }
    }

    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-action="extend"]');
      if (!trigger) {
        return;
      }

      event.preventDefault();

      currentRate = parseIntSafe(trigger.getAttribute('data-hourly-rate'), 0);
      var stationName = trigger.getAttribute('data-station-name') || '-';

      if (sessionIdEl) {
        sessionIdEl.value = trigger.getAttribute('data-session-id') || '';
      }
      if (metaEl) {
        metaEl.textContent = stationName + ' · ' + formatRupiah(currentRate) + '/jam';
      }
      if (previewRate) {
        previewRate.textContent = formatRupiah(currentRate);
      }

      presets.forEach(function (radio) {
        radio.checked = radio.value === '60';
      });

      updatePreview();
      openModal(modal);
    });

    presets.forEach(function (radio) {
      radio.addEventListener('change', updatePreview);
    });
  }

  /* ------------------------------------------------------------
   * Modal Bayar
   * ------------------------------------------------------------ */
  function initPayModal() {
    var modal = document.getElementById('modalPay');
    var form = document.getElementById('payForm');

    if (!modal || !form) {
      return;
    }

    var sessionIdEl = document.getElementById('paySessionId');
    var metaEl = document.getElementById('payStationMeta');
    var customerEl = document.getElementById('payCustomer');
    var modeEl = document.getElementById('payMode');
    var durationEl = document.getElementById('payDuration');
    var psCostEl = document.getElementById('payPsCost');
    var fnbCostEl = document.getElementById('payFnbCost');
    var totalEl = document.getElementById('payGrandTotal');
    var noteEl = document.getElementById('payNote');

    function fillFromCard(card) {
      var stationName = card.getAttribute('data-station-name') || '-';
      var customer = card.getAttribute('data-customer-name') || '-';
      var mode = card.getAttribute('data-billing-mode') || '';
      var modeLabel = card.getAttribute('data-mode-label') || (mode === 'open' ? 'Open Billing' : 'Paket');
      var durationLabel = card.getAttribute('data-live-duration-label') ||
        card.getAttribute('data-duration-label') || '-';
      var psCost = parseIntSafe(card.getAttribute('data-ps-cost'), 0);
      var fnbCost = parseIntSafe(card.getAttribute('data-fnb-cost'), 0);
      var totalCost = parseIntSafe(card.getAttribute('data-total-cost'), psCost + fnbCost);

      if (sessionIdEl) {
        sessionIdEl.value = card.getAttribute('data-session-id') || '';
      }
      if (metaEl) {
        metaEl.textContent = stationName + ' · ' + customer;
      }
      if (customerEl) {
        customerEl.textContent = customer;
      }
      if (modeEl) {
        modeEl.textContent = modeLabel;
      }
      if (durationEl) {
        durationEl.textContent = durationLabel;
      }
      if (psCostEl) {
        psCostEl.textContent = formatRupiah(psCost);
      }
      if (fnbCostEl) {
        fnbCostEl.textContent = formatRupiah(fnbCost);
      }
      if (totalEl) {
        totalEl.textContent = formatRupiah(totalCost);
      }
      if (noteEl) {
        noteEl.textContent = mode === 'open'
          ? 'Total Open Billing mengikuti durasi berjalan dan dihitung ulang saat pembayaran.'
          : 'Pastikan nominal sudah sesuai sebelum menyelesaikan sesi.';
      }
    }

    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-action="pay"]');
      if (!trigger) {
        return;
      }

      event.preventDefault();

      var card = trigger.closest('.ps-card');
      if (!card) {
        return;
      }

      fillFromCard(card);
      openModal(modal);
    });
  }

  /* ------------------------------------------------------------
   * Modal Bayar & Tambah Waktu di halaman detail sesi
   * ------------------------------------------------------------ */
  function initDetailActions() {
    // Hanya aktif di halaman detail sesi (bukan dashboard billing).
    if (!document.querySelector('[data-detail-billing]')) {
      return;
    }

    var extendTrigger = document.querySelector('[data-detail-extend]');
    var extendModal = document.getElementById('modalExtend');

    if (extendTrigger && extendModal) {
      extendTrigger.addEventListener('click', function () {
        openModal(extendModal);
      });
    }

    var payTrigger = document.querySelector('[data-detail-pay]');
    var payModal = document.getElementById('modalPay');

    if (payTrigger && payModal) {
      payTrigger.addEventListener('click', function () {
        openModal(payModal);
      });
    }

    var extendForm = document.getElementById('extendForm');
    var extendPreviewCost = document.getElementById('extendPreviewCost');

    if (extendForm && extendPreviewCost) {
      var bill = document.querySelector('[data-detail-billing]');
      var rate = bill ? parseIntSafe(bill.getAttribute('data-hourly-rate'), 0) : 0;
      var minutesInput = document.getElementById('extendMinutes');
      var extendPresets = Array.prototype.slice.call(
        extendForm.querySelectorAll('#extendPresets input[name="extend_preset"]')
      );

      function updateDetailExtend() {
        var checked = extendPresets.filter(function (radio) {
          return radio.checked;
        });
        var minutes = checked.length ? parseIntSafe(checked[0].value, 60) : 60;

        if (minutesInput) {
          minutesInput.value = String(minutes);
        }
        extendPreviewCost.textContent = formatRupiah(Math.round((minutes / 60) * rate));
      }

      extendPresets.forEach(function (radio) {
        radio.addEventListener('change', updateDetailExtend);
      });

      if (extendTrigger) {
        extendTrigger.addEventListener('click', updateDetailExtend);
      }

      updateDetailExtend();
    }
  }

  /* ------------------------------------------------------------
   * Cafe POS - katalog, pencarian/filter, dan keranjang pesanan
   * ------------------------------------------------------------ */
  function showToast(icon, title) {
    if (typeof Swal === 'undefined') {
      return;
    }

    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: icon,
      title: title,
      showConfirmButton: false,
      timer: 2200,
      timerProgressBar: true
    });
  }

  function createIcon(name) {
    var el = document.createElement('i');
    el.setAttribute('data-lucide', name);
    return el;
  }

  function initCafePos() {
    var form = document.getElementById('cafeOrderForm');
    var grid = document.getElementById('menuGrid');
    var cartList = document.getElementById('cartItems');

    if (!form || !grid || !cartList) {
      return;
    }

    var cards = Array.prototype.slice.call(grid.querySelectorAll('.menu-card'));
    var search = document.getElementById('cafeSearch');
    var pills = Array.prototype.slice.call(document.querySelectorAll('[data-cafe-filter]'));
    var emptyFilter = document.getElementById('cafeEmptyFilter');
    var cartEmpty = document.getElementById('cartEmpty');
    var hiddenWrap = document.getElementById('cartHiddenInputs');
    var totalEl = document.getElementById('cartTotal');
    var countEl = document.getElementById('cartCount');
    var submitBtn = document.getElementById('cartSubmit');
    var resetBtn = document.getElementById('cartReset');
    var hint = document.getElementById('cartHint');
    var targetSelect = document.getElementById('targetSession');
    var walkinGroup = document.getElementById('walkinNameGroup');
    var psInfo = document.getElementById('psTargetInfo');
    var walkinInput = document.getElementById('walkinName');

    var cart = {};
    var activeCategory = 'all';

    function isWalkinTarget() {
      return !targetSelect || targetSelect.value === 'walkin';
    }

    function syncTarget() {
      var walkin = isWalkinTarget();

      if (walkinGroup) {
        walkinGroup.classList.toggle('is-hidden', !walkin);
      }

      if (psInfo) {
        psInfo.classList.toggle('is-hidden', walkin);
      }

      if (walkinInput) {
        walkinInput.disabled = !walkin;
        if (!walkin) {
          walkinInput.value = '';
        }
      }
    }

    function render() {
      var ids = Object.keys(cart);
      var totalQty = 0;
      var totalPrice = 0;

      cartList.textContent = '';
      if (hiddenWrap) {
        hiddenWrap.textContent = '';
      }

      ids.forEach(function (menuId) {
        var entry = cart[menuId];
        totalQty += entry.qty;
        totalPrice += entry.qty * entry.price;

        var item = document.createElement('div');
        item.className = 'cart-item';

        var head = document.createElement('div');
        head.className = 'cart-item-head';

        var name = document.createElement('span');
        name.className = 'cart-item-name';
        name.textContent = entry.name;

        var unitPrice = document.createElement('span');
        unitPrice.className = 'cart-item-price text-mono';
        unitPrice.textContent = formatRupiah(entry.price);

        head.appendChild(name);
        head.appendChild(unitPrice);

        var foot = document.createElement('div');
        foot.className = 'cart-item-foot';

        var stepper = document.createElement('div');
        stepper.className = 'qty-stepper';

        var decBtn = document.createElement('button');
        decBtn.type = 'button';
        decBtn.className = 'qty-btn';
        decBtn.setAttribute('data-cart-action', 'dec');
        decBtn.setAttribute('data-cart-id', menuId);
        decBtn.setAttribute('aria-label', 'Kurangi jumlah ' + entry.name);
        decBtn.disabled = entry.qty <= 1;
        decBtn.appendChild(createIcon('minus'));

        var qtyValue = document.createElement('span');
        qtyValue.className = 'qty-value';
        qtyValue.textContent = String(entry.qty);

        var incBtn = document.createElement('button');
        incBtn.type = 'button';
        incBtn.className = 'qty-btn';
        incBtn.setAttribute('data-cart-action', 'inc');
        incBtn.setAttribute('data-cart-id', menuId);
        incBtn.setAttribute('aria-label', 'Tambah jumlah ' + entry.name);
        incBtn.disabled = entry.qty >= entry.max;
        incBtn.appendChild(createIcon('plus'));

        stepper.appendChild(decBtn);
        stepper.appendChild(qtyValue);
        stepper.appendChild(incBtn);

        var subtotal = document.createElement('span');
        subtotal.className = 'cart-item-subtotal text-mono';
        subtotal.textContent = formatRupiah(entry.qty * entry.price);

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'cart-remove';
        removeBtn.setAttribute('data-cart-action', 'remove');
        removeBtn.setAttribute('data-cart-id', menuId);
        removeBtn.setAttribute('aria-label', 'Hapus ' + entry.name + ' dari keranjang');
        removeBtn.appendChild(createIcon('trash-2'));

        foot.appendChild(stepper);
        foot.appendChild(subtotal);
        foot.appendChild(removeBtn);

        item.appendChild(head);
        item.appendChild(foot);
        cartList.appendChild(item);

        if (hiddenWrap) {
          var hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = 'items[' + menuId + ']';
          hidden.value = String(entry.qty);
          hiddenWrap.appendChild(hidden);
        }
      });

      if (totalEl) {
        totalEl.textContent = formatRupiah(totalPrice);
      }

      if (countEl) {
        countEl.textContent = totalQty + ' Item';
      }

      if (cartEmpty) {
        cartEmpty.classList.toggle('is-hidden', ids.length !== 0);
      }

      if (submitBtn) {
        submitBtn.disabled = ids.length === 0;
      }

      if (resetBtn) {
        resetBtn.disabled = ids.length === 0;
      }

      if (hint) {
        if (ids.length === 0) {
          hint.textContent = 'Pilih minimal satu menu untuk mengaktifkan tombol simpan.';
        } else if (isWalkinTarget()) {
          hint.textContent = 'Pesanan walk-in akan langsung tercatat lunas saat disimpan.';
        } else {
          hint.textContent = 'Pesanan akan digabung ke tagihan unit PS dan dibayar saat sesi selesai.';
        }
      }

      refreshIcons();
    }

    function addToCart(card) {
      var menuId = card.getAttribute('data-menu-id');
      var stock = parseIntSafe(card.getAttribute('data-menu-stock'), 0);

      if (!menuId || stock <= 0) {
        return;
      }

      if (!cart[menuId]) {
        cart[menuId] = {
          name: card.getAttribute('data-menu-name') || 'Menu',
          price: parseIntSafe(card.getAttribute('data-menu-price'), 0),
          qty: 0,
          max: stock
        };
      }

      if (cart[menuId].qty >= cart[menuId].max) {
        showToast('warning', 'Stok "' + cart[menuId].name + '" hanya ' + cart[menuId].max + '.');
        return;
      }

      cart[menuId].qty += 1;
      render();
    }

    cards.forEach(function (card) {
      var button = card.querySelector('[data-add-menu]');

      if (button) {
        button.addEventListener('click', function () {
          addToCart(card);
        });
      }
    });

    cartList.addEventListener('click', function (event) {
      var target = event.target;

      if (!(target instanceof Element)) {
        return;
      }

      var control = target.closest('[data-cart-action]');

      if (!control) {
        return;
      }

      var menuId = control.getAttribute('data-cart-id');
      var action = control.getAttribute('data-cart-action');

      if (!menuId || !cart[menuId]) {
        return;
      }

      var entry = cart[menuId];

      if (action === 'inc') {
        if (entry.qty >= entry.max) {
          showToast('warning', 'Stok "' + entry.name + '" hanya ' + entry.max + '.');
          return;
        }
        entry.qty += 1;
      } else if (action === 'dec') {
        entry.qty = Math.max(1, entry.qty - 1);
      } else if (action === 'remove') {
        delete cart[menuId];
      }

      render();
    });

    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        cart = {};
        render();
      });
    }

    function applyFilter() {
      var term = search ? search.value.trim().toLowerCase() : '';
      var visibleCount = 0;

      cards.forEach(function (card) {
        var haystack = (card.getAttribute('data-search') || '').toLowerCase();
        var category = card.getAttribute('data-menu-category') || '';
        var matchesTerm = term === '' || haystack.indexOf(term) !== -1;
        var matchesCategory = activeCategory === 'all' || category === activeCategory;
        var show = matchesTerm && matchesCategory;

        card.classList.toggle('is-hidden', !show);

        if (show) {
          visibleCount += 1;
        }
      });

      if (emptyFilter) {
        emptyFilter.classList.toggle('is-hidden', visibleCount !== 0);
      }
    }

    if (search) {
      search.addEventListener('input', applyFilter);
    }

    pills.forEach(function (pill) {
      pill.addEventListener('click', function () {
        pills.forEach(function (other) {
          other.classList.toggle('is-active', other === pill);
          other.setAttribute('aria-pressed', other === pill ? 'true' : 'false');
        });

        activeCategory = pill.getAttribute('data-cafe-filter') || 'all';
        applyFilter();
      });
    });

    if (targetSelect) {
      targetSelect.addEventListener('change', function () {
        syncTarget();
        render();
      });
    }

    form.addEventListener('submit', function (event) {
      if (Object.keys(cart).length === 0) {
        event.preventDefault();
        showToast('warning', 'Keranjang masih kosong.');
        return;
      }

      if (submitBtn) {
        submitBtn.disabled = true;
      }
    });

    syncTarget();
    applyFilter();
    render();

    window.addEventListener('pageshow', function () {
      if (submitBtn && Object.keys(cart).length !== 0) {
        submitBtn.disabled = false;
      }
    });
  }

  /* ------------------------------------------------------------
   * Konfirmasi hapus item F&B di halaman detail sesi
   * ------------------------------------------------------------ */
  function initFnbDeleteConfirm() {
    var forms = Array.prototype.slice.call(document.querySelectorAll('.fnb-delete-form'));

    if (forms.length === 0) {
      return;
    }

    forms.forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (form.getAttribute('data-confirmed') === '1') {
          return;
        }

        event.preventDefault();

        var itemName = form.getAttribute('data-item-name') || 'item ini';

        function proceed() {
          form.setAttribute('data-confirmed', '1');
          form.submit();
        }

        if (typeof Swal === 'undefined') {
          if (window.confirm('Hapus "' + itemName + '" dari pesanan? Stok menu akan dikembalikan.')) {
            proceed();
          }
          return;
        }

        Swal.fire({
          icon: 'warning',
          title: 'Hapus Item Pesanan?',
          text: 'Item "' + itemName + '" akan dihapus dari sesi ini dan stok menu dikembalikan.',
          showCancelButton: true,
          confirmButtonText: 'Ya, Hapus',
          cancelButtonText: 'Batal',
          confirmButtonColor: '#EF4444'
        }).then(function (result) {
          if (result.isConfirmed) {
            proceed();
          }
        });
      });
    });
  }

  /* ------------------------------------------------------------
   * Modul 3: Grafik Keuangan Chart.js (Report)
   * ------------------------------------------------------------ */
  function initReportChart() {
    var canvas = document.getElementById('reportChart');
    if (!canvas || typeof window.Chart === 'undefined' || !window.DGS_REPORT_CHART) {
      return;
    }

    var chartData = window.DGS_REPORT_CHART;
    var ctx = canvas.getContext('2d');

    new window.Chart(ctx, {
      type: 'bar',
      data: {
        labels: chartData.labels || [],
        datasets: [
          {
            label: 'Rental PS',
            data: chartData.psData || [],
            backgroundColor: '#16A34A',
            borderRadius: 4,
            borderSkipped: false
          },
          {
            label: 'Cafe / F&B',
            data: chartData.fnbData || [],
            backgroundColor: '#4ADE80',
            borderRadius: 4,
            borderSkipped: false
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false
        },
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: '#121815',
            titleColor: '#F0FDF4',
            bodyColor: '#94A39B',
            borderColor: '#23332B',
            borderWidth: 1,
            padding: 10,
            boxPadding: 4,
            usePointStyle: true,
            callbacks: {
              label: function (context) {
                var val = context.parsed.y || 0;
                return ' ' + context.dataset.label + ': ' + formatRupiah(val);
              }
            }
          }
        },
        scales: {
          x: {
            grid: {
              color: '#23332B',
              drawBorder: false
            },
            ticks: {
              color: '#94A39B',
              font: {
                family: "'Plus Jakarta Sans', sans-serif",
                size: 11
              }
            }
          },
          y: {
            beginAtZero: true,
            grid: {
              color: '#23332B',
              drawBorder: false
            },
            ticks: {
              color: '#94A39B',
              font: {
                family: "'JetBrains Mono', monospace",
                size: 10
              },
              callback: function (val) {
                return formatRupiah(val);
              }
            }
          }
        }
      }
    });
  }

  /* ------------------------------------------------------------
   * Modul 3: Master Data Unit PS (Modal & Form)
   * ------------------------------------------------------------ */
  function initMasterStation() {
    var modal = document.getElementById('modalStation');
    if (!modal) {
      return;
    }

    var form = document.getElementById('formStation');
    var btnAdd = document.getElementById('btnAddStation');
    var title = document.getElementById('modalStationTitle');
    var subtitle = document.getElementById('modalStationSubtitle');
    var actionInput = document.getElementById('stationFormAction');
    var idInput = document.getElementById('stationId');
    var nameInput = document.getElementById('stationName');
    var consoleInput = document.getElementById('stationConsole');
    var rateInput = document.getElementById('stationRate');
    var statusGroup = document.getElementById('groupStationStatus');
    var statusSelect = document.getElementById('stationStatus');
    var alertPlaying = document.getElementById('alertStationPlaying');
    var submitBtn = document.getElementById('btnStationSubmit');

    if (btnAdd) {
      btnAdd.addEventListener('click', function () {
        if (form) {
          form.reset();
        }
        if (actionInput) actionInput.value = 'create';
        if (idInput) idInput.value = '';
        if (title) title.textContent = 'Tambah Unit PS';
        if (subtitle) subtitle.textContent = 'Daftarkan unit konsol baru';
        if (statusGroup) statusGroup.classList.remove('is-hidden');
        if (statusSelect) {
          statusSelect.value = 'idle';
          statusSelect.disabled = false;
        }
        if (alertPlaying) alertPlaying.classList.add('is-hidden');
        if (submitBtn) submitBtn.innerHTML = '<i data-lucide="save"></i>Simpan Unit';
        refreshIcons();
        openModal(modal);
      });
    }

    var editButtons = document.querySelectorAll('.btnEditStation');
    editButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-id');
        var name = btn.getAttribute('data-name');
        var consoleType = btn.getAttribute('data-console');
        var rate = btn.getAttribute('data-rate');
        var status = btn.getAttribute('data-status');

        if (actionInput) actionInput.value = 'update';
        if (idInput) idInput.value = id || '';
        if (nameInput) nameInput.value = name || '';
        if (consoleInput) consoleInput.value = consoleType || 'PS4';
        if (rateInput) rateInput.value = rate || '';

        var isPlaying = status === 'playing';
        if (statusGroup) statusGroup.classList.toggle('is-hidden', isPlaying);
        if (alertPlaying) alertPlaying.classList.toggle('is-hidden', !isPlaying);
        if (statusSelect) {
          statusSelect.value = isPlaying ? 'idle' : status;
          statusSelect.disabled = isPlaying;
        }

        if (title) title.textContent = 'Edit Unit ' + (name || '');
        if (subtitle) subtitle.textContent = 'Perbarui konfigurasi unit PS #' + (id || '');
        if (submitBtn) submitBtn.innerHTML = '<i data-lucide="save"></i>Perbarui Unit';
        refreshIcons();
        openModal(modal);
      });
    });
  }

  /* ------------------------------------------------------------
   * Modul 3: Master Data Menu Cafe (Modal, Form, & Filter)
   * ------------------------------------------------------------ */
  function initMasterMenu() {
    var modal = document.getElementById('modalMenu');
    var form = document.getElementById('formMenu');
    var btnAdd = document.getElementById('btnAddMenu');
    var title = document.getElementById('modalMenuTitle');
    var subtitle = document.getElementById('modalMenuSubtitle');
    var actionInput = document.getElementById('menuFormAction');
    var idInput = document.getElementById('menuId');
    var nameInput = document.getElementById('menuName');
    var categorySelect = document.getElementById('menuCategory');
    var priceInput = document.getElementById('menuPrice');
    var stockInput = document.getElementById('menuStock');
    var availSelect = document.getElementById('menuAvailable');
    var submitBtn = document.getElementById('btnMenuSubmit');

    if (btnAdd && modal) {
      btnAdd.addEventListener('click', function () {
        if (form) {
          form.reset();
        }
        if (actionInput) actionInput.value = 'create';
        if (idInput) idInput.value = '';
        if (title) title.textContent = 'Tambah Menu Cafe';
        if (subtitle) subtitle.textContent = 'Daftarkan item makanan/minuman baru';
        if (categorySelect) categorySelect.value = 'makanan';
        if (availSelect) availSelect.value = '1';
        if (submitBtn) submitBtn.innerHTML = '<i data-lucide="save"></i>Simpan Menu';
        refreshIcons();
        openModal(modal);
      });
    }

    if (modal) {
      var editButtons = document.querySelectorAll('.btnEditMenu');
      editButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-id');
          var name = btn.getAttribute('data-name');
          var category = btn.getAttribute('data-category');
          var price = btn.getAttribute('data-price');
          var stock = btn.getAttribute('data-stock');
          var available = btn.getAttribute('data-available');

          if (actionInput) actionInput.value = 'update';
          if (idInput) idInput.value = id || '';
          if (nameInput) nameInput.value = name || '';
          if (categorySelect) categorySelect.value = category || 'makanan';
          if (priceInput) priceInput.value = price || '';
          if (stockInput) stockInput.value = stock || '';
          if (availSelect) availSelect.value = available === '1' ? '1' : '0';

          if (title) title.textContent = 'Edit Menu ' + (name || '');
          if (subtitle) subtitle.textContent = 'Perbarui harga, stok, atau ketersediaan';
          if (submitBtn) submitBtn.innerHTML = '<i data-lucide="save"></i>Perbarui Menu';
          refreshIcons();
          openModal(modal);
        });
      });
    }

    // Filter pencarian & kategori tabel master menu
    var search = document.getElementById('masterMenuSearch');
    var pills = Array.prototype.slice.call(document.querySelectorAll('[data-master-cat]'));
    var rows = Array.prototype.slice.call(document.querySelectorAll('.master-menu-row'));

    if (rows.length > 0) {
      var activeCat = 'all';
      var emptyNote = document.getElementById('masterMenuEmptyFilter');

      function filterMenuTable() {
        var term = search ? search.value.trim().toLowerCase() : '';
        var visible = 0;

        rows.forEach(function (row) {
          var rowCat = row.getAttribute('data-cat') || '';
          var rowSearch = (row.getAttribute('data-search') || '').toLowerCase();
          var matchSearch = term === '' || rowSearch.indexOf(term) !== -1;
          var matchCat = activeCat === 'all' || rowCat === activeCat;
          var show = matchSearch && matchCat;

          row.style.display = show ? '' : 'none';
          if (show) {
            visible += 1;
          }
        });

        if (emptyNote) {
          emptyNote.classList.toggle('is-hidden', visible !== 0);
        }
      }

      if (search) {
        search.addEventListener('input', filterMenuTable);
      }

      pills.forEach(function (pill) {
        pill.addEventListener('click', function () {
          pills.forEach(function (other) {
            other.classList.toggle('is-active', other === pill);
          });
          activeCat = pill.getAttribute('data-master-cat') || 'all';
          filterMenuTable();
        });
      });
    }
  }

  /* ------------------------------------------------------------
   * Modul 3: Konfirmasi Hapus Master Data (Unit / Menu)
   * ------------------------------------------------------------ */
  function initMasterDeleteConfirm() {
    var forms = Array.prototype.slice.call(document.querySelectorAll('.formDeleteMaster'));
    if (forms.length === 0) {
      return;
    }

    forms.forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (form.getAttribute('data-confirmed') === '1') {
          return;
        }

        event.preventDefault();
        var itemName = form.getAttribute('data-name') || 'item ini';
        var itemType = form.getAttribute('data-type') || 'Data';

        function proceed() {
          form.setAttribute('data-confirmed', '1');
          form.submit();
        }

        if (typeof Swal === 'undefined') {
          if (window.confirm('Hapus ' + itemType + ' "' + itemName + '"? Tindakan ini permanen.')) {
            proceed();
          }
          return;
        }

        Swal.fire({
          icon: 'warning',
          title: 'Hapus ' + itemType + '?',
          text: 'Anda yakin ingin menghapus "' + itemName + '"? Tindakan ini tidak dapat dibatalkan.',
          showCancelButton: true,
          confirmButtonText: 'Ya, Hapus',
          cancelButtonText: 'Batal',
          confirmButtonColor: '#EF4444'
        }).then(function (result) {
          if (result.isConfirmed) {
            proceed();
          }
        });
      });
    });
  }

  /* ------------------------------------------------------------
   * Proteksi double-submit untuk form transaksi (POST ke actions/)
   * ------------------------------------------------------------ */
  function initSubmitGuard() {
    var forms = Array.prototype.slice.call(document.querySelectorAll('form[method="post"][action*="actions/"]'));

    if (forms.length === 0) {
      return;
    }

    function setBusy(form, busy) {
      var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');

      Array.prototype.forEach.call(buttons, function (button) {
        if (busy) {
          if (!button.hasAttribute('data-was-disabled')) {
            button.setAttribute('data-was-disabled', button.disabled ? '1' : '0');
          }
          button.disabled = true;
          button.classList.add('is-loading');
          button.setAttribute('aria-busy', 'true');
        } else {
          if (!button.hasAttribute('data-was-disabled')) {
            return; // tombol tidak pernah diubah oleh guard, jangan disentuh
          }
          button.disabled = button.getAttribute('data-was-disabled') === '1';
          button.removeAttribute('data-was-disabled');
          button.classList.remove('is-loading');
          button.removeAttribute('aria-busy');
        }
      });
    }

    forms.forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (event.defaultPrevented) {
          return;
        }

        // Cegah submit kedua saat form sedang diproses.
        if (form.getAttribute('data-submitting') === '1') {
          event.preventDefault();
          return;
        }

        form.setAttribute('data-submitting', '1');

        // Tunda disable agar nilai tombol submit tetap sempat terkirim,
        // dan batalkan bila handler lain membatalkan submit (mis. konfirmasi).
        window.setTimeout(function () {
          if (event.defaultPrevented) {
            form.removeAttribute('data-submitting');
            return;
          }
          setBusy(form, true);
        }, 0);
      });
    });

    // Kembalikan tombol saat halaman dipulihkan dari bfcache (tombol Back).
    window.addEventListener('pageshow', function () {
      forms.forEach(function (form) {
        form.removeAttribute('data-submitting');
        setBusy(form, false);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    refreshIcons();
    initSidebar();
    initFlash();
    initPasswordToggle();
    initModalBase();
    initBillingFilters();
    initStartModal();
    initExtendModal();
    initPayModal();
    initDetailActions();
    initCafePos();
    initFnbDeleteConfirm();
    initReportChart();
    initMasterStation();
    initMasterMenu();
    initMasterDeleteConfirm();
    initSubmitGuard();
  });
})();
