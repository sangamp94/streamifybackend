# AppGuard on Render + Neon — deployment guide

This replaces the InfinityFree (PHP + MySQL) setup with Render (Docker) +
Neon (PostgreSQL, free forever). The app's behavior and UI are unchanged;
only the hosting and database engine changed.

## 1. Create the database (Neon)

1. Sign up at https://neon.tech (free) and create a new project.
2. On the project dashboard, copy the **Connection string** — it looks like:
   `postgres://user:password@ep-xxxx.region.aws.neon.tech/dbname?sslmode=require`
3. Open the **SQL Editor** in the Neon console, paste in the full contents
   of `backend/schema-postgres.sql` from this project, and run it. This
   creates all tables and the demo seed data.

## 2. Push this project to GitHub

Render deploys from a Git repo. Create a new repo and push everything in
this folder (`Dockerfile`, `render.yaml`, `backend/`, `frontend/`) to it.

## 3. Deploy on Render

1. On https://dashboard.render.com, click **New +** -> **Blueprint**.
2. Connect the GitHub repo you just pushed. Render will detect `render.yaml`
   automatically.
3. Before the first deploy finishes, go to the service's **Environment**
   tab and set:
   - `DATABASE_URL` — paste the Neon connection string from step 1.
   - `SHORTENER_URL` — your GPLinks/Linkvertise/etc link (optional, can add
     later). Its final destination should be:
     `https://<your-render-service>.onrender.com/backend/extend_gateway.php`
4. Deploy. Render gives you a URL like `https://appguard-xxxx.onrender.com`.

Free-plan note: the service spins down after 15 minutes of no traffic and
takes a few seconds to wake back up on the next request — normal on
Render's free tier, not a bug.

## 4. Create your admin login

Visit `https://<your-service>.onrender.com/backend/setup.php` once, create
your admin username/password.

**Then remove `setup.php` from the repo and push again.** Unlike
InfinityFree's File Manager, there's no "delete a file on the live server"
step here — the container is rebuilt from your Git repo on every deploy,
so deleting means: delete the file locally, commit, push. Render redeploys
automatically.

## 5. Test the self-service extend flow

Same as before: open `/extend.html`, log in with a token (e.g.
`TKN-7H2K-99XQ-3A1D` from the seed data), click **Get +7 Day Extension**.
If you haven't set a real `SHORTENER_URL` yet, temporarily set it to your
own `.../backend/extend_gateway.php` URL to test the full loop without a
real shortener in the way.

## What changed under the hood (if you're curious)

- **Database engine**: MySQL -> PostgreSQL. `backend/schema-postgres.sql`
  is the converted schema (MySQL's `schema.sql` / `migrate_add_signup.sql`
  are kept in the repo for reference but aren't used here).
- **Credentials**: no longer hardcoded in `config.php` — read from the
  `DATABASE_URL` environment variable instead, so a code push or redeploy
  can never overwrite your real database connection.
- **Expiry math**: MySQL's `DATE_ADD(NOW(), INTERVAL ? MINUTE)` became
  Postgres's `NOW() + (INTERVAL '1 minute' * ?)` in the self-service
  extend/signup/login endpoints.
- **`update_history.force`** column renamed to `force_update` (backtick-
  quoted MySQL identifiers don't carry over to Postgres) — updated in
  `update_push.php`, `update_history_list.php`, and `frontend/index.html`.
- **HTTPS detection** in `user_generate_link.php` now also checks the
  `X-Forwarded-Proto` header, since Render's proxy terminates TLS and talks
  plain HTTP to the container — without this, generated extend links would
  have come out as `http://` instead of `https://`.
- `install.php` (InfinityFree's DB-credential-writing form) and the raw
  MySQL `.sql` files are excluded from the Docker image — they're
  InfinityFree-only and unused here.
