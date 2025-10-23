// assets/js/accounting_boot.js
import React from "react";
import { createRoot } from "react-dom/client";
import AccountingApp from "./AccountingApp.jsx";

const mount = document.getElementById("accounting-root");
if (mount) {
  const root = createRoot(mount);
  root.render(<AccountingApp />);
}
