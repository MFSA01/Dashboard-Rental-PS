/**
 * assets/js/timer.js
 * Timer live kartu PS (countdown paket & count-up open billing)
 * serta sinkronisasi ringkasan tagihan di halaman detail sesi.
 */
(function () {
  'use strict';

  var TICK_MS = 1000;

  function formatRupiah(value) {
    var amount = Math.round(Number(value) || 0);
    return 'Rp ' + amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function pad(value) {
    var n = Math.max(0, Math.floor(Number(value) || 0));
    return n < 10 ? '0' + n : String(n);
  }

  function splitDuration(totalSeconds) {
    var seconds = Math.max(0, Math.floor(totalSeconds));
    return {
      hours: Math.floor(seconds / 3600),
      minutes: Math.floor((seconds % 3600) / 60),
      seconds: seconds % 60
    };
  }

  function formatDurationLabel(totalMinutes) {
    var minutes = Math.max(0, Math.floor(totalMinutes));
    if (minutes === 0) {
      return '0 Menit';
    }
    var hours = Math.floor(minutes / 60);
    var rest = minutes % 60;
    if (hours > 0 && rest > 0) {
      return hours + ' Jam ' + rest + ' Menit';
    }
    if (hours > 0) {
      return hours + ' Jam';
    }
    return rest + ' Menit';
  }

  function calculateOpenCost(elapsedSeconds, hourlyRate, minMinutes, roundingMinutes) {
    var min = Math.max(1, Number(minMinutes) || 30);
    var rounding = Math.max(1, Number(roundingMinutes) || 15);
    var elapsedMinutes = Math.max(1, Math.ceil(Math.max(0, elapsedSeconds) / 60));
    var billableMinutes;

    if (elapsedMinutes <= min) {
      billableMinutes = min;
    } else {
      billableMinutes = Math.ceil(elapsedMinutes / rounding) * rounding;
    }

    return {
      billableMinutes: billableMinutes,
      cost: Math.round((billableMinutes / 60) * (Number(hourlyRate) || 0))
    };
  }

  function setTimerParts(card, totalSeconds) {
    var parts = splitDuration(totalSeconds);
    var hoursEl = card.querySelector('[data-timer-part="hours"]');
    var minutesEl = card.querySelector('[data-timer-part="minutes"]');
    var secondsEl = card.querySelector('[data-timer-part="seconds"]');

    if (hoursEl) {
      hoursEl.textContent = pad(parts.hours);
    }
    if (minutesEl) {
      minutesEl.textContent = pad(parts.minutes);
    }
    if (secondsEl) {
      secondsEl.textContent = pad(parts.seconds);
    }
  }

  function emitStateChange(card, state) {
    if (card.getAttribute('data-card-state') === state) {
      return;
    }
    card.setAttribute('data-card-state', state);
    document.dispatchEvent(new CustomEvent('dgs:card-state', { detail: { card: card, state: state } }));
  }

  function markTimeUp(card) {
    if (card.classList.contains('ps-card--timeup')) {
      return;
    }

    card.classList.remove('ps-card--playing');
    card.classList.add('ps-card--timeup');
    setTimerParts(card, 0);

    var badge = card.querySelector('[data-timeup-badge]');
    if (badge) {
      badge.classList.remove('is-hidden');
    }

    emitStateChange(card, 'timeup');
  }

  function initGridTimers() {
    var grid = document.getElementById('psGrid');
    if (!grid) {
      return;
    }

    var serverNow = parseInt(grid.getAttribute('data-server-now') || '0', 10);
    if (!serverNow) {
      return;
    }

    var minMinutes = parseInt(grid.getAttribute('data-min-minutes') || '30', 10);
    var roundingMinutes = parseInt(grid.getAttribute('data-rounding-minutes') || '15', 10);
    var offsetMs = serverNow * 1000 - Date.now();

    var cards = Array.prototype.slice.call(grid.querySelectorAll('.ps-card'));

    function currentServerSeconds() {
      return Math.floor((Date.now() + offsetMs) / 1000);
    }

    function tick() {
      var now = currentServerSeconds();

      cards.forEach(function (card) {
        var sessionId = parseInt(card.getAttribute('data-session-id') || '0', 10);
        var mode = card.getAttribute('data-billing-mode') || '';
        var state = card.getAttribute('data-card-state') || '';

        if (!sessionId || (state !== 'playing' && state !== 'timeup')) {
          return;
        }

        if (mode === 'open') {
          var startTs = parseInt(card.getAttribute('data-start-time') || '0', 10);
          var hourlyRate = parseInt(card.getAttribute('data-hourly-rate') || '0', 10);
          var fnbCost = parseInt(card.getAttribute('data-fnb-cost') || '0', 10);
          var elapsed = Math.max(0, now - startTs);
          var estimate = calculateOpenCost(elapsed, hourlyRate, minMinutes, roundingMinutes);

          setTimerParts(card, elapsed);

          var psCostEl = card.querySelector('[data-cost="ps"]');
          var totalEl = card.querySelector('[data-cost="total"]');
          if (psCostEl) {
            psCostEl.textContent = formatRupiah(estimate.cost);
          }
          if (totalEl) {
            totalEl.textContent = formatRupiah(estimate.cost + fnbCost);
          }

          card.setAttribute('data-ps-cost', String(estimate.cost));
          card.setAttribute('data-total-cost', String(estimate.cost + fnbCost));
          card.setAttribute('data-live-duration-label', formatDurationLabel(estimate.billableMinutes));
          return;
        }

        if (mode === 'package') {
          var scheduledEnd = parseInt(card.getAttribute('data-scheduled-end') || '0', 10);
          var remaining = Math.max(0, scheduledEnd - now);

          if (remaining <= 0) {
            markTimeUp(card);
            return;
          }

          setTimerParts(card, remaining);
        }
      });
    }

    tick();
    window.setInterval(tick, TICK_MS);
  }

  function initDetailBill() {
    var bill = document.querySelector('[data-detail-billing]');
    if (!bill || bill.getAttribute('data-is-active') !== '1') {
      return;
    }

    var serverNow = parseInt(bill.getAttribute('data-server-now') || '0', 10);
    if (!serverNow) {
      return;
    }

    var mode = bill.getAttribute('data-billing-mode') || '';
    var offsetMs = serverNow * 1000 - Date.now();
    var countdownEl = document.querySelector('[data-detail-countdown]');

    if (mode === 'package') {
      var scheduledEnd = parseInt(bill.getAttribute('data-scheduled-end') || '0', 10);
      if (!scheduledEnd) {
        return;
      }

      function tickCountdown() {
        var now = Math.floor((Date.now() + offsetMs) / 1000);
        var remaining = Math.max(0, scheduledEnd - now);
        var parts = splitDuration(remaining);

        if (countdownEl) {
          countdownEl.textContent = pad(parts.hours) + ':' + pad(parts.minutes) + ':' + pad(parts.seconds);
        }
      }

      tickCountdown();
      window.setInterval(tickCountdown, TICK_MS);
      return;
    }

    if (mode !== 'open') {
      return;
    }

    var minMinutes = parseInt(bill.getAttribute('data-min-minutes') || '30', 10);
    var roundingMinutes = parseInt(bill.getAttribute('data-rounding-minutes') || '15', 10);
    var hourlyRate = parseInt(bill.getAttribute('data-hourly-rate') || '0', 10);
    var startTs = parseInt(bill.getAttribute('data-start-time') || '0', 10);
    var fnbCost = parseInt(bill.getAttribute('data-fnb-cost') || '0', 10);

    var timerEl = document.querySelector('[data-detail-timer]');
    var durationEls = document.querySelectorAll('[data-detail-duration]');
    var psEl = bill.querySelector('[data-bill-ps]');
    var totalEl = bill.querySelector('[data-bill-total]');

    function tick() {
      var now = Math.floor((Date.now() + offsetMs) / 1000);
      var elapsed = Math.max(0, now - startTs);
      var estimate = calculateOpenCost(elapsed, hourlyRate, minMinutes, roundingMinutes);
      var parts = splitDuration(elapsed);
      var durationLabel = formatDurationLabel(estimate.billableMinutes);

      if (timerEl) {
        timerEl.textContent = pad(parts.hours) + ':' + pad(parts.minutes) + ':' + pad(parts.seconds);
      }

      durationEls.forEach(function (el) {
        el.textContent = durationLabel;
      });

      if (psEl) {
        psEl.textContent = formatRupiah(estimate.cost);
      }
      if (totalEl) {
        totalEl.textContent = formatRupiah(estimate.cost + fnbCost);
      }

      var payPs = document.querySelector('[data-pay-ps]');
      var payTotal = document.querySelector('[data-pay-total]');
      var payDuration = document.querySelector('[data-pay-duration]');

      if (payPs) {
        payPs.textContent = formatRupiah(estimate.cost);
      }
      if (payTotal) {
        payTotal.textContent = formatRupiah(estimate.cost + fnbCost);
      }
      if (payDuration) {
        payDuration.textContent = durationLabel;
      }

      bill.setAttribute('data-ps-cost', String(estimate.cost));
      bill.setAttribute('data-total-cost', String(estimate.cost + fnbCost));
      bill.setAttribute('data-live-duration-label', durationLabel);
    }

    tick();
    window.setInterval(tick, TICK_MS);
  }

  document.addEventListener('DOMContentLoaded', function () {
    initGridTimers();
    initDetailBill();
  });
})();
