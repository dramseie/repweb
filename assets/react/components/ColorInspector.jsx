import React, { useCallback, useEffect, useMemo, useRef, useState } from "react";
import Webcam from "react-webcam";
import tinycolor from "tinycolor2";

const HARMONIES = [
  { key: "complement", label: "Complémentaire" },
  { key: "triad", label: "Triadique" },
  { key: "analogous", label: "Analogique" },
];

const normalizeHex = (value) => {
  const color = tinycolor(value);
  return color.isValid() ? color.toHexString() : "#cccccc";
};

const harmonyPalette = (hex, mode) => {
  const base = tinycolor(hex);
  if (!base.isValid()) return [];

  switch (mode) {
    case "complement":
      return [base, base.complement()];
    case "triad":
      return base.triad();
    case "analogous":
      return base.analogous();
    default:
      return [base];
  }
};

const toHexPalette = (colors) => {
  const set = new Set();
  colors.forEach((c) => {
    const hex = c.toHexString();
    if (!set.has(hex)) set.add(hex);
  });
  return Array.from(set);
};

export default function ColorInspector({ ideasEndpoint = null }) {
  const webcamRef = useRef(null);
  const canvasRef = useRef(null);

  const [isCameraReady, setIsCameraReady] = useState(false);
  const [cameraError, setCameraError] = useState("");
  const [previewColor, setPreviewColor] = useState("#cccccc");
  const [activeHarmony, setActiveHarmony] = useState(HARMONIES[0].key);
  const [aiState, setAiState] = useState({ loading: false, message: "", suggestions: [] });
  const [hexInput, setHexInput] = useState(previewColor);
  const [isCameraVisible, setIsCameraVisible] = useState(true);

  useEffect(() => {
    return () => {
      const stream = webcamRef.current?.video?.srcObject;
      if (stream instanceof MediaStream) {
        stream.getTracks().forEach((track) => track.stop());
      }
    };
  }, []);

  useEffect(() => {
    setHexInput(previewColor.toUpperCase());
  }, [previewColor]);

  const palette = useMemo(() => {
    return toHexPalette(harmonyPalette(previewColor, activeHarmony));
  }, [previewColor, activeHarmony]);

  const handleHarmonyChange = useCallback((event) => {
    setActiveHarmony(event.target.value);
  }, []);

  const sampleColor = useCallback(() => {
    const video = webcamRef.current?.video;
    const canvas = canvasRef.current;
    if (!video || !canvas) return;

    const { videoWidth, videoHeight } = video;
    if (!videoWidth || !videoHeight) return;

    canvas.width = videoWidth;
    canvas.height = videoHeight;

    const ctx = canvas.getContext("2d", { willReadFrequently: true });
    if (!ctx) return;

    ctx.drawImage(video, 0, 0, videoWidth, videoHeight);
    const size = 8;
    const x = Math.floor(videoWidth / 2 - size / 2);
    const y = Math.floor(videoHeight / 2 - size / 2);
    const data = ctx.getImageData(x, y, size, size).data;

    // Average a small square of pixels to smooth sensor noise
    let r = 0;
    let g = 0;
    let b = 0;
    const pixelCount = size * size;
    for (let i = 0; i < data.length; i += 4) {
      r += data[i];
      g += data[i + 1];
      b += data[i + 2];
    }

    const hex = tinycolor({
      r: Math.round(r / pixelCount),
      g: Math.round(g / pixelCount),
      b: Math.round(b / pixelCount),
    }).toHexString();

    setPreviewColor(hex);
  }, []);

  const handleManualColor = useCallback((event) => {
    const next = normalizeHex(event.target.value);
    setPreviewColor(next);
  }, []);

  const handleHexChange = useCallback((event) => {
    const value = event.target.value.trim();
    setHexInput(value);
    if (/^#?[0-9A-Fa-f]{6}$/.test(value)) {
      setPreviewColor(normalizeHex(value));
    }
  }, []);

  const toggleCamera = useCallback(() => {
    setIsCameraVisible((visible) => {
      if (visible) {
        const stream = webcamRef.current?.video?.srcObject;
        if (stream instanceof MediaStream) {
          stream.getTracks().forEach((track) => track.stop());
        }
        setIsCameraReady(false);
      }
      return !visible;
    });
  }, []);

  const requestAiIdeas = useCallback(async () => {
    if (!ideasEndpoint) {
      setAiState({ loading: false, message: "Aucun service d'idées IA n'est configuré.", suggestions: [] });
      return;
    }

    try {
      setAiState({ loading: true, message: "", suggestions: [] });
      const response = await fetch(ideasEndpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ baseColor: previewColor, palette }),
      });

      if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
      }

      const payload = await response.json();
      const suggestions = Array.isArray(payload?.suggestions)
        ? payload.suggestions
            .map((item) => {
              if (!item) return null;
              if (typeof item === "string") {
                return { idea: item, color: payload?.baseColor || previewColor };
              }
              if (typeof item === "object") {
                const idea = typeof item.idea === "string" ? item.idea.trim() : typeof item.idee === "string" ? item.idee.trim() : "";
                if (!idea) return null;
                const color = typeof item.color === "string" ? item.color : typeof item.couleur === "string" ? item.couleur : (payload?.baseColor || previewColor);
                return { idea, color };
              }
              return null;
            })
            .filter(Boolean)
        : [];

      setAiState({
        loading: false,
        message: suggestions.length ? "" : "Aucune idée disponible pour l'instant.",
        suggestions,
      });
    } catch (error) {
      setAiState({
        loading: false,
        message: error.message ? `Erreur IA : ${error.message}` : "Impossible de récupérer des idées.",
        suggestions: [],
      });
    }
  }, [ideasEndpoint, palette, previewColor]);

  return (
    <section className="color-inspector">
      <div className="color-inspector__stack">
        <div className="color-inspector__preview">
          <div className="color-inspector__preview-header">
            <button
              type="button"
              className="btn btn-outline-secondary btn-sm"
              onClick={toggleCamera}
            >
              {isCameraVisible ? "Masquer la caméra" : "Afficher la caméra"}
            </button>
          </div>

          {isCameraVisible && (
            <Webcam
              ref={webcamRef}
              audio={false}
              mirrored
              onUserMedia={() => setIsCameraReady(true)}
              onUserMediaError={(err) => setCameraError(err?.message || "Camera access denied")}
              videoConstraints={{ facingMode: "environment" }}
              className="color-inspector__video"
            />
          )}

          <div className="color-inspector__controls">
            <button
              type="button"
              className="btn btn-primary"
              onClick={sampleColor}
              disabled={!isCameraReady || Boolean(cameraError)}
            >
                Capturer la couleur centrale
            </button>

            <select
              className="form-select"
              value={activeHarmony}
              onChange={handleHarmonyChange}
            >
              {HARMONIES.map((item) => (
                <option key={item.key} value={item.key}>
                  {item.label}
                </option>
              ))}
            </select>
          </div>

          <div className="color-inspector__swatch" style={{ backgroundColor: previewColor }}>
            <span>{previewColor}</span>
          </div>

          <div className="color-inspector__chooser">
            <label className="form-label">Ajuster la couleur</label>
            <div className="color-inspector__chooser-inputs">
              <input
                type="color"
                className="color-inspector__color-input"
                value={previewColor}
                onChange={handleManualColor}
                aria-label="Choisir une couleur"
              />
              <input
                type="text"
                className="form-control color-inspector__hex-input"
                value={hexInput}
                onChange={handleHexChange}
                aria-label="Entrer un code couleur hexadécimal"
              />
            </div>
          </div>

          {cameraError && (
            <div className="alert alert-warning mt-3" role="alert">
              {cameraError}
            </div>
          )}
        </div>

        <div className="color-inspector__palette">
          <h2 className="h5">Palette</h2>
          <ul className="list-unstyled d-flex flex-wrap gap-3">
            {palette.map((color) => (
              <li key={color} className="color-inspector__palette-item">
                <div className="color-inspector__tile" style={{ backgroundColor: color }} />
                <span>{color}</span>
              </li>
            ))}
          </ul>

          <button
            type="button"
            className="btn btn-outline-secondary"
            onClick={requestAiIdeas}
            disabled={aiState.loading}
          >
            {aiState.loading ? "Chargement des idées…" : "Demander des idées IA"}
          </button>

          {aiState.suggestions.length > 0 && (
            <ul className="color-inspector__ideas mt-3">
              {aiState.suggestions.map((entry, index) => (
                <li key={`${entry.color}-${index}`} className="color-inspector__idea-item">
                  <span
                    className="color-inspector__idea-chip"
                    style={{ backgroundColor: entry.color }}
                    aria-hidden="true"
                  />
                  <span>{entry.idea}</span>
                </li>
              ))}
            </ul>
          )}

          {aiState.message && (
            <div className="alert alert-info mt-3" role="status">
              {aiState.message}
            </div>
          )}
        </div>
      </div>

      <canvas ref={canvasRef} style={{ display: "none" }} />
    </section>
  );
}
