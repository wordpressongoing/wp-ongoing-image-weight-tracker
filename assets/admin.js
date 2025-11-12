(function () {
  const BATCH = 25; // posts por lote
  // const PAGE_SIZE = 20; // filas por página
  // const PAGE_SIZE = Number(WPOIWT_VARS?.page_size || 20);
  const PAGE_SIZE = Number((window.WPOIWT_VARS && WPOIWT_VARS.page_size) || 20);
  const AUTOSCAN = true; //
  const MAX_LOCS = 6; // Máximo de links visibles por imagen antes de "+N more"
  const DEBOUNCE_MS = 200;

  const STATE = {
    dict: {}, // Key -> image object (merge entre lotes)
    order: [], // Array de keys ordenadas para render
    page: 1, // Página visible
    //
    filterStatus: "all", // All | heavy | medium | optimal
    filterFormat: "all", // All | jpg | png | ...
    query: "", // Búsqueda
  };

  // -------------------- utils --------------------
  function setLoading(isLoading, text) {
    const btn = document.getElementById("wpoiwt-rescan");
    if (!btn) return;
    btn.disabled = isLoading;
    btn.textContent = isLoading ? text || "Scanning..." : "Re-scan";
  }
  function humanBytes(bytes) {
    const b = Number(bytes || 0);
    if (b < 1024) return `${b} B`;
    const kb = b / 1024;
    if (kb < 1024) return `${kb.toFixed(1)} KB`;
    const mb = kb / 1024;
    if (mb < 1024) return `${mb.toFixed(2)} MB`;
    const gb = mb / 1024;
    return `${gb.toFixed(2)} GB`;
  }
  function escapeHtml(s) {
    const d = document.createElement("div");
    d.textContent = String(s ?? "");
    return d.innerHTML;
  }
  function debounce(fn, ms) {
    let t = null;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), ms);
    };
  }

  /* -------------------- Filtros -------------------- */
  // Filtrar la lista según estado/formato/búsqueda
  function filteredList() {
    const q = STATE.query.trim().toLowerCase();
    const fs = STATE.filterStatus;
    const ff = STATE.filterFormat;

    return Object.values(STATE.dict).filter((r) => {
      // filtro status
      if (fs !== "all" && r.status_key !== fs) return false;

      // filtro formato (usa minúsculas)
      if (ff !== "all" && (r.format || "").toLowerCase() !== ff) return false;

      // búsqueda en nombre de imagen o en cualquiera de los labels de used_in
      if (q) {
        const hitName = (r.name || "").toLowerCase().includes(q);
        const hitUsed = (r.used_in || []).some((u) =>
          (u.label || "").toLowerCase().includes(q)
        );
        if (!hitName && !hitUsed) return false;
      }
      return true;
    });
  }
  // Ordenar la lista filtrada
  function sortKeys() {
    const orderPrio = { heavy: 0, medium: 1, optimal: 2 };
    const arr = filteredList(); // <<-- ahora ordenamos el filtrado
    arr.sort((a, b) => {
      const pa = orderPrio[a.status_key] ?? 9;
      const pb = orderPrio[b.status_key] ?? 9;
      if (pa !== pb) return pa - pb;
      if ((b.bytes || 0) !== (a.bytes || 0))
        return (b.bytes || 0) - (a.bytes || 0);
      return (a.name || "").localeCompare(b.name || "");
    });
    STATE.order = arr.map((x) => x.key);
  }
  // Aplicar filtros y renderizar
  function applyFiltersAndRender() {
    STATE.page = 1;
    sortKeys();
    renderPagination();
    renderCounter();
    renderTablePage();
  }

  /* -------------------- Renderizado -------------------- */
  function renderCounter() {
    const el = document.getElementById("wpoiwt-counter");
    if (!el) return;
    const total = Object.keys(STATE.dict).length;
    const shown = STATE.order.length;
    el.textContent = `${shown} shown — ${total} total images`;
  }
  function renderPagination() {
    const total = STATE.order.length;
    const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    STATE.page = Math.min(Math.max(1, STATE.page), totalPages);

    const el = document.getElementById("wpoiwt-pagination");
    if (!el) return;
    const prevDisabled = STATE.page <= 1 ? "disabled" : "";
    const nextDisabled = STATE.page >= totalPages ? "disabled" : "";

    el.innerHTML = `
      <button id="wpoiwt-prev" class="button" ${prevDisabled}>Prev</button>
      <span style="margin:0 8px;">Page ${STATE.page} / ${totalPages}</span>
      <button id="wpoiwt-next" class="button" ${nextDisabled}>Next</button>
    `;
  }

  function renderUsedInCell(usedArr, rowKey) {
    if (!Array.isArray(usedArr) || usedArr.length === 0) return "";
    const shown = usedArr
      .slice(0, MAX_LOCS)
      .map(
        (u) =>
          `<a href="${
            u.permalink
          }" target="_blank" rel="noopener noreferrer">${escapeHtml(
            u.label
          )}</a>`
      )
      .join("");
    const rest = usedArr.slice(MAX_LOCS);
    if (rest.length === 0) {
      return `<div class="wpoiwt-usedin">${shown}</div>`;
    }
    // guardamos los restantes con una clase para toggle
    const hidden = rest
      .map(
        (u) =>
          `<a class="wpoiwt-hidden" data-row="${rowKey}" href="${
            u.permalink
          }" target="_blank" rel="noopener noreferrer">${escapeHtml(
            u.label
          )}</a>`
      )
      .join("");
    const toggle = `<span class="wpoiwt-more-toggle" data-row="${rowKey}" data-open="0">… +${rest.length} more</span>`;
    return `<div class="wpoiwt-usedin">${shown}${hidden}${toggle}</div>`;
  }

  function renderTablePage() {
    const tbody = document.getElementById("wpoiwt-tbody");
    if (!tbody) return;

    const start = (STATE.page - 1) * PAGE_SIZE;
    const end = start + PAGE_SIZE;
    const keys = STATE.order.slice(start, end);

    if (!keys.length) {
      tbody.innerHTML = `
        <tr><td colspan="6" style="text-align:center; padding:20px;">
          No data. Click "Re-scan".
        </td></tr>`;
      return;
    }

    const rowsHtml = keys
      .map((k) => {
        const r = STATE.dict[k];
        const chip = `<span class="wpoiwt-state wpoiwt-state-${
          r.status_key
        }">${escapeHtml(r.status_label)}</span>`;
        const name = escapeHtml(r.name || "");
        const format = escapeHtml(r.format || "");
        const img = `<img src="${
          r.preview_url || r.url
        }" alt="" loading="lazy" decoding="async" style="width:110px; height:62px;object-fit:cover"/>`;
        const usedHtml = renderUsedInCell(r.used_in || [], k);

        return `
        <tr>
          <td>${chip}</td>
          <td><a href="${
            r.url
          }" target="_blank" rel="noopener noreferrer">${name}</a></td>
          <td>${format}</td>
          <td>${img}</td>
          <td>${usedHtml}</td>
          <td>${escapeHtml(humanBytes(r.bytes))}</td>
        </tr>
      `;
      })
      .join("");

    tbody.innerHTML = rowsHtml;
  }

  /* -------------------- Merge -------------------- */
  // Merge de imágenes por key (une used_in)
  function mergeImages(list) {
    for (const it of list) {
      const k = it.key;
      if (!STATE.dict[k]) {
        STATE.dict[k] = it;
      } else {
        // unir used_in y usage_count
        const existing = STATE.dict[k];
        const map = {};
        for (const u of existing.used_in || []) map[u.post_id] = u;
        for (const u of it.used_in || []) map[u.post_id] = u;
        existing.used_in = Object.values(map);
        existing.usage_count = existing.used_in.length;

        // mantener bytes/status/format/url/name de la versión con datos (no debería cambiar)
      }
    }
  }

  /* -------------------- AJAX -------------------- */
  async function runBatch(offset) {
    const form = new FormData();
    form.append("action", "wpoiwt_scan_images_batch");
    form.append("nonce", (window.WPOIWT_VARS && WPOIWT_VARS.nonce) || "");
    form.append("offset", String(offset));
    form.append("limit", String(BATCH));

    const res = await fetch(WPOIWT_VARS.ajax_url, {
      method: "POST",
      credentials: "same-origin",
      body: form,
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);

    const payload = await res.json();
    if (!payload.success)
      throw new Error(
        payload.data?.message || payload.message || "Batch failed"
      );

    const data = payload.data || {};
    if (Array.isArray(data.images)) {
      mergeImages(data.images);
      applyFiltersAndRender();
    }
    return data.has_more === true ? offset + BATCH : null;
  }
  async function fullScan() {
    STATE.dict = {};
    STATE.order = [];
    STATE.page = 1;
    renderPagination();
    renderTablePage();
    const loader = document.querySelector("#loader-image-weight-tracker");

    let offset = 0;
    loader.classList.add("active");
    setLoading(true, "Scanning...");
    // Tabla Principal
    const tbody = document.getElementById("wpoiwt-tbody");
    if (tbody) {
      tbody.innerHTML =
        '<tr><td colspan="6" style="text-align:center; padding:20px;">Starting scan…</td></tr>';
    }

    try {
      while (offset !== null) {
        offset = await runBatch(offset);
      }
    } catch (error) {
      console.error(error);
      const tbody2 = document.getElementById("wpoiwt-tbody");
      if (tbody2) {
        tbody2.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:20px;">${escapeHtml(
          error.message
        )}</td></tr>`;
      }
    } finally {
      setLoading(false);
      loader.classList.remove("active");
    }
  }

  /* -------------------- Eventos -------------------- */
  document.addEventListener("click", function (e) {
    const target = e.target;

    // Click en el botón de Re-scan
    if (target && target.id === "wpoiwt-rescan") {
      e.preventDefault();
      fullScan().catch((err) => {
        setLoading(false);
        const tbody = document.getElementById("wpoiwt-tbody");
        if (tbody)
          tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:20px;" class="err-message">${err.message}</td></tr>`;
      });
    }

    // Paginación Prev/Next
    if (target && target.id === "wpoiwt-prev") {
      e.preventDefault();
      STATE.page = Math.max(1, STATE.page - 1);
      renderPagination();
      renderCounter();
      renderTablePage();
    }
    if (target && target.id === "wpoiwt-next") {
      e.preventDefault();
      const totalPages = Math.max(1, Math.ceil(STATE.order.length / PAGE_SIZE));
      STATE.page = Math.min(totalPages, STATE.page + 1);
      renderPagination();
      renderCounter();
      renderTablePage();
    }

    // Filtro por peso de imagen
    const chip = target.closest(".wpoiwt-chip");
    if (chip && chip.dataset.status) {
      document
        .querySelectorAll(".wpoiwt-chip")
        .forEach((el) => el.classList.remove("is-active"));
      chip.classList.add("is-active");
      STATE.filterStatus = chip.dataset.status;
      applyFiltersAndRender();
    }

    // toggle ... +N more
    const toggle = target.closest(".wpoiwt-more-toggle");
    if (toggle && toggle.dataset.row) {
      const rowKey = toggle.dataset.row;
      const open = toggle.getAttribute("data-open") === "1";
      const container = toggle.parentElement;
      if (!container) return;

      const hiddenLinks = container.querySelectorAll(
        `.wpoiwt-hidden[data-row="${rowKey}"]`
      );
      hiddenLinks.forEach((a) => (a.style.display = open ? "none" : "block"));

      toggle.setAttribute("data-open", open ? "0" : "1");
      if (open) {
        // volver a estado cerrado: recalcular cuántos quedan ocultos
        const all = STATE.dict[rowKey]?.used_in || [];
        const rest = Math.max(0, all.length - MAX_LOCS);
        toggle.textContent = `… +${rest} more`;
      } else {
        toggle.textContent = "show less";
      }
      return;
    }
  });

  // Auto-scan al cargar página
  document.addEventListener("DOMContentLoaded", () => {
    // fullScan().catch(() => {});
    if (AUTOSCAN) fullScan();
  });

  //
  const onSearch = debounce(function (val) {
    STATE.query = String(val || "");
    applyFiltersAndRender();
  }, DEBOUNCE_MS);
  document.addEventListener("input", function (e) {
    const target = e.target;
    if (target && target.id === "wpoiwt-search") {
      onSearch(target.value);
    }
  });

  //
  document.addEventListener("change", function (e) {
    const target = e.target;
    if (target && target.id === "wpoiwt-format") {
      STATE.filterFormat = String(target.value || "all").toLowerCase();
      applyFiltersAndRender();
    }
  });

  //
})();
