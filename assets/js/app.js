/**
 * Product Inbound Shipment Counting Record - Client JS
 */

document.addEventListener('DOMContentLoaded', function () {
    initMobileNav();

    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrapper = btn.closest('.password-wrapper');
            if (!wrapper) return;
            var input = wrapper.querySelector('input');
            if (!input) return;
            var showLabel = btn.getAttribute('data-label-show') || 'Show password';
            var hideLabel = btn.getAttribute('data-label-hide') || 'Hide password';
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙈';
                btn.setAttribute('aria-label', hideLabel);
            } else {
                input.type = 'password';
                btn.textContent = '👁';
                btn.setAttribute('aria-label', showLabel);
            }
        });
    });

    // Modal helpers
    document.querySelectorAll('[data-modal-open]').forEach(function (el) {
        el.addEventListener('click', function () {
            var id = el.getAttribute('data-modal-open');
            var modal = document.getElementById(id);
            if (modal) modal.classList.add('active');
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            var modal = el.closest('.modal-overlay');
            if (modal) modal.classList.remove('active');
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.classList.remove('active');
        });
    });

    // Confirm delete
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            var msg = el.getAttribute('data-confirm') || '确定要删除吗？';
            if (!confirm(msg)) e.preventDefault();
        });
    });

    initShipmentSearch();

    initCountingTimer();
    window.__issAppLoaded = true;

    // Clickable shipment rows -> detail page (admin)
    document.querySelectorAll('.shipment-row[data-href]').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('.td-actions')) return;
            window.location.href = row.getAttribute('data-href');
        });
        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                window.location.href = row.getAttribute('data-href');
            }
        });
    });

    // Auto-hide alerts
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity .5s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 500);
        }, 5000);
    });
});

function initMobileNav() {
    var toggle = document.getElementById('nav-toggle');
    var menu = document.getElementById('nav-menu');
    if (!toggle || !menu) return;

    function setOpen(open) {
        menu.classList.toggle('is-open', open);
        toggle.classList.toggle('is-active', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('nav-open', open);
    }

    toggle.addEventListener('click', function () {
        setOpen(!menu.classList.contains('is-open'));
    });

    menu.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.matchMedia('(max-width: 900px)').matches) {
                setOpen(false);
            }
        });
    });

    window.addEventListener('resize', function () {
        if (window.matchMedia('(min-width: 901px)').matches) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
    });
}

function loadShipmentDataFromPage() {
    var wrap = document.getElementById('shipment-search-wrap');
    if (!wrap) return;

    function parseJsonAttr(name, fallback) {
        var raw = wrap.getAttribute(name);
        if (!raw) return fallback;
        try {
            return JSON.parse(raw);
        } catch (err) {
            return fallback;
        }
    }

    if (!window.shipmentSearchList || !window.shipmentSearchList.length) {
        window.shipmentSearchList = parseJsonAttr('data-shipment-list', []);
    }
    if (!window.shipmentProducts || typeof window.shipmentProducts !== 'object') {
        window.shipmentProducts = {};
        window.shipmentCartons = window.shipmentCartons || {};
        (window.shipmentSearchList || []).forEach(function (item) {
            if (item && item.sn) {
                window.shipmentProducts[item.sn] = item.product || '';
                window.shipmentCartons[item.sn] = item.cartons != null ? item.cartons : 0;
            }
        });
    }
}

