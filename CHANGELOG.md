# Histórico de alterações

## Núcleo funcional — 04/11/2026

- Adiciona schema MySQL/MariaDB para instalações independentes, usuários, clientes, cursos, módulos, aulas, mídia, acesso, progresso e auditoria.
- Adiciona instalador web compatível com aaPanel/cPanel para configurar banco, primeiro administrador e identidade inicial.
- Adiciona sessões, login, configurações white label persistidas e endpoints de CRUD para conteúdos e clientes.
- Adiciona upload de vídeo em partes de 8 MiB, armazenamento privado e limite inicial de 10 GiB por arquivo.
- Mantém fallback demonstrativo local enquanto o banco não estiver instalado.
- Adiciona pacote funcional separado com a estrutura correta para instalação no diretório do subdomínio.

## Prévia visual 0.2.0 — 03/11/2026

- Redesenha o cliente a partir das duas capturas Avante fornecidas: catálogo escuro, cabeçalho horizontal, menu recolhido, banner central, continuar assistindo e capas verticais.
- Reorganiza a sala de aula com player amplo, painel de módulos à direita, pesquisa interna, progresso segmentado e navegação anterior/próxima.
- Demonstra abas de descrição, materiais e anotações, avaliação e aulas salvas, com persistência exclusiva no navegador.
- Inclui busca por curso/aula sem exigir acentos, filtros combinados e seis cursos ilustrativos para revisão do catálogo.
- Permite selecionar capas vertical e horizontal no editor administrativo da demonstração.
- Adapta catálogo, sala de aula e navegação para celular/tablet; preserva o painel administrativo existente.
- Atualiza o pacote público para 0.2.0; backend e publicação remota continuam na etapa seguinte.

## Prévia visual 0.1.0 — 03/11/2026

- Adiciona interface responsiva do cliente e do admin com identidade da Morais, Manrope local, logo e imagem institucional.
- Implementa navegação demonstrativa, progresso local, filtros, cadastro/edição de clientes, conteúdo, personalização e exportação CSV.
- Adiciona prévia de player HTML5 com vídeo local, biblioteca demonstrativa e fluxos visuais de acesso, sem envio de credenciais.
- Prepara scripts de execução e pacote contendo apenas os arquivos públicos para aaPanel/PHP 8.4.
- Mantém backend, MySQL, autenticação, uploads reais e instalação funcional na etapa posterior ao aceite visual.

## Arquitetura v1.0 — 03/11/2026

- Consolida o levantamento de requisitos respondido pelo responsável pelo produto.
- Define aplicação PHP independente, instalações por escritório, personalização e entrega por pacote.
- Especifica modelo de dados, acesso por cliente/grupo, expiração, progresso e contratos de interface.
- Define upload em partes e importação de vídeos grandes, com entrega privada pelo servidor web.
- Separa uso habitual de até 10 espectadores do cenário de expansão para 60.
- Documenta restrições de hospedagem, aaPanel de testes, DNS, instalação, atualização e homologação.
- Mantém frontend e backend aguardando as aprovações previstas; não adiciona funcionalidades executáveis.
