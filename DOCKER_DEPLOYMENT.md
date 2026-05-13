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
