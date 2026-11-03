# Prévia visual 0.1.0 — Morais

Primeira etapa de implementação, conforme a sequência de aprovação do projeto: frontend navegável com dados ilustrativos. A área administrativa é aberta para revisão e **não possui autenticação real**. Usar apenas dados fictícios.

## Abrir no computador

Requisitos de desenvolvimento: PHP 8.4 no PATH. Nenhum pacote Composer ou Node é necessário para executar a prévia.

Na raiz do repositório, execute:

```powershell
.\scripts\preview.ps1
```

Ou diretamente:

```text
php -S 127.0.0.1:8085 -t public
```

Abra `http://127.0.0.1:8085/`. A navegação usa fragmentos `#/` nesta etapa; funciona sem configurar rewrite. O servidor local não é um servidor de produção. Use Ctrl+C no terminal para encerrá-lo.

## O que revisar

- Área do cliente: visão geral, trilhas, curso/módulos, aula/player, materiais, progresso, ajuda e perfil.
- Acesso: login, recuperação, convite e nova senha como fluxos demonstrativos. Os formulários não armazenam nem transmitem credenciais.
- Admin: dashboard, clientes, ficha individual, trilhas, editor de módulos/aulas, grupos, biblioteca, comunicação, relatórios, personalização e configuração do ambiente.
- Identidade: nome, logo, cores, fonte, contatos, mensagens, títulos, rótulos da navegação e ordem/visibilidade dos blocos principais.
- Interações: filtros, busca, conclusão/desfazer, retomada de vídeo local, cadastro/edição de cliente, status, edição de curso/aula, módulos, ordenação de cursos, publicação demonstrativa e exportação CSV.
- Mobile: menu lateral adaptado, formulários, leitura da aula e listas administrativas apresentadas em cards nas telas estreitas.

Os gráficos, atividades, horários e indicadores de outros clientes são ilustrativos. O progresso da conta demonstrativa é calculado a partir das aulas concluídas. Arquivos de exemplo são identificados e baixados no formato indicado.

Vídeos oficiais ainda não foram fornecidos. A sala de aula permite selecionar MP4/WebM do computador para testar o player HTML5; o arquivo não é enviado. A biblioteca guarda metadados demonstrativos. Após recarregar, selecione novamente o arquivo para abri-lo. A posição do vídeo e demais ajustes são salvos localmente quando o navegador permite.

O recurso de personalização desta entrega demonstra os campos principais; o catálogo completo de textos, menus, imagens, e-mails e permissões editáveis do produto será integrado ao backend nas próximas etapas. Não considerar todos os requisitos funcionais da arquitetura implementados nesta versão.

## Persistência e limites

- Dados de demonstração ficam em `localStorage`, chave `morais-visual-v1`, exclusivamente no navegador/origem em que a prévia foi aberta.
- Nenhuma conexão MySQL, autenticação, e-mail, upload para servidor ou autorização de mídia foi implementada nesta etapa.
- Alterações feitas no computador não se propagam para outro navegador ou para a hospedagem.
- Arquivos locais selecionados usam URLs temporárias da sessão; o conteúdo binário não é salvo em `localStorage`.
- Em Admin → Configurações, use “Restaurar dados da demonstração” para voltar aos exemplos iniciais.
- O arquivo de entrada define CSP, política de referência e `noindex`. Isso não transforma uma demonstração aberta em área privada.

## Publicar a prévia no aaPanel

Ambiente informado pelo responsável:

- Domínio: `https://onboarding.kaizenmidias.com/`.
- Diretório atual: `/www/wwwroot/onboarding.kaizenmidias.com`.
- PHP 8.4 e SSL já configurados.

Gerar o pacote no computador:

```powershell
.\scripts\package-preview.ps1
```

O script usa PHP e Node **somente para checagem local** e gera `dist/morais-previa-visual-0.1.0.zip` com `index.php`, `robots.txt` e `assets/`. O pacote não contém `.git`, documentação, scripts, credenciais ou dados de uma instalação.

No aaPanel, guardar uma cópia da página atual fora do diretório público e extrair o conteúdo do ZIP no diretório informado. Conferir que `index.php` é o documento inicial; uma página `index.html` existente pode ter prioridade conforme a configuração. Não enviar a raiz inteira do repositório ao diretório público.

Também é possível publicar o repositório e configurar o diretório de execução do site como `public/`. A futura aplicação PHP terá diretórios privados, migrações e requisitos adicionais; seu processo de instalação está na arquitetura.

A conexão ao aaPanel e a publicação remota não foram realizadas nesta entrega. O pacote permite revisar o layout sem utilizar o banco já criado. Antes de operar com clientes reais, concluir backend, autenticação, permissões, mídia privada, instalador e homologação.

## Identidade e procedência dos assets

Fonte visual: [site institucional da Morais](https://moraiscontabilidade.com/), fornecido pelo responsável pelo projeto. Foram reutilizados logo, favicon e imagem institucional, apenas como identidade desta instalação de referência.

Tokens observados na configuração visual do site: laranja `#FF6A1A`, azul-marinho `#0F172B`, fundo claro `#F2F3F9`; fonte Manrope. As formas decorativas das capas e os ícones são elementos SVG/CSS do projeto. A proposta de interface EAD é uma adaptação nova da identidade institucional; não foi fornecida uma referência específica de layout EAD.

Fontes WOFF2 servidas localmente, com a licença Manrope OFL em `public/assets/fonts/OFL.txt`. Imagens da marca não compõem uma licença genérica de redistribuição: cada novo escritório deve substituir sua identidade e conteúdo.

## Validação desta entrega

PHP e JavaScript verificados quanto à sintaxe. Navegação e estados principais inspecionados no navegador; o relatório de revisão registra as verificações executadas e limites. A aprovação visual do responsável continua pendente, assim como testes em Safari/iOS/Android reais e todas as integrações de backend.
