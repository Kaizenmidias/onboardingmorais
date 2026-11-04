# Requisitos, entregas e decisões pendentes

Consolidação do levantamento de 03/11/2026 e das respostas complementares. Este documento distingue requisito confirmado, padrão proposto e dependência externa.

## 1. Rastreabilidade

| ID | Requisito | Tratamento / aceite |
| --- | --- | --- |
| RF01 | PHP, MySQL/MariaDB, independente do WordPress | Aplicação própria, stack e banco definidos na arquitetura |
| RF02 | Produção em hospedagem compartilhada, sem instalações por terminal | ZIP com dependências e assets; instalação limpa via painel/navegador em host homologado |
| RF03 | Testar em `onboarding.kaizenmidias.com` | aaPanel e IP informados; DNS/HTTPS/virtual host pendentes de acesso e preparação |
| RF04 | GitHub como base oficial e branch adequada | Clone oficial, branch de documentação e fluxo de PR/release; `main` sem alteração direta |
| RF05 | Vídeos locais acima de 2 GB | PHP 64 bits, offsets/tamanhos grandes, upload em partes e importação; ensaio >= 3 GiB |
| RF06 | Reprodução sem transferir vídeo pelo PHP | Adaptador privado do servidor obrigatório; validação prática de offload e Range |
| RF07 | Avaliar mais de 50 espectadores | Cenário de expansão com 60; uso habitual atualizado para menos de 10; publicar capacidades testadas separadamente |
| RF08 | Referência visual fiel e responsiva | Receber referência, inventário, capturas e aprovação desktop/tablet/mobile antes do backend |
| RF09 | Identidade white label completa | Marca, fontes, cores, imagens, textos, contatos e e-mails no painel; sem edição de código |
| RF10 | Cursos/trilhas, módulos e aulas | Hierarquia única de três níveis; múltiplos cursos por instalação |
| RF11 | CRUD, publicação e ordenação | Editor administrativo, prévia, estados e exclusão/arquivamento conforme histórico |
| RF12 | Vídeos, textos, imagens, PDFs, planilhas, links e downloads | Biblioteca e materiais vinculados; validação e autorização |
| RF13 | Conceder cursos por cliente/grupo e expirar | Política determinística, origem de acesso visível e testes de prazos |
| RF14 | Botão de conclusão e progresso | Operação idempotente, cálculos por aula/módulo/curso/cliente e histórico |
| RF15 | Última aula e continuar de onde parou | Posição de vídeo e última aula; tratamento de conteúdo removido/bloqueado |
| RF16 | Sequência opcional | Livre por padrão; bloqueio também no backend/mídia quando ativado |
| RF17 | Sem cadastro público; convite e recuperação | Admin cadastra, token de uso único, SMTP e criação de senha pelo cliente |
| RF18 | Dados cadastrais informados e edição pelo admin | Campos descritos; CNPJ numérico e alfanumérico; validação no servidor |
| RF19 | Admin e cliente; futuros colaboradores | Catálogo de permissões, perfis fixos iniciais e espaço para evolução sem UI de RBAC livre |
| RF20 | Agência com acesso próprio | Contas nominativas explícitas por instalação, auditadas e revogáveis |
| RF21 | Blocos/seções editáveis sem page builder | Tipos predefinidos com ativação, edição, ordem e prévia |
| RF22 | WhatsApp configurável | Abrir conversa; sem envio automatizado |
| RF23 | Convite, recuperação e notificações | Convites/recuperação obrigatórios; avisos simples e fila; lembretes dependem de Cron |
| RF24 | Indicadores e página individual | Definições de ativo, inatividade, possível abandono e média documentadas |
| RF25 | Exportação desejável | CSV compatível com Excel incluído na v1 proposta; XLSX nativo posterior |
| RF26 | Instalador web e replicação | Banco existente configurado, schema criado, primeiro admin e defaults; segunda instalação como aceite |
| RF27 | Atualizar preservando dados e marca | Migrações versionadas, backup e restauração, sem customização no núcleo |
| RF28 | Segurança e materiais privados | Autorização, sessões, CSRF, uploads, auditoria e proteção de arquivos |
| RF29 | LGPD desde a arquitetura | Minimização, política de retenção, direitos, exportação/exclusão e responsabilidades operacionais |
| RF30 | Avaliar FAQ, comunicados e formulários | FAQ/comunicados simples propostos na v1; formulários/questionários reservados para extensão |
| RF31 | Não iniciar desenvolvimento completo antes das aprovações | Etapas e pontos de revisão abaixo; esta entrega contém documentação e organização do repositório |

## 2. Escopo proposto da primeira versão

