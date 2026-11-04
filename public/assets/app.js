import {
  defaults,
  seedCourses,
  seedClients,
  materials,
  faqs,
  icons,
} from "./data.js";
import { createAcademy } from "./academy.js";

const KEY = "morais-visual-v1";
const AUTH_KEY = "onboarding-user-v2";
const fresh = () => ({
  version: 1,
  catalogRevision: 2,
  settings: structuredClone(defaults),
  courses: structuredClone(seedCourses),
  clients: structuredClone(seedClients),
  completed: ["l1", "l2", "l3", "l4", "t1", "t2", "t3", "t4"],
  lastLesson: "l5",
  positions: {},
  notes: {},
  saved: [],
  ratings: {},
  files: [],
  notices: [
    {
      title: "Seu onboarding, no seu ritmo",
      body: "Reserve alguns minutos do seu dia para conhecer melhor nossos processos. Estamos com você em cada etapa.",
    },
  ],
});
let state;
try {
  state = JSON.parse(localStorage.getItem(KEY));
  if (
    !state ||
    state.version !== 1 ||
    !Array.isArray(state.courses) ||
    !state.settings?.brand
  )
    state = fresh();
} catch {
  state = fresh();
}
// Atualiza somente os exemplos adicionais; preserva edições e progresso existentes.
if (!state.catalogRevision) {
  for (const c of seedCourses.slice(3))
    if (!state.courses.some((v) => v.id === c.id))
      state.courses.push(structuredClone(c));
  state.catalogRevision = 2;
}
state.notes ??= {};
state.saved ??= [];
state.ratings ??= {};
let courseFilter = "all",
  search = "",
  clientFilter = "all",
  settingsTab = "brand",
  menuOpen = false,
  outlineOpen = true,
  lessonTab = "description",
  modalHandler = null,
  mediaUrls = new Map();
const $ = (s, root = document) => root.querySelector(s);
const app = $("#app"),
  modal = $("#modal");
const esc = (v) =>
  String(v ?? "").replace(
    /[&<>"']/g,
    (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        c
      ],
  );
const icon = (name, cls = "") =>
  `<svg class="icon ${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${icons[name] || icons.file}</svg>`;
const text = (key) => state.settings.texts[key] ?? defaults.texts[key] ?? "";
const brand = () => state.settings.brand;
const allLessons = (c) => c.modules.flatMap((m) => m.lessons);
const progress = (c) => {
  const lessons = allLessons(c);
  return lessons.length
    ? Math.round(
        (lessons.filter((l) => state.completed.includes(l.id)).length /
          lessons.length) *
          100,
      )
    : 0;
};
const activeCourses = () =>
  state.courses.filter((c) => c.status === "published");
const completedCount = () =>
  activeCourses()
    .flatMap(allLessons)
    .filter((l) => state.completed.includes(l.id)).length;
const lessonInfo = (id) => {
  for (const c of state.courses)
    for (const m of c.modules) {
      const l = m.lessons.find((l) => l.id === id);
      if (l) return { c, m, l };
    }
  return null;
};
const nextLesson = (c) =>
  allLessons(c).find((l) => !state.completed.includes(l.id)) ||
  allLessons(c)[0];
const route = () =>
  (location.hash.slice(1) || "/inicio").split("/").filter(Boolean);
const uid = (prefix) => prefix + "-" + crypto.randomUUID();
const isAdmin = () => route()[0] === "admin";
const pct = () => {
  const ls = activeCourses().flatMap(allLessons);
  return ls.length
    ? Math.round(
        (ls.filter((l) => state.completed.includes(l.id)).length / ls.length) *
          100,
      )
    : 0;
};
const persist = () => {
  try {
    localStorage.setItem(KEY, JSON.stringify(state));
    return true;
  } catch {
    toast(
      "A memória do navegador está indisponível ou cheia. As alterações continuam nesta sessão.",
    );
    return false;
  }
};
let apiCsrf = "";
async function apiRequest(action, options = {}) {
  try {
    if (!apiCsrf) {
      const token = await fetch("api.php?action=csrf", { credentials: "same-origin" });
      if (!token.ok) return null;
      apiCsrf = (await token.json()).csrf || "";
    }
    const method = options.method || "GET";
    const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, {
      ...options,
      method,
      credentials: "same-origin",
      headers: { ...(options.body instanceof FormData ? {} : { "Content-Type": "application/json" }), ...(method !== "GET" ? { "X-CSRF-Token": apiCsrf } : {}), ...(options.headers || {}) },
    });
    const data = await response.json().catch(() => null);
    if (response.status === 419) { apiCsrf = ""; return null; }
    return response.ok ? data : null;
  } catch { return null; }
}
let toastTimer;
function toast(message) {
  $("#toast").textContent = message;
  $("#toast").classList.add("visible");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => $("#toast").classList.remove("visible"), 5000);
}
async function loadBackend() {
  const data = await apiRequest("bootstrap");
  if (!data) return;
  if (data.settings) {
    for (const [key, value] of Object.entries(data.settings)) {
      const [group, name] = key.split(".", 2);
      if (group === "brand" && name) state.settings.brand[name] = value;
      if (group === "texts" && name) state.settings.texts[name] = value;
    }
  }
  if (Array.isArray(data.courses)) {
    state.courses = data.courses.map((course) => ({ ...course, id: String(course.id), sequence: Boolean(Number(course.sequence_required)), modules: (course.modules || []).map((module) => ({ ...module, id: String(module.id), lessons: (module.lessons || []).map((lesson) => ({ ...lesson, id: String(lesson.id), duration: secondsToDuration(lesson.duration_seconds) })) })) }));
  }
  if (Array.isArray(data.media)) {
    state.files = data.media.map((file) => ({ id: String(file.id), name: file.original_name, size: Number(file.file_size || 0), type: file.mime_type, status: file.status }));
  }
  if (Array.isArray(data.clients)) {
    state.clients = data.clients.map((client) => ({ ...client, id: String(client.id), group: "Clientes", courses: [], progress: 0, last: client.last_access_at ? new Date(`${client.last_access_at.replace(" ", "T")}Z`).toLocaleString("pt-BR") : "Sem atividade", expires: client.expires_at ? client.expires_at.slice(0, 10) : "" }));
  }
  persist();
  render();
}
const secondsToDuration = (seconds) => `${String(Math.floor(Number(seconds || 0) / 60)).padStart(2, "0")}:${String(Number(seconds || 0) % 60).padStart(2, "0")}`;
const link = (url, label, cls = "btn") =>
  `<a class="${cls}" href="#${url}">${label}</a>`;
const badge = (label, kind = "neutral") =>
  `<span class="badge ${kind}">${esc(label)}</span>`;
const bar = (p, label = "Progresso") =>
  `<div class="progress-track" role="progressbar" aria-label="${esc(label)}" aria-valuenow="${p}" aria-valuemin="0" aria-valuemax="100"><span style="width:${p}%"></span></div>`;
const empty = (title, body, action = "") =>
  `<div class="empty">${icon("search")}<h3>${esc(title)}</h3><p>${esc(body)}</p>${action}</div>`;
const heading = (eyebrow, title, desc, action = "") =>
  `<div class="page-heading"><div><p class="eyebrow">${esc(eyebrow)}</p><h1 tabindex="-1">${esc(title)}</h1><p class="muted">${esc(desc)}</p></div>${action}</div>`;
const avatar = (name, cls = "") =>
  `<span class="avatar ${cls}">${esc(
    name
      .split(" ")
      .map((n) => n[0])
      .slice(0, 2)
      .join(""),
  )}</span>`;
function applyBrand() {
  const b = brand();
  document.documentElement.style.setProperty(
    "--accent",
    /^#[0-9a-f]{6}$/i.test(b.primary) ? b.primary : defaults.brand.primary,
  );
  document.documentElement.style.setProperty(
    "--navy",
    /^#[0-9a-f]{6}$/i.test(b.navy) ? b.navy : defaults.brand.navy,
  );
  document.documentElement.style.setProperty(
    "--font",
    b.font === "Arial" ? "Arial, sans-serif" : "Manrope, sans-serif",
  );
  const favicon = document.querySelector('link[rel="icon"]');
  if (favicon && /^(assets\/|data:image\/)/.test(b.favicon || "")) favicon.href = b.favicon;
}
function logo() {
  const src = /^(assets\/|data:image\/(png|jpeg|webp);base64,)/.test(
    brand().logo,
  )
    ? brand().logo
    : defaults.brand.logo;
  return `<img src="${esc(src)}" alt="${esc(brand().name)}" class="brand-logo">`;
}
const contact = (cls = "btn btn-support") =>
  `<a class="${cls}" href="https://wa.me/${esc(brand().whatsapp.replace(/\D/g, ""))}" target="_blank" rel="noopener">${icon("message")} Falar com a equipe ${icon("arrow")}</a>`;
const academy = () =>
  createAcademy({
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
  });
