(function () {
  const BATCH = 25; // posts por lote
  const PAGE_SIZE = 20; // filas por página

  const STATE = {
    dict: {}, // key -> image object (merge entre lotes)
    order: [], // array de keys ordenadas para render
    page: 1, // página visible
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

  function renderTablePage() {
    const tbody = document.getElementById("wpoiwt-tbody");
    if (!tbody) return;

    const start = (STATE.page - 1) * PAGE_SIZE;
    const end = start + PAGE_SIZE;
    const keys = STATE.order.slice(start, end);

    if (!keys.length) {
      tbody.innerHTML = `
        <tr><td colspan="5" style="text-align:center; padding:20px;">
          No data. Click "Re-scan".
        </td></tr>`;
      return;
    }
    // tbody.innerHTML =
    const rowsHtml = keys
      .map((k) => {
        const r = STATE.dict[k];
        const chip = `<span class="wpoiwt-state wpoiwt-state-${
          r.status_key
        }">${escapeHtml(r.status_label)}</span>`;
        const name = escapeHtml(r.name || "");
        const format = escapeHtml(r.format || "");
        const img = `<img src="${r.url}" alt="" style="max-width:120px; height:auto;"/>`;

        // used_in links (todos como <a>, con ellipsis vía CSS)
        const usedHtml = (r.used_in || [])
          .map(
            (u) =>
              `<a href="${
                u.permalink
              }" target="_blank" rel="noopener noreferrer">${escapeHtml(
                u.label
              )}</a>`
          )
          .join("");

        return `
        <tr>
          <td>${chip}</td>
          <td><a href="${
            r.url
          }" target="_blank" rel="noopener noreferrer">${name}</a></td>
          <td>${format}</td>
          <td>${img}</td>
          <td><div class="wpoiwt-usedin">${usedHtml}</div></td>
          <td>${escapeHtml(humanBytes(r.bytes))}</td>
        </tr>
      `;
      })
      .join("");

    tbody.innerHTML = rowsHtml;
  }

  function escapeHtml(s) {
    const d = document.createElement("div");
    d.textContent = String(s ?? "");
    return d.innerHTML;
  }

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

  // Orden global: Heavy -> Medium -> Optimal ; bytes desc ; nombre asc
  function sortKeys() {
    const orderPrio = { heavy: 0, medium: 1, optimal: 2 };
    const arr = Object.values(STATE.dict);
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

  // ----- AJAX batch -----
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
      sortKeys();
      renderPagination();
      renderTablePage();
    }
    return data.has_more === true ? offset + BATCH : null;
  }

  async function fullScan() {
    STATE.dict = {};
    STATE.order = [];
    STATE.page = 1;
    renderPagination();
    renderTablePage();

    let offset = 0;
    setLoading(true, "Scanning...");
    // Tabla Principal
    const tbody = document.getElementById("wpoiwt-tbody");
    if (tbody) {
      tbody.innerHTML =
        '<tr><td colspan="6" style="text-align:center; padding:20px;">Starting scan…</td></tr>';
    }

    while (offset !== null) {
      offset = await runBatch(offset);
    }
    setLoading(false);
  }

  // ---------- Eventos ----------
  document.addEventListener("click", function (e) {
    // WPOIWT_VARS viene de wp_localize_script
    // click en el botón de Re-scan
    if (e.target && e.target.id === "wpoiwt-rescan") {
      e.preventDefault();
      fullScan().catch((err) => {
        setLoading(false);
        const tbody = document.getElementById("wpoiwt-tbody");
        if (tbody)
          tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:20px;" class="err-message">${err.message}</td></tr>`;
      });
    }

    // Paginación Prev/Next
    if (e.target && e.target.id === "wpoiwt-prev") {
      e.preventDefault();
      STATE.page = Math.max(1, STATE.page - 1);
      renderPagination();
      renderTablePage();
    }
    if (e.target && e.target.id === "wpoiwt-next") {
      e.preventDefault();
      const totalPages = Math.max(1, Math.ceil(STATE.order.length / PAGE_SIZE));
      STATE.page = Math.min(totalPages, STATE.page + 1);
      renderPagination();
      renderTablePage();
    }
  });

  // Auto-scan al cargar página
  document.addEventListener("DOMContentLoaded", () => {
    fullScan().catch(() => {
      /* silencioso */
    });
  });
})();
