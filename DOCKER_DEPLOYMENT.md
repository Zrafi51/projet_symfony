# Docker and Deployment

## Local full-stack Docker

This project now has a Docker Compose stack for:

- Symfony/PHP web app on `http://localhost:8080`
- FastAPI travel agent on `http://localhost:8000`
- MariaDB on `localhost:3306`

Start Docker Desktop first, then run:

```powershell
docker compose up --build
```

The first MariaDB startup imports `voyage.sql` into the `voyage` database. If you need to re-import from scratch, remove the database volume first:

```powershell
docker compose down -v
docker compose up --build
```

Runtime configuration is in `compose.yaml`. A copyable reference lives in `.env.docker.example`.

## Render deployment

Render can run this project with Docker services. The root `render.yaml` defines:

- `easytravel-web`: public Symfony/PHP web service.
- `easytravel-agent`: private FastAPI service.
- `easytravel-db`: private MariaDB service with a persistent disk.

Create a Render Blueprint from this repository. During Blueprint creation, Render will prompt for:

- `OPENAI_API_KEY`
- `LITEAPI_KEY`

The MariaDB service imports `voyage.sql` only when its `/var/lib/mysql` disk is empty. To reseed later, create a new database service/disk or run a manual import from a shell.

## Fly.io deployment

Fly.io uses three apps for this project:

- `easytravel-web`: public Symfony/PHP app from `fly.web.toml`.
- `easytravel-agent`: private FastAPI app from `fly.agent.toml`.
- `easytravel-db`: private MariaDB app with a persistent volume from `fly.db.toml`.

The apps communicate over Fly private networking with `.internal` DNS:

- `easytravel-db.internal:3306`
- `easytravel-agent.internal:8000`

Typical deploy order:

```powershell
fly apps create easytravel-db --config fly.db.toml
fly volumes create easytravel_db_data --app easytravel-db --region cdg --size 1
fly secrets set --app easytravel-db MYSQL_PASSWORD=... MYSQL_ROOT_PASSWORD=...
fly deploy --config fly.db.toml

fly apps create easytravel-agent --config fly.agent.toml
fly secrets set --app easytravel-agent OPENAI_API_KEY=... LITEAPI_KEY=...
fly deploy --config fly.agent.toml

fly apps create easytravel-web --config fly.web.toml
fly secrets set --app easytravel-web APP_SECRET=... DB_PASSWORD=...
fly deploy --config fly.web.toml
```

## Important Vercel note

Vercel does not run `docker compose` production stacks or long-running Docker containers. Vercel deploys projects as static output and Functions using supported runtimes such as Node.js, Bun, Python, Go, Ruby, Rust, Wasm, and Edge.

For this repository, that means:

- The Dockerized full stack should be hosted on a container platform such as Fly.io, Render, Railway, DigitalOcean App Platform, AWS ECS, or a VPS.
- Vercel can host a refactored Python FastAPI function, but the Symfony PHP app would need a PHP community runtime or a migration to a Vercel-native framework.
- The MariaDB database must be external in production; it cannot run as an in-repo Compose service on Vercel.

## Production shape recommendation

For the least risky production deployment, keep this Docker Compose setup and deploy it to a container host. If Vercel is mandatory, split the system:

- Move the public web app to a Vercel-supported frontend/backend runtime.
- Deploy the FastAPI agent as Vercel Python Functions or as a separate container service.
- Use an external database service and set `DATABASE_URL`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in the hosting provider.
