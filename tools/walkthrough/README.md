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
   `docker tag catlab-drinks:php85 catlab-drinks-walk-webserver:latest` and then the same command with `--no-build` in place of `--build`.
   The header of `compose.override.yml` shows both commands.
   Wait until `docker logs catlab-drinks-walk` shows `Startup complete!`.

2. Make a production build. The committed baseline report comes from a production build. If you compare with it, use the same build type.

   ```bash
   npm run prod
   ```

   If you must read the real function names in console errors, make a development build with `npm run dev`.
   Do not compare a development run with the committed production baseline. Some messages differ between the two build types.

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
The last entry is the pseudo-item `between-items`. It collects the console errors, page errors, failed API calls, and blocked requests that arrive while no item runs.
Its note tells after which item each one arrived. If it has a console error or a blocked request, its status is `fail`. If not, its status is `pass`.
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
| `WALK_CARD_UID` | `04a1b2c3` and the six digits of the run ID | The UID of the simulated card. Each run makes a new UID, so that a second run on the same database gets a blank card. |
| `WALK_ONLY` | empty | A regular expression. If set, only the items with a matching ID run. Most items need earlier items, so use it only to debug. If it matches no item, the script stops with exit code 2. |
| `WALK_ITEM_TIMEOUT_MS` | `120000` | The maximum time for one item, in milliseconds. If an item takes longer, it fails and the run stops. Each later item gets the note `not run: an earlier item timed out (<id>)`. |
| `WALK_HEADED` | empty | If set, Chrome opens a window |
| `WALK_PLAYWRIGHT` | empty | The folder of the Playwright package |

## Rules of the script

- The script blocks each request to a host that is not `localhost` or `127.0.0.1`. It also blocks each such websocket. This route guard sees only the requests of the browser.
- The script also makes its own requests: `page.request` to the stack and Node `fetch` to the NFC simulator. The route guard does not see them. Before the first request, the script makes sure that the hosts of `WALK_BASE_URL` and `WALK_NFC_SIM` are `localhost`, `127.0.0.1`, or `[::1]`. If not, it stops with a message and exit code 2.
- If the request goes to a public host other than the Google font hosts, the item fails with a note.
- Manage shows the connect URL with the host `drinks.catlab.eu`. The script never opens that host. It opens the same path on `WALK_BASE_URL`.
- The script never clicks `Buy License`, `Open POS on this device`, or `Client order form`. It reads their links only.
- The script accepts each browser dialog. It answers each prompt with the name that the item needs.
- Some items expect an error status on purpose, for example the 403 of `/register`. Such an item moves that one console error from `consoleErrors` to its note.

## Manual items

The items with mode `manual` in the list need a person.
For `manage-org-switch`, give the user a second organisation with this command, and then reload Manage.
The image has no `tinker`, so the command starts the Laravel kernel with `php -r`.
It prints the ID of the new organisation.

```bash
docker exec catlab-drinks-walk php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $o = App\Models\Organisation::create(["name" => "Walk Org Two"]); App\Models\User::first()->organisations()->attach($o); echo $o->id, PHP_EOL;'
```

Do the manual items after the script run. Record their results in the same table format as the baseline file.

## Compare with the baseline

The committed baseline report is `docs/testing/baseline-2026-11-main.report.json`.
It was recorded on a production build. Run the script on the same build type, and then run this command.

```bash
node tools/walkthrough/compare.mjs docs/testing/baseline-2026-11-main.report.json
```

The second argument is the report to compare. It defaults to `tools/walkthrough/output/report.json`.
The command prints one line for each item that differs, one line for each item that is in one file only, and a summary line.
If there is a difference, the exit code is 1. If there is no difference, the exit code is 0.

- The unit of comparison is not pass or fail. For each item, the command compares the status, the set of console errors, and the failed API calls in the note.
- Any message on any item that is not in the baseline is a regression. This includes items that already fail in the baseline.
- Before the comparison, the command removes what changes between two runs of the same code.
  These are the host and port, numeric IDs, run IDs, UUIDs, hashes, and file positions. The answer and the body of a failed API call are not compared.
- The issue 251 error has a different name in each build type. A table in `compare.mjs` maps the known names to one token. If a new build shows a new minified name, add it to that table.
- Cause `P2` of the baseline (the 422 of the POS offline queue) moves between items, because the queue tries again in the background. The command compares it as one set over the POS block.
  The POS block is all items from the first `pos-sale-*` item to `pos-summary`, and also `nfc-card-payment` and `nfc-card-insufficient-funds`.
  In the block, an item that fails only on that 422 counts as a pass. The command reports the 422 in three cases only. It shows on an item outside the block. It goes away from the whole block. A new form of the message shows.
- The command compares the pseudo-item `between-items` as any other item.
- In the pull request that changes the component library, the issue 251 error must go away. All the other results must stay the same.
  If an item turns from fail to pass for a different reason, explain the reason in the pull request.

## Selectors

The selectors of the script are tied to the markup of BootstrapVue 2 and Bootstrap 4, for example `.modal.show`, `.dropdown-menu.show`, and `.custom-control`.
The pull request that changes the component library must update the selectors together with that change.
Put the selector changes in a commit that changes only selectors.
Do not change the assertions, the expected texts, or the sequence of the items, so that the walk-through keeps its meaning as a gate.
In new code, use locators by role or by text where you can.

## Stop and clean up

1. Stop the NFC simulator with Ctrl+C.
2. Remove the walk-through project and its volume.

   ```bash
   docker compose -p catlab-drinks-walk -f docker-compose.yml -f tools/walkthrough/compose.override.yml down -v
   ```

3. If you tagged a local image in step 1, remove the tag with `docker rmi catlab-drinks-walk-webserver:latest`.

## Notes about this machine type

- If `docker compose up` fails with `all predefined address pools have been fully subnetted`, the override file already gives the network the subnet `10.213.98.0/24`. If that subnet is in use, change it in `compose.override.yml`.
- The stack mounts the checkout. If `.env` is missing, `docker/startup.sh` copies `.env.example` to `.env`. If `APP_KEY` is empty, `docker/startup.sh` writes a key into `.env` with `php artisan key:generate`. It writes nothing else to `.env`.
- The override file sets variables in the environment of the web server. They are `APP_ENV=local`, `USAGE_REPORTING=false`, `ERROR_REPORTING=false`, and the `DB_*` variables.
  The `DB_*` variables point to the `mysql-db` service of this project.
  Laravel reads `.env` only for the variables that are not set already, and this stack does not cache the configuration. So these values win over the `.env` of the checkout. The other values still come from `.env`.
- The route guard of the script sees only the browser. It cannot see requests that the server makes. The override file turns off usage and error reporting for that reason.
- The stack shares `storage/` with the checkout, so its log lines go to `storage/logs/` of the checkout.
