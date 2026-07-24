(() => {
  "use strict";

  const state = {
    items: [],
    filter: "ALL",
    source: "local",
  };

  const elements = {
    grid: document.querySelector("#media-grid"),
    loading: document.querySelector("#loading-grid"),
    empty: document.querySelector("#empty-state"),
    count: document.querySelector("#item-count"),
    status: document.querySelector("#sync-status"),
    notice: document.querySelector("#notice"),
    refresh: document.querySelector("#refresh-button"),
    refreshIcon: document.querySelector(".refresh-icon"),
    refreshLabel: document.querySelector(".refresh-label"),
    filters: [...document.querySelectorAll("[data-filter]")],
    menu: document.querySelector(".menu-button"),
    nav: document.querySelector(".nav"),
    lightbox: document.querySelector("#lightbox"),
    lightboxMedia: document.querySelector("#lightbox-media"),
    lightboxDate: document.querySelector("#lightbox-date"),
    lightboxCaption: document.querySelector("#lightbox-caption"),
    lightboxLink: document.querySelector("#lightbox-link"),
  };

  const escapeHtml = (value = "") =>
    String(value).replace(/[&<>"']/g, (character) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    })[character]);

  const formatDate = (value) =>
    new Intl.DateTimeFormat("pt-BR", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    }).format(new Date(value));

  function filteredItems() {
    if (state.filter === "ALL") return state.items;
    if (state.filter === "IMAGE") {
      return state.items.filter((item) => item.media_type !== "VIDEO");
    }
    return state.items.filter((item) => item.media_type === "VIDEO");
  }

  function render() {
    const items = filteredItems();
    elements.count.textContent = `${String(items.length).padStart(2, "0")} itens`;
    elements.empty.hidden = items.length !== 0;

    elements.grid.innerHTML = items.map((item, index) => {
      const image = item.thumbnail_url || item.media_url;
      const caption = item.caption || "Sem título";
      const type = item.media_type === "VIDEO"
        ? '<span class="media-card__type">▶ vídeo</span>'
        : "";

      return `
        <article class="media-card">
          <button type="button" data-item-id="${escapeHtml(item.id)}" aria-label="Abrir ${escapeHtml(caption)}">
            <img src="${escapeHtml(image)}" alt="${escapeHtml(caption)}" loading="${index > 2 ? "lazy" : "eager"}">
            <span class="media-card__shade"></span>
            <span class="media-card__index">${String(index + 1).padStart(2, "0")}</span>
            ${type}
            <span class="media-card__meta">
              <strong>${escapeHtml(caption)}</strong>
              <small>${formatDate(item.timestamp)}</small>
            </span>
          </button>
        </article>
      `;
    }).join("");
  }

  async function requestJson(url) {
    const response = await fetch(`${url}${url.includes("?") ? "&" : "?"}_=${Date.now()}`, {
      cache: "no-store",
      headers: { Accept: "application/json" },
    });
    if (!response.ok) throw new Error(`Falha ao consultar ${url}`);
    return response.json();
  }

  async function loadFeed({ quiet = false } = {}) {
    if (!quiet) {
      elements.refresh.disabled = true;
      elements.refreshIcon.classList.add("is-spinning");
      elements.refreshLabel.textContent = "Atualizando";
    }

    try {
      let payload;
      try {
        payload = await requestJson("api/instagram.php");
        state.source = "instagram";
      } catch {
        payload = await requestJson("feed.json");
        state.source = "local";
      }

      state.items = Array.isArray(payload) ? payload : (payload.items || payload.data || []);
      elements.notice.hidden = state.source === "instagram";
      elements.status.textContent = `Atualizado ${new Date().toLocaleTimeString("pt-BR", {
        hour: "2-digit",
        minute: "2-digit",
      })}`;
      render();
    } catch {
      elements.status.textContent = "Não foi possível atualizar";
      elements.empty.hidden = false;
      elements.empty.textContent = "Verifique o arquivo feed.json ou a configuração do conector.";
    } finally {
      elements.loading.hidden = true;
      elements.refresh.disabled = false;
      elements.refreshIcon.classList.remove("is-spinning");
      elements.refreshLabel.textContent = "Atualizar";
    }
  }

  function openLightbox(item) {
    const media = item.media_type === "VIDEO"
      ? `<video src="${escapeHtml(item.media_url)}" controls autoplay playsinline></video>`
      : `<img src="${escapeHtml(item.media_url)}" alt="${escapeHtml(item.caption || "Publicação ampliada")}">`;

    elements.lightboxMedia.innerHTML = media;
    elements.lightboxDate.textContent = formatDate(item.timestamp);
    elements.lightboxCaption.textContent = item.caption || "Sem legenda";
    elements.lightboxLink.href = item.permalink || "https://www.instagram.com/ricardoferreira_official/";
    elements.lightbox.hidden = false;
    document.body.classList.add("is-locked");
  }

  function closeLightbox() {
    elements.lightbox.hidden = true;
    elements.lightboxMedia.innerHTML = "";
    document.body.classList.remove("is-locked");
  }

  elements.filters.forEach((button) => {
    button.addEventListener("click", () => {
      state.filter = button.dataset.filter;
      elements.filters.forEach((item) => item.classList.toggle("is-active", item === button));
      render();
    });
  });

  elements.grid.addEventListener("click", (event) => {
    const button = event.target.closest("[data-item-id]");
    if (!button) return;
    const item = state.items.find((entry) => entry.id === button.dataset.itemId);
    if (item) openLightbox(item);
  });

  document.querySelectorAll("[data-close]").forEach((button) => {
    button.addEventListener("click", closeLightbox);
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !elements.lightbox.hidden) closeLightbox();
  });

  elements.menu.addEventListener("click", () => {
    const open = elements.nav.classList.toggle("is-open");
    elements.menu.setAttribute("aria-expanded", String(open));
  });

  elements.nav.addEventListener("click", () => {
    elements.nav.classList.remove("is-open");
    elements.menu.setAttribute("aria-expanded", "false");
  });

  elements.refresh.addEventListener("click", () => loadFeed());
  document.querySelector("#current-year").textContent = new Date().getFullYear();

  loadFeed();
  window.setInterval(() => loadFeed({ quiet: true }), 60_000);
})();
