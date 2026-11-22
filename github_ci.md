# GitHub: branch protection and continuous integration (CI)

This document explains, step by step, what was done to secure the `main` branch of the
**Vite & Gourmand** repository and to set up automatic checks with GitHub Actions.

---

## 1. The starting point: a GitHub warning

GitHub showed this message on the repository:

> *Your main branch isn't protected. Protect this branch from force pushing or deletion, or require status checks before merging.*

**What it means:** right now anyone with write access (here, only me) could:

- **delete** the `main` branch by mistake,
- **force push** (`git push --force`) and erase commits from history,
- **merge code that doesn't work**, because nothing checks it first.

GitHub's answer to this is **rulesets**: rules attached to one or more branches
(see the official docs: *About rulesets*).

---

## 2. Step 1: look at the repository

Before changing anything, I checked the current state with the GitHub CLI (`gh`):

| Item | Result |
|---|---|
| Repository | `j-reaud/vite-et-gourmand` |
| Visibility | **Private** |
| GitHub plan | **Free** |
| Branches | `main`, `dev`, and 3 `feature/*` branches |
| Collaborators | only me |
| Existing CI workflows | **none** |

Commands used (read-only, nothing changed):

```bash
gh repo list j-reaud
gh api repos/j-reaud/vite-et-gourmand/rulesets
gh api repos/j-reaud/vite-et-gourmand/actions/workflows
```

---

## 3. Step 2: the blocker (rulesets aren't available)

When I asked GitHub for the rulesets, it answered:

> *Upgrade to GitHub Pro or make this repository public to enable this feature.* (HTTP 403)

**Lesson:** on a **free** account, rulesets and branch protection only work on
**public** repositories. For a private repository you need **GitHub Pro** (paid).

So there are two options:

1. **Upgrade to GitHub Pro**, or
2. **Make the repository public.** Before doing that, check the whole git history
   for secrets (`.env`, passwords, API keys), because everyone will be able to read it.

**Decision:** still pending. No ruleset has been created yet.

### The ruleset that's planned

Once it's unlocked, the rules for `main` will be:

- **Block deletion** of the branch.
- **Block force pushes.**
- **Require a pull request** before merging (0 approvals needed, because I work alone and
  GitHub doesn't let you approve your own PR).
- **Require the CI check "Lint & tests" to pass** (see below).
- **No bypass**: the rules apply to me too.

---

## 4. Step 3: why CI is needed first

The rule *"require status checks before merging"* needs a **status check** to exist,
meaning an automatic test that GitHub runs on every push or pull request.
The repository had none, so CI comes first.

**CI (Continuous Integration)** = every time code is pushed, a GitHub server
automatically installs the project and runs the tests. If something breaks, the PR
shows a red ❌ instead of a green ✅.

> Good to know: GitHub Actions **works fine** on a free private repository
> (2,000 minutes a month). Only the *"required"* part (blocking the merge) needs a ruleset.

---

## 5. Step 4: pick the CI approach

There are two common ways to run a Symfony CI:

| Approach | How it works | Pros | Cons |
|---|---|---|---|
| **1. Install directly** on the GitHub runner | install PHP, MySQL and MongoDB as GitHub services | faster | you configure everything twice (Dockerfile + YAML), and the two can drift apart |
| **2. Docker Compose** ✅ chosen | reuse the project's own `compose.yaml` and `Dockerfile` | CI uses **the same environment** as local development | a bit slower (the image has to be built) |

**Why approach 2:** the project already runs with Docker (PHP 8.4, MySQL 8.4,
MongoDB 8.0). Testing in the same environment is more reliable and easier to explain.

---

## 6. Step 5: the problems found before writing the workflow

Reading the project's files turned up several things that would have broken the CI:

1. **Test database permissions.** In the `test` environment, Symfony adds `_test`
   to the database name (`vite_et_gourmand_test`). The MySQL image only gives the
   normal user rights on `vite_et_gourmand`, so creating the test database would fail.
   → **Fix:** CI connects to MySQL as `root` (throwaway passwords, CI only).
2. **`APP_ENV: dev` is hard-coded** in `compose.yaml`.
   → **Fix:** CI sets `APP_ENV: test`.
3. **The `.env` file isn't in git** (and that's correct: it holds secrets). But Docker Compose
   needs it for variables like `${MYSQL_USER}`.
   → **Fix:** CI copies `.env.example` to `.env` (example values only).
4. **MongoDB has no health check.** "Started" doesn't mean "ready to accept connections".
   → **Fix:** a health check that runs `mongosh ... ping` before the tests start.
5. **Variable names.** The app reads `MONGODB_URI` and `MONGODB_DB`
   (in `config/packages/doctrine_mongodb.yaml`).
   → **Fix:** CI sets these variables explicitly.
6. **Mailer.** `compose.yaml` sends emails to **Mailpit**, which isn't started in CI.
   → **Fix:** in CI, `MAILER_DSN=null://null` (emails aren't sent, but tests can still
   check that they were "sent").
7. **Services not needed in CI:** Nginx (no browser needed) and the Messenger worker
   (in tests, Messenger already uses `in-memory://`). → They aren't started.
