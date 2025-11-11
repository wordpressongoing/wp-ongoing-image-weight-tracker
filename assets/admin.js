(function () {
  const BATCH = 25; // posts por lote
  const PAGE_SIZE = 20; // filas por página
  const STATE = {
    rows: [], // dataset completo (todas las filas escaneadas)
    page: 1, // página visible
  };

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
    const total = STATE.rows.length;
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
    const pageRows = STATE.rows.slice(start, end);

    if (!pageRows.length) {
      tbody.innerHTML = `
        <tr><td colspan="5" style="text-align:center; padding:20px;">
          No data. Click "Re-scan".
        </td></tr>`;
      return;
    }

    tbody.innerHTML = pageRows
      .map((r) => {
        const stateChip = `<span class="wpoiwt-state wpoiwt-state-${r.state_key}">${r.state}</span>`;
        const label = `${r.type} - ${r.title}`;
        return `
        <tr>
          <td>${stateChip}</td>
          <td>${escapeHtml(label)}</td>
          <td>${r.image_count}</td>
          <td>${escapeHtml(humanBytes(r.total_bytes))}</td>
          <td><a class="button button-small" href="${
            r.details
          }">View Details</a></td>
        </tr>
      `;
      })
      .join("");
  }
  function escapeHtml(s) {
    const d = document.createElement("div");
    d.textContent = String(s ?? "");
    return d.innerHTML;
  }
  function appendDataRows(newRows) {
    if (!Array.isArray(newRows) || !newRows.length) return;
    STATE.rows = STATE.rows.concat(newRows);
  }
  function sortAllRowsGlobally() {
    const order = { heavy: 0, medium: 1, optimal: 2 };
    STATE.rows.sort((a, b) => {
      const pa = order[a.state_key] ?? 9;
      const pb = order[b.state_key] ?? 9;
      if (pa !== pb) return pa - pb;
      return (b.total_bytes || 0) - (a.total_bytes || 0); // desc
    });
  }

  function appendRows(html) {
    const tbody = document.getElementById("wpoiwt-tbody");
    if (!tbody) return;
    if (tbody.dataset.cleared !== "1") {
      tbody.innerHTML = "";
      tbody.dataset.cleared = "1";
    }
    const temp = document.createElement("tbody");
    temp.innerHTML = html;
    // mover sus hijos al tbody real:
    Array.from(temp.children).forEach((tr) => tbody.appendChild(tr));
  }

  async function runBatchScan(offset) {
    const form = new FormData();
    form.append("action", "wpoiwt_rescan_batch");
    form.append("nonce", (window.WPOIWT_VARS && WPOIWT_VARS.nonce) || "");
    form.append("offset", String(offset));
    form.append("limit", String(BATCH));

    const response = await fetch(WPOIWT_VARS.ajax_url, {
      method: "POST",
      credentials: "same-origin",
      body: form,
    });

    if (!response.ok) {
      throw new Error(`HTTP error ${response.status}`);
    }

    const apiResponse = await response.json();
    if (!apiResponse.success) {
      throw new Error(
        apiResponse.data?.message || apiResponse.message || "Batch failed"
      );
    }
    console.log(apiResponse);
    const responseData = apiResponse.data || {};
    // if (responseData.html) appendRows(responseData.html);
    if (Array.isArray(responseData.rows)) {
      appendDataRows(responseData.rows);
      renderPagination();
      renderTablePage(); // re-render con lo acumulado
    }
    return responseData.has_more === true ? offset + BATCH : null;
  }

  async function fullScan() {
    STATE.rows = [];
    STATE.page = 1;
    renderPagination();
    renderTablePage();

    let offset = 0;
    setLoading(true, "Scanning (0%)...");
    // Tabla Principal
    const tbody = document.getElementById("wpoiwt-tbody");
    if (tbody) {
      tbody.innerHTML =
        '<tr><td colspan="5" style="text-align:center; padding:20px;">Starting scan…</td></tr>';
      // tbody.dataset.cleared = "0";
    }

    while (offset !== null) {
      setLoading(true, `Scanning (offset ${offset})…`);
      offset = await runBatchScan(offset);
    }
    // ordenar y re-render final
    sortAllRowsGlobally();
    renderPagination();
    renderTablePage();

    setLoading(false);
  }

  // ---------- Exportar .xlsx ----------
  function exportSummaryXLSX() {
    if (!window.XLSX) {
      alert("SheetJS not loaded");
      return;
    }
    // dataset: STATE.rows
    const rows = STATE.rows.map((r) => ({
      state: r.state,
      page_post: `${r.type} - ${r.title}`,
      image_count: r.image_count,
      total_bytes: r.total_bytes,
      url: r.page_url,
    }));

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(rows);
    XLSX.utils.book_append_sheet(wb, ws, "Summary");
    // autosize (simple heurística)
    const cols = Object.keys(
      rows[0] || {
        state: "",
        page_post: "",
        image_count: "",
        total_bytes: "",
        url: "",
      }
    ).map((k) => ({ wch: Math.max(12, k.length + 2) }));
    ws["!cols"] = cols;

    XLSX.writeFile(wb, `image-weight-summary-${dateStamp()}.xlsx`);
  }
  // ---------- Exportar Detalle ----------
  function exportDetailsXLSX() {
    if (!window.XLSX) {
      alert("SheetJS not loaded");
      return;
    }
    // Los datos los inyectamos en un <script type="application/json" id="wpoiwt-details-data">
    const el = document.getElementById("wpoiwt-details-data");
    if (!el) return alert("No detail data");
    let items = [];
    try {
      items = JSON.parse(el.textContent || "[]");
    } catch (e) {
      items = [];
    }

    const rows = items.map((it) => ({
      file_name: it.name,
      bytes: it.bytes,
      state: it.state, // lo ponemos abajo al inyectar
      image_url: it.url,
    }));

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(rows);
    XLSX.utils.book_append_sheet(wb, ws, "Details");
    XLSX.writeFile(wb, `image-weight-details-${dateStamp()}.xlsx`);
  }
  // Eventos Export
  function dateStamp() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}-${pad(
      d.getHours()
    )}${pad(d.getMinutes())}${pad(d.getSeconds())}`;
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
          tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:20px;" class="err-message">${err.message}</td></tr>`;
      });
    }

    // Exportar resumen
    if (e.target && e.target.id === "wpoiwt-export") {
      e.preventDefault();
      exportSummaryXLSX();
    }
    if (e.target && e.target.id === "wpoiwt-export-details") {
      e.preventDefault();
      exportDetailsXLSX();
    }

    // Paginación
    if (e.target && e.target.id === "wpoiwt-prev") {
      e.preventDefault();
      STATE.page = Math.max(1, STATE.page - 1);
      renderPagination();
      renderTablePage();
    }
    if (e.target && e.target.id === "wpoiwt-next") {
      e.preventDefault();
      const totalPages = Math.max(1, Math.ceil(STATE.rows.length / PAGE_SIZE));
      STATE.page = Math.min(totalPages, STATE.page + 1);
      renderPagination();
      renderTablePage();
    }
  });

  // Exponer para debug opcional:
  window.WPOIWT_STATE = STATE;
})();
