# E7 Company WordPress

Tema institucional e contrato de produção do multisite WordPress da E7 Company.

## Fluxo de publicação

Todo push para `main` executa os testes, recompila o Tailwind e, se o artefato estiver consistente, aciona o Compose de produção no Dokploy. O serviço `theme_sync` baixa a revisão atual da `main` e troca somente o diretório do tema E7; banco, uploads, plugins e demais temas permanecem nos volumes persistentes.

Segredos exigidos no ambiente `production` do GitHub:

- `DOKPLOY_URL`
- `DOKPLOY_KEY`
- `DOKPLOY_COMPOSE_ID`

## Desenvolvimento

```bash
pnpm install --frozen-lockfile
pnpm test
pnpm build
```

O arquivo `docker-compose.dokploy.yml` é a fonte versionada da infraestrutura. Os valores reais de banco vivem somente no ambiente do Dokploy.
