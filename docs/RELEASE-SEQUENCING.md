# Sequenciamento de releases — roadmap fatiado em subversões

Decisão: em vez de entregar o roadmap inteiro numa única "versão de impacto" (estratégia descrita
no [ROADMAP](ROADMAP.md)), o backlog é entregue **incrementalmente**, uma feature por subversão,
em bumps de **patch** a partir do 0.1.0 (release inicial do Warden).

## O que já entrou no 0.1.0

Além da base completa (captura, pipeline, dashboard, alerting, security audit), o 0.1.0 incorporou:

- **Self-monitoring do parent** — o parent observa a si mesmo, gravando direto no banco.
- **Edição de projetos** — cliente, contato, grupos e tags; agrupamento e filtro por tag na
  overview e na listagem.
- **Configuração de e-mail no dashboard** (#28) — global + por projeto, severidade mínima e cooldown.
- **Intervalos unificados** — janela de uptime configurável (#25) + agendamento de audit intuitivo
  (#26), no mesmo padrão por projeto.
- **Segurança** — acesso ao painel configurável via `.env` (password / email / gate) e HTTPS
  enforce opcional na comunicação.

## Princípios de ordenação (subversões seguintes)

1. **Infra antes de quem depende dela.** Os canais de alerta (#30) precedem o motor de regras (#34).
2. **Hero cedo.** O tracing distribuído de frota (#29) — o diferencial de campanha.
3. **i18n antes do grosso das telas novas**, para que as features seguintes nasçam traduzidas.
4. **Cada subversão é coesa e entregável.** Fecha um valor, tem entrada própria no CHANGELOG,
   testes verdes e pode ser lançada sozinha.

## Plano

| Versão  | Feature                                       | Issue |
| ------- | --------------------------------------------- | ----- |
| 0.1.0   | Warden — release inicial (ver acima)          | —     |
| 0.1.1   | Canais Slack/Discord/webhook                  | #30   |
| 0.1.2   | Tracing distribuído de frota (hero)           | #29   |
| 0.1.3   | Onboarding de 2 minutos                       | #31   |
| 0.1.4   | Multilíngue (pt-BR/es/en)                     | #16   |
| 0.1.5   | Release/deploy tracking                       | #32   |
| 0.1.6   | Regras de alerta configuráveis                | #34   |
| 0.1.7   | Dashboard em tempo real                       | #27   |
| 0.1.8   | Security near-real-time                       | #24   |
| 0.1.9   | Ciclo de vida de issue                        | #33   |
| 0.1.10  | Seção Database dedicada                       | #36   |
| 0.1.11  | API de instrumentação custom + busca de logs  | #35   |
| 0.1.12  | Materiais de adoção (não-código)              | #37   |

## Fluxo por subversão

Cada subversão segue o mesmo ciclo:

1. Design curto (brainstorm) + plano da feature.
2. Implementação com TDD onde aplicável.
3. Auto-validação (Pint, smoke test, Pest/PHPUnit, PHPStan level max sem baseline/mixed-burla).
4. Entrada no CHANGELOG + bump de versão.
5. Commit/merge.

> O catálogo de features e a tese de posicionamento permanecem no [ROADMAP](ROADMAP.md). Este
> documento rastreia apenas a **ordem de entrega**.
