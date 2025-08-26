import React, { useEffect, useRef } from "react";
import { initTrumbowygOn } from "../../../app"; // <- adjust the relative path to your app.js

export default function TrumboField({
  value,
  onChange,
  placeholder = "Write your email…",
  height = 320,
  trumbowygOptions = {}, // pass upload/colors/etc here
  as = "div",            // or "textarea" if you prefer
}) {
  const ref = useRef(null);
  const instance = useRef(null);

  // init once
  useEffect(() => {
    let isMounted = true;
    (async () => {
      const el = ref.current;
      if (!el) return;
      const $el = await initTrumbowygOn(el, trumbowygOptions);
      if (!isMounted) return;

      // set initial value
      $el.trumbowyg("html", value || "");

      // min height
      $el.closest(".trumbowyg-box").find(".trumbowyg-editor").css("min-height", `${height}px`);

      // change propagation
      const handler = () => {
        const html = $el.trumbowyg("html");
        onChange && onChange(html);
      };
      $el.on("tbwchange tbwpaste", handler);
      instance.current = { $el, handler };
    })();

    return () => {
      isMounted = false;
      if (instance.current?.$el) {
        try {
          instance.current.$el.off("tbwchange tbwpaste", instance.current.handler);
          instance.current.$el.trumbowyg("destroy");
        } catch (_) {}
      }
      instance.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // external value updates (e.g. loading an existing template)
  useEffect(() => {
    const $el = instance.current?.$el;
    if ($el) {
      const cur = $el.trumbowyg("html") || "";
      if (cur !== (value || "")) $el.trumbowyg("html", value || "");
    }
  }, [value]);

  const Tag = as; // 'div' or 'textarea'
  return <Tag ref={ref} placeholder={placeholder} data-editor="trumbowyg" />;
}
