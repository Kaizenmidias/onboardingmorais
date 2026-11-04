# Instalação, ambientes, manutenção e homologação

Procedimentos planejados para implementação posterior. Nenhum registro DNS, virtual host, certificado, banco ou funcionalidade foi criado nesta etapa de documentação.

## 1. Ambientes e evidências atuais

| Item | Informação / estado em 03/11/2026 |
| --- | --- |
| Repositório | `https://github.com/Kaizenmidias/onboardingmorais.git`, acessível por Git neste ambiente |
| Branch da documentação | `docs/arquitetura-v1`, derivada da `main` inicial |
| Testes | aaPanel, IP `89.116.52.173`, informado pelo responsável |
| URL prevista | `https://onboarding.kaizenmidias.com/` |
| DNS do subdomínio | Consulta local e consulta a `1.1.1.1` retornaram nome inexistente no momento da inspeção |
| NS do domínio | `paityn.ns.cloudflare.com` e `tosana.ns.cloudflare.com`, conforme consulta pública |
| HTTP/HTTPS no IP informado com Host do subdomínio | Tentativas curtas nas portas 80 e 443 expiraram na conexão a partir deste ambiente. Não permite concluir que as portas estão fechadas para todas as origens |
| Software do servidor de testes | Servidor web, versões de PHP/banco, firewall, disco e limites ainda não inspecionados |
| Produção | Provedor e plano ainda não homologados; requisito permanece hospedagem compartilhada compatível |

Não há credenciais de aaPanel/DNS disponíveis no repositório nem sessão administrativa utilizada. A configuração depende de acesso ao aaPanel e à zona DNS. O IP foi tratado como endereço de destino informado, não como um ID de painel ou prova de disponibilidade.

## 2. Preparação do aaPanel e do domínio

Após revisão da arquitetura e disponibilidade dos acessos:

1. Inventariar site já existente para esse nome, software web ativo, PHP 64 bits, banco, armazenamento e firewall. Registrar configuração atual antes de alterar. Não instalar um segundo servidor web nem substituir sites existentes sem necessidade.
2. Criar um site PHP para `onboarding.kaizenmidias.com` com diretório público exclusivo. Nome e caminhos reais devem ser confirmados no painel.
3. No DNS autoritativo, criar ou ajustar somente o registro do subdomínio. Proposta inicial:

   | Tipo | Nome | Destino | Proxy | TTL |
   | --- | --- | --- | --- | --- |
   | A | `onboarding` | `89.116.52.173` | Somente DNS inicialmente | 300 s, se aceito, ou padrão do provedor |

4. Verificar se existe CNAME/AAAA conflitante para esse nome; preservar os demais registros do domínio. Não alterar nameservers, MX, site principal ou e-mails para conectar apenas o subdomínio.
5. Validar resolução pública e alcance das portas necessárias a partir de origens apropriadas. Corrigir firewall/serviço se o diagnóstico confirmar a causa, sem inferir isso somente pelo timeout inicial.
6. Emitir e instalar certificado para o hostname pelo recurso ACME do painel ou método disponível; validar cadeia, validade e renovação. Ativar redirecionamento HTTPS após o certificado funcionar.
7. Configurar URL canônica, trusted hosts e proxies conhecidos. Não confiar em `Host`/`X-Forwarded-*` arbitrários para gerar convites.
8. Identificar e configurar o adaptador privado de mídia. Se Nginx estiver ativo, usar location interna e `X-Accel-Redirect`; se Apache/LiteSpeed, usar o mecanismo correspondente homologado.
9. Quando existir um pacote aprovado da etapa corrente, publicá-lo com identificação de release. Antes da etapa visual, apenas página técnica temporária e verificações de ambiente, quando autorizadas, sem implementar o sistema completo.
10. Registrar evidências de DNS, HTTPS, diretório, versões e isolamento. Proteger a revisão visual com acesso restrito e `noindex`; `robots.txt` sozinho não torna dados privados.

