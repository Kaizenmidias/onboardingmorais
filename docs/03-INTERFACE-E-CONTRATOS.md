# Interface, editabilidade e contratos de integração

Especificação para a etapa visual, a iniciar após aprovação da arquitetura e recebimento da referência. Este documento não define cores, tipografia ou layout final antes da referência.

## 1. Fluxo de aprovação visual

1. Inventariar telas e componentes da referência, incluindo navegação, espaçamento, tipografia, capas, player e comportamento mobile.
2. Registrar quais elementos serão reproduzidos e quais exigem adaptação. Se faltar referência mobile/admin, apresentar a adaptação correspondente para revisão.
3. Construir a interface usando os contratos abaixo, dados demonstrativos e componentes compartilhados.
4. Comparar capturas com a referência em desktop e mobile, revisar conteúdo longo, erros e estados vazios.
5. Aprovar a direção visual antes de conectar autenticação, persistência e operações reais.

As simulações do protótipo ficam identificadas no ambiente de revisão. Botões devem demonstrar seus estados e fluxos, sem aparentar que salvaram dados reais em um backend ainda inexistente. Não inserir informações técnicas de implementação nas telas finais do produto.

## 2. Mapa de telas

| Área | Telas/fluxos previstos |
| --- | --- |
| Acesso | Login; convite válido, usado ou expirado; criação de senha; recuperação solicitada; redefinição; sessão expirada; MFA e recuperação administrativa, se aprovado |
| Cliente — início | Boas-vindas, continuar, cursos disponíveis, progresso, comunicados e blocos institucionais |
| Cliente — cursos | Listagem e busca de cursos atribuídos; curso com módulos/aulas; estados não iniciado, em andamento, concluído e expirado |
| Cliente — aula | Player, texto, materiais, sequência de aulas, conclusão/desfazer, anterior/próxima, suporte e retomada |
| Cliente — perfil | Dados de identificação, alteração de senha e contato do escritório. Alteração cadastral pelo cliente não faz parte do escopo inicial; admin edita |
| Cliente — ajuda | FAQ e WhatsApp; aviso de privacidade e termos configurados |
| Admin — dashboard | Indicadores, filtros, clientes sem atividade e acessos recentes |
| Admin — clientes | Lista, cadastro, edição, situação do convite, reenvio, ativação/bloqueio e concessões |
| Admin — cliente individual | Dados, grupos, acesso efetivo, validade, progresso por curso/módulo/aula e histórico |
| Admin — conteúdo | Cursos, módulos, aulas, ordenação, rascunho/publicação, prévia e arquivamento |
| Admin — mídia | Biblioteca, usos do arquivo, upload retomável, importação de arquivo, erro, quota e exclusão segura |
| Admin — grupos | Lista e membros; concessões de cursos, início e validade |
| Admin — personalização | Identidade, fontes, imagens, textos, blocos, menus, contatos, e-mails e prévia |
| Admin — comunicação | FAQs e comunicados simples, templates, envios com falha e reenvio |
| Admin — relatórios | Filtros, indicadores, visão individual e exportação CSV |
| Admin — operação | Contas administrativas, sessões/MFA, auditoria, solicitações de dados, diagnóstico, versão e estado das rotinas |
| Instalação | Chave inicial, verificação de requisitos, banco, primeiro admin, identidade inicial, SMTP e conclusão |

Erros da aplicação incluem 403, 404, manutenção, falha de conexão e serviço temporariamente indisponível. Não exibir stack traces ou credenciais ao cliente. O admin recebe diagnóstico apropriado ao seu perfil.

## 3. Catálogo de componentes e estados

Componentes compartilhados: marca, navegação desktop/mobile, breadcrumb, busca, filtros, cards de curso, barra de progresso, lista/accordion de módulos, linha de aula, player, materiais, campos, modal, confirmação, toast, paginação e blocos institucionais.

Player: capa, carregamento, reprodução, pausa, retomada, erro de formato/rede, acesso expirado e recurso indisponível. No mobile, reprodução inline quando suportada, controles de toque e tela cheia. Não depender de autoplay para iniciar aula nem carregar todos os vídeos da listagem.

