---
name: run-demo
description: Install, launch and verify the Symfony demo app in demo/ (Vite dev server + PHP built-in server), including a headless-browser check that React actually renders.
---

# Run the demo app

`demo/` is a Symfony app (`php-architecture-kit/demo`) with a React + TSX frontend built by Vite
(`pentatrion/vite-bundle` + `vite-plugin-symfony`). npm workspaces are declared in the workspace
root `package.json`, so **npm commands run from the workspace root**, PHP commands from `demo/`.

## 1. Prerequisites

- PHP 8.4+ and Composer.
- **Linux** Node ≥ 20.19 with npm (Vite 8). On WSL, check `which npm`: if it resolves to
  `/mnt/c/...`, that is Windows npm and it fails with `EISDIR` on symlinks under
  `\\wsl.localhost`. Install Node in WSL instead, e.g. without sudo:

  ```bash
  V=$(curl -sSf https://nodejs.org/dist/index.json | python3 -c "import json,sys;print(next(r['version'] for r in json.load(sys.stdin) if r['version'].startswith('v22.')))")
  mkdir -p ~/.local/opt && cd ~/.local/opt && curl -sSfL https://nodejs.org/dist/$V/node-$V-linux-x64.tar.xz | tar xJ
  ln -sfn ~/.local/opt/node-$V-linux-x64 ~/.local/opt/node
  for b in node npm npx corepack; do ln -sf ~/.local/opt/node/bin/$b ~/.local/bin/$b; done
  hash -r && node -v && npm -v
  ```

  `~/.local/bin` must come before `/mnt/c/...` in `PATH`.

## 2. Install

```bash
cd demo && XDEBUG_MODE=off composer install && cd ..   # PHP deps (own vendor/)
npm install                                            # from the workspace root
```

## 3. Launch (two background processes)

```bash
npm run demo:dev                                       # workspace root → Vite on :5173
cd demo && XDEBUG_MODE=off php -S 127.0.0.1:8000 -t public
```

Open http://127.0.0.1:8000.

- Run `npm run demo:dev` **without extra arguments**. `npm run demo:dev -- --host X` passes `X`
  to Vite as the project root, so the dev server never writes its entrypoints.
- If Vite is not running, Symfony serves the last production build from `demo/public/build/`
  (or fails when there is none). Build it with `npm run demo:build` (runs `tsc` first).

## 4. Verify

1. **Dev entrypoints**: Symfony must point at the Vite server, not at `/build/assets/*.js`:

   ```bash
   curl -s http://127.0.0.1:8000/ | grep -E '<script'
   # expect: http://127.0.0.1:5173/build/@vite/client and .../build/assets/app.tsx
   ```

2. **React renders**: the HTML only contains `<div data-react-component=…>`, so check in a real
   browser. On WSL, Windows Chrome reaches WSL's `127.0.0.1`:

   ```bash
   CHROME="/mnt/c/Program Files/Google/Chrome/Application/chrome.exe"
   cd /mnt/c/Users/Public
   "$CHROME" --headless=new --disable-gpu --user-data-dir='C:\Users\Public\pak-demo-profile' \
     --enable-logging=stderr --v=0 --window-size=1200,700 --virtual-time-budget=5000 \
     --screenshot='C:\Users\Public\pak-demo.png' http://127.0.0.1:8000/ 2>&1 | grep CONSOLE
   ```

   Look at the screenshot: the page title, the PHP/Symfony badges and the package list must be
   visible. A page with only the background colour means CSS loaded but React did not mount.
   Delete `pak-demo.png` and `pak-demo-profile` afterwards.

## Known failure

`Uncaught Error: @vitejs/plugin-react can't detect preamble` in the console: the React Refresh
preamble is missing. `templates/base.html.twig` must call
`vite_entry_script_tags('app', { dependency: 'react' })`.
