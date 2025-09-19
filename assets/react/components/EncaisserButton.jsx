// assets/react/components/EncaisserButton.jsx
import React, { useState } from "react";
import PaymentDialog from "./PaymentDialog";

export default function EncaisserButton({ order, rendezVousAtIso, onSaved }) {
  const [open, setOpen] = useState(false);

  const amountDueCents = order.total_cents; // from orders.total_cents

  const savePayment = async (payload) => {
    const res = await fetch(`/api/pos/orders/${order.id}/encaisser`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    if (!res.ok) throw new Error(await res.text());
    const saved = await res.json();
    onSaved?.(saved);
  };

  return (
    <>
      <button className="btn btn-success" onClick={() => setOpen(true)}>
        Encaisser
      </button>
      <PaymentDialog
        show={open}
        onClose={() => setOpen(false)}
        onConfirm={savePayment}
        amountDueCents={amountDueCents}
        rendezVousAtIso={rendezVousAtIso} // pass appointment start (real_start_at or start_at)
      />
    </>
  );
}