O painel não determina o servidor web. Referências: [aaPanel — sites e conceitos](https://www.aapanel.com/docs/Function/Website.html) e [cPanel — domínios e HTTPS](https://docs.cpanel.net/cpanel/domains/domains/). Não é necessário colocar o vídeo atrás do proxy do Cloudflare para usar o DNS desse provedor.

## 3. Layout e isolamento dos arquivos

aaPanel: diretório de aplicação privado e diretório público exclusivo, por exemplo `.../onboarding/app` e `.../onboarding/public`. Caminhos concretos serão definidos pela instalação. Segredos e arquivos de clientes não podem ficar expostos por outro site/alias do mesmo servidor.

Em cPanel que permite apontar para `public/`, usar diretamente o diretório público do pacote. Se o host restringir raízes ao `public_html`, distribuir em duas partes:

```text
/home/USUARIO/onboarding_app/            # código, vendor, config e storage privados
/home/USUARIO/public_html/clientes/      # index.php e assets públicos
```

A entrada pública referencia o bootstrap privado com caminho configurado na instalação. O operador extrai os arquivos no local correto pelo painel. Não depender só de `.htaccess` para esconder toda a aplicação. Nginx ignora essas regras e exige configuração própria; Apache/LiteSpeed precisam ser testados com suas regras reais.

Permissões de arquivo seguem o usuário/grupo do host. Escrita somente em configuração privada inicial e armazenamento/cache necessários; não recomendar `777`. Backups e releases anteriores nunca são disponibilizados por URL.

## 4. Instalador web

O instalador configura conexão, cria **tabelas em banco já existente**, cadastra o primeiro administrador e aplica defaults. Criar banco e usuário é responsabilidade do operador nas ferramentas do cPanel/aaPanel; o produto não precisa de credenciais administrativas globais do painel.

Fluxo:

1. Exigir HTTPS e segredo de bootstrap único por instalação, criado por ferramenta local ou arquivo privado enviado pelo operador. O segredo não deve vir como valor público igual em todos os ZIPs nem ser gerado na primeira visita anônima.
2. Fazer preflight: versão/arquitetura PHP, extensões, escrita, raízes privadas, rewrite, banco e conexão. Diferenciar requisitos básicos, SMTP e mídia.
3. Validar banco selecionado e ausência de tabelas conflitantes. Não apagar dados existentes nem reutilizar silenciosamente um banco de outro sistema.
4. Executar migrações com histórico, checksum e lock. Como DDL pode fazer commit implícito, guardar estado de cada etapa e permitir retomada controlada da mesma instalação após falha.
5. Criar o primeiro administrador nominativo, com senha escolhida pelo operador. A conta da agência é criada explicitamente no assistente ou por convite administrativo posterior; não existe usuário oculto.
6. Informar marca inicial, URL canônica, fuso, contato e SMTP. Testar e-mail. Permitir salvar SMTP pendente com diagnóstico visível, mas bloquear liberação operacional de convites até validar o transporte.
7. Gerar chaves da instalação, gravar configuração privada com acesso mínimo e registrar UUID da instalação. Não exibir valores secretos na conclusão.
8. Testar um arquivo de prova privado: pedido direto deve falhar, rota autorizada deve entregar conteúdo e Range. Só habilitar publicação de vídeos quando o adaptador passar.
9. Gravar estado instalado no banco e lock privado, invalidar segredo inicial e desabilitar as rotas de instalação. Remover o lock isoladamente não reabre a instalação sobre banco já inicializado.

O preflight não certifica carga. Instalação básica pode ser concluída para revisão/admin com mídia desabilitada; a liberação de conteúdo com vídeos depende da homologação. Recuperação de instalação e atualização exigem um segredo operacional próprio e acesso técnico, nunca um parâmetro público `reset=1`.

## 5. Build e pacote de distribuição

O ambiente de desenvolvimento prepara `vendor` com dependências travadas, somente de produção, e assets finais. Verificar compatibilidade da plataforma, licenças e vulnerabilidades antes do empacotamento. O servidor recebe arquivos prontos.

Pacote inclui código, dependências de execução, assets públicos, migrações, defaults, manifesto de versão/checksums e guia de instalação. Exclui `.git`, ferramentas de desenvolvimento, fixtures pessoais, credenciais, configuração de outra instalação, vídeos e backups. Preservar o arquivo de lock no repositório quando existir, mesmo com `vendor/` fora do Git.

Artefato de distribuição e código-fonte são entregas diferentes. Cada publicação registra versão, commit de origem, ambiente, data, operador e migrações aplicadas. Se o build ocorrer em Windows, verificar nomes de arquivos, maiúsculas/minúsculas e permissões para o Linux de destino.

## 6. Estratégia Git e sincronização

- `main` permanece a referência de versões aprovadas, integrada por pull request; proteção de branch deve ser ativada pelo administrador do repositório.
- Branches curtas por entrega: `docs/arquitetura-v1`, `feat/frontend-base`, `feat/client-area`, `feat/admin-ui`, `feat/auth`, etc. Não é necessário manter `develop` permanentemente para este porte.
- Commits descrevem decisões ou comportamento. PR inclui validação e impacto de implantação. Não declarar arquitetura aprovada apenas porque a documentação foi publicada em uma branch.
- Tags de release identificam pacotes; sugestões `v0.x` para etapas de validação e `v1.0.0` para primeira versão funcional homologada.
- Alteração emergencial no servidor só é exceção operacional e precisa voltar ao repositório antes do próximo deploy. Dados da aplicação e marca são geridos no painel/banco e não se confundem com divergência de código.
- Nunca configurar publicação automática de todo push em produção. Implantação da versão aprovada é uma etapa identificada; pode ser manual por pacote/FTPS, sem exigir Git/SSH no host.

## 7. Atualização e restauração

1. Conferir versão atual, requisitos da nova versão e estado das migrações. Preparar o pacote e plano de restauração antes de entrar em manutenção.
2. Backup consistente do banco, configuração/chaves e arquivos alterados. Manter inventário de mídia e uma cópia recuperável fora da mesma conta/servidor conforme o contrato operacional.
3. Ativar manutenção para alterações conflitantes, impedindo novos uploads e gravações enquanto a versão muda. Agendar janela compatível com vídeos em reprodução.
4. Extrair código novo em diretório separado. No host sem troca atômica de raiz/symlink, usar procedimento documentado de cópia em manutenção; não prometer zero downtime.
5. Aplicar migrações autenticadas pelo navegador, com lock, lotes quando viável e histórico. Migrações grandes exigem janela específica, sem `ALTER` pesado escondido em um acesso normal.
6. Preservar configurações privadas, mídia e customizações no banco. Novos textos usam defaults para chaves ausentes; não sobrescrever valores definidos pelo escritório.
7. Executar smoke test, invalidar caches da versão e encerrar manutenção. Guardar resultado e release anterior pelo período acordado.
8. Em falha, restaurar código e banco coerentes com o ponto anterior. Só usar rollback de código isolado quando a compatibilidade da migração foi explicitamente verificada.

Não incluir um atualizador remoto irrestrito na primeira versão. O fluxo de atualização web aplica migrações de um pacote confiável já enviado pelo operador; não baixa e executa URL arbitrária.

## 8. Backups e continuidade

Metas iniciais propostas: RPO de 24 horas para banco/configuração e RTO de 8 horas após disponibilidade do acesso, artefatos e infraestrutura. São objetivos operacionais a contratar e testar, não garantias do software. Vídeos originais precisam de cópia verificada antes de serem considerados recuperáveis.

Rotina sugerida: banco diário, configuração/chaves em toda mudança, mídia incremental conforme envio, cópias fora do servidor de origem e restauração assistida antes da primeira produção e após mudanças relevantes. Proposta de retenção: 7 diários e 4 semanais, sujeita a custo, política do escritório e espaço. Cifrar cópias e limitar acesso.

Backup de banco não inclui o MP4; backup de MP4 não recupera permissões e progresso. Manter manifesto que relacione mídia e banco, testar arquivos de amostra e a chave de cifragem. Não gerar ZIP de dezenas de GB em uma única requisição PHP; usar recursos do provedor ou transferência incremental. Confirmar limites e exclusões do backup oferecido pelo plano.

## 9. Plano de homologação

| Grupo | Evidência de aceite |
| --- | --- |
| Ambiente | DNS correto, HTTPS válido/renovável, diretório público exclusivo, versões e extensões registradas |
| Instalação | Instalação limpa sem terminal; primeiro admin seguro; reentrada bloqueada; falha retomável sem apagar banco existente |
| Replicação | Segunda instalação vazia com marca diferente, sem alterar código nem copiar clientes/segredos |
| Acesso | Convite, recuperação, MFA proposto, bloqueio, expiração, isolamento entre clientes e conta própria da agência |
| Conteúdo | Criar/publicar/ordenar/arquivar, prévia, concessões/grupos e sequência consistente |
| Progresso | Persistência, idempotência, retomada, recálculo, histórico e relatórios corretos |
| Mídia | Arquivo >= 3 GiB por partes e importação; retomada após interrupção; quota; Range/seek; acesso direto bloqueado; PHP liberado durante reprodução |
| Interface | Referência revisada, larguras/dispositivos acordados, estados de interação e acessibilidade de projeto |
| Operação | SMTP, retentativas, Cron, auditoria, CSV seguro, atualização e restauração verificadas |
| Privacidade | Fluxo de exportação/exclusão e reaplicação de exclusões após restauração |

### Capacidade e metas propostas

Executar testes de reprodução real e carga HTTP de mídia em etapas de 1, 5 e 10 espectadores; depois 25 e 60 para expansão, se autorizado e suportado. Registrar versão, plano, bitrate/resolução, local dos geradores, rede, duração, CPU, RAM, I/O, PHP, banco e conexões HTTP. Sessões de espectadores devem ser distintas; um único cookie pode mascarar contenção.

Metas de projeto a confirmar na homologação: 30 minutos de carga sustentada no alvo, taxa de erro < 1%, p95 de respostas dinâmicas sem mídia < 1,5 s no cenário controlado, início do vídeo p95 < 3 s em rede de referência de pelo menos duas vezes o bitrate e menos de 1% do tempo de reprodução em rebuffering. Definir o método e publicar o resultado; não inferir UX do player apenas por teste de download.

A mídia deve ser transmitida sem manter um processo PHP por espectador; validar com logs/telemetria do servidor, não só pela existência de um header. Verificar simultaneamente navegação/admin e impacto no WordPress, quando compartilharem recursos. Teste no aaPanel e no perfil cPanel real são evidências distintas. Usar dados sintéticos e limites acordados com o provedor.

O relatório final deve dizer explicitamente: capacidade aprovada, limites encontrados, tamanho/bitrate dos arquivos, condições, gargalos e o que não foi testado. Nesta etapa, os testes funcionais, de carga, publicação e restauração **ainda não foram executados**.

## 10. Incidentes e manutenção

Definir responsáveis do escritório e agência, contatos e cobertura. Logs técnicos com `request_id`, retenção limitada e consulta restrita; alertas sobre SMTP, Cron parado, quota baixa, certificado e erros recorrentes sem exportar dados pessoais desnecessários.

Em incidente: conter acesso, preservar evidência mínima, revogar credenciais afetadas, restaurar serviço e registrar impacto/ações. O responsável pelo tratamento avalia as providências de privacidade aplicáveis com base no ocorrido; não embutir prazos jurídicos fixos em código sem política atualizada.