function shell(content, section = "Visão geral") {
  const admin = isAdmin(),
    [root, sub] = route();
  if (!admin) return academy().shell(content, section);
  const hiddenMobileMenu = matchMedia("(max-width:760px)").matches && !menuOpen;
  const nav = admin
    ? [
        ["", "grid", "Visão geral"],
        ["clientes", "users", "Clientes"],
        ["conteudos", "book", "Conteúdos"],
        ["grupos", "layers", "Grupos de clientes"],
        ["midia", "folder", "Biblioteca de mídia"],
        ["comunicacao", "message", "Comunicação"],
        ["relatorios", "chart", "Relatórios"],
        ["personalizacao", "settings", "Personalização"],
        ["operacao", "shield", "Configurações"],
      ]
    : [
        ["inicio", "home", text("navHome")],
        ["cursos", "book", text("navCourses")],
        ["materiais", "folder", text("navMaterials")],
        ["progresso", "chart", text("navProgress")],
        ["ajuda", "help", text("navHelp")],
      ];
  return `<div class="demo-bar"><span><i></i> Prévia visual <span class="demo-detail">· Dados ilustrativos, salvos neste navegador</span></span><a href="#/${admin ? "inicio" : "admin"}">${icon(admin ? "eye" : "settings")} ${admin ? "Ver área do cliente" : "Conhecer painel admin"} ${icon("arrow")}</a></div>
  <div class="app-shell ${admin ? "admin-shell" : ""}"><button class="sidebar-overlay ${menuOpen ? "show" : ""}" data-action="menu" aria-label="Fechar menu"></button>
  <aside class="sidebar ${menuOpen ? "open" : ""}" aria-label="Navegação principal" ${hiddenMobileMenu ? 'inert aria-hidden="true"' : ""}><a href="#/${admin ? "admin" : "inicio"}" class="brand">${logo()}<span>${esc(admin ? "Painel administrativo" : brand().portal)}</span></a><div class="sidebar-label">${admin ? "GERENCIAMENTO" : "SEU ESPAÇO"}</div><nav>${nav
    .map(([id, ic, label]) => {
      const selected = admin ? (sub || "") === id : root === id;
      return `<a href="#/${admin ? "admin" + (id ? "/" + id : "") : id}" class="nav-link ${selected ? "active" : ""}" ${selected ? 'aria-current="page"' : ""}>${icon(ic)}<span>${esc(label)}</span>${id === "cursos" ? `<span class="nav-count">${activeCourses().length}</span>` : ""}</a>`;
    })
    .join("")}</nav>
  ${admin ? `<div class="sidebar-note">${icon("shield")}<strong>Sua marca. Seu portal.</strong><p>Uma experiência exclusiva para os clientes do seu escritório.</p></div>` : `<div class="sidebar-help"><span class="help-symbol">${icon("message")}</span><strong>Estamos com você.</strong><p>Precisa de uma orientação?</p>${contact("text-link")}</div>`}
  <div class="sidebar-bottom"><a href="#/perfil" class="nav-link">${icon("user")}Minha conta</a><a href="#/login" data-action="logout" class="nav-link">${icon("logout")}Sair</a><small>© 2026 ${esc(brand().name)}</small></div></aside>
  <div class="workspace"><header class="topbar"><button class="icon-btn mobile-menu" data-action="menu" aria-label="Abrir menu" aria-expanded="${menuOpen}">${icon("menu")}</button><div class="breadcrumbs">${admin ? "Administração" : "Área do cliente"} ${icon("chevron")} <span>${esc(section)}</span></div><div class="topbar-actions"><span class="secure-label">${icon("shield")} Acesso exclusivo</span><button class="icon-btn notification" data-action="notifications" aria-label="Ver comunicados">${icon("bell")}<i></i></button><a class="user-control" href="#/perfil">${avatar(admin ? "Equipe Morais" : "Mariana Oliveira")}<span><strong>${admin ? "Equipe Morais" : "Mariana Oliveira"}</strong><small>${admin ? "Administrador" : "Empresa Exemplo"}</small></span>${icon("down")}</a></div></header>
  <main id="main-content" tabindex="-1" class="main-content">${content}</main><footer class="footer"><span>Conhecimento e proximidade em cada etapa.</span><a href="#/privacidade">Privacidade e termos ${icon("shield")}</a></footer></div></div>`;
}
function courseCover(c, small = false) {
  return `<div class="course-cover tone-${esc(c.tone)} ${small ? "small" : ""}"><span class="cover-label">${esc(brand().name.toUpperCase())} • CONHECIMENTO</span><span class="cover-orbit"></span><span class="cover-icon">${icon(c.icon)}</span><span class="cover-bottom">${esc(c.category)} ${icon("arrow")}</span></div>`;
}
function courseCard(c) {
  const p = progress(c),
    count = allLessons(c).length;
  return `<article class="course-card">${link("/curso/" + c.id, courseCover(c), "cover-link")}<div class="course-card-body"><div class="course-meta">${badge(p === 100 ? "Concluída" : p ? "Em andamento" : "Não iniciada", p === 100 ? "success" : p ? "orange" : "neutral")}<span>${c.modules.length} ${c.modules.length === 1 ? "módulo" : "módulos"}</span></div><h3><a href="#/curso/${esc(c.id)}">${esc(c.title)}</a></h3><p>${esc(c.description)}</p><div class="course-progress"><span>${state.completed.filter((id) => allLessons(c).some((l) => l.id === id)).length} de ${count} aulas concluídas</span><strong>${p}%</strong></div>${bar(p, c.title)}<a class="course-cta" href="#/curso/${esc(c.id)}">${p === 100 ? "Revisar trilha" : p ? "Continuar trilha" : "Começar trilha"} ${icon("arrow")}</a></div></article>`;
}
function dashboard() {
  return academy().home();
}
function coursesPage() {
  return academy().courses();
}
function filteredCourseCards() {
  return academy().results(search);
}
function lessonUnlocked(c, l) {
  if (!c.sequence) return true;
  const ls = allLessons(c);
  return ls
    .slice(
      0,
      ls.findIndex((v) => v.id === l.id),
    )
    .every((v) => state.completed.includes(v.id));
}
function outline(c, current = "", editor = false) {
  return c.modules
    .map(
      (m, i) =>
        `<details class="module" ${!current || m.lessons.some((l) => l.id === current) ? "open" : ""}><summary><span class="module-number">${String(i + 1).padStart(2, "0")}</span><div><strong>${esc(m.title)}</strong><small>${m.lessons.filter((l) => state.completed.includes(l.id)).length} de ${m.lessons.length} aulas concluídas</small></div>${icon("down")}</summary><div class="module-lessons">${
          m.lessons
            .map((l) => {
              const done = state.completed.includes(l.id),
                open = lessonUnlocked(c, l);
              return `<div class="lesson-row ${l.id === current ? "current" : ""}"><a href="#/aula/${esc(l.id)}" ${!open ? 'data-action="locked"' : ""}><span class="lesson-state ${done ? "done" : ""}">${icon(done ? "check" : open ? "play" : "lock")}</span><span>${esc(l.title)}<small>${l.kind === "video" ? "Videoaula" : "Conteúdo"} • ${l.duration} min</small></span>${done ? badge("Concluída", "success") : icon("chevron")}</a>${editor ? `<button class="icon-btn" data-action="edit-lesson" data-id="${l.id}" aria-label="Editar ${esc(l.title)}">${icon("edit")}</button>` : ""}</div>`;
            })
            .join("") || '<p class="pad muted">Nenhuma aula cadastrada.</p>'
        }${editor ? `<button class="btn btn-secondary add-lesson" data-action="add-lesson" data-course="${c.id}" data-module="${m.id}">${icon("plus")} Adicionar aula</button>` : ""}</div></details>`,
    )
    .join("");
}
function courseDetail(id) {
  return academy().course(id);
}
function lessonPage(id) {
  return academy().lesson(id);
}
function materialRow(m) {
  return `<div class="material-row"><span class="file-icon">${icon(m.ext === "csv" ? "chart" : "file")}</span><div><strong>${esc(m.title)}</strong><small>${esc(m.type)} • ${m.ext.toUpperCase()} • Exemplo ilustrativo</small></div><button class="icon-btn" data-action="download" data-id="${m.id}" aria-label="Baixar ${esc(m.title)}">${icon("download")}</button></div>`;
}
function materialsPage() {
  return (
    heading(
      "PARA TER SEMPRE À MÃO",
      "Materiais de apoio",
      "Guias e modelos para acompanhar sua jornada.",
    ) +
    `<div class="panel materials-panel"><div class="section-title"><h2>Sua biblioteca</h2>${badge(materials.length + " materiais")}</div>${materials.map(materialRow).join("")}</div><div class="inline-note">${icon("help")} Estes arquivos são modelos demonstrativos para avaliar a experiência de download.</div>`
  );
}
function progressPage() {
  return (
    heading(
      "OLHE O QUANTO VOCÊ JÁ AVANÇOU",
      "Cada etapa faz a diferença.",
      "Acompanhe suas conquistas e escolha seu próximo passo.",
    ) +
    `<div class="progress-overview panel"><div class="progress-ring large" style="--percent:${pct()}"><span>${pct()}<small>%</small></span></div><div><span class="eyebrow">SUA JORNADA</span><h2>Conhecimento que se transforma em confiança.</h2><p>${completedCount()} aulas concluídas em ${activeCourses().length} trilhas disponíveis.</p></div><span class="achievement">${icon("trophy")} Um passo de cada vez.</span></div><div class="section-title"><h2>Progresso por trilha</h2></div><div class="progress-list">${activeCourses()
      .map(
        (c) =>
          `<a class="panel progress-item" href="#/curso/${c.id}"><span class="progress-course-icon tone-${c.tone}">${icon(c.icon)}</span><div><h3>${esc(c.title)}</h3><p>${allLessons(c).filter((l) => state.completed.includes(l.id)).length} de ${allLessons(c).length} aulas concluídas</p>${bar(progress(c))}</div><strong>${progress(c)}%</strong>${icon("chevron")}</a>`,
      )
      .join("")}</div>`
  );
}
function helpPage() {
  return (
    heading(
      "PODE CONTAR COM A GENTE",
      "Como podemos ajudar?",
      "Encontre uma resposta rápida ou converse com a nossa equipe.",
    ) +
    `<div class="help-layout"><section class="panel faq-panel"><h2>Perguntas frequentes</h2>${faqs.map(([q, a], i) => `<details class="faq" ${i === 0 ? "open" : ""}><summary>${esc(q)}${icon("plus")}</summary><p>${esc(a)}</p></details>`).join("")}</section><aside class="contact-card large-contact">${icon("message")}<h2>Uma conversa pode facilitar o caminho.</h2><p>Fale com a equipe do escritório pelo canal que for melhor para você.</p>${contact()}<a class="text-link" href="mailto:${esc(brand().email)}">${icon("mail")} ${esc(brand().email)}</a><hr><small>Segunda a sexta<br>8h às 12h · 13h30 às 17h</small></aside></div>`
  );
}
function authPage(kind = "login") {
  const login = kind === "login",
    invite = kind === "convite",
    reset = kind === "redefinir",
    title = login
      ? text("loginTitle")
      : invite
        ? "Sua jornada começa aqui."
        : reset
          ? "Crie uma nova senha."
          : "Vamos recuperar seu acesso.";
  return `<div class="auth-page"><aside class="auth-art"><a href="#/login">${logo()}</a><div><span class="eyebrow">MORAIS CONTABILIDADE CONSULTIVA</span><h1>Uma parceria que vai além dos números.</h1><p>Conhecimento, orientação e proximidade para sua empresa seguir em frente.</p><span class="auth-art-line"></span></div><small>Conhecimento que aproxima.</small></aside><main id="main-content" class="auth-main"><div class="auth-box"><span class="auth-kicker">${icon("shield")} ${esc(brand().portal)}</span><h2>${esc(title)}</h2><p>${esc(login ? text("loginBody") : invite ? "Você foi convidado pelo escritório. Defina sua senha para começar." : reset ? "Escolha uma senha para acessar sua área." : "Informe seu e-mail para visualizar o fluxo de recuperação.")}</p><form id="auth-form" data-kind="${kind}">${!invite && !reset ? `<label>Login ou e-mail<input type="text" name="email" autocomplete="username" placeholder="seu login ou e-mail" required></label>` : ""}${login || invite || reset ? `<label>Senha<div class="password-field"><input type="password" name="password" autocomplete="${login ? "current-password" : "new-password"}" placeholder="${login ? "Digite sua senha" : "Crie uma senha"}" minlength="${login ? "1" : "8"}" required><button type="button" class="icon-btn" data-action="show-password" aria-label="Mostrar senha">${icon("eye")}</button></div></label>` : ""}${invite || reset ? '<label>Confirme sua senha<input type="password" name="confirmation" autocomplete="new-password" minlength="8" required></label>' : ""}${login ? '<a class="forgot-link" href="#/recuperar">Esqueci minha senha</a>' : ""}<button class="btn btn-primary full" type="submit">${login ? "Acessar minha área" : invite ? "Criar meu acesso" : reset ? "Salvar nova senha" : "Solicitar recuperação"} ${icon("arrow")}</button><p id="auth-message" role="status"></p></form>${login ? "" : link("/login", `${icon("left")} Voltar para o acesso`, "text-link")}<a class="auth-support" href="#/ajuda">Precisa de ajuda? Fale com a equipe.</a></div><footer>© 2026 ${esc(brand().name)} · Acesso exclusivo para clientes</footer></main></div>`;
}
function statsCard(ic, value, label, detail = "", tone = "") {
  return `<div class="stat-card"><span class="stat-icon ${tone}">${icon(ic)}</span><span class="stat-label">${esc(label)}</span><strong>${esc(value)}</strong><small>${esc(detail)}</small></div>`;
}
function clientTable(list = state.clients) {
  return `<div class="table-scroll"><table><thead><tr><th>Cliente / Empresa</th><th>Status</th><th>Progresso</th><th>Última atividade</th><th><span class="sr-only">Ações</span></th></tr></thead><tbody>${list.map((c) => `<tr><td><a class="table-client" href="#/admin/cliente/${c.id}">${avatar(c.name)}<span><strong>${esc(c.name)}</strong><small>${esc(c.company)}</small></span></a></td><td>${badge(c.status === "active" ? "Ativo" : c.status === "invited" ? "Convite pendente" : "Inativo", c.status === "active" ? "success" : c.status === "invited" ? "orange" : "neutral")}</td><td><div class="table-progress">${bar(c.id === "cliente-1" ? pct() : c.progress)}<span>${c.id === "cliente-1" ? pct() : c.progress}%</span></div></td><td><span class="muted">${esc(c.last)}</span></td><td><a class="icon-btn" href="#/admin/cliente/${c.id}" aria-label="Ver ${esc(c.name)}">${icon("chevron")}</a></td></tr>`).join("") || '<tr><td colspan="5">Nenhum cliente neste filtro.</td></tr>'}</tbody></table></div>`;
}
function adminHome() {
  const active = state.clients.filter((c) => c.status === "active").length;
  return (
    heading(
      "SEU ESCRITÓRIO, MAIS PRÓXIMO",
      "Visão geral do onboarding",
      "Acompanhe jornadas, cuide dos próximos passos e mantenha seus clientes por perto.",
      `<button class="btn btn-primary" data-action="client-form">${icon("plus")} Novo cliente</button>`,
    ) +
    `<div class="stats-grid">${statsCard("users", state.clients.length, "Clientes cadastrados", "Empresas na demonstração")}${statsCard("shield", active, "Clientes ativos", "Acessos liberados", "green")}${statsCard("chart", Math.round(state.clients.reduce((a, c) => a + (c.id === "cliente-1" ? pct() : c.progress), 0) / state.clients.length) + "%", "Conclusão média", "Indicador demonstrativo", "orange")}${statsCard("clock", state.clients.filter((c) => c.last.includes("dias")).length, "Precisam de atenção", "Sem atividade recente", "pink")}</div><div class="admin-overview"><section class="panel"><div class="section-title"><div><h2>Jornadas em movimento</h2><p class="muted">Atividade ilustrativa dos últimos 7 dias</p></div>${badge("Esta semana")}</div><div class="activity-chart" role="img" aria-label="Gráfico ilustrativo de atividade de segunda a domingo"><div class="chart-grid"><span>20</span><span>10</span><span>0</span></div>${[45, 67, 55, 88, 72, 30, 42].map((h, i) => `<div class="chart-column"><div style="height:${h}%" class="${i === 3 ? "highlight" : ""}"><span>${Math.round(h / 5)}</span></div><small>${["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"][i]}</small></div>`).join("")}</div></section><section class="attention-card"><span class="stat-icon orange">${icon("spark")}</span><h2>Um contato faz a diferença.</h2><p>Identifique quem precisa de apoio para continuar a jornada com o seu escritório.</p><div class="attention-person">${avatar("Pedro Lima")}<span><strong>Pedro Lima</strong><small>Último acesso há 16 dias</small></span></div>${link("/admin/cliente/cliente-4", `Acompanhar cliente ${icon("arrow")}`, "text-link")}</section></div><section class="panel"><div class="section-title"><div><h2>Acompanhe seus clientes</h2><p class="muted">Uma visão próxima de cada jornada.</p></div>${link("/admin/clientes", `Ver todos ${icon("arrow")}`, "text-link")}</div>${clientTable(state.clients.slice(0, 4))}</section>`
  );
}
function adminClients() {
  return (
    heading(
      "RELACIONAMENTO",
      "Cada cliente tem uma jornada.",
      "Gerencie acessos e acompanhe a evolução das empresas.",
      `<button class="btn btn-primary" data-action="client-form">${icon("plus")} Novo cliente</button>`,
    ) +
    `<section class="panel"><div class="filter-bar"><div class="tabs">${[
      ["all", "Todos"],
      ["active", "Ativos"],
      ["invited", "Convites"],
      ["inactive", "Inativos"],
    ]
      .map(
        ([id, label]) =>
          `<button data-action="filter-client" data-id="${id}" class="${clientFilter === id ? "selected" : ""}">${label}</button>`,
      )
      .join(
        "",
      )}</div><label class="search-field">${icon("search")}<input id="client-search" placeholder="Buscar cliente ou empresa" aria-label="Buscar cliente ou empresa"></label></div><div id="client-results">${clientTable(state.clients.filter((c) => clientFilter === "all" || c.status === clientFilter))}</div></section>`
  );
}
function clientDetail(id) {
  const c = state.clients.find((c) => c.id === id);
  if (!c) return notFound();
  return `<a class="back-link" href="#/admin/clientes">${icon("left")} Voltar para clientes</a>${heading("ACOMPANHAMENTO INDIVIDUAL", c.name, c.company, `<button class="btn btn-secondary" data-action="client-form" data-id="${c.id}">${icon("edit")} Editar cadastro</button>`)}<div class="content-columns"><section><div class="panel client-profile"><div class="section-title"><h2>Dados do cliente</h2>${badge(c.status === "active" ? "Ativo" : c.status === "invited" ? "Convite pendente" : "Inativo", c.status === "active" ? "success" : "orange")}</div><dl class="data-grid"><div><dt>E-mail</dt><dd>${esc(c.email)}</dd></div><div><dt>CNPJ</dt><dd>${esc(c.cnpj || "Não informado na prévia")}</dd></div><div><dt>Grupo</dt><dd>${esc(c.group)}</dd></div><div><dt>Última atividade</dt><dd>${esc(c.last)}</dd></div><div><dt>Validade do acesso</dt><dd>${esc(c.expires || "Sem prazo definido")}</dd></div><div><dt>Telefone</dt><dd>${esc(c.phone || "Não informado")}</dd></div></dl></div><div class="section-title"><h2>Trilhas liberadas</h2></div>${
    state.courses
      .filter((v) => c.courses.includes(v.id))
      .map(
        (v) =>
          `<div class="panel progress-item"><span class="progress-course-icon tone-${v.tone}">${icon(v.icon)}</span><div><h3>${esc(v.title)}</h3><p>Acesso direto • ${esc(c.expires || "Sem expiração")}</p>${bar(c.id === "cliente-1" ? progress(v) : c.progress)}</div><strong>${c.id === "cliente-1" ? progress(v) : c.progress}%</strong></div>`,
      )
      .join("") ||
    empty(
      "Nenhuma trilha liberada",
      "Edite o cadastro para selecionar os conteúdos.",
    )
  }</section><aside class="panel course-summary"><h2>Ações do acesso</h2><p>Fluxo demonstrativo. Os convites reais serão ativados com o backend.</p><button class="btn btn-primary full" data-action="invite" data-id="${c.id}">${icon("mail")} Simular convite</button><button class="btn btn-secondary full" data-action="toggle-client" data-id="${c.id}">${icon("shield")} ${c.status === "inactive" ? "Ativar cliente" : "Desativar cliente"}</button><div class="summary-help">${icon("clock")}<p>Histórico ilustrativo<br>Cadastro e liberação de trilhas.</p></div></aside></div>`;
}
function adminContent() {
  return (
    heading(
      "CONTEÚDO QUE APROXIMA",
      "Trilhas e conteúdos",
      "Organize os primeiros passos dos seus clientes.",
      `<button class="btn btn-primary" data-action="course-form">${icon("plus")} Nova trilha</button>`,
    ) +
    `<div class="admin-course-list">${state.courses.map((c, i) => `<article class="panel admin-course">${courseCover(c, true)}<div class="admin-course-copy">${badge(c.status === "published" ? "Publicada" : "Rascunho", c.status === "published" ? "success" : "neutral")}<h2>${esc(c.title)}</h2><p>${c.modules.length} módulos <i>•</i> ${allLessons(c).length} aulas <i>•</i> ${c.sequence ? "Sequência obrigatória" : "Acesso livre"}</p></div><div class="row-actions"><button class="icon-btn" data-action="move-course" data-id="${c.id}" data-direction="up" ${i === 0 ? "disabled" : ""} aria-label="Mover trilha para cima">↑</button><button class="icon-btn" data-action="move-course" data-id="${c.id}" data-direction="down" ${i === state.courses.length - 1 ? "disabled" : ""} aria-label="Mover trilha para baixo">↓</button><a class="btn btn-secondary" href="#/admin/curso/${c.id}">Gerenciar ${icon("arrow")}</a></div></article>`).join("")}</div>`
  );
}
function courseEditor(id) {
  const c = state.courses.find((c) => c.id === id);
  if (!c) return notFound();
  return `<a class="back-link" href="#/admin/conteudos">${icon("left")} Voltar para conteúdos</a>${heading("EDITOR DE CONTEÚDO", c.title, "Organize módulos e aulas da sua trilha.", `<button class="btn btn-secondary" data-action="course-form" data-id="${c.id}">${icon("settings")} Configurar trilha</button>`)}<div class="content-columns"><section>${outline(c, "", true)}<button class="btn btn-secondary" data-action="add-module" data-id="${c.id}">${icon("plus")} Adicionar módulo</button></section><aside class="panel course-summary"><h2>Publicação</h2><p>${esc(c.description)}</p>${badge(c.status === "published" ? "Publicada" : "Rascunho", c.status === "published" ? "success" : "neutral")}<p>${c.sequence ? "As aulas seguem uma sequência." : "O cliente pode explorar as aulas livremente."}</p><button class="btn btn-primary full" data-action="publish-course" data-id="${c.id}">${c.status === "published" ? "Mover para rascunho" : "Publicar na prévia"}</button>${c.status === "published" ? link("/curso/" + c.id, `${icon("eye")} Ver como cliente`, "btn btn-secondary full") : ""}<button class="btn btn-quiet full" data-action="archive-course" data-id="${c.id}">${icon("trash")} Remover da demonstração</button></aside></div>`;
}
function adminGroups() {
  const groups = [
    ...new Set(state.clients.map((c) => c.group).filter(Boolean)),
  ];
  return (
    heading(
      "ACESSO ORGANIZADO",
      "Grupos de clientes",
      "Agrupe empresas para organizar suas jornadas e liberações.",
    ) +
    `<div class="course-grid">${groups
      .map(
        (g) =>
          `<article class="panel group-card"><span class="stat-icon">${icon("users")}</span><h2>${esc(g)}</h2><p>${state.clients.filter((c) => c.group === g).length} empresas neste grupo</p><div class="group-avatars">${state.clients
            .filter((c) => c.group === g)
            .map((c) => avatar(c.name))
            .join(
              "",
            )}</div><button class="btn btn-secondary full" data-action="view-group" data-id="${esc(g)}">Ver membros ${icon("arrow")}</button></article>`,
      )
      .join(
        "",
      )}</div><div class="inline-note">${icon("help")} Na prévia, altere o grupo no cadastro do cliente. Concessões automáticas por grupo serão conectadas ao backend.</div>`
  );
}
function adminMedia() {
  return (
    heading(
      "TUDO EM SEU LUGAR",
      "Biblioteca de mídia",
      "Uma prévia dos arquivos que vão compor suas aulas.",
      `<label class="btn btn-primary file-button">${icon("upload")} Selecionar arquivos<input id="media-input" type="file" multiple accept="video/mp4,video/webm,video/quicktime,video/x-m4v,image/png,image/jpeg,image/webp,application/pdf,.csv,.xlsx,.docx,.vtt"></label>`,
    ) +
    `<div class="upload-zone">${icon("upload")}<h2>Biblioteca de conteúdos</h2><p>Envie vídeos e materiais de apoio para reutilizá-los nas aulas.</p><small>Os arquivos enviados ficam disponíveis para seleção na criação das aulas.</small></div><section class="panel"><div class="section-title"><h2>Arquivos da biblioteca</h2>${badge(state.files.length + " arquivos")}</div>${state.files.length ? state.files.map((f) => `<div class="material-row"><span class="file-icon">${icon(f.type.startsWith("video") ? "play" : "file")}</span><div><strong>${esc(f.name)}</strong><small>${formatBytes(f.size)} • ${f.status === "ready" ? "Disponível no servidor" : "Processando"}</small></div><button class="icon-btn" data-action="open-file" data-id="${f.id}" aria-label="Visualizar ${esc(f.name)}">${icon("eye")}</button><button class="icon-btn" data-action="remove-file" data-id="${f.id}" aria-label="Remover ${esc(f.name)}">${icon("trash")}</button></div>`).join("") : empty("Sua biblioteca está vazia", "Envie o primeiro vídeo ou material de apoio.")}</section>`
  );
}
function adminCommunication() {
  return (
    heading(
      "UMA COMUNICAÇÃO MAIS PRÓXIMA",
      "Recados e orientações",
      "Mantenha as informações importantes no caminho do cliente.",
    ) +
    `<div class="help-layout"><section class="panel"><div class="section-title"><h2>Comunicado da página inicial</h2></div><form id="notice-form"><label>Título<input name="title" value="${esc(state.notices[0]?.title)}" required maxlength="100"></label><label>Mensagem<textarea name="body" rows="5" required maxlength="600">${esc(state.notices[0]?.body)}</textarea></label><button class="btn btn-primary" type="submit">Salvar na prévia ${icon("check")}</button></form></section><section class="panel"><h2>E-mails do portal</h2>${["Convite de acesso", "Recuperação de senha", "Lembrete de jornada"].map((n, i) => `<div class="template-row"><span class="file-icon">${icon("mail")}</span><strong>${n}</strong><button class="icon-btn" data-action="email-preview" data-id="${i}" aria-label="Ver modelo de ${n}">${icon("eye")}</button></div>`).join("")}<p class="muted">Modelos visuais. O envio e a edição de templates serão conectados ao serviço de e-mail.</p></section></div>`
  );
}
function adminReports() {
  return (
    heading(
      "INFORMAÇÃO PARA ACOMPANHAR",
      "Relatórios e progresso",
      "Um olhar sobre cada etapa da experiência dos seus clientes.",
      `<button class="btn btn-primary" data-action="export-clients">${icon("download")} Exportar CSV</button>`,
    ) +
    `<div class="stats-grid">${statsCard("users", state.clients.length, "Clientes na análise", "Base demonstrativa")}${statsCard("trophy", activeCourses().filter((c) => progress(c) === 100).length, "Trilhas concluídas", "Cliente em demonstração", "orange")}${statsCard("clock", "14 dias", "Critério de inatividade", "Parâmetro proposto")}${statsCard("mail", state.clients.filter((c) => c.status === "invited").length, "Convites pendentes", "Aguardando ativação", "green")}</div><section class="panel"><div class="section-title"><h2>Progresso individual</h2>${badge("Dados ilustrativos")}</div>${clientTable()}</section><div class="inline-note">${icon("help")} A exportação contém somente os dados fictícios exibidos nesta demonstração.</div>`
  );
}
function customization() {
  return (
    heading(
      "SUA IDENTIDADE EM CADA DETALHE",
      "Personalização do portal",
      "Ajuste a marca e experimente a experiência white label.",
      `<a class="btn btn-secondary" href="#/inicio">${icon("eye")} Ver área do cliente</a>`,
    ) +
    `<div class="settings-layout"><nav class="settings-nav" aria-label="Categorias da personalização">${[
      ["brand", "Identidade visual"],
      ["texts", "Textos e mensagens"],
      ["blocks", "Blocos da página inicial"],
    ]
      .map(
        ([id, n]) =>
          `<button data-action="settings-tab" data-id="${id}" class="${settingsTab === id ? "active" : ""}">${n}${icon("chevron")}</button>`,
      )
      .join(
        "",
      )}<button class="reset-button" data-action="reset-brand">Restaurar identidade Morais</button></nav><section class="panel settings-panel">${settingsTab === "brand" ? brandForm() : settingsTab === "texts" ? textsForm() : blocksForm()}</section></div>`
  );
}
function brandForm() {
  const b = brand();
  return `<h2>Uma experiência com a sua marca.</h2><p class="muted">As alterações aparecem em todas as telas desta prévia.</p><form id="brand-form"><div class="logo-upload"><div class="logo-sample">${logo()}</div><div><strong>Logo do escritório</strong><p>PNG, JPEG ou WebP • até 1 MB</p><label class="btn btn-secondary file-button">${icon("upload")} Alterar logo<input id="logo-input" type="file" accept="image/png,image/jpeg,image/webp"></label></div></div><div class="form-grid"><label>Nome do escritório<input name="name" value="${esc(b.name)}" required maxlength="80"></label><label>Nome da plataforma<input name="portal" value="${esc(b.portal)}" required maxlength="50"></label><label>Cor de destaque<div class="color-control"><input name="primary" type="color" value="${esc(b.primary)}"><span>${esc(b.primary)}</span></div></label><label>Cor principal<div class="color-control"><input name="navy" type="color" value="${esc(b.navy)}"><span>${esc(b.navy)}</span></div></label><label>Tipografia<select name="font"><option ${b.font === "Manrope" ? "selected" : ""}>Manrope</option><option ${b.font === "Arial" ? "selected" : ""}>Arial</option></select></label><label>E-mail de atendimento<input type="email" name="email" value="${esc(b.email)}" required></label><label>WhatsApp com DDI e DDD<input name="whatsapp" value="${esc(b.whatsapp)}" required pattern="[0-9+() .-]{10,22}"></label></div><div class="form-footer"><span>${icon("shield")} Configuração desta demonstração</span><button type="submit" class="btn btn-primary">Salvar identidade ${icon("check")}</button></div></form>`;
}
function textsForm() {
  return `<h2>Seu jeito de dar boas-vindas.</h2><p class="muted">Edite mensagens, títulos e nomes da navegação.</p><form id="texts-form">${Object.entries(
    defaults.texts,
  )
    .map(
      ([key, value]) =>
        `<label>${esc({ welcome: "Título da página inicial", welcomeBody: "Descrição da página inicial", heroEyebrow: "Identificação do banner", heroTitle: "Título do banner", heroBody: "Texto do banner", coursesTitle: "Título das trilhas", catalogTitle: "Título do catálogo de cursos", continueTitle: "Título de continuar assistindo", catalogSearch: "Busca de cursos e aulas", supportTitle: "Título do suporte", supportBody: "Texto do suporte", loginTitle: "Título do acesso", loginBody: "Descrição do acesso", navHome: "Menu: início", navCourses: "Menu: trilhas", navMaterials: "Menu: materiais", navProgress: "Menu: progresso", navHelp: "Menu: ajuda" }[key])}${key.includes("Body") || key === "heroTitle" ? `<textarea name="${key}" rows="2" maxlength="500" required>${esc(text(key))}</textarea>` : `<input name="${key}" value="${esc(text(key))}" maxlength="100" required>`}</label>`,
    )
    .join(
      "",
    )}<button class="btn btn-primary" type="submit">Salvar textos ${icon("check")}</button></form>`;
}
function blocksForm() {
  return `<h2>Uma página no seu ritmo.</h2><p class="muted">Ative, desative e reordene as seções da página inicial.</p><div class="block-list">${state.settings.blocks.map((b, i) => `<div class="block-row"><span class="block-grip">⋮⋮</span><strong>${esc(b.label)}</strong><button class="icon-btn" data-action="move-block" data-id="${b.id}" data-direction="up" ${i === 0 ? "disabled" : ""} aria-label="Mover ${esc(b.label)} para cima">↑</button><button class="icon-btn" data-action="move-block" data-id="${b.id}" data-direction="down" ${i === state.settings.blocks.length - 1 ? "disabled" : ""} aria-label="Mover ${esc(b.label)} para baixo">↓</button><button class="switch ${b.enabled ? "on" : ""}" role="switch" aria-checked="${b.enabled}" aria-label="Exibir ${esc(b.label)}" data-action="toggle-block" data-id="${b.id}"><span></span></button></div>`).join("")}</div><div class="inline-note">${icon("check")} Alterações aplicadas automaticamente nesta prévia.</div>`;
}
function operations() {
  return (
    heading(
      "CUIDADO COM A SUA PLATAFORMA",
      "Configurações do ambiente",
      "Visão técnica para acompanhar as próximas etapas da instalação.",
    ) +
    `<section class="panel"><h2>Ambiente informado</h2><dl class="data-grid"><div><dt>Domínio de referência</dt><dd>onboarding.kaizenmidias.com</dd></div><div><dt>Hospedagem atual</dt><dd>aaPanel • informado pelo responsável</dd></div><div><dt>PHP de destino</dt><dd>8.4 • informado pelo responsável</dd></div><div><dt>Versão desta entrega</dt><dd>0.2.0 • prévia visual</dd></div></dl><div class="inline-note">${icon("help")} Dados de referência. Esta tela não inspeciona nem se conecta à hospedagem.</div></section><div class="operations-grid">${[
      ["lock", "Autenticação e permissões"],
      ["folder", "Mídia privada e upload retomável"],
      ["mail", "SMTP e convites"],
      ["shield", "Backup e diagnóstico"],
    ]
      .map(
        ([ic, n]) =>
          `<section class="panel operation-card">${icon(ic)}<h3>${n}</h3>${badge("Integração posterior")}<p>Previsto na arquitetura, após aprovação da interface.</p></section>`,
      )
      .join(
        "",
      )}</div><button class="btn btn-secondary" data-action="reset-demo">Restaurar dados da demonstração</button>`
  );
}
function profilePage() {
  return (
    heading(
      "SEU ESPAÇO",
      "Minha conta",
      "Informações do acesso demonstrativo.",
    ) +
    `<section class="panel profile-panel"><div class="profile-top">${avatar(isAdmin() ? "Equipe Morais" : "Mariana Oliveira")}<div><h2>Mariana Oliveira</h2><p>Empresa Exemplo</p></div>${badge("Perfil demonstrativo")}</div><dl class="data-grid"><div><dt>E-mail</dt><dd>mariana@example.com</dd></div><div><dt>Tipo de acesso</dt><dd>Um acesso por empresa</dd></div><div><dt>Escritório responsável</dt><dd>${esc(brand().name)}</dd></div><div><dt>Atualizações cadastrais</dt><dd>Solicite à equipe do escritório.</dd></div></dl><div class="row-actions"><a class="btn btn-secondary" href="#/redefinir">Visualizar troca de senha</a>${contact("btn btn-dark")}</div></section>`
  );
}
const notFound = () =>
  empty(
    "Este caminho não foi encontrado.",
    "Escolha uma das áreas do portal para continuar.",
    link("/inicio", "Ir para o início", "btn btn-primary"),
  );
