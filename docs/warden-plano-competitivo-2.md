# Warden — Plano para bater de frente com Pulse + Nightwatch (self-hosted, para frota)

> **Atualização de escopo (pós-0.3.2):** o **Telescope saiu do escopo como alvo**. O foco daqui
> pra frente é ser o **Pulse + Nightwatch self-hosted de uma frota**. O que já foi implementado
> com sabor "debug local" permanece (não removemos nada), mas **deixa de ser objetivo perseguido**
> — não investimos mais em paridade de watchers de dev. As menções a Telescope abaixo ficam só
> como contexto histórico/comparativo, marcadas como **[fora de escopo]**.
>
> **Legenda de status:** ✅ feito (até 0.3.2) · 🔄 parcial/em andamento · ⬜ falta · ⛔ fora de escopo

> Objetivo declarado: **um único produto self-hosted** que substitua, ao mesmo tempo, o
> Pulse (métricas agregadas em produção) e o Nightwatch (APM/observabilidade em produção) — e
> que possa rodar **como um app próprio que monitora os outros** (modelo parent/fleet).
> *(O Telescope — debug local — saiu do escopo como alvo; ver nota de escopo acima.)*
>
> Este documento é um diagnóstico honesto do que falta + um roadmap em fases com "portões"
> (o que precisa estar pronto antes de você poder *afirmar* que substitui cada um).

---

## 1. Posicionamento realista (leia isto antes do roadmap)

Você **não vence** os concorrentes no campo deles:

- **Nightwatch** é hosted, com backend colunar (ClickHouse + Kafka + Lambda) processando
  bilhões de eventos/dia em tempo real, feito pelo time do Laravel, com colaboração de time
  e integração nativa com Forge/Vapor/Cloud. Você não compete em escala bruta nem em
  confiança de marca first-party.
- **Pulse** é first-party, gratuito, leve, e já está instalado em milhares de apps.
- **Telescope [fora de escopo]** é o padrão de fato para debug local — gratuito e oficial.
  Não é mais alvo: deixou de fazer sentido perseguir paridade de debugger de dev contra a
  ferramenta oficial. Fica como contexto.

**Onde Warden ganha de verdade** (o moat real, e onde todo o foco deve ir):

1. **Frota self-hosted num único painel.** Pulse é essencialmente por-app; Nightwatch é
   multi-app mas SaaS. Ninguém entrega "um painel on-premise para *N* apps Laravel" de forma
   limpa. Esse é o seu produto.
2. **Zero dependência + roda no RDBMS que você já tem.** Argumento forte para quem não pode
   ou não quer SaaS.
3. **Privacy-by-default / LGPD-GDPR.** Mascaramento de credenciais e PII por padrão,
   data controller = o host. Vende sozinho para fintech BR, saúde, governo, jurídico.
4. **APM de frota num produto só:** agregados (nível Pulse) + traces/issues/alertas
   (nível Nightwatch) self-hosted, sem SaaS e sem amarrar a um agente externo.

> ⚠️ **Concorrência direta já existe:** *NightOwl Agent* (MIT) faz quase o seu pitch —
> self-hosted, drena a telemetria do `laravel/nightwatch` para um Postgres seu, ~13k
> payloads/s. Diferença chave: ele **depende do SDK do Nightwatch** para instrumentar;
> Warden instrumenta sozinho e não amarra você ao ecossistema pago. Isso é uma vantagem —
> deixe explícito no posicionamento.

**Frase de posicionamento sugerida:** *"O Nightwatch self-hosted da sua frota — métricas nível
Pulse, traces e issues nível Nightwatch, sem SaaS, sem agente externo, sem dependências,
rodando no seu próprio banco."*

---

## 2. Decisão TRAVADA ✅ — RDBMS puro agora, bridge para SaaS colunar depois

**Decidido:** o RDBMS continua sendo o **sistema de registro** (system of record) e a
identidade do produto. Para quem estourar o teto, no futuro oferecemos **integração com um
SaaS colunar maior** — não construímos nosso próprio ClickHouse. Isso preserva o "zero-dep" no
núcleo e empurra a complexidade de escala para um componente **opcional e externo**.

**Como isso fica na arquitetura (o "Warden Bridge"):**

- O caminho `child → outbox → parent → RDBMS` permanece igual e continua sendo a verdade.
- A escala vira um **exporter opcional** que reenvia eventos adiante. Recomendação forte:
  fazer esse exporter **vendor-neutral via OTLP/OpenTelemetry**, não acoplado a um SaaS
  específico. Assim o mesmo bridge serve ClickHouse Cloud, Grafana/Tempo, Datadog, ou um
  futuro "Warden Cloud" — sem reescrever nada e sem prender o usuário.
