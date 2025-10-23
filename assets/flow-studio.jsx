// assets/flow-studio.jsx
import React from "react";
import { createRoot } from "react-dom/client";
import FlowStudio from "./react/FlowStudio";
import "reactflow/dist/style.css";

const el = document.getElementById("flow-studio-root");
if (el) createRoot(el).render(<FlowStudio />);
