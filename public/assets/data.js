export const defaults = {
  brand: {
    name: "Morais Contabilidade",
    portal: "Portal do cliente",
    primary: "#FF6A1A",
    navy: "#0F172B",
    font: "Manrope",
    logo: "assets/brand/morais-logo.png",
    email: "atendimento@moraiscontabilidade.com",
    whatsapp: "557532621902",
  },
  texts: {
    welcome: "Seu próximo passo começa aqui.",
    welcomeBody:
      "Tudo o que você precisa para começar sua jornada com a Morais, em um só lugar.",
    heroEyebrow: "BEM-VINDO À MORAIS",
    heroTitle:
      "Mais clareza para o seu negócio.\nMais tranquilidade para você.",
    heroBody:
      "Conheça nossos processos, organize sua rotina e descubra como vamos caminhar juntos.",
    coursesTitle: "Suas trilhas de conhecimento",
    catalogTitle: "Essenciais para sua jornada",
    continueTitle: "Continuar assistindo",
    catalogSearch: "Procurar por nome de curso ou aula",
    supportTitle: "Conte com a nossa equipe",
    supportBody: "Uma dúvida no caminho? Estamos por perto para ajudar.",
    loginTitle: "Que bom ter você por aqui.",
    loginBody:
      "Acesse sua área e dê o próximo passo na sua jornada com a Morais.",
    navHome: "Visão geral",
    navCourses: "Minhas trilhas",
    navMaterials: "Materiais de apoio",
    navProgress: "Meu progresso",
    navHelp: "Central de ajuda",
  },
  blocks: [
    { id: "hero", label: "Banner de boas-vindas", enabled: true },
    { id: "continue", label: "Continuar aprendendo", enabled: true },
    { id: "courses", label: "Trilhas de conhecimento", enabled: true },
    { id: "notice", label: "Comunicados", enabled: true },
  ],
};
const lesson = (id, title, duration, kind = "video") => ({
  id,
  title,
  duration,
  kind,
});
export const seedCourses = [
  {
    id: "primeiros-passos",
    title: "Primeiros passos com a Morais",
    category: "Comece por aqui",
    description:
      "Conheça sua equipe, nossos canais e os processos que vão facilitar o seu dia a dia.",
    tone: "navy",
    icon: "compass",
    status: "published",
    sequence: false,
    modules: [
      {
        id: "m1",
        title: "Boas-vindas à sua nova contabilidade",
        lessons: [
          lesson("l1", "Uma nova parceria começa aqui", "04:30"),
          lesson("l2", "Conheça a equipe que cuida de você", "06:15"),
          lesson("l3", "Nossos canais de atendimento", "05:20"),
        ],
      },
      {
        id: "m2",
        title: "Organizando os primeiros passos",
        lessons: [
          lesson("l4", "Acessando suas ferramentas", "08:10"),
          lesson("l5", "Como organizar seus documentos", "07:45"),
          lesson("l6", "O que enviar para a contabilidade", "06:30"),
          lesson("l7", "Prazos e responsabilidades", "05:50"),
        ],
      },
      {
        id: "m3",
        title: "Uma rotina mais tranquila",
        lessons: [
          lesson("l8", "Acompanhando as entregas", "06:10"),
          lesson("l9", "Sua primeira reunião de acompanhamento", "05:00"),
          lesson("l10", "Próximos passos da nossa parceria", "04:20"),
        ],
      },
    ],
  },
  {
    id: "rotina-financeira",
    title: "Sua rotina financeira mais simples",
    category: "Gestão e organização",
    description:
      "Orientações práticas para organizar as informações financeiras da sua empresa.",
    tone: "orange",
    icon: "chart",
    status: "published",
    sequence: false,
    modules: [
      {
        id: "m4",
        title: "Organização financeira",
        lessons: [
          lesson("f1", "Separando as contas da empresa", "06:00"),
          lesson("f2", "Organização de entradas e saídas", "07:30"),
          lesson("f3", "Preparando suas informações", "05:00"),
        ],
      },
      {
        id: "m5",
        title: "Informação para decidir",
        lessons: [
          lesson("f4", "Conheça seus relatórios", "08:00"),
          lesson("f5", "Conferindo a documentação mensal", "06:00"),
          lesson("f6", "Conversando com seu contador", "05:00"),
        ],
      },
    ],
  },
  {
    id: "ferramentas",
    title: "Conheça suas ferramentas digitais",
    category: "Tecnologia a seu favor",
    description:
      "Mais autonomia e praticidade para acessar os recursos da sua contabilidade.",
    tone: "sage",
    icon: "layers",
    status: "published",
    sequence: false,
    modules: [
      {
        id: "m6",
        title: "Seu escritório na palma da mão",
        lessons: [
          lesson("t1", "Apresentação do portal do cliente", "04:00"),
          lesson("t2", "Encontrando seus documentos", "05:00"),
          lesson("t3", "Acessando pelo celular", "03:30"),
          lesson("t4", "Boas práticas de acesso", "05:00"),
        ],
      },
    ],
  },
];
// Cursos ilustrativos extras para revisar a apresentação de um catálogo completo.
seedCourses.push(
  ...[
    [
      "documentos",
      "Documentos e prazos, sem complicação",
      "Organização da rotina",
      "file",
      ["Preparando seus documentos", "Uma rotina mensal organizada"],
    ],
    [
      "atendimento",
      "Uma equipe perto de você",
      "Relacionamento e suporte",
      "message",
      ["Como falar com a sua equipe", "Acompanhando suas solicitações"],
    ],
    [
      "planejamento",
      "Mais clareza para decidir",
      "Gestão e planejamento",
      "chart",
      ["Seu primeiro acompanhamento", "Conheça seus próximos passos"],
    ],
  ].map(([id, title, category, icon, titles]) => ({
    id,
    title,
    category,
    icon,
    description:
      "Orientações para facilitar a jornada da sua empresa com o escritório.",
    tone: "navy",
    status: "published",
    sequence: false,
    modules: [
      {
        id: id + "-modulo",
        title: category,
        lessons: titles.map((title, i) => lesson(id + "-" + i, title, "05:00")),
      },
    ],
  })),
);