Inclui todos os fluxos centrais de acesso, conteúdo, mídia privada, conclusão manual, grupos, expiração, sequência, identidade e textos editáveis, blocos, admin, progresso, CSV, instalador, atualização assistida, diagnóstico e auditoria. FAQ e comunicados são simples. Convites e recuperação funcionam por SMTP.

Escopo de segurança adicional proposto para aprovação: MFA TOTP em contas administrativas. Não foi exigido explicitamente no levantamento; está apresentado como decisão de projeto e deve entrar também no mapa visual se aprovado.

Não inclui na v1: SaaS central, painel central da agência, pagamentos, aplicativo nativo, provas/notas/certificados, comentários/comunidade, chamados, coleta de documentos de clientes, integração com sistemas contábeis, automação de WhatsApp, DRM, transcodificação/HLS, construtor livre de páginas, XLSX nativo e editor livre de perfis de colaboradores.

Formulários/questionários dentro das aulas: manter ponto de extensão para um futuro tipo de atividade com definição versionada, respostas, autorização, retenção e revisão pelo admin. Não tratar isso como um campo HTML livre inserido numa aula. Fechar esse escopo antes de implementá-lo.

## 3. Etapas e pontos de revisão

| Etapa | Entrega | Condição para avançar |
| --- | --- | --- |
| A — Arquitetura | Estes seis documentos, requisitos rastreáveis e decisões propostas | Revisão e aprovação do responsável pelo produto |
| B — Ambiente | DNS, HTTPS, site de teste, requisitos e diagnóstico registrados | Acessos aaPanel/DNS e configuração confirmados; não exige backend completo |
| C — Direção visual | Referência analisada, tokens, componentes e mapa de telas | Referência fornecida e direção visual revisada |
| D — Frontend | Telas cliente/admin, estados, responsividade e dados demonstrativos | Aprovação visual registrada |
| E — Implementação | Autenticação, banco, painel, conteúdo, arquivos, progresso e comunicação | Regras e integrações verificadas; capacidade sem afirmações não medidas |
| F — Homologação e distribuição | Instalação limpa/replicada, atualização/restauração e relatório de capacidade | Aceite funcional, visual, operacional e do perfil de hospedagem |

Desenvolvimento e preparação do ambiente usam o repositório oficial. Cada etapa é publicada com branch/commit identificável e notas do que é simulado ou real. Não implementar telas definitivas antes da referência; não integrar backend completo antes do aceite visual.

## 4. Pendências externas atuais

| Item | Necessário para |
| --- | --- |
| Aprovação desta arquitetura | Avançar conforme a metodologia solicitada |
| Referência visual e assets autorizados | Construir direção visual e frontend |
| URL/acesso ao aaPanel e acesso à zona Cloudflare | Preparar o domínio e inspecionar o servidor informado |
| Software web e versões reais do ambiente de testes | Escolher/configurar adaptador de mídia e confirmar requisitos |
| Provedor/plano de produção que permita os vídeos e tenha offload privado | Certificar instalação compartilhada; HostGator de referência não foi homologada |
| Duração, bitrate/resolução e arquivo de amostra > 2 GB | Dimensionamento e testes reais de vídeo |
| SMTP do remetente e DNS de e-mail | Convites/recuperação e entregabilidade; SPF/DKIM/DMARC conforme o serviço escolhido |
| Responsáveis, destino e política de backup/retenção | Validar RPO/RTO, privacidade e continuidade |

Não é necessário enviar senhas em documentação ou no Git. Usar sessão autenticada ou canal apropriado de credenciais na execução operacional. A falta desses acessos não impede a revisão do desenho lógico.

## 5. Padrões a revisar na aprovação

As seguintes decisões foram preenchidas para tornar o planejamento implementável, sem exigir novo questionário extenso:

- Curso/trilha/jornada representa o mesmo nível nesta versão; coleção de vários cursos não é obrigatória.
- Exceção individual prevalece sobre grupo; bloqueio explícito impede concessões indiretas.
- Novas aulas alteram o percentual atual, preservando a conclusão histórica e sua revisão.
- Cliente pode desfazer conclusão; admin pode resetar com motivo.
- Empresa, responsável, e-mail e CNPJ obrigatórios; pelo menos telefone ou WhatsApp.
- Inatividade operacional com padrão inicial de 14 dias, configurável; convite pendente e nunca iniciou separados.
- CSV na v1; formulários/questionários e XLSX nativo posteriores.
- MFA administrativo proposto; cliente por senha.
- Prazos de convite/sessão, retenção operacional e metas de backup/capacidade são valores iniciais de projeto, sujeitos à aprovação e ao contrato operacional.

## 6. Critério de conclusão desta entrega

Documentação consistente, arquivos versionados em branch separada, links locais verificados e registro explícito das limitações conhecidas. Aprovação do usuário, configuração do servidor, integração funcional e homologação de carga são marcos futuros e não serão declarados concluídos por esta entrega documental.