- Dois modos de operação do bridge (configuráveis):
  - **Overflow / tiering:** RDBMS guarda janela quente (ex.: 7 dias) e o SaaS colunar guarda o
    histórico longo/analítico. Barato e simples.
  - **Mirror (dual-write):** RDBMS é a fonte operacional e o SaaS recebe cópia para analítico
    pesado. Mais caro, melhor para BI.
- **Pré-requisito de design (fazer já, custa quase nada):** manter os contratos
  `Ingestor` / `WardenRepository` / `Aggregator` limpos e adicionar um ponto de extensão
  "post-ingest hook" no parent, para o bridge plugar depois **sem tocar no core**. Não
  construa o bridge agora — só deixe a costura pronta.

**Mensagem de marketing honesta:** *"Roda no seu banco até onde você precisa; quando precisar
de escala analítica, conecta num backend colunar via OTLP — sem trocar de ferramenta."*

---

## 3. Decisão TRAVADA ✅ — dois modos de deploy, sem Docker oficial

**Decidido:** oferecemos **dois modos** e o usuário escolhe conforme o caso. Warden **não**
publica imagem Docker oficial — quem quiser containerizar ajusta o próprio Docker.

| Modo | Quando usar | Como instala/deploya |
|---|---|---|
| **Pacote (como hoje)** | Tem **um app só** para monitorar, ou quer ver as métricas **dentro do próprio app** | `composer require` no app + `warden:install`. Pode ser self-monitor (parent+child no mesmo app) ou esse app virar o parent dos outros |
| **App standalone** | Quer um **painel dedicado** que monitora a frota, separado dos apps | Um repo Laravel mínimo que **só** roda o parent. Instala e faz deploy como qualquer app Laravel (Forge, servidor, ou Docker próprio) |

**O que o "app standalone" é, na prática:** um esqueleto Laravel enxuto (auth + o pacote em
modo parent + scheduler) distribuído como **template** — via `composer create-project
victorstochero/warden-app` e/ou um **GitHub template repo**. O usuário clona/cria, configura
`.env`, faz deploy. Sem imagem oficial: cada um decide a infra. Isso reduz drasticamente a sua
manutenção (é um app fino sobre o pacote que você já tem).

**Vantagem de manter os dois:** o **pacote** atende o caso "1 app / quero ver direto no app"
(o jeito Pulse de pensar — métricas no próprio app), e o **standalone** atende o caso "frota / painel
dedicado" (o jeito Nightwatch de pensar). Um mesmo código-base serve os dois — parent e child
já saem do mesmo pacote.

**Regra de ouro de arquitetura:** o pacote continua sendo a **única fonte de verdade do
código**. O app standalone é só uma *casca de distribuição* — ele depende do pacote via
Composer, não duplica lógica. Toda feature nova entra no pacote; o standalone herda de graça.

Implicações para o roadmap (Fase 4): para o standalone virar produto de frota sério, ele
precisa de **multi-tenancy, RBAC, SSO/OIDC** — que entram no **pacote** (modo parent), não no
template.

---

## 4. Matriz de capacidades (status atual vs. o que falta)

Coluna **Status**: ✅ feito (até 0.3.2) · 🔄 parcial · ⬜ falta · ⛔ fora de escopo. Telescope
sai como alvo; mantido só como nota comparativa onde ajuda.