Uploads: selecionado, verificando, enviando, pausado, retomando, concluído, cancelado, sessão expirada, arquivo incompatível, falta de espaço e conflito de deslocamento. Mostrar bytes enviados, tamanho total e possibilidade real de retomar; porcentagem de transferência não significa que o vídeo já foi validado/publicado.

Conteúdo: carregando, vazio, com muitos itens, não publicado, novo, bloqueado por sequência, expirado e arquivado. Botões de alteração possuem estados de envio, sucesso, falha e repetição segura.

## 4. Catálogo de campos editáveis

| Grupo | Exemplos de chaves estáveis | Validação e alcance |
| --- | --- | --- |
| Marca | `brand.name`, `brand.logo_light`, `brand.logo_dark`, `brand.favicon` | Arquivo permitido, proporção prevista e descrição acessível |
| Tema | `theme.primary`, `theme.surface`, `theme.text`, `theme.font_heading`, `theme.font_body` | Paleta tipada, fonte de catálogo e checagem de contraste com prévia |
| Institucional | `company.name`, `company.contact_email`, `company.phone`, `company.whatsapp` | Formato, tamanho e links gerados a partir de valores validados |
| Login | `auth.title`, `auth.subtitle`, `auth.background`, textos dos fluxos de acesso | Imagens e texto; sem substituir a lógica de autenticação |
| Início | `home.welcome_title`, `home.welcome_body`, banners e blocos | Regiões e tipos de blocos permitidos |
| Navegação | Rótulo, destino permitido, público, posição e visibilidade | Rotas internas conhecidas e links externos HTTPS permitidos |
| Interface | `ui.actions.*`, `ui.states.*`, `ui.validation.*`, `ui.errors.*` | Catálogo pesquisável, padrões e variáveis nomeadas documentadas |
| E-mails | Assunto, corpo e rodapé por template | Variáveis permitidas, links obrigatórios e prévia com dados fictícios |
| Privacidade | Aviso, contato e termos | Texto rico sanitizado e versão publicada |
| Regras | Sequência, prazos, limiar de inatividade e opções aprovadas | Limites e validação; permissões de edição administrativa |

Todos os textos produzidos pela aplicação usam a camada de tradução/configuração. Valores dinâmicos como nome do cliente e progresso vêm dos dados, com escape. O admin pode restaurar o padrão de uma chave e pesquisar onde ela é utilizada. O sistema impede publicação de texto que omita uma variável obrigatória, como o link de ativação de um convite.

Blocos: boas-vindas, continuar, cursos, progresso, banner, FAQ, comunicado, contatos e texto institucional. Cada região define os blocos admitidos e limites; alteração de ordem inclui alternativa por botões/teclado, além de arrastar. Configuração permite prévia e publicação em uma revisão, sem expor estados intermediários.

## 5. Contratos de dados para o frontend

View models são contratos independentes do formato das tabelas. Valores ausentes são explícitos; IDs enviados ao JavaScript são strings, datas usam ISO 8601 com fuso e percentuais são números limitados a 0–100. Textos exibidos passam pelo catálogo.

| Objeto | Campos principais |
| --- | --- |
| `BrandView` | Nome, logos por contexto, favicon, tokens validados, fontes e contatos |
| `CourseCard` | ID, título, capa, descrição curta, contagem de aulas, progresso, estado, validade e URL |
| `CourseOutline` | Curso, módulos, aulas, posição, concluída, acessível e motivo de bloqueio |
| `LessonView` | ID, título, HTML sanitizado, materiais, curso/módulo, anterior/próxima, conclusão e regras |
| `PlaybackView` | URL da rota autorizada, MIME, capa, duração opcional, posição salva e suporte a legendas |
| `AccessView` | Permitido, origem, início, expiração, motivo e ação de suporte |
| `ClientProgressView` | Curso, revisão atual, progresso, conclusão histórica, última atividade e linha do tempo |
| `UploadView` | ID, total, deslocamento confirmado, tamanho de parte, validade, estado e erro recuperável |
| `DashboardView` | Filtros, métricas com denominadores, intervalo, geração e paginação |

Nunca retornar caminho de disco, credencial, token de sessão ou chave interna nos view models. Links de mídia não substituem a checagem de acesso a cada requisição.

