// Apresentação da área de cursos. Recebe os dados e serviços da prévia por contrato.
export function createAcademy(ctx) {
  const {
    state,
    esc,
    icon,
    logo,
    text,
    bar,
    progress,
    allLessons,
    activeCourses,
    nextLesson,
    lessonInfo,
    lessonUnlocked,
    route,
    avatar,
    contact,
    materials,
    menuOpen,
    outlineOpen,
    lessonTab,
    mediaUrls,
    persist,
    courseFilter,
    search,
  } = ctx;
  const b = state.settings.brand;
  const norm = (s) =>
    s
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLocaleLowerCase("pt-BR");
  const courseHref = (c) => "#/curso/" + c.id;
  const lessonHref = (c) => "#/aula/" + (nextLesson(c)?.id || "");
  const continued = (c) =>
    state.lastLesson && lessonInfo(state.lastLesson)?.c.id === c.id
      ? "#/aula/" + state.lastLesson
      : lessonHref(c);
  const svgArt = (c) => {
    const kind =
      c.icon === "chart"
        ? "chart"
        : c.icon === "layers"
          ? "device"
          : c.icon === "file"
            ? "documents"
            : "orbit";
    if (kind === "chart")
      return `<svg viewBox="0 0 360 220" aria-hidden="true"><defs><linearGradient id="chart-${esc(c.id)}" x2="0" y2="1"><stop stop-color="#ffab6f"/><stop offset="1" stop-color="#b44a18"/></linearGradient></defs><g stroke="#ffffff14"><path d="M35 35h290M35 80h290M35 125h290M35 170h290M70 20v170M130 20v170M190 20v170M250 20v170M310 20v170"/></g><g fill="url(#chart-${esc(c.id)})"><rect x="58" y="127" width="22" height="62" rx="3"/><rect x="106" y="101" width="22" height="88" rx="3"/><rect x="154" y="119" width="22" height="70" rx="3"/><rect x="202" y="65" width="22" height="124" rx="3"/><rect x="250" y="29" width="22" height="160" rx="3"/></g><path d="m40 145 53-35 50 15 57-68 57-35 55 12" stroke="#ffc096" stroke-width="3" fill="none"/><circle cx="257" cy="22" r="5" fill="#fff"/></svg>`;
    if (kind === "device")
      return `<svg viewBox="0 0 360 220" aria-hidden="true"><defs><linearGradient id="screen-${esc(c.id)}" x2="1" y2="1"><stop stop-color="#29436b"/><stop offset="1" stop-color="#101c35"/></linearGradient></defs><g transform="translate(90 12) rotate(9 90 90)"><rect width="125" height="194" rx="17" fill="#101828" stroke="#7290b5" stroke-width="2"/><rect x="8" y="8" width="109" height="178" rx="11" fill="url(#screen-${esc(c.id)})"/><rect x="39" y="10" width="48" height="7" rx="4" fill="#0c1424"/><rect x="22" y="42" width="80" height="55" rx="5" fill="#ff8337"/><path d="m53 55 23 14-23 15z" fill="#fff"/><path d="M24 115h76M24 127h55M24 154h76" stroke="#aabbd2" stroke-width="5"/><circle cx="62" cy="175" r="3" fill="#7b8da5"/></g><circle cx="242" cy="140" r="31" fill="#ffad73" fill-opacity=".15" stroke="#ffad73"/><path d="m228 140 10 10 19-23" fill="none" stroke="#ffad73" stroke-width="3"/></svg>`;
    if (kind === "documents")
      return `<svg viewBox="0 0 360 220" aria-hidden="true"><g transform="translate(115 20) rotate(-13 70 90)"><rect x="-17" y="12" width="130" height="170" rx="8" fill="#172b49" stroke="#4a6689"/><rect width="130" height="170" rx="8" fill="#233b5c" stroke="#8095ae"/><rect x="20" y="23" width="37" height="37" rx="6" fill="#ff8d4b"/><path d="m28 41 8 8 14-19" stroke="#fff" stroke-width="3" fill="none"/><path d="M20 85h88M20 103h72M20 121h88M20 139h55" stroke="#a2b0c4" stroke-width="5"/></g></svg>`;
    return `<svg viewBox="0 0 360 220" aria-hidden="true"><defs><radialGradient id="orb-${esc(c.id)}"><stop stop-color="#f5b185"/><stop offset=".55" stop-color="#b76939"/><stop offset="1" stop-color="#172942"/></radialGradient></defs><g transform="translate(180 110)"><ellipse rx="120" ry="45" fill="none" stroke="#6d809e" stroke-width="1" transform="rotate(-25)"/><ellipse rx="95" ry="95" fill="none" stroke="#536781" stroke-width="1"/><circle r="64" fill="url(#orb-${esc(c.id)})"/><ellipse rx="120" ry="45" fill="none" stroke="#fba469" stroke-width="2" stroke-dasharray="180 370" transform="rotate(-25)"/><path d="m-20 0 16 16L27-19" stroke="#fff" stroke-width="4" fill="none"/><circle cx="104" cy="-49" r="6" fill="#ffd2b1"/></g></svg>`;
  };
  function cover(c, orientation = "portrait") {
    const chosen =
      orientation === "landscape"
        ? c.landscapeImage || c.coverImage
        : c.coverImage;
    const custom = /^data:image\/(png|jpeg|webp);base64,/.test(chosen || "")
      ? chosen
      : "";
    const photo = c.id === "primeiros-passos";
    return `<div class="academy-cover ${orientation} academy-tone-${esc(c.tone)} art-${esc(c.icon)} ${photo ? "with-photo" : ""} ${custom ? "custom-cover" : ""}">${custom ? `<img src="${esc(custom)}" alt="Capa de ${esc(c.title)}">` : `<span class="cover-grid-lines"></span><span class="academy-cover-brand">${esc(b.name)}<small>CONHECIMENTO QUE APROXIMA</small></span><div class="academy-art">${photo ? '<span class="course-photo"></span>' : svgArt(c)}</div><div class="academy-cover-title"><i></i><small>${esc(c.category)}</small><h3>${esc(c.title)}</h3><span>UMA JORNADA COM A SUA CONTABILIDADE</span></div>`}<span class="cover-access" aria-label="Acesso liberado">${icon("shield")}</span><span class="cover-hover-play">${icon("play")}</span></div>`;
  }
  function card(c, orientation = "portrait", direct = false) {
    const p = progress(c);
    return `<article class="academy-course"><a class="academy-cover-link" href="${direct ? continued(c) : courseHref(c)}" aria-label="${esc(c.title)}">${cover(c, orientation)}</a><div class="academy-card-progress"><span>${p}%</span>${bar(p, c.title)}</div><div class="academy-card-caption"><a href="${courseHref(c)}">${esc(c.title)}</a><span>${allLessons(c).length} aulas</span></div></article>`;
  }
  function filters() {
    return `<div class="academy-filters" role="group" aria-label="Filtrar cursos">${[
      ["all", "Todos os cursos"],
      ["started", "Em andamento"],
      ["new", "Não iniciados"],
      ["done", "Concluídos"],
    ]
      .map(
        ([id, label]) =>
          `<button data-action="filter-course" data-id="${id}" aria-pressed="${courseFilter === id}" class="${courseFilter === id ? "selected" : ""}">${label}</button>`,
      )
      .join("")}</div>`;
  }
  function matching(query) {
    const q = norm(query.trim());
    return activeCourses()
      .filter(
        (c) =>
          !q ||
          norm(
            c.title +
              " " +
              c.category +
              " " +
              allLessons(c)
                .map((l) => l.title)
                .join(" "),
          ).includes(q),
      )
      .filter(
        (c) =>
          courseFilter === "all" ||
          (courseFilter === "started" &&
            progress(c) > 0 &&
            progress(c) < 100) ||
          (courseFilter === "new" && !progress(c)) ||
          (courseFilter === "done" && progress(c) === 100),
      );
  }
  function results(query = "") {
    const courses = matching(query),
      q = query.trim();
    const lessonMatches = q
      ? courses.flatMap((c) =>
          allLessons(c)
            .filter((l) => norm(l.title).includes(norm(q)))
            .map((l) => ({ c, l })),
        )
      : [];
    return `<div class="academy-section-title"><h2>${q ? "Resultados para “" + esc(q) + "”" : esc(text("catalogTitle"))}</h2><span>${courses.length} ${courses.length === 1 ? "curso" : "cursos"}</span></div><div class="academy-poster-grid">${courses.map((c) => card(c)).join("") || '<div class="academy-empty"><h3>Nenhum curso encontrado.</h3><p>Experimente outro nome ou filtro.</p></div>'}</div>${
      lessonMatches.length
        ? `<section class="academy-lesson-results"><h2>Aulas encontradas</h2>${lessonMatches
            .slice(0, 12)
            .map(
              ({ c, l }) =>
                `<a href="#/aula/${l.id}">${icon("play")}<span><strong>${esc(l.title)}</strong><small>${esc(c.title)}</small></span>${icon("arrow")}</a>`,
            )
            .join("")}</section>`
        : ""
    }`;
  }
  function globalResults(query) {
    if (!query.trim()) return "";
    const q = norm(query.trim());
    const courses = matching(query)
      .slice(0, 3)
      .map(
        (c) =>
          `<a href="${courseHref(c)}">${icon("book")}<span>${esc(c.title)}<small>Curso • ${allLessons(c).length} aulas</small></span>${icon("chevron")}</a>`,
      )
      .join("");
    const lessons = activeCourses()
      .flatMap((c) =>
        allLessons(c)
          .filter((l) => norm(l.title).includes(q))
          .map((l) => ({ c, l })),
      )
      .slice(0, 4)
      .map(
        ({ c, l }) =>
          `<a href="#/aula/${l.id}">${icon("play")}<span>${esc(l.title)}<small>Aula • ${esc(c.title)}</small></span>${icon("chevron")}</a>`,
      )
      .join("");
    return (
      courses + lessons || "<p>Nenhum resultado. Experimente outro termo.</p>"
    );
  }
  function hero() {
    return `<section class="academy-hero"><div class="academy-hero-rings" aria-hidden="true"><i></i><i></i><i></i><i></i></div><div class="academy-hero-content"><span class="academy-hero-eyebrow">${esc(text("heroEyebrow"))}</span><h1 tabindex="-1">${esc(text("heroTitle")).replace(/\n/g, "<br> ")}</h1><p>${esc(text("heroBody"))}</p><span class="academy-hero-signature">${esc(b.name)}<small>CONHECIMENTO • PARCERIA • CONFIANÇA</small></span></div></section>`;
  }
  function home() {
    const recent = activeCourses().filter((c) => progress(c) > 0);
    const sections = {
      hero: hero(),
      continue: recent.length
        ? `<section class="academy-section academy-continue"><div class="academy-section-title"><h2>${esc(text("continueTitle"))}</h2><a href="#/progresso">Meu progresso ${icon("arrow")}</a></div><div class="academy-landscape-row">${recent.map((c) => card(c, "landscape", true)).join("")}</div></section>`
        : "",
      courses: `<section class="academy-section academy-catalog">${filters()}<div id="academy-results">${results(search)}</div></section>`,
      notice: `<section class="academy-notice"><span>${icon("message")}</span><div><h3>${esc(state.notices[0]?.title || text("supportTitle"))}</h3><p>${esc(state.notices[0]?.body || text("supportBody"))}</p></div>${contact("btn academy-outline-btn")}</section>`,
    };
    return state.settings.blocks
      .filter((v) => v.enabled)
      .map((v) => sections[v.id] || "")
      .join("");
  }
  function courses() {
    return `<div class="academy-catalog-intro"><span class="eyebrow">SEU ESPAÇO DE CONHECIMENTO</span><h1 tabindex="-1">${esc(text("catalogTitle"))}</h1><p>Escolha um curso e siga no seu ritmo.</p></div><section class="academy-section academy-catalog">${filters()}<div id="academy-results">${results(search)}</div></section>`;
  }
  function drawer() {
    return `<button class="academy-drawer-overlay ${menuOpen ? "open" : ""}" data-action="menu" aria-label="Fechar menu" ${menuOpen ? "" : "hidden"}></button><aside class="academy-drawer ${menuOpen ? "open" : ""}" aria-label="Navegação do portal" ${menuOpen ? "" : 'inert aria-hidden="true"'}><div class="academy-drawer-head">${logo()}<button class="icon-btn" data-action="menu" aria-label="Fechar navegação">${icon("close")}</button></div><span class="eyebrow">${esc(b.portal)}</span><nav>${[
      ["inicio", "home", "navHome"],
      ["cursos", "book", "navCourses"],
      ["materiais", "folder", "navMaterials"],
      ["progresso", "chart", "navProgress"],
      ["ajuda", "help", "navHelp"],
    ]
      .map(
        ([id, ic, key]) => `<a href="#/${id}">${icon(ic)}${esc(text(key))}</a>`,
      )
      .join(
        "",
      )}<a href="#/salvos">${icon("book")}Aulas salvas</a></nav><div class="academy-drawer-bottom"><a href="#/perfil">${icon("user")}Minha conta</a><a href="#/login">${icon("logout")}Sair da prévia</a></div></aside>`;
  }
  function shell(content, section) {
    const theater = route()[0] === "aula",
      catalog = ["inicio", "cursos"].includes(route()[0] || "inicio"),
      user = window.__currentUser || (() => { try { return JSON.parse(localStorage.getItem("onboarding-user-v2") || "null"); } catch { return null; } })(),
      displayName = user?.name || "Cliente",
      adminLink = ["admin", "office_admin", "agency_admin"].includes(user?.role) ? `<a href="#/admin">${icon("settings")} Painel administrativo ${icon("arrow")}</a>` : "";
    return `<div class="demo-bar"><span><i></i> Prévia visual <span class="demo-detail">· Dados ilustrativos, salvos neste navegador</span></span>${adminLink}</div><div class="academy-shell ${theater ? "academy-theater" : ""} ${outlineOpen ? "outline-open" : "outline-closed"} ${catalog ? "catalog-page" : ""} ${catalog && search.trim() ? "searching" : ""}"><header class="academy-topbar"><div class="academy-topbar-brand"><button class="academy-menu-trigger icon-btn" data-action="menu" aria-label="Abrir menu" aria-expanded="${menuOpen}">${icon("menu")}</button><a href="#/inicio" aria-label="Ir para o início">${logo()}</a></div>${!theater ? `<div class="academy-search-wrap"><label class="academy-search">${icon("search")}<input id="academy-search" type="search" value="${esc(search)}" placeholder="${esc(text("catalogSearch"))}" aria-label="${esc(text("catalogSearch"))}" autocomplete="off"></label><div class="academy-search-popover" id="academy-search-popover" hidden></div></div>` : ""}<a href="#/perfil" class="academy-profile">${avatar(displayName)}<span>${esc(displayName)}</span></a></header>${drawer()}<main id="main-content" tabindex="-1" class="academy-main ${!catalog && !theater ? "academy-interior" : ""}">${content}</main><footer class="academy-footer">${esc(b.name)} © 2026 <a href="#/privacidade">Privacidade e termos</a><small>Prévia de interface com dados ilustrativos</small></footer></div>`;
  }
  function course(id) {
    const c = activeCourses().find((c) => c.id === id);
    if (!c)
      return '<div class="academy-empty"><h1>Curso não encontrado.</h1></div>';
    return `<section class="academy-course-detail"><a class="back-link" href="#/inicio">${icon("left")} Voltar para os cursos</a><div class="academy-detail-grid"><div><span class="eyebrow">${esc(c.category)}</span><h1 tabindex="-1">${esc(c.title)}</h1><p>${esc(c.description)}</p><div class="academy-detail-meta"><span>${icon("layers")} ${c.modules.length} módulos</span><span>${icon("play")} ${allLessons(c).length} aulas</span><span>${icon("shield")} Acesso liberado</span></div>${allLessons(c).length ? `<a class="btn btn-primary" href="${continued(c)}">${progress(c) ? "Continuar curso" : "Começar curso"} ${icon("play")}</a>` : '<button class="btn btn-primary" disabled>Conteúdo em preparação</button>'}<div class="academy-detail-progress"><span>${progress(c)}% concluído</span>${bar(progress(c))}</div></div><div class="academy-detail-poster">${cover(c)}</div></div><section class="academy-curriculum"><h2>O que você vai encontrar</h2>${outline(c, "")}</section></section>`;
  }
  function outline(c, current, query = "") {
    const q = norm(query.trim());
    const modules = c.modules
      .map((m, i) => ({
        m,
        i,
        ls: m.lessons.filter((l) => !q || norm(l.title).includes(q)),
      }))
      .filter((v) => !q || v.ls.length);
    return (
      modules
        .map(
          ({ m, i, ls }) =>
            `<details class="academy-module" ${q || !current || m.lessons.some((l) => l.id === current) ? "open" : ""}><summary><span><strong>${esc(m.title)}</strong><small>${m.lessons.filter((l) => state.completed.includes(l.id)).length}/${m.lessons.length} aulas concluídas</small></span>${icon("down")}</summary><div>${ls
              .map((l) => {
                const done = state.completed.includes(l.id),
                  open = lessonUnlocked(c, l);
                return `<a class="academy-lesson-row ${current === l.id ? "current" : ""}" href="#/aula/${l.id}" ${open ? "" : 'data-action="locked"'}><span class="academy-lesson-mark ${done ? "done" : ""}">${icon(done ? "check" : open ? "play" : "lock")}</span><span><strong>${esc(l.title)}</strong><small>${l.duration} min</small></span>${current === l.id ? "<i></i>" : ""}</a>`;
              })
              .join("")}</div></details>`,
        )
        .join("") ||
      '<p class="academy-no-lessons">Nenhuma aula encontrada neste curso.</p>'
    );
  }
  function lessonPanel(c, l) {
    return `<aside class="academy-syllabus" aria-label="Conteúdo do curso"><button class="icon-btn syllabus-close" data-action="toggle-outline" aria-label="Fechar conteúdo do curso">${icon("close")}</button><a href="${courseHref(c)}" class="syllabus-cover">${cover(c, "landscape")}</a><div class="syllabus-body"><h2>${esc(c.title)}</h2><div class="syllabus-progress"><div class="progress-segments" role="progressbar" aria-label="Progresso do curso" aria-valuenow="${progress(c)}" aria-valuemin="0" aria-valuemax="100">${allLessons(
      c,
    )
      .map(
        (v) =>
          `<span class="${state.completed.includes(v.id) ? "complete" : ""}"></span>`,
      )
      .join(
        "",
      )}</div><span>${progress(c)}%</span></div><label class="syllabus-search"><input id="lesson-search" placeholder="Pesquisar aula" aria-label="Pesquisar aula">${icon("search")}</label><div id="syllabus-modules">${outline(c, l.id)}</div></div></aside>`;
  }
  function tabContent(c, l) {
    if (lessonTab === "materials")
      return `<h2>Materiais desta aula</h2>${
        (l.materials || []).map((id) => state.files.find((v) => String(v.id) === String(id))).filter(Boolean)
          .map(
            (v) =>
              `<div class="material-row"><span class="file-icon">${icon("file")}</span><div><strong>${esc(v.name)}</strong><small>${esc(v.type || "Arquivo")} • Material de apoio</small></div><a class="icon-btn" href="media.php?id=${encodeURIComponent(v.id)}&lesson=${encodeURIComponent(l.id)}" target="_blank" rel="noopener" aria-label="Baixar ${esc(v.name)}">${icon("download")}</a></div>`,
          )
          .join("") || "<p>Nenhum material disponível para esta aula.</p>"
      }`;
    if (lessonTab === "notes")
      return `<label for="lesson-notes">Suas anotações</label><textarea id="lesson-notes" data-lesson-id="${l.id}" rows="6" maxlength="12000" placeholder="Registre os pontos importantes desta aula…">${esc(state.notes[l.id] || "")}</textarea><div class="academy-notes-footer"><span id="notes-status" role="status">Anotações salvas apenas neste navegador.</span><button class="btn btn-primary" data-action="save-note" data-id="${l.id}">Salvar anotação ${icon("check")}</button></div>`;
    return `<h2>Sobre esta aula</h2>${l.body ? `<div class="lesson-description">${esc(l.body).replace(/\n/g,"<br>")}</div>` : "<p>Esta aula ainda não possui uma descrição cadastrada.</p>"}`;
  }
  function lesson(id) {
    const info = lessonInfo(id);
    if (!info || info.c.status !== "published")
      return '<div class="academy-empty"><h1>Aula não encontrada.</h1></div>';
    const { c, m, l } = info;
    if (!lessonUnlocked(c, l))
      return `<div class="academy-empty"><h1>Uma etapa de cada vez.</h1><p>Conclua as aulas anteriores para acessar este conteúdo.</p><a class="btn btn-primary" href="${courseHref(c)}">Ver curso</a></div>`;
    state.lastLesson = id;
    persist();
    const ls = allLessons(c),
      idx = ls.findIndex((v) => v.id === id),
      done = state.completed.includes(id),
      video = mediaUrls.get(id) || (l.media_id ? `media.php?id=${encodeURIComponent(l.media_id)}&lesson=${encodeURIComponent(l.id)}` : ""),
      adminPreview = (() => { try { return ["admin", "office_admin", "agency_admin"].includes(JSON.parse(localStorage.getItem("onboarding-user-v2") || "null")?.role); } catch { return false; } })(),
      saved = state.saved.includes(id),
      rating = state.ratings[id] || 0;
    return `<section class="academy-classroom"><a class="back-link" href="#/inicio">${icon("left")} Voltar para os cursos</a><div class="classroom-title"><div><span class="eyebrow">${esc(m.title)} • Aula ${idx + 1}</span><h1 tabindex="-1">${esc(l.title)}</h1></div><button class="icon-btn syllabus-toggle" data-action="toggle-outline" aria-expanded="${outlineOpen}" aria-label="${outlineOpen ? "Ocultar" : "Mostrar"} conteúdo do curso">${icon("book")}</button></div><div class="academy-video">${video ? `<video id="lesson-video" data-lesson-id="${l.id}" src="${esc(video)}" controls playsinline preload="metadata" aria-label="Vídeo selecionado para prévia"></video>` : `<div class="academy-video-placeholder"><span class="academy-player-brand">${esc(b.name)}</span><div><span class="eyebrow">${esc(c.title)}</span><h2>${esc(l.title)}</h2><button class="academy-play-button" data-action="preview-video" data-id="${l.id}" aria-label="Selecionar vídeo para prévia">${icon("play")}</button><small>Selecionar vídeo local para testar o player</small></div><div class="academy-player-bottom">${icon("play")} <span>00:00 / ${esc(l.duration)}</span><i></i>${icon("settings")} ${icon("grid")}</div></div>`}</div><div class="classroom-toolbar"><div class="classroom-rating" role="group" aria-label="Avaliar aula"><span>Avaliar aula</span>${[1, 2, 3, 4, 5].map((n) => `<button aria-label="Avaliar com ${n} ${n === 1 ? "estrela" : "estrelas"}" aria-pressed="${rating === n}" data-action="rate-lesson" data-id="${id}" data-rating="${n}" class="${rating >= n ? "selected" : ""}">★</button>`).join("")}</div><div class="classroom-actions"><button class="btn academy-outline-btn ${saved ? "saved" : ""}" data-action="save-lesson" data-id="${id}" aria-pressed="${saved}">${icon("book")} ${saved ? "Aula salva" : "Salvar aula"}</button><button class="btn academy-outline-btn ${done ? "completed" : ""}" data-action="complete" data-id="${id}">${icon("check")} ${done ? "Aula concluída" : "Concluir aula"}</button>${idx ? `<a class="icon-btn" aria-label="Aula anterior" href="#/aula/${ls[idx - 1].id}">${icon("left")}</a>` : '<button class="icon-btn" disabled aria-label="Aula anterior">' + icon("left") + "</button>"}${idx < ls.length - 1 ? `<a class="icon-btn" aria-label="Próxima aula" href="#/aula/${ls[idx + 1].id}" ${lessonUnlocked(c, ls[idx + 1]) ? "" : 'data-action="locked"'}>${icon("arrow")}</a>` : `<a class="icon-btn" aria-label="Voltar ao curso" href="${courseHref(c)}">${icon("check")}</a>`}</div></div><div class="classroom-tabs" role="tablist" aria-label="Informações da aula">${[
      ["description", "Descrição", "file"],
      ["materials", "Materiais", "download"],
      ["notes", "Anotações", "edit"],
    ]
      .map(
        ([key, label, ic]) =>
          `<button id="tab-${key}" role="tab" aria-selected="${lessonTab === key}" aria-controls="lesson-tabpanel" tabindex="${lessonTab === key ? "0" : "-1"}" data-action="lesson-tab" data-id="${key}" class="${lessonTab === key ? "selected" : ""}">${icon(ic)}${label}</button>`,
      )
      .join(
        "",
      )}</div><section class="classroom-tabpanel" id="lesson-tabpanel" role="tabpanel" aria-labelledby="tab-${lessonTab}">${tabContent(c, l)}</section><div class="classroom-course-credit">${icon("compass")}<span>${esc(b.name)}<small>${esc(c.title)}</small></span></div></section>${outlineOpen ? lessonPanel(c, l) : ""}`;
  }
  function saved() {
    const courses = activeCourses();
    const lessons = courses.flatMap((c) =>
      allLessons(c)
        .filter((l) => state.saved.includes(l.id))
        .map((l) => ({ c, l })),
    );
    return `<section class="academy-saved"><span class="eyebrow">PARA REVER NO SEU RITMO</span><h1 tabindex="-1">Aulas salvas</h1><div class="academy-lesson-results">${lessons.map(({ c, l }) => `<a href="#/aula/${l.id}">${icon("play")}<span><strong>${esc(l.title)}</strong><small>${esc(c.title)}</small></span>${icon("arrow")}</a>`).join("") || '<div class="academy-empty"><h3>Sua lista começa na próxima aula.</h3><p>Use “Salvar aula” para encontrar os conteúdos aqui.</p></div>'}</div></section>`;
  }
  return {
    shell,
    home,
    courses,
    results,
    globalResults,
    course,
    lesson,
    outline,
    saved,
    cover,
  };
}