| Capacidade | Pulse | Nightwatch | **Warden — Status** | O que falta / nota |
|---|---|---|---|---|
| Captura requests/queries/jobs/cache/mail/notif/cmd/schedule/http/logs/users | 🟡 (agregado) | ✅ | ✅ | 12 recorders, agora com isolação estrutural + breaker (0.3.x) |
| Métricas de host (CPU/mem/disco) | ✅ | 🟡 | ✅ | — |
| Traces correlacionados + waterfall de spans | ❌ | ✅ | ✅ | inclui waterfall cross-app de frota (§29) |
| Detecção de N+1 | ❌ | 🟡 | ✅ | + QueryHealthAnalyzer: duplicadas, SELECT*, sem WHERE, gorda, lentas |
| Agrupamento de exceções em issues | 🟡 | ✅ | ✅ | fingerprint |
| **Colaboração em issues** (assignee, status, comentários, mute) | ❌ | ✅ | ✅ | assignee/status/resolve/ignore/reopen/snooze + usuários afetados ✅ (`IssueWorkflow`); falta **só comentários** |
| **Detecção de regressão** (issue reabre após deploy) | ❌ | ✅ | ✅ | reabertura *deploy-aware*: issue resolvida reabre só em release mais nova que `resolved_release` (`IssueProcessor`) |
| Dashboard em **tempo real** (live) | 🟡 | ✅ | ✅ | polling coalescido por cursor com `304` pronto (`StreamController` + rotas `/stream`); falta **só o upgrade SSE** opt-in |
| **Motor de regras de alerta** (limiar, janelas, cooldown) | ❌ | ✅ | ✅ | regras gerenciáveis pela UI (Evaluator + AlertRule) |
| Canais de alerta Slack/Webhook/Mail/DB | ❌ | ✅ | ✅ | Slack, Discord, Webhook, Mail, DB. Falta: **PagerDuty/Opsgenie** (⬜) |
| **Release/deploy tracking** ("erros desde este deploy") | ❌ | ✅ | 🔄 | marcadores de deploy + correlação de regressão por release ✅; falta a **visão "desde o deploy"** consolidada |
| Instrumentação custom (`measure()`/`increment()`) | 🟡 | 🟡 | ✅ | API pública no core |
| Multi-app / frota num painel | 🟡 (só servers) | ✅ (SaaS) | ✅ **(seu moat)** | — |
| Multi-tenancy + RBAC + SSO/OIDC | ❌ | ✅ | 🔄 | RBAC por-projeto via gates ✅; API read-only + tokens ✅; audit log ✅. Falta SSO/OIDC e multi-tenancy plena |
| API de leitura + tokens | ❌ | 🟡 | ✅ | read-only, tokens hash SHA-256 (ver correções de segurança §9.5) |
| Uptime/heartbeat de scheduler | 🟡 | ✅ | ✅ | — |
| Auditoria de dependências (composer/npm audit) | ❌ | ❌ | ✅ **(além dos 2)** | self-audit do parent agendado |
| Escala analítica (alto volume) | ❌ | ✅ | 🔄 | RDBMS hoje; bridge OTLP futuro (§2, Fase 5) |
| **Kill-switch global + isolação de recorder** | ❌ | 🟡 | ✅ | `WARDEN_ENABLED` + breaker por-processo (A0.1/A0.2/A0.3) |
| Compat. **Octane / Horizon / workers long-running** | ✅ | ✅ | 🔄 | reset de Octane wired; **falta prova sob carga real em CI** (A4/A5) |
| Suíte failure-injection completa + overhead budget em CI | — | — | 🔄 | A0 parcial; **falta A2–A4 + Octane/Horizon real + benchmark** |
| Integração Forge/Vapor/Cloud/Envoyer | 🟡 | ✅ | ⬜ | Fase 4 |
| Privacy-by-default / LGPD | 🟡 | 🟡 | ✅ **(seu moat)** | toggles de captura + override do .env (0.3.1) |
| RUM / erros de JS no browser | ❌ | 🟡 | ⬜ | Fase 5 (futuro) |
| Custom cards/recorders pela comunidade | ✅ (forte) | ❌ | 🔄 | recorders extensíveis; falta doc/superfície pública |
| **Busca global / command palette (⌘K)** | ❌ | 🟡 | ✅ **(DX)** | endpoint `warden.search` read-only (0.3.1) |
| **Diagnóstico de query** (N+1, duplicadas, SELECT\*, sem WHERE, gorda, lenta) | ❌ | 🟡 | ✅ | QueryHealthAnalyzer na seção Database unificada (0.3.1) |
| **Controles de tempo** (presets, range custom, persistência, busca de logs no banco) | 🟡 | ✅ | ✅ | range custom + cookie de persistência (0.3.1) |
| **Admin do parent** (CRUD de projetos, rotação de credenciais, UI de regras de alerta) | ❌ | ✅ | ✅ | ProjectAdmin + SettingsController + AlertRule |
| **Rota real por trás de `livewire/update`** (resolução via Referer, sem persistir URL) | ❌ | 🟡 | ✅ **(privacidade)** | relabel best-effort em memória (0.3.1) |
| ~~Debug local granular nível Telescope (entry-by-entry em dev)~~ | ❌ | ❌ | ⛔ | **fora de escopo** — não perseguir paridade de watchers de dev |
| Selo de confiança (oficial/marca) | ✅ | ✅ | ❌ | **gap de GTM** — Warden não é first-party; compensar com prova pública (demo/benchmark/docs) |

**Leitura rápida:** o núcleo de captura, a colaboração em issues, o tempo real (polling `304`) e o
motor de alertas já estão entregues. Os gaps reais que sobram são **robustez/compatibilidade de
runtime provada (Fase 0), produto de frota (template + SSO/multi-tenancy, Fase 4), e
confiança/GTM (demo, benchmark, docs)** — mais a cauda de APM (comentários, SSE, PagerDuty,
amostragem adaptativa/rollups).

