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

## Backlog já planejado (issues abertas)

> **Entregues no 0.1.0:** #25 (uptime configurável), #26 (agendamento de audit intuitivo) e
> #28 (e-mail no dashboard). Permanecem abaixo como referência histórica.

### #16 — Multilíngue (pt-BR, es, en)
Tornar o dashboard/sistema multilingual com pt-BR, espanhol e inglês. Usar os arquivos de
tradução do Laravel (`lang/`) + `__()` nas views, registrar o namespace de tradução no provider
e adicionar um seletor de idioma. Cobrir todas as strings do dashboard.

### #24 — Security near-real-time (+ investigar "não retornou nada")
A seção Security não exibiu dados. Hoje ela mostra o último evento `security`, que só existe
depois que `warden:audit` roda no child **e** é shippado (via `audit_due` no ciclo de ship, ou
"Run now" → próximo ship). Tarefas:
- Investigar por que veio vazio (audit não rodou? `composer audit`/`npm audit` indisponível ou
  sem `package.json`? ship não disparou?).
- Reduzir a latência para near-real-time (ex.: ship imediato após o audit, ou push direto).

### #25 — Periodicidade de uptime configurável
A janela do uptime é fixa hoje (24h/7d/30d e KPI de 30d). Permitir configurar a janela por
projeto e/ou escolher no dashboard.

### #26 — Agendamento de security audit intuitivo
O agendamento atual (Off / Hourly / 6h / Daily / Weekly + hora avulsa) é confuso — "6h" não
combina com diário/semanal. Reestruturar em níveis intuitivos num único fluxo de select:
- **Mensal** → dia do mês → hora
- **Diário** → hora
- **Semanal** → dia(s) da semana → hora

Substituir `audit_interval` / `audit_hour` por um modelo de agendamento mais expressivo
(cron-like) com UI intuitiva.

### #27 — Dashboard em tempo real
Dashboard do parent com atualização em tempo real (hoje é meta-refresh por intervalo). Avaliar
polling leve via `fetch`/JSON, SSE ou WebSocket — mantendo o lema "no build step / zero deps"
do pacote.

### #28 — Configuração de notificação por e-mail no dashboard
Hoje o alerta por e-mail é configurado só via config/env (`MailAlertChannel` opt-in +
`WARDEN_ALERT_EMAILS`). Levar para o dashboard de forma amigável:
- Habilitar/desabilitar o canal de e-mail.
- Gerenciar destinatários (global e/ou por projeto).
- Possivelmente severidade mínima e cooldown.
- Persistir no banco, gerenciável por UI (Manage projects ou uma página de Settings/Alerts),
  integrando com o `Evaluator` / `MailAlertChannel`.

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

#### #30 — Canais Slack / Discord / webhook genérico
Hoje só DB, log e e-mail. Adicionar canais de alerta sobre **HTTP puro (zero-dep)**:
- Canal webhook genérico (payload JSON configurável) — cobre Slack/Discord/Teams/Telegram.
- Atalhos prontos para Slack e Discord (formatação de mensagem).
- Gerenciável por UI junto com o #28 (página de Alerts/Settings).

#### #31 — Onboarding de 2 minutos
Reduzir o tempo até o primeiro "wow":
- `docker-compose.yml` oficial para subir o parent (DB + app) num comando.
- `warden:demo` populando um dataset de demonstração rico (vários projetos, traces, issues).
- **Demo público read-only** (instância nossa, dados fictícios) linkada no README — "ver antes
  de instalar" multiplica conversão.

#### #32 — Release / deploy tracking
O child envia a versão/SHA do deploy; o dashboard mostra **"erros desde este deploy"** e detecta
regressão (issue resolvida que reaparece após um deploy → reabre + alerta).
- Captura automática da versão (git SHA / tag / `APP_VERSION`).
- Marcador de deploy nas timelines (erros, throughput, p95).
- Fala a língua do público Forge/Envoyer.

### Depth — sinaliza que é projeto sério, não brinquedo

#### #33 — Ciclo de vida de issue (nível Sentry)
Assign, snooze, ignore, resolve e **reabertura automática** quando uma issue resolvida reaparece
(+ alerta). Comentários por issue. **Usuários impactados** (quantos usuários distintos bateram
numa exceção) e Apdex por rota.

#### #34 — Regras de alerta configuráveis
Motor de regras de threshold gerenciável pela UI: _"error rate > 5% em 5min"_, _"p95 da rota X >
2s"_, _"fila com > N jobs pendentes"_. Salto de observabilidade passiva → alerta proativo.
Integra com os canais do #30.

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
