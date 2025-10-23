
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