---

## 5. Gaps detalhados por categoria (o "o que falta")

### 5.1. Confiabilidade do caminho de captura — *pré-requisito de tudo*
A promessa "o app host nunca quebra" (RNF-2) é a mais importante e a mais difícil. Hoje o flush
é no `terminate` do request. Faltam garantias para:
- **Octane** (sem bootstrap por request; estado compartilhado entre requests — risco de
  vazamento de buffer/contexto entre requisições). **Verificar e testar explicitamente.**
- **Filas/Horizon** (jobs não passam pelo ciclo HTTP `terminate`; precisa hook de
  `JobProcessed`/`after`). Confirmar que o buffer de job é drenado corretamente.
- **Comandos longos / daemons** (buffer pode crescer indefinidamente sem terminate).
- **Falha do recorder** (um recorder que lança exceção não pode derrubar o request — exige
  `try/catch` defensivo + circuit breaker por recorder).
- **Backpressure do outbox** (você já tem high/low water — testar sob carga real e sob parent
  offline por horas).

➡️ **Entregável:** suíte de *failure-injection* (recorder que explode, parent 500/timeout,
disco cheio, Octane com 2 requests concorrentes) provando que o host nunca quebra. Sem isso,
você não pode pedir para ninguém colocar Warden em produção.

### 5.2. ⛔ Paridade "Telescope" (debug local) — FORA DE ESCOPO
**Removido como alvo.** Não perseguimos mais paridade de debugger de dev contra a ferramenta
oficial. O que já existe nos 12 recorders continua funcionando; o que **não** vamos construir:
watchers extras de dev (Views, Models, Events, Gates, Redis, Dumps, Batches), modo dev ephemeral
local e inspeção entry-by-entry estilo Telescope. Esforço que iria aqui é redirecionado para o
moat (frota self-hosted + APM de produção). *Texto original preservado abaixo apenas como
histórico:*

> ~~Para substituir o Telescope você precisaria de um modo dev com inspeção granular: timeline de
> entries por request com payload completo; filtro/tag por request/usuário/status/rota; watchers
> Views/Models/Events/Gates/Redis/Dumps/Batches; modo local ephemeral lendo o DB local.~~

### 5.3. Issues & colaboração — paridade Nightwatch · ✅ QUASE COMPLETA
Implementado (`IssueWorkflow` + rotas `warden.issue.*` + `Issue` model):
- ✅ Atribuir issue a uma pessoa (`assign`); status aberto/resolvido/ignorado; reabrir (`reopen`).
- ✅ "Resolvido na release X" (`resolved_release`) + **reabertura automática** *deploy-aware*
  (`IssueProcessor` reabre só numa release mais nova que a resolvida).
- ✅ Snooze/mute por tempo (`snooze` / `snoozed_until`). ✅ Contagem de usuários afetados
  (`users_affected`).
- ✅ Link direto issue → traces de exemplo (`last_trace_id`).
- ⬜ **Falta só:** comentários na issue (thread de triagem) e agrupamento explícito por ambiente.

### 5.4. Tempo real & frontend — DECIDIDO ✅
Hoje o dashboard depende de agregação por cron. Arquitetura escolhida (sem Livewire, sem afogar
o sistema, dependência só no lado do dashboard — nunca no child):

- **Princípio:** o servidor entrega **dados (JSON)**, não HTML-por-interação. Reatividade no
  cliente. É o oposto do modelo Livewire/htmx (round-trip de markup), que é justamente o que
  pesa requisição.
- **Interatividade:** **Alpine.js vendorizado localmente** (~15KB, sem build, sem NPM, sem CDN
  externo — mantém "no build step" + privacidade). Não Livewire, não htmx.
- **Transporte abstraído (uma camada, dois modos):**
  - ✅ **FEITO — Default universal: polling coalescido por cursor + GET condicional.** *Um*
    endpoint (`StreamController` + rotas `/stream`) devolve os deltas desde um cursor; se nada
    mudou, responde **`304 Not Modified`** com o ETag/cursor, então a leitura pesada nem roda em
    ocioso. Zero-dep, roda em PHP-FPM barato. 1 request com deltas, não N requests com reloads.
  - ⬜ **FALTA — Upgrade opt-in: SSE (Server-Sent Events).** *Uma* conexão longa por viewer, servidor
    empurra deltas via `EventSource` nativo. Sem servidor de websocket, sem Reverb, sem Pusher.
    Para quem aguenta conexão longa (Octane/processo dedicado). Em FPM, segura 1 worker por
    viewer → cap nas sessões do dashboard e recomendar Octane para muitos viewers.
