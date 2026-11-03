# Portal de Onboarding Contábil White Label

Portal independente do WordPress, com uma instalação por escritório, desenvolvido para PHP e MySQL/MariaDB em hospedagens compatíveis.

## Estado do projeto

Arquitetura v1.0 consolidada, prévia visual 0.2.0 aprovada e núcleo funcional iniciado. O portal segue a estrutura de catálogo e sala de aula das capturas fornecidas pelo responsável. A integração funcional usa PHP/PDO/MySQL, instalador web, sessões reais, white label persistente e upload retomável de vídeos.

O ambiente atual usa **aaPanel**, PHP **8.4**, diretório **`/www/wwwroot/onboarding.kaizenmidias.com`** e domínio **https://onboarding.kaizenmidias.com/**, com SSL informado como configurado. A prioridade atual é esse ambiente; a portabilidade para hospedagem compartilhada permanece no planejamento de replicação.

## Revisar a interface

Execute `php -S 127.0.0.1:8085 -t public` e abra `http://127.0.0.1:8085/`. Não é necessário instalar dependências para executar esta prévia. O aviso superior permite alternar entre cliente e admin.

Leia o [guia da prévia visual e publicação no aaPanel](docs/07-PREVIA-VISUAL.md). A prévia continua funcionando localmente sem banco; quando instalada, o portal usa o instalador em `install.php`, banco MySQL/MariaDB e sessões reais.

## Documentação

| Documento | Conteúdo |
| --- | --- |
| [Arquitetura](docs/01-ARQUITETURA.md) | Stack, componentes, segurança, white label e decisões estruturais |
| [Dados e regras](docs/02-DADOS-E-REGRAS.md) | Entidades, integridade, autorização, progresso e relatórios |
| [Interface e contratos](docs/03-INTERFACE-E-CONTRATOS.md) | Telas, campos editáveis, estados e conexão entre front e backend |
| [Mídia e capacidade](docs/04-MIDIA-E-CAPACIDADE.md) | Arquivos acima de 2 GB, uploads, entrega privada e dimensionamento |
| [Operação e homologação](docs/05-OPERACAO-E-HOMOLOGACAO.md) | DNS, aaPanel, cPanel, instalador, atualizações, backups e testes |
| [Requisitos e entregas](docs/06-REQUISITOS-E-ENTREGAS.md) | Rastreabilidade, fases, critérios de aceite e pendências |
| [Prévia visual](docs/07-PREVIA-VISUAL.md) | Executar, revisar, empacotar e publicar a etapa visual |
| [Histórico de alterações](CHANGELOG.md) | Mudanças versionadas |

## Condição para vídeos locais

A produção exige hospedagem que permita esse uso e disponibilize entrega privada de arquivos pelo servidor web. Não se presume que um plano compartilhado suporte vídeos grandes ou uma determinada simultaneidade. A análise das restrições atuais da HostGator e os critérios de homologação estão no documento de [mídia e capacidade](docs/04-MIDIA-E-CAPACIDADE.md).

## Versionamento

- Repositório oficial: `https://github.com/Kaizenmidias/onboardingmorais.git`.
- `main`: versões revisadas e aprovadas. Trabalhar em branches `docs/*`, `feat/*`, `fix/*` e integrar por pull request.
- Não alterar arquivos da aplicação diretamente no servidor. Aplicar mudanças no repositório, gerar um pacote identificado por versão/commit e publicar esse pacote.
- Credenciais, arquivos de clientes, vídeos, configurações privadas e backups ficam fora do Git.
- A pasta `vendor/` será preparada no desenvolvimento e incluída no pacote de distribuição; não precisa estar versionada no código-fonte.

## Núcleo funcional em implementação

O repositório já contém `app/bootstrap.php`, `public/install.php`, `public/api.php`, `public/upload.php` e `public/media.php`. O instalador cria o schema, o primeiro administrador e os valores iniciais de white label. O painel pode persistir cursos, módulos, aulas e clientes quando a instalação estiver configurada. Vídeos MP4/WebM/MOV/M4V são enviados em partes de 8 MiB, com limite inicial de 10 GiB, para armazenamento privado fora da pasta pública.

Para concluir a operação, ainda falta ligar todas as telas administrativas aos endpoints, criar convite/recuperação por SMTP, aplicar autorização de mídia para clientes, concluir materiais e relatórios e homologar o limite real do aaPanel.

## Próxima etapa

Publicar o núcleo funcional no ambiente de testes, configurar o banco pelo instalador e validar o primeiro usuário. A publicação remota depende do acesso ao aaPanel ou transferência de arquivos.
