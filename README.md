# Portal de Onboarding Contábil White Label

Portal independente do WordPress, com uma instalação por escritório, desenvolvido para PHP e MySQL/MariaDB em hospedagens compatíveis.

## Estado do projeto

Arquitetura v1.0 consolidada para revisão em 03/11/2026. A implementação visual e funcional ainda não foi iniciada. A aprovação da arquitetura e o recebimento da referência visual antecedem o desenvolvimento do front-end; sua aprovação antecede a integração do backend.

O ambiente de testes informado usa **aaPanel**, no IP **89.116.52.173**, com domínio previsto **https://onboarding.kaizenmidias.com/**. Produção continua planejada para hospedagem compartilhada compatível com cPanel. Validar um ambiente não homologa automaticamente o outro.

## Documentação

| Documento | Conteúdo |
| --- | --- |
| [Arquitetura](docs/01-ARQUITETURA.md) | Stack, componentes, segurança, white label e decisões estruturais |
| [Dados e regras](docs/02-DADOS-E-REGRAS.md) | Entidades, integridade, autorização, progresso e relatórios |
| [Interface e contratos](docs/03-INTERFACE-E-CONTRATOS.md) | Telas, campos editáveis, estados e conexão entre front e backend |
| [Mídia e capacidade](docs/04-MIDIA-E-CAPACIDADE.md) | Arquivos acima de 2 GB, uploads, entrega privada e dimensionamento |
| [Operação e homologação](docs/05-OPERACAO-E-HOMOLOGACAO.md) | DNS, aaPanel, cPanel, instalador, atualizações, backups e testes |
| [Requisitos e entregas](docs/06-REQUISITOS-E-ENTREGAS.md) | Rastreabilidade, fases, critérios de aceite e pendências |
| [Histórico de alterações](CHANGELOG.md) | Mudanças versionadas |

## Condição para vídeos locais

A produção exige hospedagem que permita esse uso e disponibilize entrega privada de arquivos pelo servidor web. Não se presume que um plano compartilhado suporte vídeos grandes ou uma determinada simultaneidade. A análise das restrições atuais da HostGator e os critérios de homologação estão no documento de [mídia e capacidade](docs/04-MIDIA-E-CAPACIDADE.md).

## Versionamento

- Repositório oficial: `https://github.com/Kaizenmidias/onboardingmorais.git`.
- `main`: versões revisadas e aprovadas. Trabalhar em branches `docs/*`, `feat/*`, `fix/*` e integrar por pull request.
- Não alterar arquivos da aplicação diretamente no servidor. Aplicar mudanças no repositório, gerar um pacote identificado por versão/commit e publicar esse pacote.
- Credenciais, arquivos de clientes, vídeos, configurações privadas e backups ficam fora do Git.
- A pasta `vendor/` será preparada no desenvolvimento e incluída no pacote de distribuição; não precisa estar versionada no código-fonte.

## Próxima etapa

Revisar a arquitetura. Depois, preparar o domínio e o ambiente de testes com os acessos necessários, registrar os recursos efetivamente disponíveis e receber a referência visual. Este repositório ainda não contém um instalador ou aplicação executável.