- Mesmo frontend para os dois modos e para os dois deploys (pacote e standalone) — só troca o
  `transport` por trás do mesmo código de render. Interface idêntica, garantida.
- Gráficos: lib pequena vendorizada local (uPlot/Chart.js), nunca CDN (privacidade).
- ⬜ **Dívida de segurança promovida a roadmap:** com o real-time já implementado, migrar a CSP de
  `script-src 'unsafe-inline'` para nonces/hashes e remover o `unsafe-inline` de script (§9.5).

### 5.5. Alertas de verdade (motor de regras) · 🔄 MAJORITARIAMENTE FEITO
- ✅ Regras configuráveis pela UI (`SettingsController` + `AlertRule`): error rate, p95, fila,
  heartbeat ausente, nova issue, pico de exceção.
- ⬜ Detecção de anomalia simples (desvio sobre baseline móvel) além de limiar fixo.
- Canais: ✅ **Slack, Discord, Webhook, Mail, DB** já entregues; ⬜ falta **PagerDuty/Opsgenie**.
- ✅ Deduplicação/cooldown por subject; "resolvido automaticamente quando normaliza" via
  `Evaluator`. ⬜ Falta escalonamento.

### 5.6. Release / deploy tracking · 🔄 PARCIAL
- ✅ Marcadores de deploy nas timelines + **regressão por release** (issue reabre deploy-aware, §5.3).
- 🔄 Capturar versão/commit/deploy id no child e carimbar nos eventos (base existe via `last_release`).
- ⬜ Visões consolidadas "erros desde o último deploy" e "regressão de p95 após deploy".
- ⬜ Integração opcional com Envoyer/Forge/GitHub Actions (webhook "deploy aconteceu").

### 5.7. Produto multi-tenant (para o "app que monitora os outros")
- **Usuários, papéis (view/manage/admin), e times** no parent (hoje é senha única / e-mail).
- **SSO/OIDC + SAML** (essencial para empresa) — pode ser via Socialite (dev-dep) sem ferir o
  runtime do child.
- Isolamento por projeto/tenant; quem vê o quê; tokens de API para automação.
- Audit log de quem fez o quê no painel.

### 5.8. Escala & dados (ver §2)
- Amostragem adaptativa (mais amostra quando há erro/lentidão, menos no caminho feliz).
- Rollups multi-resolução (1m/5m/1h/1d) para o overview ficar barato em qualquer volume.
- Driver de store plugável já desenhado para um futuro backend colunar opcional.

### 5.9. Ecossistema & DX
- Integração explícita com **Horizon** (métricas de fila por supervisor/queue), **Reverb**
  (conexões/canais), **Octane** (modo de captura).
- **Imagem Docker oficial do parent** + recipe Forge "1 clique".
- Telemetria de adoção honesta + comando `warden:doctor` (diagnostica child mal configurado).

### 5.10. Confiança / Go-to-market (sem isto, o resto não importa)
- **Releases semver tagueadas + CHANGELOG**, badge de CI verde, cobertura publicada.
- **Instância de demo pública** (read-only) com dados sintéticos — vale mais que 10 páginas de
  README.
- **Docs site** (não só README): guia parent, guia child, tuning, segurança, comparativo
  honesto vs. os 3, FAQ de performance.
- **Benchmark publicado**: overhead por request, throughput de ingestão, custo de storage por
  1M eventos. Nightwatch ganha no marketing; ganhe na transparência.
- **Auditoria de segurança externa** (ou ao menos um threat model público) — você pede para
  rodar no caminho crítico de apps em produção.
- Nome: "Warden" colide com o Warden de dev-env PHP/Magento e o Warden de auth no Rails.
  Considere um nome/namespace mais único antes de investir em marca.

### 5.11. Compatibilidade de schema entre versões da frota — ⬜ *lacuna nova*
O parent monitora children que podem rodar **versões diferentes** do pacote. Hoje só existe o
`schema_version: 2` do evento, citado no contexto do Bridge (§9.2). Falta uma **política explícita**:
como o parent ingere lotes de um child mais novo/antigo, o contrato de compat do payload e a janela
de versões suportadas. Sem isso, um upgrade desencontrado na frota pode quebrar a ingestão — risco
direto do pitch "um painel para *N* apps".

### 5.12. Retenção & custo de storage por projeto — ⬜ *lacuna nova*
Central no pitch self-hosted/LGPD, mas hoje só aparece como métrica no benchmark (§5.10). Falta
**retenção configurável por projeto** (janela quente, prune por idade/volume) exposta na UI: o
operador precisa controlar quanto cada app retém e o custo associado, não apenas uma política
global de partição/prune.

