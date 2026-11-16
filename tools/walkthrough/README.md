# Frontend walk-through

This tool runs the `auto` items of `docs/testing/frontend-walkthrough.md` in a real Chrome.
It records the console errors of each item and makes a screenshot of each item.
The baseline of November 2026 is in `docs/testing/baseline-2026-11-main.md`.

## What you need

- Docker with the compose plugin.
- Node.js and the npm packages of this repository (`npm install`).
- Google Chrome. The script uses the installed Chrome (`channel: 'chrome'`). It does not download a browser.
- Playwright 1.62 in the npx cache. The script does not add a package to `package.json`.

If the npx cache has no Playwright yet, run this command one time.
It downloads the npm package only, not the browsers.

```bash
npx -y playwright@1.62.0 --version
```

The script finds Playwright in this sequence: the folder in `WALK_PLAYWRIGHT`, a normal `require('playwright')`, and then each `~/.npm/_npx/*/node_modules/playwright`.

## Run the walk-through

Run all commands from the root of the repository.

1. Start the stack with its own compose project. The project has its own MySQL volume, so your development data stays as it is.

   ```bash
   docker compose -p catlab-drinks-walk -f docker-compose.yml -f tools/walkthrough/compose.override.yml up -d --build webserver
   ```

   If you have a local PHP 8.5 image already, you can tag it and skip the build:
   `docker tag catlab-drinks:php85 catlab-drinks-walk-webserver:latest` and then the same command with `--no-build`.
   Wait until `docker logs catlab-drinks-walk` shows `Startup complete!`.

2. Make a development build. A development build keeps the real function names in console errors.

   ```bash
   npm run dev
   ```

3. Start the NFC simulator in a second terminal.

   ```bash
   npm run nfc-simulator
   ```

4. Run the script.

   ```bash
   node tools/walkthrough/walkthrough.mjs
   ```

The script writes `tools/walkthrough/output/<item-id>.png` and `tools/walkthrough/output/report.json`.
The report is an array of `{ id, status, consoleErrors, note }`. If an item fails, the exit code is 1.
The folder also gets `hosts.json` (each host that the browser tried, with a count) and `dialogs.json` (each browser dialog).
If an item fails, the folder also gets a screenshot of each open page, named `<item-id>-fail-<page>.png`.
The folder `tools/walkthrough/output/` is ignored by git.

## Start from a new database

The script expects a new database. Item `blade-setup` makes the first user and the organisation.
If the database has data from an earlier run, `blade-setup` fails and the script logs in instead.
To start again from a new database, remove the walk-through project with its volume. The `-p` name makes sure that only this project is removed.

```bash
docker compose -p catlab-drinks-walk -f docker-compose.yml -f tools/walkthrough/compose.override.yml down -v
```

Then start the stack again with step 1.

## Environment variables

| Variable | Default | Meaning |
|----------|---------|---------|
| `WALK_BASE_URL` | `http://localhost:8098` | The address of the stack. The override file publishes port 8098 on `127.0.0.1`. |
| `WALK_USER` | `walk@example.test` | The email of the user that `blade-setup` makes and that the script logs in with |
| `WALK_PASSWORD` | `walk-password-1` | The password of that user |
| `WALK_NFC_SIM` | `http://127.0.0.1:3030` | The address of the NFC simulator |
| `WALK_CARD_UID` | `04a1b2c3d4e5f6` | The UID of the simulated card |
| `WALK_ONLY` | empty | A regular expression. If set, only the items with a matching ID run. Most items need earlier items, so use it only to debug. |
| `WALK_ITEM_TIMEOUT_MS` | `120000` | The maximum time for one item, in milliseconds. If an item takes longer, it fails and the run stops. Each later item gets the note `not run: an earlier item timed out (<id>)`. |
| `WALK_HEADED` | empty | If set, Chrome opens a window |
| `WALK_PLAYWRIGHT` | empty | The folder of the Playwright package |

## Rules of the script

- The script blocks each request to a host that is not `localhost` or `127.0.0.1`. It also blocks each such websocket.
- If the request goes to a public host other than the Google font hosts, the item fails with a note.
- Manage shows the connect URL with the host `drinks.catlab.eu`. The script never opens that host. It opens the same path on `WALK_BASE_URL`.
- The script never clicks `Buy License`, `Open POS on this device`, or `Client order form`. It reads their links only.
- The script accepts each browser dialog. It answers each prompt with the name that the item needs.
- Some items expect an error status on purpose, for example the 403 of `/register`. Such an item moves that one console error from `consoleErrors` to its note.

## Manual items

The items with mode `manual` in the list need a person.
For `manage-org-switch`, give the user a second organisation with this command, and then reload Manage:

```bash
docker exec catlab-drinks-walk php artisan tinker --execute='$o = App\Models\Organisation::create(["name" => "Walk Org Two"]); App\Models\User::first()->organisations()->attach($o);'
```

## Stop and clean up

1. Stop the NFC simulator with Ctrl+C.
2. Remove the walk-through project and its volume.

   ```bash
   docker compose -p catlab-drinks-walk -f docker-compose.yml -f tools/walkthrough/compose.override.yml down -v
   ```

3. If you tagged a local image in step 1, remove the tag with `docker rmi catlab-drinks-walk-webserver:latest`.

## Notes about this machine type

- If `docker compose up` fails with `all predefined address pools have been fully subnetted`, the override file already gives the network the subnet `10.213.98.0/24`. If that subnet is in use, change it in `compose.override.yml`.
- The stack mounts the checkout. If `.env` is missing, `docker/startup.sh` copies `.env.example` to `.env`. If `APP_KEY` is empty, the script writes a key into `.env`.
- The stack shares `storage/` with the checkout, so its log lines go to `storage/logs/` of the checkout.