function render() {
  const playingVideo = $("#lesson-video");
  if (playingVideo?.dataset.lessonId)
    state.positions[playingVideo.dataset.lessonId] = playingVideo.currentTime;
  const noteDraft = $("#lesson-notes");
  if (noteDraft?.dataset.lessonId) {
    state.notes[noteDraft.dataset.lessonId] = noteDraft.value;
    persist();
  }
  applyBrand();
  const [root, id, detail] = route();
  const storedUser = (() => { try { return JSON.parse(sessionStorage.getItem(AUTH_KEY) || "null"); } catch { return null; } })();
  if (!["login", "recuperar", "convite", "redefinir"].includes(root) && !storedUser) {
    app.innerHTML = authPage("login");
    bindForms();
    return;
  }
  if (root === "admin" && !["admin", "office_admin", "agency_admin"].includes(storedUser?.role)) {
    location.hash = "/inicio";
    return;
  }
  document.title = `${isAdmin() ? "Painel administrativo" : "Área do cliente"} · ${brand().name}`;
  if (["login", "recuperar", "convite", "redefinir"].includes(root))
    app.innerHTML = authPage(root);
  else {
    let content, section;
    if (root === "admin") {
      const pages = {
        clientes: [adminClients, "Clientes"],
        conteudos: [adminContent, "Conteúdos"],
        grupos: [adminGroups, "Grupos"],
        midia: [adminMedia, "Biblioteca de mídia"],
        comunicacao: [adminCommunication, "Comunicação"],
        relatorios: [adminReports, "Relatórios"],
        personalizacao: [customization, "Personalização"],
        operacao: [operations, "Configurações"],
      };
      if (id === "cliente") {
        content = clientDetail(detail);
        section = "Acompanhamento do cliente";
      } else if (id === "curso") {
        content = courseEditor(detail);
        section = "Editor de conteúdo";
      } else {
        const p = pages[id] || [adminHome, "Visão geral"];
        content = p[0]();
        section = p[1];
      }
    } else {
      const pages = {
        inicio: [dashboard, text("navHome")],
        cursos: [coursesPage, text("navCourses")],
        materiais: [materialsPage, text("navMaterials")],
        progresso: [progressPage, text("navProgress")],
        ajuda: [helpPage, text("navHelp")],
        perfil: [profilePage, "Minha conta"],
        salvos: [() => academy().saved(), "Aulas salvas"],
      };
      if (root === "curso") {
        content = courseDetail(id);
        section = "Minhas trilhas";
      } else if (root === "aula") {
        content = lessonPage(id);
        section = "Sala de aula";
      } else if (root === "privacidade") {
        content =
          heading(
            "INFORMAÇÕES DO PORTAL",
            "Privacidade e termos",
            "Espaço reservado para os documentos do escritório.",
          ) +
          '<section class="panel"><h2>Sobre esta demonstração</h2><p>Esta prévia utiliza dados fictícios e salva alterações apenas neste navegador. Não há autenticação, banco de dados ou envio de e-mails.</p><p>O aviso de privacidade e os termos oficiais serão cadastrados e revisados pelo responsável pelo escritório antes da operação com clientes reais.</p></section>';
        section = "Privacidade";
      } else {
        const p = pages[root];
        content = p ? p[0]() : notFound();
        section = p ? p[1] : "Página não encontrada";
      }
    }
    app.innerHTML = shell(content, section);
  }
  bindForms();
  bindVideo();
}
function openModal(title, body, handler = null) {
  modalHandler = handler;
  modal.innerHTML = `<div class="modal-header"><div><span class="eyebrow">PRÉVIA VISUAL</span><h2 id="modal-title">${esc(title)}</h2></div><button type="button" class="icon-btn" data-action="close-modal" aria-label="Fechar janela">${icon("close")}</button></div><div class="modal-body">${body}</div>`;
  modal.showModal();
}
const input = (label, name, value = "", type = "text", attrs = "") =>
  `<label>${esc(label)}<input name="${name}" type="${type}" value="${esc(value)}" ${attrs}></label>`;