---

## 6. Roadmap em fases (com portões de "agora posso afirmar que substituo X")

> Regra: cada fase só fecha quando o **portão** estiver provado por testes + demo pública.

### Fase 0 — Fundação de confiança · 🔄 PARCIAL · *destrava tudo*
- ✅ Kill-switch `WARDEN_ENABLED` + isolação estrutural de recorder + breaker (A0.1–A0.3).
- ✅ Releases semver + CHANGELOG (até 0.3.2).
- ⬜ Suíte de failure-injection **completa** (§5.1): parent offline, backpressure, filas, daemon
  (A2–A4) — ainda falta.
- ⬜ Prova de Octane/Horizon **sob carga real em CI** (A5/A4) — wiring existe, falta o job.
- ⬜ Overhead budget no CI + benchmark público.
- ⬜ Instância de demo pública com dados sintéticos.
- **Portão (ainda não cruzado):** dá para alguém colocar em produção sem medo. **Não deixe esta
  fase para trás** — é a base do que já foi empilhado nas Fases 1/3/4.

### Fase 1 — "Substitui o Pulse" · ✅ MAJORITARIAMENTE FEITA
- ✅ Cards/seções equivalentes (slow requests/queries/jobs/outgoing, exceptions, cache, queues,
  host, top users), + Database unificada e QueryHealthAnalyzer (além do Pulse).
- ✅ Tempo real: polling coalescido por cursor com `304` (`StreamController` + `/stream`); ⬜ falta
  só o SSE opt-in (§5.4).
- ⬜ Custom recorders/cards documentados para a comunidade.
- **Portão:** praticamente alcançável — falta só o SSE opt-in e a doc de extensão de cards/recorders.

### Fase 2 — ⛔ "Substitui o Telescope" · REMOVIDA DO ESCOPO
Era a paridade de debugger de dev. **Saiu do escopo** (ver §5.2). O esforço aqui foi redirecionado
para o moat (Fase 3/4). O que já existe nos recorders permanece; não construímos os watchers de
dev nem o modo ephemeral.

### Fase 3 — "APM de produção nível Nightwatch (no seu segmento)" · 🔄 PARCIAL
- ✅ Issues com colaboração (§5.3): fingerprint + assignee/status/resolve/ignore/reopen/snooze +
  usuários afetados; ⬜ falta só comentários.
- ✅ Motor de regras de alerta gerenciável (Evaluator + AlertRule) + canais Slack/Discord/Webhook/Mail/DB.
  ✅ Detecção de regressão por release (deploy-aware). ⬜ Falta PagerDuty/Opsgenie e anomalia sobre baseline.
- 🔄 Release/deploy tracking (§5.6): marcadores de deploy + regressão por release ✅; ⬜ falta a visão
  consolidada "desde o deploy".
- ✅ Instrumentação custom (`measure()`/`increment()`). ✅ Waterfall cross-app de frota.
- ⬜ Amostragem adaptativa + rollups multi-resolução (§5.8).
- 🔄 Octane reset wired; ⬜ falta a prova sob carga (volta pra Fase 0).
- **Portão:** uma equipe roda Warden como APM primário numa frota real por 30 dias.

### Fase 4 — Produto "app que monitora os outros" · 🔄 PARCIAL
- ⬜ **App standalone como template** (`composer create-project` + GitHub template repo), **sem
  Docker oficial** (§3).
- 🔄 RBAC por-projeto via gates ✅; API read-only + tokens ✅; audit log ✅. ⬜ Falta SSO/OIDC e
  multi-tenancy plena.
- ⬜ Docs site completo, benchmark publicado, threat model/auditoria (§5.10).
- **Portão:** alguém que nunca viu o código cria o app standalone, faz deploy, conecta 3 apps,
  convida o time com SSO e configura alertas — tudo sem te perguntar nada.

### Fase 5+ — Diferenciação ofensiva (futuro)
- **Warden Bridge:** exporter OTLP opcional para SaaS colunar (overflow ou mirror), §2 — o
  caminho de escala decidido. Deixe a costura (post-ingest hook) pronta já na Fase 3.
- RUM / erros de JS, tracing distribuído cross-app de verdade, anomalia por ML leve,
  marketplace de cards da comunidade.

---

## 7. Riscos e decisões (atualizado)

**Já decididas ✅**
1. **Escala:** RDBMS é o sistema de registro; bridge OTLP opcional para SaaS colunar no futuro
   (§2). *Resolvido.*
2. **Deploy:** dois modos — pacote (como hoje) + app standalone como template, **sem Docker
   oficial** (§3). *Resolvido.*
