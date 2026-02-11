
# repweb – React MegaNavbar for Symfony (DB-driven, production-ready)

This package includes a production-ready **React MegaNavbar** (Bootstrap 5) for your Symfony app,
served by a **/api/menu** endpoint using your DB-driven `MenuItem` tree.

## Quick start

### 1) Backend (Symfony)
- Copy `backend/src/Controller/Api/MenuController.php` to `src/Controller/Api/`.
- Ensure `App\Service\MenuBuilder` exists (from earlier) and returns the menu tree.
- Seed sample data (optional): run `backend/sql/menu_seed.sql` in MariaDB.

### 2) Frontend (Vite + React)
```bash
cd frontend
npm install
npm run dev
```
- Dev preview: open `http://localhost:5173/` (shows the navbar).
- To mount inside Symfony, add to your Twig layout:
  ```twig
  <div id="react-meganavbar"></div>
  {{ asset('build/assets/main.js') }}
  ```
  or, if you use Symfony UX/Vite:
  ```twig
  {{ vite_entry_link_tags('main') }}
  {{ vite_entry_script_tags('main') }}
  <div id="react-meganavbar"></div>
  ```

### 3) Build for Symfony
```bash
npm run build
```
- Outputs to `public/build` (see `vite.config.ts`).

### Notes
- Uses `bootstrap.bundle` (Popper included) for dropdowns.
- Icons: put Bootstrap Icons CSS in your base layout if you use `bi` classes.
- API returns fields: `id, label, url, icon, external, megaGroup, children[]`.
- Role filtering & route resolution are handled server-side by `MenuBuilder`.
# repweb

## Smartsheet embed (server-to-server token)

This project supports a menu-less Smartsheet embed route that is protected by a short-lived token.

### 1) Mint a token (server-to-server)
```bash
curl -X POST "https://repweb.ramseier.com/api/embed/token" \
  -H "X-Embed-Key: <EMBED_API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{"expiresIn": 600}'
```

### 2) Embed the iframe
```html
<iframe
  src="https://repweb.ramseier.com/smartsheet/embed?token=YOUR_TOKEN"
  referrerpolicy="no-referrer"
  sandbox="allow-scripts allow-same-origin allow-forms"
  style="width:100%;height:900px;border:0;"
></iframe>
```

### Required env vars
- `EMBED_TOKEN_SECRET`
- `EMBED_API_KEY`