export const seedClients = [
  {
    id: "cliente-1",
    name: "Mariana Oliveira",
    company: "Empresa Exemplo",
    email: "mariana@example.com",
    phone: "",
    whatsapp: "",
    cnpj: "",
    status: "active",
    group: "Novos clientes",
    progress: 40,
    last: "Hoje, às 09:42",
    courses: ["primeiros-passos", "rotina-financeira", "ferramentas"],
    expires: "",
  },
  {
    id: "cliente-2",
    name: "Ricardo Santos",
    company: "Comércio Modelo",
    email: "ricardo@example.com",
    phone: "",
    whatsapp: "",
    cnpj: "",
    status: "active",
    group: "Novos clientes",
    progress: 70,
    last: "Ontem, às 15:10",
    courses: ["primeiros-passos"],
    expires: "",
  },
  {
    id: "cliente-3",
    name: "Ana Costa",
    company: "Estúdio Exemplo",
    email: "ana@example.com",
    phone: "",
    whatsapp: "",
    cnpj: "",
    status: "invited",
    group: "Novos clientes",
    progress: 0,
    last: "Aguardando primeiro acesso",
    courses: ["primeiros-passos"],
    expires: "",
  },
  {
    id: "cliente-4",
    name: "Pedro Lima",
    company: "Serviços Modelo",
    email: "pedro@example.com",
    phone: "",
    whatsapp: "",
    cnpj: "",
    status: "active",
    group: "Serviços",
    progress: 25,
    last: "Há 16 dias",
    courses: ["primeiros-passos", "rotina-financeira"],
    expires: "",
  },
  {
    id: "cliente-5",
    name: "Juliana Souza",
    company: "Ateliê Exemplo",
    email: "juliana@example.com",
    phone: "",
    whatsapp: "",
    cnpj: "",
    status: "inactive",
    group: "Serviços",
    progress: 10,
    last: "Há 22 dias",
    courses: ["primeiros-passos"],
    expires: "",
  },
];
export const materials = [
  {
    id: "guia",
    title: "Guia de boas-vindas",
    course: "primeiros-passos",
    type: "Guia",
    ext: "txt",
    size: "2 KB",
    content:
      "GUIA DE BOAS-VINDAS — CONTEUDO DEMONSTRATIVO\n\n1. Conheca os canais de atendimento.\n2. Explore suas trilhas.\n3. Organize seus documentos.\n\nEste material e ilustrativo para revisao do portal. Nao contem orientacao contabil oficial.",
  },
  {
    id: "planilha",
    title: "Modelo de organização mensal",
    course: "rotina-financeira",
    type: "Planilha",
    ext: "csv",
    size: "1 KB",
    content:
      "Descricao;Categoria;Observacao\nExemplo de entrada;Recebimento;Dado ficticio\nExemplo de saida;Despesa;Dado ficticio",
  },
  {
    id: "checklist",
    title: "Checklist dos primeiros passos",
    course: "primeiros-passos",
    type: "Checklist",
    ext: "txt",
    size: "1 KB",
    content:
      "CHECKLIST DEMONSTRATIVO\n[ ] Conhecer a equipe\n[ ] Conferir os canais de atendimento\n[ ] Explorar suas ferramentas\n[ ] Concluir a trilha de boas-vindas",
  },
  {
    id: "acesso",
    title: "Como acessar pelo celular",
    course: "ferramentas",
    type: "Guia",
    ext: "txt",
    size: "1 KB",
    content:
      "MATERIAL DEMONSTRATIVO\nAbra o endereco do portal no navegador do seu celular.\nUtilize seu acesso individual.\nEm caso de duvida, fale com a equipe do escritorio.",
  },
];
export const faqs = [
  [
    "Como funciona minha jornada de onboarding?",
    "As trilhas reúnem os primeiros passos da sua parceria com o escritório. Acesse os módulos, assista às aulas e marque cada etapa como concluída. Você pode continuar de onde parou.",
  ],
  [
    "Preciso assistir às aulas em uma ordem específica?",
    "Nesta prévia, a navegação é livre. O escritório poderá definir uma sequência para cada trilha, quando necessário.",
  ],
  [
    "Onde encontro os materiais de apoio?",
    "Na página da aula e na seção Materiais de apoio. Os arquivos desta demonstração são exemplos ilustrativos.",
  ],
  [
    "Posso acessar pelo celular?",
    "Sim. O portal foi preparado para desktop, tablet e celular. Seu progresso acompanha o acesso da sua empresa.",
  ],
  [
    "Como entro em contato com o escritório?",
    "Use o botão Falar com a equipe. Ele abre uma conversa no WhatsApp configurado pelo escritório.",
  ],
];
export const icons = {
  home: '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
  book: '<path d="M4 4h6a3 3 0 0 1 3 3v14a4 4 0 0 0-4-2H4zM20 4h-4a3 3 0 0 0-3 3v14a4 4 0 0 1 4-2h3z"/>',
  folder:
    '<path d="M3 7V5a1 1 0 0 1 1-1h5l2 3h9a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/>',
  chart: '<path d="M4 3v17h17M8 15v-4m5 4V7m5 8V4"/>',
  help: '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4m0 3h.01"/>',
  arrow: '<path d="M5 12h14m-6-6 6 6-6 6"/>',
  left: '<path d="m14 6-6 6 6 6"/>',
  chevron: '<path d="m9 5 7 7-7 7"/>',
  down: '<path d="m6 9 6 6 6-6"/>',
  play: '<path d="m9 5 11 7-11 7z"/>',
  check: '<path d="m5 12 4 4L19 6"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  search: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/>',
  bell: '<path d="M5 16h14l-2-3V9a5 5 0 0 0-10 0v4zM10 20h4"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
  users:
    '<path d="M16 21v-2a6 6 0 0 0-12 0v2m16 0v-2a6 6 0 0 0-3-5M16 3a4 4 0 0 1 0 8"/><circle cx="10" cy="7" r="4"/>',
  settings:
    '<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3"/><circle cx="15" cy="17" r="3"/>',
  compass: '<circle cx="12" cy="12" r="9"/><path d="m16 8-2 6-6 2 2-6z"/>',
  layers: '<path d="m12 3 10 5-10 5L2 8zM2 12l10 5 10-5M2 17l10 5 10-5"/>',
  message:
    '<path d="M21 11a9 9 0 0 1-13 8l-5 2 1-5a9 9 0 1 1 17-5z"/><path d="M8 11h8m-8 4h5"/>',
  download: '<path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/>',
  file: '<path d="M14 3H5v18h14V8zM14 3v5h5M8 12h8m-8 4h6"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  close: '<path d="m6 6 12 12M6 18 18 6"/>',
  menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
  logout: '<path d="M9 4H4v16h5m4-13 5 5-5 5m-5-5h14"/>',
  edit: '<path d="m15 4 5 5-11 11H4v-5zM13 6l5 5"/>',
  upload: '<path d="M12 16V3m-5 5 5-5 5 5M4 16v5h16v-5"/>',
  shield:
    '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6z"/><path d="m8 12 3 3 5-6"/>',
  eye: '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
  trophy:
    '<path d="M8 3h8v7a4 4 0 0 1-8 0zM8 5H4v3a4 4 0 0 0 4 4m8-7h4v3a4 4 0 0 1-4 4M12 14v6m-4 1h8"/>',
  grid: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
  lock: '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>',
  calendar:
    '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18"/>',
  mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
  spark: '<path d="m12 2 3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>',
  trash: '<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/>',
};