3. **Nome:** mantém "Warden". *Resolvido.* → mitigar a colisão de SEO com docs/site fortes e um
   namespace claro (`victorstochero/warden`), já que o nome fica.
4. **Frontend/real-time:** Alpine.js vendorizado + transporte abstraído (polling coalescido por
   cursor com `304` como default; SSE opt-in). Sem Livewire, sem websocket server. Dependência
   só no dashboard, nunca no child (§5.4). *Resolvido.*
5. **Modelo de negócio:** **OSS puro** — tudo aberto, sem edição paga. Monetização (se houver)
   por fora: Sponsors/OpenCollective, suporte/consultoria, ou uma versão hospedada opcional.
   *Resolvido.* (Implicações em §9.4.)

**Ainda abertas (risco de execução, não de estratégia)**
6. **A promessa "nunca quebra o host"** — seu maior risco técnico; trate como P0 (Fase 0).
7. **Octane/Horizon/long-running** — possível bug latente de estado compartilhado. *Verificar
   na Fase 0.*
8. **Competir parcialmente contra ferramenta first-party gratuita** — o pitch é "self-hosted +
   frota + privacidade", não "melhor que o oficial em tudo".

---

## 8. TL;DR — status atual e o que falta

- **Escopo enxugado:** alvo agora é **Pulse + Nightwatch self-hosted para frota**. Telescope
  (debug de dev) saiu — o que já existe fica, mas não é mais perseguido.
- **Já feito (até 0.3.2):** captura com isolação+breaker, kill-switch, traces+waterfall de frota,
  N+1+QueryHealthAnalyzer, motor de alertas + UI de regras (Slack/Discord/Webhook/Mail/DB),
  colaboração em issues (assign/status/reopen/snooze) + regressão por release deploy-aware, tempo
  real por polling `304`, RBAC por-projeto, API read-only+tokens, audit log, marcadores de deploy,
  `measure()`/`increment()`, command palette (⌘K).
- **Falta no APM (Fase 3):** comentários em issues, SSE opt-in, PagerDuty/Opsgenie, anomalia sobre
  baseline, amostragem adaptativa + rollups multi-resolução.
- **Falta provar (Fase 0, inegociável):** failure-injection completa (parent offline, backpressure,
  filas, daemon) + Octane/Horizon sob carga real em CI + overhead budget + demo pública.
- **Falta produto (Fase 4):** app standalone (template, sem Docker) + SSO/OIDC + multi-tenancy plena.
- **Corrigir já (segurança, §9.5):** token API timing-safe (`hash_equals`), throttle do
  `last_used_at`, `$fillable` no `ApiToken`.
- **Decidido:** RDBMS + bridge OTLP futuro; dois modos de deploy; nome mantido; Alpine+SSE/polling;
  **OSS puro**.
- **Sobra moat:** frota self-hosted + zero-dep + LGPD — invista tudo nisso. O risco agora não é
  falta de features, é a **Fase 0 ficar para trás** enquanto as Fases 1/3/4 avançam.

---

## 9. Plano de execução das decisões travadas

### 9.1. App standalone (template) — acionável agora, baixo custo

Você não precisa esperar a Fase 4 para a base. O esqueleto pode existir cedo e ir ganhando
recursos conforme o pacote evolui.

Passos:
1. Criar repo **`victorstochero/warden-app`** = `laravel new` enxuto + `require
   victorstochero/warden` + `warden:install --parent` no post-create.
2. Marcar como **GitHub template repo** e publicar como **`create-project`** no Packagist.
3. Script de pós-criação (`composer create-project` hook) que: roda migrations, gera
   `APP_KEY`, e imprime os próximos passos (definir `WARDEN_DASHBOARD_AUTH`).
4. `.env.example` mínimo já voltado para parent (sem nada de child).
5. README curto do template: "crie, configure 3 envs, deploy onde quiser (Forge/servidor/seu
   Docker)". **Deixar explícito que Warden não fornece imagem Docker.**
6. CI no template que só verifica "cria + migra + responde `/warden`" — garante que o template
   nunca quebra quando o pacote sobe de versão.

> Custo de manutenção: baixo. É uma casca que depende do pacote via Composer. Toda feature nova
> entra no pacote e o template herda sem mudança.

### 9.2. Costura do Warden Bridge — fazer JÁ (não o bridge, só o gancho) · ⬜ AINDA NÃO PLANTADA

> **Status atual:** confirmado que **não existe** ainda `EventForwarder`/evento `EventsIngested`
> no core — a costura segue como pendência a plantar (não o bridge em si).

O bridge em si é Fase 5, mas o **ponto de extensão** custa quase nada e evita reescrita depois.
Faça isto enquanto mexe na ingestão (Fase 3):