8. **PHPStan isn't installed** (`phpstan/phpdoc-parser` is a different library).
   → No PHPStan step for now. It can be added later with
   `composer require --dev phpstan/phpstan`.

---

## 7. Step 6: the files created

### a) `compose.ci.yaml` (new)

A **CI-only overlay** for `compose.yaml`. Docker Compose merges the two files:

```bash
COMPOSE_FILE=compose.yaml:compose.ci.yaml docker compose up -d php mysql mongodb
```

👉 **`compose.yaml` wasn't changed.** Local development works exactly as before.

What this file does:

- sets `APP_ENV=test`,
- connects to MySQL as root (fix #1),
- sets `MONGODB_URI` / `MONGODB_DB` (fix #5),
- sets `MAILER_DSN=null://null` (fix #6),
- adds the MongoDB health check (fix #4),
- shares a Composer cache folder (faster installs).

### b) `.github/workflows/ci.yml` (new)

The workflow GitHub runs. It starts:

- on every **push** to `main` or `dev`,
- on every **pull request targeting `main` or `dev`**,
- by hand (the "Run workflow" button).

The job is called **"Lint & tests"**. Its steps, in order:

| # | Step | What it checks |
|---|---|---|
| 1 | Checkout | downloads the code |
| 2 | Create `.env` | copies `.env.example` (fix #3) |
| 3 | Build PHP image | builds the Dockerfile, **with a cache** |
| 4 | Composer cache | reuses already downloaded packages |
| 5 | Start containers | `php`, `mysql`, `mongodb`, waiting until they're *healthy* |
| 6 | `composer validate` | `composer.json` is valid |
| 7 | `composer install` | dependencies install correctly |
| 8 | `composer audit` | installed PHP dependencies have no known security advisory |
| 9 | `lint:container` | Symfony services are wired correctly |
| 10 | `lint:yaml` | config files have valid syntax |
| 11 | `lint:twig` | templates have valid syntax |
| 12 | `doctrine:database:create` | creates `vite_et_gourmand_test` |
| 13 | `doctrine:migrations:migrate` | **all migrations run** without errors |
| 14 | `doctrine:schema:validate` | entities and database are in sync |
| 15 | **PHPUnit** | **all tests pass** |
| 16 | Logs (if it fails) | prints the container logs to help debugging |
| 17 | Shut down | `docker compose down -v` (always runs) |

Security best practices used:

- `permissions: contents: read` → the workflow can only **read** the repository.
- `concurrency` → if you push twice in a row, the old run is cancelled.
- `timeout-minutes: 20` → a stuck job can't use up your free minutes.

### c) `.dockerignore` (new)

Excludes local secrets, dependencies, generated files and local notes from the Docker
build context. In particular, the real root `.env` file is never sent to Docker Buildx.

The Composer cache is stored in GitHub Runner's temporary directory, outside the
repository. No `.gitignore` change is needed.

---

## 8. Step 7: test it locally before pushing

To be sure the workflow works, I ran **the same commands** on my machine with Docker, in
a **separate copy** of the project and under **a different Compose project name**
(`vg-ci-check`), so my normal development containers and their data weren't touched.

Results:

| Step | Result |
|---|---|
| composer validate | ✅ valid |
| composer install | ✅ |
| lint:container | ✅ |
| lint:yaml | ✅ 29 files |
| lint:twig | ✅ 30 files |
| database create | ✅ `vite_et_gourmand_test` |
| migrations | ✅ 11 migrations |
| schema:validate | ✅ in sync |
| PHPUnit (first run) | ❌ 2 failures (the mailer was trying to reach Mailpit) |
| PHPUnit (after fix #6) | ✅ **82 tests, 524 assertions, OK** |

Afterwards, the test containers, volumes and image were deleted.

**Lesson:** running the CI locally first catches mistakes (here, the mailer) before
spending GitHub minutes on them.

---

## 9. Where things stand and what's next

**Done**

- [x] Repository looked over
- [x] Ruleset blocker found (free plan + private repo)
- [x] CI written (`ci.yml` + `compose.ci.yaml`)
- [x] CI tested locally: everything passes

**Still to do**

- [ ] **Commit and push** the CI files (they aren't committed yet), ideally on a dedicated
      branch with a pull request.
- [ ] Check that the workflow passes **on GitHub** (Actions tab).
- [ ] Choose: **GitHub Pro** or **public repo** (after checking for secrets).
- [ ] Create the ruleset on `main` (no deletion, no force push, PR required,
      **"Lint & tests"** check required).
- [ ] (Optional) Add PHPStan to the CI.

---

## 10. Glossary

- **Branch protection / Ruleset**: rules GitHub enforces on a branch.
- **Force push**: `git push --force`, rewrites the remote history (dangerous on `main`).
- **CI (Continuous Integration)**: automatic tests on every push or PR.
- **GitHub Actions**: GitHub's CI service. Workflows live in `.github/workflows/`.
- **Workflow / Job / Step**: a workflow holds jobs, and each job is a list of steps.
- **Status check**: the ✅/❌ result of a job, shown on commits and PRs.
- **Docker Compose override**: a second compose file that changes the first one without editing it.
- **Healthcheck**: a command Docker runs to know whether a service is really ready.