function initShipmentSearch() {
    loadShipmentDataFromPage();

    var wrap = document.getElementById('shipment-search-wrap');
    var searchInput = document.getElementById('shipment_search');
    var hiddenInput = document.getElementById('shipment_number');
    var dropdown = document.getElementById('shipment-search-dropdown');
    var productDisplay = document.getElementById('product_name_display');
    var cartonsDisplay = document.getElementById('cartons_display');
    var form = document.getElementById('counting-form');

    if (!wrap || !searchInput || !hiddenInput || !dropdown) return;

    var items = window.shipmentSearchList || [];
    var noResultsText = wrap.getAttribute('data-no-results') || 'No results';
    var pickMsg = wrap.getAttribute('data-pick-msg') || 'Select a shipment from the list';
    var activeIndex = -1;
    var syncing = false;

    function findItem(code) {
        var c = (code || '').trim();
        if (!c) return null;
        for (var i = 0; i < items.length; i++) {
            if (items[i].sn === c) return items[i];
        }
        return null;
    }

    function normalizeQuery(q) {
        return q.trim().toLowerCase();
    }

    function filterItems(query) {
        var q = normalizeQuery(query);
        if (!q) return items.slice();
        return items.filter(function (item) {
            return item.sn.toLowerCase().indexOf(q) !== -1
                || item.product.toLowerCase().indexOf(q) !== -1;
        });
    }

    function updateShipmentSelection() {
        var sn = hiddenInput.value.trim();
        var productName = (window.shipmentProducts && sn) ? (window.shipmentProducts[sn] || '') : '';
        var item = findItem(sn);
        if (!productName && item) productName = item.product;
        if (productDisplay) {
            productDisplay.value = productName;
        }
        if (cartonsDisplay) {
            var cartons = 0;
            if (item && item.cartons != null) {
                cartons = item.cartons;
            } else if (window.shipmentCartons && sn) {
                cartons = window.shipmentCartons[sn] || 0;
            }
            cartonsDisplay.value = String(cartons);
        }
    }

    function parseSnFromInput(val) {
        var v = val.trim();
        if (!v) return '';
        var sep = v.indexOf(' — ');
        if (sep === -1) {
            sep = v.indexOf(' - ');
        }
        if (sep !== -1) {
            v = v.substring(0, sep).trim();
        }
        return v;
    }

    function setSelection(item) {
        if (!item) return;
        syncing = true;
        hiddenInput.value = item.sn;
        searchInput.value = item.sn;
        syncing = false;
        closeDropdown();
        updateShipmentSelection();
    }

    function clearSelection(clearSearch) {
        hiddenInput.value = '';
        if (productDisplay) productDisplay.value = '';
        if (cartonsDisplay) cartonsDisplay.value = '';
        if (clearSearch) searchInput.value = '';
    }

    function closeDropdown() {
        dropdown.hidden = true;
        searchInput.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
    }

    function openDropdown() {
        dropdown.hidden = false;
        searchInput.setAttribute('aria-expanded', 'true');
    }

    function renderDropdown(matches) {
        dropdown.innerHTML = '';
        activeIndex = -1;
        if (!matches.length) {
            var empty = document.createElement('li');
            empty.className = 'search-empty';
            empty.textContent = noResultsText;
            dropdown.appendChild(empty);
            openDropdown();
            return;
        }
        matches.forEach(function (item, idx) {
            var li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.setAttribute('data-index', String(idx));
            li.innerHTML = '<span class="match-sn">' + escapeHtml(item.sn) + '</span>'
                + '<span class="match-product">' + escapeHtml(item.product) + '</span>';
            li.addEventListener('mousedown', function (e) {
                e.preventDefault();
                setSelection(item);
            });
            dropdown.appendChild(li);
        });
        openDropdown();
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setActiveOption(index) {
        var options = dropdown.querySelectorAll('li:not(.search-empty)');
        options.forEach(function (el, i) {
            el.classList.toggle('is-active', i === index);
        });
        activeIndex = index;
        if (index >= 0 && options[index]) {
            options[index].scrollIntoView({ block: 'nearest' });
        }
    }

    function tryAutoMatchFromInput() {
        var existing = hiddenInput.value.trim();
        if (existing && findItem(existing)) {
            searchInput.value = existing;
            updateShipmentSelection();
            return;
        }

        var snGuess = parseSnFromInput(searchInput.value);
        if (!snGuess) {
            clearSelection(true);
            return;
        }

        var direct = findItem(snGuess);
        if (direct) {
            setSelection(direct);
            return;
        }

        var matches = filterItems(snGuess);
        if (matches.length === 1) {
            setSelection(matches[0]);
            return;
        }

        clearSelection(false);
    }

    searchInput.addEventListener('input', function () {
        if (syncing) return;
        var typed = searchInput.value.trim();
        var currentSn = hiddenInput.value.trim();
        if (currentSn && typed === currentSn) {
            return;
        }
        hiddenInput.value = '';
        var matches = filterItems(searchInput.value);
        renderDropdown(matches);
        updateShipmentSelection();
    });

    searchInput.addEventListener('focus', function () {
        renderDropdown(filterItems(searchInput.value));
    });

    searchInput.addEventListener('blur', function () {
        setTimeout(function () {
            closeDropdown();
            tryAutoMatchFromInput();
        }, 150);
    });

    searchInput.addEventListener('keydown', function (e) {
        var options = dropdown.querySelectorAll('li:not(.search-empty)');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (dropdown.hidden) renderDropdown(filterItems(searchInput.value));
            options = dropdown.querySelectorAll('li:not(.search-empty)');
            if (!options.length) return;
            var next = activeIndex < options.length - 1 ? activeIndex + 1 : 0;
            setActiveOption(next);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            options = dropdown.querySelectorAll('li:not(.search-empty)');
            if (!options.length) return;
            var prev = activeIndex > 0 ? activeIndex - 1 : options.length - 1;
            setActiveOption(prev);
        } else if (e.key === 'Enter') {
            if (!dropdown.hidden && activeIndex >= 0 && options[activeIndex]) {
                e.preventDefault();
                var idx = parseInt(options[activeIndex].getAttribute('data-index'), 10);
                var matches = filterItems(searchInput.value);
                if (matches[idx]) setSelection(matches[idx]);
            }
        } else if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) closeDropdown();
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            var sn = hiddenInput.value.trim();
            if (!sn || !findItem(sn)) {
                alert(pickMsg);
                searchInput.focus();
                e.preventDefault();
                return;
            }
            var dateInput = document.getElementById('counting_date');
            if (!dateInput || !dateInput.value.trim()) {
                var dateMsg = form.getAttribute('data-date-required-msg')
                    || 'Counting date is required.';
                alert(dateMsg);
                if (dateInput) dateInput.focus();
                e.preventDefault();
                return;
            }
            var qtyInput = document.getElementById('quantity_counted');
            var qty = qtyInput ? parseInt(qtyInput.value || '0', 10) : 0;
            if (!qtyInput || isNaN(qty) || qty < 1) {
                var qtyMsg = form.getAttribute('data-quantity-required-msg')
                    || 'Quantity counted is required (at least 1).';
                alert(qtyMsg);
                if (qtyInput) qtyInput.focus();
                e.preventDefault();
                return;
            }
        });
    }

    document.querySelectorAll('.pending-row').forEach(function (row) {
        row.addEventListener('dblclick', function () {
            var sn = row.getAttribute('data-shipment') || '';
            var item = findItem(sn);
            if (item) setSelection(item);
            document.getElementById('counting-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    if (hiddenInput.value.trim()) {
        var initial = findItem(hiddenInput.value.trim());
        if (initial) {
            searchInput.value = initial.sn;
        }
        updateShipmentSelection();
    }

    var countingForm = document.getElementById('counting-form');
    if (countingForm) {
        countingForm.querySelectorAll('button[type="button"]').forEach(function (btn) {
            btn.addEventListener('mousedown', function (e) {
                e.preventDefault();
            });
        });
    }
}

function initCountingTimer() {
    var box = document.getElementById('counting-timer-box');
    if (!box) return;

    var toggleBtn = document.getElementById('timer-toggle');
    var resetBtn = document.getElementById('timer-reset');
    var displayEl = document.getElementById('timer-display');
    var statusEl = document.getElementById('timer-status');
    var startInput = document.getElementById('start_time');
    var endInput = document.getElementById('completion_time');
    var form = document.getElementById('counting-form');

    if (!toggleBtn || !displayEl || !startInput || !endInput) return;

    var labelStart = box.getAttribute('data-label-start') || 'Start';
    var labelStop = box.getAttribute('data-label-stop') || 'Stop';
    var statusNotStarted = box.getAttribute('data-status-not-started') || 'Not started';
    var statusRunning = box.getAttribute('data-status-running') || 'Running…';
    var statusStopped = box.getAttribute('data-status-stopped') || 'Stopped';
    var statusManual = box.getAttribute('data-status-manual') || 'Manual times set';

    var state = 'idle';
    var startMs = null;
    var tickId = null;

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function formatClock(date) {
        return pad(date.getHours()) + ':' + pad(date.getMinutes()) + ':' + pad(date.getSeconds());
    }

    function formatElapsed(ms) {
        var total = Math.max(0, Math.floor(ms / 1000));
        var h = Math.floor(total / 3600);
        var m = Math.floor((total % 3600) / 60);
        var s = total % 60;
        return pad(h) + ':' + pad(m) + ':' + pad(s);
    }

    function parseTimeToday(str) {
        if (!str) return null;
        var parts = str.split(':');
        if (parts.length < 2) return null;
        var d = new Date();
        d.setHours(parseInt(parts[0], 10) || 0, parseInt(parts[1], 10) || 0, parseInt(parts[2], 10) || 0, 0);
        return d;
    }

    function normalizeTimeStr(str) {
        if (!str) return '';
        var parts = str.split(':');
        if (parts.length === 2) return parts[0] + ':' + parts[1] + ':00';
        if (parts.length >= 3) return parts[0] + ':' + parts[1] + ':' + (parts[2] || '00');
        return str;
    }

    function elapsedMsBetween(startStr, endStr) {
        var startDate = parseTimeToday(startStr);
        var endDate = parseTimeToday(endStr);
        if (!startDate || !endDate) return null;
        var startParsed = startDate.getTime();
        var endParsed = endDate.getTime();
        if (endParsed < startParsed) endParsed += 86400000;
        return endParsed - startParsed;
    }

    function clearTick() {
        if (tickId) {
            clearInterval(tickId);
            tickId = null;
        }
    }

    function updateElapsedDisplay() {
        if (state === 'running' && startMs !== null) {
            displayEl.textContent = formatElapsed(Date.now() - startMs);
            return;
        }
        var startVal = normalizeTimeStr(startInput.value);
        var endVal = normalizeTimeStr(endInput.value);
        if (startVal && endVal) {
            var diff = elapsedMsBetween(startVal, endVal);
            if (diff !== null) {
                displayEl.textContent = formatElapsed(diff);
                return;
            }
        }
        if (startVal && startMs !== null && !endVal) {
            displayEl.textContent = '00:00:00';
            return;
        }
        displayEl.textContent = '00:00:00';
    }

    function inferStateFromInputs() {
        var startVal = normalizeTimeStr(startInput.value);
        var endVal = normalizeTimeStr(endInput.value);
        if (startVal) {
            var parsed = parseTimeToday(startVal);
            if (parsed) startMs = parsed.getTime();
        } else {
            startMs = null;
        }
        if (state === 'running') {
            return;
        }
        if (startVal && endVal) {
            state = 'stopped';
        } else if (startVal) {
            state = 'manual';
        } else {
            state = 'idle';
        }
    }

    function applyUi() {
        box.classList.remove('is-running', 'is-stopped', 'is-manual');
        if (state === 'running') {
            box.classList.add('is-running');
            toggleBtn.textContent = labelStop;
            toggleBtn.classList.add('is-stop');
            toggleBtn.disabled = false;
            statusEl.textContent = statusRunning;
            if (resetBtn) resetBtn.style.display = 'inline-block';
        } else if (state === 'stopped') {
            box.classList.add('is-stopped');
            toggleBtn.textContent = labelStart;
            toggleBtn.classList.remove('is-stop');
            toggleBtn.disabled = false;
            statusEl.textContent = statusStopped;
            if (resetBtn) resetBtn.style.display = 'inline-block';
        } else if (state === 'manual') {
            box.classList.add('is-manual');
            toggleBtn.textContent = labelStart;
            toggleBtn.classList.remove('is-stop');
            toggleBtn.disabled = false;
            statusEl.textContent = statusManual;
            if (resetBtn) resetBtn.style.display = 'inline-block';
        } else {
            toggleBtn.disabled = false;
            toggleBtn.textContent = labelStart;
            toggleBtn.classList.remove('is-stop');
            statusEl.textContent = statusNotStarted;
            if (resetBtn) resetBtn.style.display = 'none';
        }
        updateElapsedDisplay();
    }

    function syncFromManualInputs() {
        var prevState = state;
        if (prevState === 'running') {
            var startVal = normalizeTimeStr(startInput.value);
            if (startVal) {
                var parsed = parseTimeToday(startVal);
                if (parsed) startMs = parsed.getTime();
            }
            var endVal = normalizeTimeStr(endInput.value);
            if (endVal) {
                endInput.value = endVal;
                state = 'stopped';
                clearTick();
            }
            applyUi();
            return;
        }
        inferStateFromInputs();
        applyUi();
    }

    function startTimer() {
        var now = new Date();
        startMs = now.getTime();
        var timeStr = formatClock(now);
        startInput.value = timeStr;
        endInput.value = '';
        state = 'running';
        clearTick();
        tickId = setInterval(updateElapsedDisplay, 1000);
        applyUi();
    }

    function stopTimer() {
        var now = new Date();
        endInput.value = formatClock(now);
        state = 'stopped';
        clearTick();
        applyUi();
    }

    function resetTimer() {
        state = 'idle';
        startMs = null;
        startInput.value = '';
        endInput.value = '';
        clearTick();
        applyUi();
    }

    function loadInitial() {
        var initStart = normalizeTimeStr(box.getAttribute('data-init-start') || startInput.value);
        var initEnd = normalizeTimeStr(box.getAttribute('data-init-end') || endInput.value);
        if (initStart) startInput.value = initStart;
        if (initEnd) endInput.value = initEnd;
        if (initStart && initEnd) {
            inferStateFromInputs();
            state = 'stopped';
        } else if (initStart && !initEnd) {
            inferStateFromInputs();
            state = 'manual';
        } else {
            state = 'idle';
        }
        applyUi();
    }

    toggleBtn.addEventListener('click', function () {
        if (state === 'running') {
            stopTimer();
        } else {
            startTimer();
        }
    });

    if (resetBtn) {
        resetBtn.addEventListener('click', resetTimer);
    }

    startInput.addEventListener('change', syncFromManualInputs);
    startInput.addEventListener('input', syncFromManualInputs);
    endInput.addEventListener('change', syncFromManualInputs);
    endInput.addEventListener('input', syncFromManualInputs);

    if (form) {
        form.addEventListener('submit', function () {
            clearTick();
            if (!normalizeTimeStr(startInput.value)) {
                startInput.value = '00:00:00';
            } else {
                startInput.value = normalizeTimeStr(startInput.value);
            }
            if (!normalizeTimeStr(endInput.value)) {
                endInput.value = startInput.value || '00:00:00';
            } else {
                endInput.value = normalizeTimeStr(endInput.value);
            }
        });
    }

    loadInitial();
}