1. Definir um contrato `EventForwarder` (ou um event `EventsIngested` do Laravel) disparado
   **depois** do parent persistir um lote no RDBMS — com o payload normalizado em mãos.
2. Garantir que esse hook seja **no-op por padrão** (zero overhead, zero dep) e plugável via
   config (`warden.bridge.forwarder`).
3. Documentar o **schema canônico do evento** (você já tem `schema_version: 2`) como contrato
   estável — é o que o bridge vai mapear para OTLP.
4. Não escolher o destino agora. Quando construir, o primeiro forwarder deve emitir **OTLP**
   (vendor-neutral) — aí ClickHouse Cloud, Grafana, Datadog, etc. viram "só uma config".
5. Decidir o modo (overflow vs. mirror) só quando houver um usuário real pedindo escala.

> Resultado: hoje você não carrega nenhum peso extra; amanhã o bridge é um pacote satélite
> (`victorstochero/warden-bridge-otlp`) que assina o hook — sem tocar no core e sem ferir o
> "zero-dep" de quem não usa.

### 9.3. Ordem prática recomendada (próximos passos concretos)

1. **Fase 0 inteira primeiro** (failure-injection + releases + demo). Inegociável.
2. Em paralelo e barato: criar o **template standalone** (9.1) e plantar a **costura do bridge**
   (9.2) — ambos não dependem das features grandes.
3. Só então seguir Fase 1 → 2 → 3 → 4 na ordem, com os portões.
4. Bridge OTLP (Fase 5) só quando um usuário real estourar o RDBMS — não antes.

### 9.4. Modelo de negócio — DECIDIDO ✅: OSS puro

**Tudo aberto, sem edição paga.** Implicações práticas:

- **Licença:** tudo MIT (como já está), inclusive o que antes seria "pro" — Bridge, SSO/RBAC,
  multi-tenancy entram todos no núcleo aberto. Nada de paywall, nada de repo fechado. Simplifica
  o licenciamento e maximiza adoção/confiança.
- **Sem linha open-core para defender** — uma dor de cabeça a menos: você decide features por
  valor técnico, não por "isso é grátis ou pago".
- **Sustentação (sem prometer receita):** GitHub Sponsors + OpenCollective (já linkados),
  suporte/consultoria sob demanda, e — se um dia fizer sentido — uma **versão hospedada
  opcional** do parent (vender conveniência, não o código). Nada disso bloqueia funcionalidade.
- **Risco aceito conscientemente:** alguém pode usar/hospedar comercialmente sem contribuir de
  volta. Com OSS puro + MIT isso é permitido por design — é o trade-off por adoção e simplicidade
  máximas. (Se um dia isso incomodar, a porta AGPL ainda existe, mas não é o plano agora.)
- **Efeito no roadmap:** Fase 4 não muda de escopo, só de licença — SSO/RBAC/multi-tenancy
  continuam previstos, agora abertos. O **Warden Bridge** (Fase 5) também é um pacote satélite
  MIT.
- **GTM coerente com OSS puro:** invista o "marketing" em **prova pública** — demo, benchmark,
  docs, transparência de overhead. Numa estratégia OSS pura, confiança e DX *são* o crescimento.

### 9.5. Correções da revisão de código (0.3.2) — acionáveis agora

Da revisão do estado atual do `main`, três itens de **segurança/robustez** para corrigir antes
de divulgar a API (**confirmados ainda pendentes no código pós-0.3.2** — `ApiToken` segue com
`$guarded = []`, `findByPlaintext()` compara o hash inteiro no banco, e `last_used_at` grava em
toda request):

1. **Token de API timing-safe (prioridade alta).** `ApiToken::findByPlaintext()` compara o hash
   inteiro via `where(...)` no banco. Padrão correto (estilo Sanctum): guardar um **prefixo curto
   indexável**, buscar por ele e comparar o hash em PHP com `hash_equals()`. Fecha o canal de
   timing.
2. **`last_used_at` grava a cada request autenticada** — `UPDATE` num caminho quente da API de
   leitura. Throttlar a escrita (só se passou >1 min) ou coalescer.
3. **`$guarded = []` no `ApiToken`** → trocar por `$fillable` explícito (higiene anti
   mass-assignment; custo zero).

Dívida registrada (não bloqueia): CSP do dashboard usa `script-src 'unsafe-inline'`; ao migrar
para Alpine vendorizado (decisão de real-time), trocar por nonces/hashes e remover o
`unsafe-inline` de script.

> Estes três já estão prontos para virar um `TASK.md` pronto-pra-agente no mesmo formato dos
> demais, se quiser.