## 6. Superfície HTTP proposta

As rotas abaixo orientam a integração; não são endpoints já publicados. Usar nomes estáveis de rotas nos templates, para permitir alterar a URL sem reescrever links no conteúdo.

| Método / rota | Comportamento |
| --- | --- |
| `GET /login`, `POST /login`, `POST /logout` | Autenticação e encerramento de sessão |
| `GET /convite`, `POST /convite/aceitar` | Exibir convite e definir senha com token válido |
| `POST /senha/recuperar`, `POST /senha/redefinir` | Recuperação sem enumeração de e-mails |
| `GET /inicio`, `GET /cursos`, `GET /cursos/{id}` | Páginas autorizadas do cliente |
| `GET /aulas/{id}` | Aula autorizada e contexto do curso |
| `PUT /api/aulas/{id}/conclusao` | Estado desejado `completed: true/false`; idempotente |
| `PUT /api/aulas/{id}/retomada` | Posição e versão/instante da reprodução, validada no servidor |
| `GET/HEAD /midia/{id}` | Autoriza a mídia e delega a transferência ao servidor web |
| `GET/HEAD /materiais/{id}` | Autoriza e entrega material com cabeçalhos adequados |
| `/admin/clientes`, `/admin/grupos`, `/admin/cursos` | Rotas de lista/formulários e alterações protegidas por permissão |
| `POST /admin/uploads` | Abre sessão de upload e reserva quota |
| `HEAD /admin/uploads/{id}` | Informa deslocamento confirmado, após autorização |
| `PUT /admin/uploads/{id}/parte` | Corpo binário limitado; informa deslocamento e checksum da parte |
| `POST /admin/uploads/{id}/finalizar` | Valida estado e publica o asset como pronto, de forma idempotente |
| `DELETE /admin/uploads/{id}` | Cancela sessão e agenda limpeza segura |
| `POST /admin/midia/importacoes` | Importa arquivo validado da caixa de entrada privada |
| `GET /admin/relatorios/clientes.csv` | Exporta os dados permitidos com os filtros acordados |
| `/admin/personalizacao`, `/admin/diagnostico` | Configurações, publicação e diagnóstico restritos |

API da mesma origem usa sessão e CSRF. Não introduzir JWT ou tokens de integração externa sem necessidade. Alterações nunca são feitas por GET.

Resposta de erro JSON: `code` estável, mensagem resolvida pelo catálogo, erros de campos quando aplicável e `request_id` sem informação sensível. Status: 400 pedido inválido, 401 sessão ausente, 403 operação negada, 404 recurso não visível, 409 conflito de estado, 413 parte grande, 422 validação, 429 limitação e 503 manutenção/indisponibilidade. Vídeos autorizados seguem semântica HTTP de arquivo/Range, não envelope JSON de sucesso.

## 7. Responsividade e critérios visuais

- Inspecionar 360, 390, 768, 1024, 1366 e 1440 px, além de larguras intermediárias. Componentes se adaptam ao conteúdo, não apenas a modelos fixos de dispositivo.
- Sem rolagem horizontal da página em telas pequenas; tabelas administrativas recebem apresentação adequada, área rolável identificada ou versão em cards.
- Navegação operável por teclado, foco visível, labels associados, mensagens de erro vinculadas e contraste suficiente. Buscar WCAG 2.2 AA como critério de projeto, sem declarar certificação automática.
- Testar zoom, texto longo, nomes extensos, logo horizontal/vertical e cards sem capa. Respeitar redução de movimento.
- Player testado em Chrome/Edge/Firefox desktop e Safari iOS/Chrome Android nas versões acordadas. Legendas WebVTT podem ser anexadas pelo admin; a produção das legendas é responsabilidade editorial.
- Carregar imagens dimensionadas, fontes locais e JS apenas quando necessário. Vídeos não fazem preload integral por padrão.
- Realizar QA por comparação com a referência e registrar telas aprovadas. Mudança posterior de backend deve preservar os componentes e estados aprovados.

Referência para acessibilidade: [W3C — WCAG 2.2](https://www.w3.org/TR/WCAG22/). Critérios de desempenho e reprodução ficam na [homologação](05-OPERACAO-E-HOMOLOGACAO.md).
