# Roadmap — próxima versão

Backlog planejado para o Warden, entregue em subversões a partir do 0.1.0 (ver
[RELEASE-SEQUENCING](RELEASE-SEQUENCING.md) para a ordem).

## Tese de posicionamento (público & objetivo)

**Público-alvo:** desenvolvedores e times Laravel 11+ — em especial **agências/times que rodam
vários apps Laravel** (Forge/Ploi/Envoyer). **Objetivo:** crescer o projeto open-source.

O dev Laravel já tem **Telescope** (debug local, 1 app), **Pulse** (métricas em prod, 1 app,
oficial) e **Sentry/Flare/Nightwatch** (SaaS, pago). O buraco que ninguém preenche bem:

> **Um painel self-hosted, zero-dependências, para a _frota inteira_ de apps Laravel.**
> Sem conta SaaS, sem agente, sem serviço externo.

Esse é o nosso wedge. **Toda feature abaixo serve a essa tese** e respeita a bandeira
**zero runtime dependencies** (sem build step, sem pacote fora do core do Laravel).

> Estratégia de entrega: as features abaixo são lançadas **incrementalmente**, uma por
> subversão — ver [RELEASE-SEQUENCING](RELEASE-SEQUENCING.md). O tracing distribuído de frota
> é o hero da campanha.

---

## Backlog já planejado

### Entregue

#### dev-main (rumo a 0.3.0)

- **Confiabilidade do caminho de captura ("o host nunca quebra"):** kill-switch global
  `WARDEN_ENABLED` (lido em runtime; desligado = overhead zero, nem middleware nem recorders
  são instalados); isolação estrutural por-recorder + circuit breaker por-processo
  (`RecorderHealth`); verificação de Octane (reset por-boundary, sem vazamento entre requests
  no mesmo worker); boundary de fila (cada job drena seu batch sem herdar o anterior); e jobs
  de CI de **runtime real** (Octane RoadRunner sob carga + worker) além da matriz PHPUnit.
- **#27 — Dashboard em tempo real:** transporte de polling coalescido por cursor com GET
  condicional (`304 Not Modified`) — no projeto e no overview da frota. JSON, sem build step,
  sem WebSocket; substitui o meta-refresh de página cheia. SSE opt-in fica como evolução.
- **#30 — Canais Slack / Discord / webhook genérico:** canais de alerta sobre HTTP puro
  (zero-dep), config-driven por URL, com piso de severidade, best-effort (nunca quebram o
  evaluate) e suprimidos contra auto-observação.
- **#33 (parcial) — Ciclo de vida de issue:** resolver / ignorar / reabrir / **atribuir** /
  **snooze** pela UI (gated por `manageWarden`), e snooze que silencia o alerta de verdade.
  _Abertos:_ comentários por issue, usuários impactados e Apdex por rota.
- **#32 (parcial) — Release/deploy tracking:** child carimba a release em cada evento
  (`WARDEN_RELEASE`/`APP_VERSION`); badge no evento + filtro "erros desde o deploy"; e
  **detecção de regressão deploy-aware** — issue resolvida só reabre se recorre numa release
  nova. _Abertos:_ marcador de deploy nas timelines, integração Envoyer/Forge.

- **#16 — Multilíngue (pt-BR, es, en):** dashboard traduzido nos três idiomas via arquivos
  `lang/` do Laravel + `__()` nas views, namespace de tradução `warden::` no provider,
  middleware que resolve o locale (cookie `warden_locale` > `Accept-Language` > `config`) e
  seletor de idioma no rodapé da sidebar.
- **#25 — Periodicidade de uptime configurável** (0.1.0): janela por projeto (`uptime_window`).
- **#26 — Agendamento de security audit intuitivo** (0.1.0): `audit_frequency`
  (Off / Daily / Weekly / Monthly) + dia + hora, substituindo `audit_interval`.
- **#28 — Notificação por e-mail no dashboard** (0.1.0): canal gerenciável por UI, com override
  por projeto.

### Abertas

### #24 — Security near-real-time (+ investigar "não retornou nada")
A seção Security não exibiu dados. Hoje ela mostra o último evento `security`, que só existe
depois que `warden:audit` roda no child **e** é shippado (via `audit_due` no ciclo de ship, ou
"Run now" → próximo ship). Tarefas:
- Investigar por que veio vazio (audit não rodou? `composer audit`/`npm audit` indisponível ou
  sem `package.json`? ship não disparou?).
- Reduzir a latência para near-real-time (ex.: ship imediato após o audit, ou push direto).

