# Staging deployment through Git

Commit all completed ticket source, migrations, documentation and tests, then push `origin/main`. Staging is the `main` checkout at `/home/r31xlbc711ah/public_html` on `r31xlbc711ah@107.180.117.224`, accessed with the existing `~/.ssh/psc-crm-staging` identity. The hosted application is `https://sz8.894.mytemp.website`.

Inspect both working trees and fetch before deployment. Preserve `.env`, uploads, the database and staging-only `.gitignore` configuration. Prior direct deployments may have modified tracked files and added untracked source; back these up or stash the known source paths before pulling. Keep the recovery copy and do not reapply duplicate source changes after the same work is committed.

On staging, fetch `origin/main`, verify the expected commit, and use `git pull --ff-only origin main`. Build assets from this checkout rather than copying source files. A user-local Node installation may be used through `PATH` when the host has no system Node; Node 22.14.0 matches the local build environment and is compatible with this host's x86_64/glibc 2.28 system.

```sh
cd /home/r31xlbc711ah/public_html
git fetch origin
git pull --ff-only origin main
export PATH="$HOME/.local/psc-crm-node/node-v22.14.0-linux-x64/bin:$PATH"
npm ci --no-audit --no-fund
GOMAXPROCS=1 UV_THREADPOOL_SIZE=1 RAYON_NUM_THREADS=1 npm run build
php artisan migrate --force --no-interaction
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Review pending migrations before running them. Never run `migrate:refresh`, seed example records or replace the database during deployment. Verify the authenticated Tasks, RFI and Migration Agents pages, generated assets and existing record counts. Confirm staging `HEAD`, `origin/main` and the pushed local commit match. Preserve existing daily worklog hours when updating deployment accomplishments.

The shared host limits concurrent processes/threads. Keep all three build concurrency settings above; an unrestricted esbuild process can exceed the limit. Back up `public/build` before building, keep staging in maintenance mode while assets are replaced, and restore the asset backup if the build fails before bringing the app back up.