const modalFooter = (label = "Salvar na demonstração") =>
  `<div class="modal-footer"><button class="btn btn-secondary" type="button" data-action="close-modal">Cancelar</button><button class="btn btn-primary" type="submit">${label} ${icon("check")}</button></div>`;
function clientForm(id) {
  const c = state.clients.find((c) => c.id === id) || {};
  const accessPassword = !id ? input("Senha de acesso", "password", "", "password", 'required minlength="8" autocomplete="new-password"') : "";
  openModal(
    id ? "Editar cliente" : "Um novo começo para seu cliente",
    `<form>${input("Nome do responsável", "name", c.name, "text", 'required maxlength="80"')}${input("Empresa", "company", c.company, "text", 'required maxlength="100"')}${input("E-mail", "email", c.email, "email", "required")}<div class="form-grid">${input("CNPJ (opcional nesta demonstração)", "cnpj", c.cnpj, "text", 'maxlength="18"')}${input("Telefone", "phone", c.phone, "tel")}${input("WhatsApp", "whatsapp", c.whatsapp, "tel")}${input("Grupo", "group", c.group || "Novos clientes")}${input("Expiração do acesso", "expires", c.expires, "date")}</div><fieldset><legend>Trilhas liberadas</legend>${state.courses.map((v) => `<label class="checkbox-label"><input type="checkbox" name="courses" value="${v.id}" ${(c.courses || ["primeiros-passos"]).includes(v.id) ? "checked" : ""}>${esc(v.title)}</label>`).join("")}</fieldset><p class="form-note">Cadastro de demonstração. Nenhum convite real será enviado.</p>${modalFooter()}</form>`,
    (fd) => {
      const values = Object.fromEntries(fd);
      if (
        state.clients.some(
          (v) =>
            v.id !== id && v.email.toLowerCase() === values.email.toLowerCase(),
        )
      )
        return toast("Este e-mail já existe na demonstração.");
      const value = {
        ...values,
        id: id || uid("cliente"),
        courses: fd.getAll("courses"),
        status: c.status || "invited",
        progress: c.progress || 0,
        last: c.last || "Aguardando primeiro acesso",
      };
      if (id) Object.assign(c, value);
      else state.clients.push(value);
      persist();
      void apiRequest("client.save", { method: "POST", body: JSON.stringify({ id: /^\d+$/.test(String(id || "")) ? Number(id) : 0, name: value.name, company: value.company, email: value.email, password: value.password || "", cnpj: value.cnpj, phone: value.phone, whatsapp: value.whatsapp, status: value.status === "invited" ? "pending" : value.status, expires_at: value.expires || null }) });
      modal.close();
      render();
      toast("Cadastro salvo na demonstração.");
    },
  );
  if (!id) modal.querySelector(".form-grid")?.insertAdjacentHTML("beforebegin", accessPassword);
}
function courseForm(id) {
  const c = state.courses.find((c) => c.id === id) || {};
  const images = {
    coverImage: c.coverImage || "",
    landscapeImage: c.landscapeImage || "",
  };
  openModal(
    id ? "Configurar trilha" : "Nova trilha de conhecimento",
    `<form>${input("Título", "title", c.title, "text", 'required maxlength="120"')}${input("Categoria", "category", c.category || "Conhecimento")}<label>Descrição<textarea name="description" required maxlength="400" rows="3">${esc(c.description)}</textarea></label><label>Aparência da capa<select name="tone">${[
      ["navy", "Azul-marinho"],
      ["orange", "Laranja"],
      ["sage", "Verde suave"],
    ]
      .map(
        ([v, l]) =>
          `<option value="${v}" ${c.tone === v ? "selected" : ""}>${l}</option>`,
      )
      .join(
        "",
      )}</select></label><fieldset><legend>Capas do curso</legend><p class="form-note">Opcional. Sem imagem, a prévia cria uma capa com o título do curso. PNG, JPEG ou WebP até 600 KB por imagem, guardados neste navegador.</p>${[
      ["coverImage", "Capa vertical · proporção 2:3"],
      ["landscapeImage", "Capa horizontal · proporção 16:9"],
    ]
      .map(
        ([key, label]) =>
          `<div class="course-image-field"><label>${label}<input type="file" data-course-image="${key}" accept="image/png,image/jpeg,image/webp"></label><div id="preview-${key}" class="course-image-sample">${images[key] ? `<img src="${esc(images[key])}" alt="Prévia da capa">` : "Capa automática"}</div><button type="button" class="btn btn-secondary" data-clear-image="${key}">Usar capa automática</button></div>`,
      )
      .join(
        "",
      )}</fieldset><label class="checkbox-label"><input type="checkbox" name="sequence" ${c.sequence ? "checked" : ""}> Exigir conclusão das aulas em sequência</label>${modalFooter()}</form>`,
    (fd) => {
      const v = {
        ...Object.fromEntries(fd),
        ...images,
        sequence: fd.has("sequence"),
      };
      if (id) Object.assign(c, v);
      else
        state.courses.push({
          ...v,
          id: uid("curso"),
          icon: "compass",
          status: "draft",
          modules: [],
        });
      persist();
      void apiRequest("course.save", { method: "POST", body: JSON.stringify({ id: /^\d+$/.test(String(id || "")) ? Number(id) : 0, title: v.title, category: v.category, description: v.description, status: c.status || "draft", sequence_required: v.sequence }) });
      modal.close();
      render();
      toast("Trilha salva na demonstração.");
    },
  );
  modal.querySelectorAll("[data-course-image]").forEach((field) =>
    field.addEventListener("change", () => {
      const file = field.files[0],
        key = field.dataset.courseImage;
      if (!file) return;
      if (
        !["image/png", "image/jpeg", "image/webp"].includes(file.type) ||
        file.size > 614400
      ) {
        field.value = "";
        return toast("Selecione PNG, JPEG ou WebP de até 600 KB.");
      }
      const reader = new FileReader();
      reader.onload = () => {
        images[key] = reader.result;
        $("#preview-" + key).innerHTML =
          `<img src="${esc(reader.result)}" alt="Prévia da capa">`;
      };
      reader.readAsDataURL(file);
    }),
  );
  modal.querySelectorAll("[data-clear-image]").forEach((button) =>
    button.addEventListener("click", () => {
      const key = button.dataset.clearImage;
      images[key] = "";
      $("#preview-" + key).textContent = "Capa automática";
      modal.querySelector(`[data-course-image="${key}"]`).value = "";
    }),
  );
}
function lessonForm(courseId, moduleId, id) {
  const info = id ? lessonInfo(id) : null,
    c = info?.c || state.courses.find((v) => v.id === courseId),
    m = info?.m || c?.modules.find((v) => v.id === moduleId),
    l = info?.l || {};
  let lessonMediaId = l.media_id || "";
  if (!m) return;
  openModal(
    id ? "Editar aula" : "Adicionar aula",
    `<form>${input("Título da aula", "title", l.title, "text", 'required maxlength="120"')}${input("Duração (min:seg)", "duration", l.duration || "05:00", "text", 'required pattern="[0-9]{1,3}:[0-5][0-9]"')}<label>Tipo de conteúdo<select name="kind"><option value="video">Videoaula</option><option value="text" ${l.kind === "text" ? "selected" : ""}>Texto e materiais</option></select></label><label>Vídeo da aula<input id="lesson-video-file" type="file" accept="video/mp4,video/webm,video/quicktime,video/x-m4v"></label><p id="lesson-upload-status" class="form-note">Selecione um vídeo para enviá-lo diretamente para esta aula.</p>${modalFooter()}</form>`,
    (fd) => {
      const v = Object.fromEntries(fd);
      if (id) Object.assign(l, v, { media_id: lessonMediaId });
      else m.lessons.push({ ...v, media_id: lessonMediaId, id: uid("aula") });
      persist();
      void apiRequest("lesson.save", { method: "POST", body: JSON.stringify({ id: /^\d+$/.test(String(id || "")) ? Number(id) : 0, module_id: /^\d+$/.test(String(moduleId || "")) ? Number(moduleId) : 0, title: v.title, body: v.body || "", media_id: lessonMediaId || null, duration_seconds: v.duration ? v.duration.split(":").reduce((a, n) => a * 60 + Number(n), 0) : 0, status: "draft" }) });
      modal.close();
      render();
      toast("Aula salva na demonstração.");
    },
  );
  $("#lesson-video-file")?.addEventListener("change", (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const status = $("#lesson-upload-status");
    if (status) status.textContent = `Enviando ${file.name}...`;
    uploadVideo(file).then((result) => {
      if (result?.id) {
        lessonMediaId = result.id;
        if (status) status.textContent = "Vídeo enviado e vinculado a esta aula.";
      } else if (status) {
        status.textContent = "Vídeo selecionado localmente. O vínculo será concluído após o servidor estar configurado.";
      }
    });
  });
}
function download(name, content, type = "text/plain;charset=utf-8") {
  const blob = new Blob(["\ufeff" + content], { type }),
    url = URL.createObjectURL(blob),
    a = document.createElement("a");
  a.href = url;
  a.download = name;
  a.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
function formatBytes(size) {
  return size >= 1073741824
    ? (size / 1073741824).toFixed(2) + " GiB"
    : size >= 1048576
      ? (size / 1048576).toFixed(1) + " MB"
      : Math.ceil(size / 1024) + " KB";
}
const csvSafe = (v) => {
  let s = String(v ?? "").replace(/[\r\n]/g, " ");
  if (/^[\s]*[=+\-@\t]/.test(s)) s = "'" + s;
  return '"' + s.replace(/"/g, '""') + '"';
};
function bindForms() {
  $("#academy-search")?.addEventListener("input", (e) => {
    search = e.target.value;
    const popover = $("#academy-search-popover");
    popover.hidden = !search.trim();
    popover.innerHTML = academy().globalResults(search);
    const results = $("#academy-results");
    if (results) results.innerHTML = academy().results(search);
    $(".academy-shell")?.classList.toggle("searching", !!search.trim());
  });
  $("#lesson-search")?.addEventListener("input", (e) => {
    const info = lessonInfo(route()[1]);
    if (info)
      $("#syllabus-modules").innerHTML = academy().outline(
        info.c,
        info.l.id,
        e.target.value,
      );
  });
  $("#auth-form")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const form = e.currentTarget;
    const fd = new FormData(form),
      kind = form.dataset.kind;
    if (
      (kind === "convite" || kind === "redefinir") &&
      fd.get("password") !== fd.get("confirmation")
    ) {
      $("#auth-message").textContent = "As senhas precisam ser iguais.";
      return;
    }
    if (kind === "login") {
      const localClient = state.clients.find((client) => String(client.email || "").toLowerCase() === String(fd.get("email") || "").trim().toLowerCase() && client.password && client.password === fd.get("password"));
      if (localClient) {
        sessionStorage.setItem(AUTH_KEY, JSON.stringify({ id: localClient.id, name: localClient.name, role: "client", email: localClient.email, client_id: localClient.id }));
        location.hash = "/inicio";
        toast("Acesso realizado.");
        return;
      }
      const result = await apiRequest("login", { method: "POST", body: JSON.stringify({ email: fd.get("email"), password: fd.get("password") }) });
      form.reset();
      if (result?.user) { sessionStorage.setItem(AUTH_KEY, JSON.stringify(result.user)); location.hash = result.user.role === "client" ? "/inicio" : "/admin"; toast("Acesso realizado."); }
      else if (String(fd.get("email")).trim().toLowerCase() === "kaizen" && fd.get("password") === "Kaizen@@2026") {
        const user = { id: "demo-kaizen", name: "Kaizen", role: "admin", email: "kaizen" };
        sessionStorage.setItem(AUTH_KEY, JSON.stringify(user));
        location.hash = "/admin";
        toast("Acesso de teste realizado.");
      } else {
        $("#auth-message").textContent = "Login ou senha inválidos.";
      }
    } else {
      form.reset();
      $("#auth-message").textContent =
        kind === "recuperar"
          ? "Solicitação simulada. Nenhum e-mail foi enviado."
          : "Fluxo visual concluído. Nenhuma senha foi gravada.";
    }
  });
  $("#brand-form")?.addEventListener("submit", (e) => {
    e.preventDefault();
    Object.assign(
      state.settings.brand,
      Object.fromEntries(new FormData(e.currentTarget)),
    );
    persist();
    render();
    toast("Identidade aplicada à demonstração.");
  });
  $("#texts-form")?.addEventListener("submit", (e) => {
    e.preventDefault();
    Object.assign(
      state.settings.texts,
      Object.fromEntries(new FormData(e.currentTarget)),
    );
    persist();
    render();
    toast("Textos atualizados na demonstração.");
  });
  $("#notice-form")?.addEventListener("submit", (e) => {
    e.preventDefault();
    state.notices[0] = Object.fromEntries(new FormData(e.currentTarget));
    persist();
    toast("Comunicado atualizado na demonstração.");
  });
  $("#course-search")?.addEventListener("input", (e) => {
    search = e.target.value;
    $("#course-results").innerHTML = filteredCourseCards();
  });
  $("#client-search")?.addEventListener("input", (e) => {
    const q = e.target.value.toLocaleLowerCase("pt-BR");
    $("#client-results").innerHTML = clientTable(
      state.clients.filter(
        (c) =>
          (clientFilter === "all" || c.status === clientFilter) &&
          (c.name + " " + c.company).toLocaleLowerCase("pt-BR").includes(q),
      ),
    );
  });
  $("#logo-input")?.addEventListener("change", (e) => {
    const f = e.target.files[0];
    if (!f) return;
    if (
      !["image/png", "image/jpeg", "image/webp"].includes(f.type) ||
      f.size > 1048576
    )
      return toast("Selecione PNG, JPEG ou WebP de até 1 MB.");
    const reader = new FileReader();
    reader.onload = () => {
      state.settings.brand.logo = reader.result;
      persist();
      render();
      toast("Logo aplicada nesta demonstração.");
    };
    reader.readAsDataURL(f);
  });
  const brandForm = $("#brand-form");
  if (brandForm && !$("#favicon-input")) {
    const holder = brandForm.querySelector(".logo-upload > div:last-child");
    if (holder) {
      const label = document.createElement("label");
      label.className = "btn btn-secondary file-button";
      label.textContent = "Alterar favicon";
      const inputEl = document.createElement("input");
      inputEl.id = "favicon-input";
      inputEl.type = "file";
      inputEl.accept = "image/png,image/jpeg,image/webp,image/x-icon,image/svg+xml";
      label.appendChild(inputEl);
      holder.appendChild(label);
      inputEl.addEventListener("change", (e) => {
        const f = e.target.files?.[0];
        if (!f || f.size > 524288) return toast("Selecione um favicon de até 512 KB.");
        const reader = new FileReader();
        reader.onload = () => {
          state.settings.brand.favicon = reader.result;
          persist();
          applyBrand();
          void apiRequest("setting", { method: "POST", body: JSON.stringify({ key: "brand.favicon", value: reader.result }) });
          toast("Favicon aplicado nesta demonstração.");
        };
        reader.readAsDataURL(f);
      });
    }
  }
  $("#media-input")?.addEventListener("change", (e) => {
    for (const f of e.target.files) {
      const allowed =
        /\.(mp4|webm|mov|m4v|png|jpe?g|webp|pdf|csv|xlsx|docx|vtt)$/i.test(f.name);
      if (!allowed) {
        toast("Arquivo não aceito nesta prévia: " + f.name);
        continue;
      }
      const id = uid("arquivo");
      state.files.push({ id, name: f.name, size: f.size, type: f.type });
      mediaUrls.set(id, URL.createObjectURL(f));
      if (/\.(mp4|webm|mov|m4v)$/i.test(f.name)) uploadVideo(f);
    }
    persist();
    render();
  });
}
async function uploadVideo(file) {
  const session = await apiRequest("session");
  if (!session?.user) return null;
  const started = await fetch("upload.php?action=start", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json", "X-CSRF-Token": apiCsrf }, body: JSON.stringify({ name: file.name, size: file.size, mime: file.type }) });
  const created = started.ok ? await started.json() : null;
  if (!created?.id) {
    toast("A prévia local foi mantida; não foi possível iniciar o upload no servidor.");
    return null;
  }
  const chunkSize = created.chunk_size || 8388608;
  for (let offset = 0; offset < file.size; offset += chunkSize) {
    const chunk = file.slice(offset, Math.min(offset + chunkSize, file.size));
    const response = await fetch(`upload.php?action=chunk&id=${encodeURIComponent(created.id)}`, { method: "POST", credentials: "same-origin", headers: { "X-CSRF-Token": apiCsrf, "X-Upload-Offset": String(offset), "Content-Type": "application/octet-stream" }, body: chunk });
    if (!response.ok) {
      toast("O upload foi interrompido. Você poderá tentar novamente.");
      return null;
    }
  }
  toast("Vídeo enviado para a biblioteca.");
  return { id: created.id, url: `media.php?id=${encodeURIComponent(created.id)}` };
}
function bindVideo() {
  const v = $("#lesson-video"),
    id = route()[1];
  if (!v) return;
  v.addEventListener("loadedmetadata", () => {
    v.currentTime = Math.min(
      state.positions[id] || 0,
      Math.max(0, v.duration - 1),
    );
  });
  let last = 0;
  v.addEventListener("timeupdate", () => {
    if (Date.now() - last < 10000) return;
    last = Date.now();
    state.positions[id] = v.currentTime;
    persist();
  });
  v.addEventListener("pause", () => {
    state.positions[id] = v.currentTime;
    persist();
  });
}
modal.addEventListener("submit", (e) => {
  e.preventDefault();
  modalHandler?.(new FormData(e.target));
});
modal.addEventListener("click", (e) => {
  if (e.target === modal) {
    const r = modal.getBoundingClientRect();
    if (
      e.clientX < r.left ||
      e.clientX > r.right ||
      e.clientY < r.top ||
      e.clientY > r.bottom
    )
      modal.close();
  }
});
document.addEventListener("click", (e) => {
  const el = e.target.closest("[data-action]");
  if (!el) return;
  e.preventDefault();
  const { action, id, direction } = el.dataset;
  if (action === "logout") {
    sessionStorage.removeItem(AUTH_KEY);
    location.hash = "/login";
    return;
  }
  if (action === "close-modal") {
    modal.close();
    return;
  }
  if (action === "skip-main") {
    $("#main-content")?.focus();
    $("#main-content")?.scrollIntoView();
    return;
  }
  if (action === "menu") {
    menuOpen = !menuOpen;
    render();
    if (menuOpen) $(".academy-drawer a, .sidebar a")?.focus();
    else $(".academy-menu-trigger, .mobile-menu")?.focus();
    return;
  }
  if (action === "toggle-outline") {
    outlineOpen = !outlineOpen;
    render();
    if (outlineOpen && matchMedia("(max-width:760px)").matches)
      $(".academy-syllabus")?.scrollIntoView({ block: "start" });
    return;
  }
  if (action === "lesson-tab") {
    lessonTab = id;
    render();
    $("#tab-" + id)?.focus({ preventScroll: true });
    return;
  }
  if (action === "save-note") {
    state.notes[id] = $("#lesson-notes")?.value || "";
    persist();
    $("#notes-status").textContent = "Anotação salva neste navegador.";
    toast("Anotação salva nesta demonstração.");
    return;
  }
  if (action === "save-lesson") {
    state.saved = state.saved.includes(id)
      ? state.saved.filter((v) => v !== id)
      : [...state.saved, id];
    persist();
    render();
    return;
  }
  if (action === "rate-lesson") {
    const rating = Number(el.dataset.rating);
    state.ratings[id] = state.ratings[id] === rating ? 0 : rating;
    persist();
    render();
    return;
  }
  if (action === "notifications") {
    openModal(
      "Recados da equipe",
      state.notices
        .map((n) => `<h3>${esc(n.title)}</h3><p>${esc(n.body)}</p>`)
        .join(""),
    );
    return;
  }
  if (action === "locked") {
    toast("Conclua as aulas anteriores para continuar.");
    return;
  }
  if (action === "filter-course") {
    courseFilter = id;
    render();
    return;
  }
  if (action === "filter-client") {
    clientFilter = id;
    render();
    return;
  }
  if (action === "complete") {
    if (state.completed.includes(id))
      state.completed = state.completed.filter((v) => v !== id);
    else state.completed.push(id);
    persist();
    render();
    toast(
      state.completed.includes(id)
        ? "Mais uma etapa concluída. Seu progresso foi atualizado."
        : "Conclusão desfeita nesta demonstração.",
    );
    return;
  }
  if (action === "download") {
    const m = materials.find((v) => v.id === id);
    if (m)
      download(
        m.id + "." + m.ext,
        m.content,
        m.ext === "csv" ? "text/csv;charset=utf-8" : undefined,
      );
    return;
  }
  if (action === "show-password") {
    const input = el.previousElementSibling;
    input.type = input.type === "password" ? "text" : "password";
    el.setAttribute(
      "aria-label",
      input.type === "password" ? "Mostrar senha" : "Ocultar senha",
    );
    return;
  }
  if (action === "preview-video") {
    openModal(
      "Experimente o player",
      `<p>As aulas ainda não possuem vídeos cadastrados. Selecione um arquivo local para testar reprodução, controles e retomada.</p><label class="btn btn-primary file-button">${icon("play")} Selecionar vídeo<input id="local-video" type="file" accept="video/mp4,video/webm,video/quicktime,.mov"></label><p class="form-note">O vídeo fica apenas nesta sessão. Nenhum arquivo será enviado.</p>`,
    );
    $("#local-video").onchange = (ev) => {
      const f = ev.target.files[0];
      if (!f) return;
      if (!["video/mp4", "video/webm", "video/quicktime", "video/x-m4v"].includes(f.type) && !/\.mov$/i.test(f.name)) {
        toast("Selecione MP4 ou WebM compatível.");
        return;
      }
      const prior = mediaUrls.get(id);
      if (prior) URL.revokeObjectURL(prior);
      mediaUrls.set(id, URL.createObjectURL(f));
      modal.close();
      render();
    };
    return;
  }
  if (action === "client-form") {
    clientForm(id);
    return;
  }
  if (action === "course-form") {
    courseForm(id);
    return;
  }
  if (action === "edit-lesson") {
    lessonForm(null, null, id);
    return;
  }
  if (action === "add-lesson") {
    lessonForm(el.dataset.course, el.dataset.module);
    return;
  }
  if (action === "add-module") {
    openModal(
      "Novo módulo",
      `<form>${input("Título do módulo", "title", "", "text", 'required maxlength="120"')}${modalFooter()}</form>`,
      (fd) => {
        state.courses
          .find((c) => c.id === id)
          .modules.push({
            id: uid("modulo"),
            title: fd.get("title"),
            lessons: [],
          });
        persist();
        const createdCourse = state.courses.find((c) => c.id === id);
        void apiRequest("module.save", { method: "POST", body: JSON.stringify({ id: 0, course_id: /^\d+$/.test(String(id)) ? Number(id) : 0, title: fd.get("title"), description: "", status: "published" }) });
        modal.close();
        render();
        toast("Módulo adicionado.");
      },
    );
    return;
  }
  if (action === "invite") {
    openModal(
      "Convite de acesso",
      `<p>Este é um fluxo demonstrativo. No sistema integrado, o cliente receberá um link de uso único para definir sua senha.</p>${link("/convite", "Visualizar tela do convite " + icon("arrow"), "btn btn-primary")}`,
    );
    return;
  }
  if (action === "toggle-client") {
    const c = state.clients.find((v) => v.id === id);
    c.status = c.status === "inactive" ? "active" : "inactive";
    persist();
    render();
    toast("Status atualizado na demonstração.");
    return;
  }
  if (action === "publish-course") {
    const c = state.courses.find((v) => v.id === id);
    if (c.status !== "published" && !allLessons(c).length) {
      toast("Adicione pelo menos uma aula antes de publicar.");
      return;
    }
    c.status = c.status === "published" ? "draft" : "published";
    persist();
    render();
    return;
  }
  if (action === "archive-course") {
    openModal(
      "Remover trilha da demonstração?",
      `<p>A trilha será removida apenas dos dados locais desta prévia. Você pode restaurar os dados iniciais nas configurações.</p><form>${modalFooter("Remover da prévia")}</form>`,
      () => {
        state.courses = state.courses.filter((v) => v.id !== id);
        persist();
        modal.close();
        location.hash = "/admin/conteudos";
      },
    );
    return;
  }
  if (action === "move-course" || action === "move-block") {
    const a = action === "move-course" ? state.courses : state.settings.blocks,
      i = a.findIndex((v) => v.id === id),
      j = i + (direction === "up" ? -1 : 1);
    if (j >= 0 && j < a.length) [a[i], a[j]] = [a[j], a[i]];
    persist();
    render();
    return;
  }
  if (action === "toggle-block") {
    const b = state.settings.blocks.find((v) => v.id === id);
    b.enabled = !b.enabled;
    persist();
    render();
    return;
  }
  if (action === "settings-tab") {
    settingsTab = id;
    render();
    return;
  }
  if (action === "reset-brand") {
    state.settings = structuredClone(defaults);
    persist();
    render();
    toast("Identidade Morais restaurada.");
    return;
  }
  if (action === "view-group") {
    openModal(id, clientTable(state.clients.filter((c) => c.group === id)));
    return;
  }
  if (action === "open-file") {
    const f = state.files.find((v) => v.id === id),
      url = mediaUrls.get(id);
    if (!url) {
      toast("Selecione novamente o arquivo para visualizar nesta sessão.");
      return;
    }
    openModal(
      f.name,
      f.type.startsWith("video")
        ? `<video controls playsinline src="${esc(url)}" class="media-preview"></video>`
        : f.type.startsWith("image")
          ? `<img class="media-preview" src="${esc(url)}" alt="${esc(f.name)}">`
          : `<p>Arquivo disponível nesta sessão.</p><a class="btn btn-primary" href="${esc(url)}" download="${esc(f.name)}">${icon("download")} Baixar arquivo selecionado</a>`,
    );
    return;
  }
  if (action === "remove-file") {
    const url = mediaUrls.get(id);
    if (url) URL.revokeObjectURL(url);
    mediaUrls.delete(id);
    state.files = state.files.filter((v) => v.id !== id);
    persist();
    render();
    return;
  }
  if (action === "export-clients") {
    const lines = [
      ["Responsavel", "Empresa", "Email", "Status", "Progresso demonstrativo"],
      ...state.clients.map((c) => [
        c.name,
        c.company,
        c.email,
        c.status,
        c.id === "cliente-1" ? pct() : c.progress,
      ]),
    ];
    download(
      "clientes-demonstracao.csv",
      lines.map((row) => row.map(csvSafe).join(";")).join("\r\n"),
      "text/csv;charset=utf-8",
    );
    return;
  }
  if (action === "email-preview") {
    const name = [
      "Convite de acesso",
      "Recuperação de senha",
      "Lembrete de jornada",
    ][Number(id)];
    openModal(
      name,
      `<div class="email-preview">${logo()}<h2>Olá, Mariana.</h2><p>${Number(id) === 0 ? "Seu escritório preparou um espaço para acompanhar sua jornada." : Number(id) === 1 ? "Este é o exemplo visual da mensagem de recuperação de acesso." : "Que tal dar o próximo passo na sua jornada?"}</p>${link(Number(id) === 0 ? "/convite" : Number(id) === 1 ? "/redefinir" : "/inicio", "Acessar meu portal " + icon("arrow"), "btn btn-primary")}<p class="form-note">Modelo demonstrativo. Nenhum e-mail foi enviado.</p></div>`,
    );
    return;
  }
  if (action === "reset-demo") {
    openModal(
      "Restaurar a demonstração?",
      `<p>As alterações feitas nesta prévia serão substituídas pelos exemplos iniciais. Arquivos do seu computador não serão alterados.</p><form>${modalFooter("Restaurar demonstração")}</form>`,
      () => {
        state = fresh();
        for (const url of mediaUrls.values()) URL.revokeObjectURL(url);
        mediaUrls.clear();
        persist();
        modal.close();
        render();
        toast("Demonstração restaurada.");
      },
    );
  }
});
window.addEventListener("hashchange", () => {
  menuOpen = false;
  if (modal.open) modal.close();
  search = "";
  lessonTab = "description";
  render();
  window.scrollTo(0, 0);
  $("main h1")?.focus({ preventScroll: true });
});
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && menuOpen) {
    menuOpen = false;
    render();
    $(".academy-menu-trigger, .mobile-menu")?.focus();
  }
  if (e.key === "Escape") {
    const popover = $("#academy-search-popover");
    if (popover) popover.hidden = true;
  }
  if (
    ["ArrowLeft", "ArrowRight", "Home", "End"].includes(e.key) &&
    e.target.matches('.classroom-tabs [role="tab"]')
  ) {
    e.preventDefault();
    const keys = ["description", "materials", "notes"];
    const idx = keys.indexOf(lessonTab);
    lessonTab =
      e.key === "Home"
        ? keys[0]
        : e.key === "End"
          ? keys[2]
          : keys[(idx + (e.key === "ArrowRight" ? 1 : 2)) % 3];
    render();
    $("#tab-" + lessonTab)?.focus({ preventScroll: true });
  }
});
matchMedia("(max-width:760px)").addEventListener("change", () => {
  menuOpen = false;
  render();
});
window.addEventListener("pagehide", () => {
  const v = $("#lesson-video");
  if (v) {
    state.positions[route()[1]] = v.currentTime;
    persist();
  }
});
render();
void loadBackend();
