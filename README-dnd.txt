Drag & Drop enhancement
=======================
1) Install deps:
   yarn add react-grid-layout react-resizable
2) Ensure your Encore entry includes assets/styles/tiles.css
3) Replace assets/components/TilesDashboard.jsx with the version included here.
4) The layout is persisted per tile via /api/user-tiles/{id}/layout (PATCH).