### #27 — Dashboard em tempo real — ✅ ENTREGUE (dev-main)
Entregue como polling coalescido por cursor + GET condicional (`304`) no projeto e no overview
(ver "Entregue"). Resta apenas o **upgrade opt-in para SSE** sobre o mesmo payload, mantendo o
lema "no build step / zero deps".

---

## Novas propostas (brainstorm 2026-06-09) — ranqueadas por poder de adoção

Priorizadas pelo que faz um dev Laravel 11+ **instalar e dar star**, não por profundidade
técnica isolada. Os números (#NN) são provisórios — abrir issues ao destrinchar.

### Hero — o diferencial que vira screenshot de campanha

#### #29 — Tracing distribuído pela frota
Propagar um `trace_id` via header HTTP entre apps child: uma request no app A que chama o app B
vira **um trace só**, atravessando apps. Ninguém self-hosted faz isso bem em Laravel, e o
Warden já é multi-app por design. É _a_ imagem que vende o projeto.
- Header de propagação (W3C `traceparent` ou header próprio) injetado no client HTTP do child.
- Parent costura spans de múltiplos projetos numa única waterfall.
- Visualização cross-app no trace viewer existente.

### Table-stakes — sem isso o dev não confia em rodar em prod / não converte

#### #30 — Canais Slack / Discord / webhook genérico — ✅ ENTREGUE (dev-main)
Entregue: canal webhook genérico (payload JSON) + atalhos Slack (`{"text"}`) e Discord
(`{"content"}`), sobre HTTP puro (zero-dep), config-driven por URL com piso de severidade.
_Resta:_ gerenciamento por UI junto com o #28 (hoje é config/env).

#### #31 — Onboarding de 2 minutos
Reduzir o tempo até o primeiro "wow":
- `docker-compose.yml` oficial para subir o parent (DB + app) num comando.
- `warden:demo` populando um dataset de demonstração rico (vários projetos, traces, issues).
- **Demo público read-only** (instância nossa, dados fictícios) linkada no README — "ver antes
  de instalar" multiplica conversão.

#### #32 — Release / deploy tracking — 🟡 PARCIAL (dev-main)
Entregue: o child carimba a release (`WARDEN_RELEASE`/`APP_VERSION`) em cada evento; o dashboard
mostra **"erros desde este deploy"** (filtro por release) e detecta **regressão deploy-aware**
(issue resolvida só reabre se reaparece numa release nova). _Abertos:_
- Marcador de deploy nas timelines (erros, throughput, p95).
- Integração com Envoyer/Forge (webhook "deploy aconteceu").

### Depth — sinaliza que é projeto sério, não brinquedo

#### #33 — Ciclo de vida de issue (nível Sentry) — 🟡 PARCIAL (dev-main)
Entregue: assign, snooze, ignore, resolve e **reabertura automática** quando uma issue resolvida
reaparece (+ alerta), com snooze que silencia o alerta. _Abertos:_ comentários por issue,
**usuários impactados** (quantos usuários distintos bateram numa exceção) e Apdex por rota.

#### #34 — Regras de alerta configuráveis — 🟡 PARCIAL (dev-main)
Entregue: motor de regras de threshold config-driven (`warden.alerts.rules`) — _"error_rate > 5
em 1h"_, _"p95 > 500"_, _"failed_jobs > N"_ — abrindo/resolvendo incidente `rule:<name>` pelos
canais do #30. _Abertos:_ gestão pela UI, anomalia sobre baseline móvel, regra por rota.

#### #35 — API de instrumentação custom + busca de logs
- Facade para instrumentação pelo host: `Warden::measure()`, `Warden::increment()`, spans e
  métricas de negócio. Transforma "captura automática" em **plataforma extensível**.
- Busca full-text de logs/eventos (hoje só filtra por nível).

#### #36 — Seção Database dedicada
Fingerprint de queries (SQL normalizado), tempo total × nº de chamadas, N+1 agregado por toda a
app e sugestão de índice. Hoje o N+1 só aparece dentro do trace; falta a visão agregada "quais
queries doem mais". Página de detalhe por rota/endpoint (distribuição de latência, throughput,
error rate, traces mais lentos, top queries) como complemento.

### Promoção OSS (não-código, mas parte do lançamento)

#### #37 — Materiais de adoção
- **Página de comparação** honesta vs Pulse / Telescope / Sentry no README/wiki.
- Selo/badge **"zero runtime dependencies"** em destaque — temos o argumento técnico, falta
  gritar ele.
- Post de lançamento para r/laravel, X e Laravel News ancorado no tracing distribuído.

---

> Itens já entregues estão no 0.1.0 — ver [CHANGELOG](../CHANGELOG.md).
