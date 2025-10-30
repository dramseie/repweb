import React, { useEffect, useState } from 'react';

// Minimal Mail UI skeleton. This requires installing a WYSIWYG editor such as Trumbowyg
// (https://alex-d.github.io/Trumbowyg/) or a React wrapper. This file is a starting
// point and intentionally small. You can expand it with pagination, attachment upload, etc.

export default function MailApp() {
  const [messages, setMessages] = useState([]);
  const [selected, setSelected] = useState(null);
  const [composeHtml, setComposeHtml] = useState('');
  const [to, setTo] = useState('');
  const [subject, setSubject] = useState('');

  useEffect(() => {
    fetch('/api/mig/mail/')
      .then((r) => r.json())
      .then((data) => setMessages(data.items || []));
  }, []);

  const loadMessage = (id: number) => {
    fetch(`/api/mig/mail/${id}`)
      .then((r) => r.json())
      .then((d) => setSelected(d));
  };

  const handleSend = async () => {
    const payload = {
      to: to.split(',').map((s) => s.trim()),
      subject,
      html: composeHtml,
    };
    const res = await fetch('/api/mig/mail/send', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (data.error) {
      alert('Send error: ' + data.error);
    } else {
      alert('Queued: ' + data.id);
    }
  };

  return (
    <div className="container py-3">
      <div className="row">
        <div className="col-md-4">
          <h5>Inbox</h5>
          <div className="list-group">
            {messages.map((m) => (
              <button key={m.id} className="list-group-item list-group-item-action" onClick={() => loadMessage(m.id)}>
                <div className="fw-semibold">{m.subject}</div>
                <div className="small text-muted">{m.from} · {new Date(m.createdAt).toLocaleString()}</div>
              </button>
            ))}
          </div>
        </div>
        <div className="col-md-8">
          <h5>Read / Compose</h5>
          {selected ? (
            <div className="card mb-3">
              <div className="card-body">
                <h6>{selected.subject}</h6>
                <div dangerouslySetInnerHTML={{ __html: selected.bodyHtml || selected.bodyText || '<i>(no body)</i>' }} />
              </div>
            </div>
          ) : (
            <div className="mb-3 text-muted">Select a message to view it.</div>
          )}

          <div className="card">
            <div className="card-body">
              <h6>Compose</h6>
              <div className="mb-2">
                <input className="form-control" placeholder="To (comma separated)" value={to} onChange={(e) => setTo(e.target.value)} />
              </div>
              <div className="mb-2">
                <input className="form-control" placeholder="Subject" value={subject} onChange={(e) => setSubject(e.target.value)} />
              </div>
              <div className="mb-2">
                {/* Replace with a proper WYSIWYG component. Example: use react-trumbowyg or a modern editor like Quill. */}
                <textarea className="form-control" rows={6} value={composeHtml} onChange={(e) => setComposeHtml(e.target.value)} />
              </div>
              <div className="d-flex justify-content-end">
                <button className="btn btn-primary" onClick={handleSend}>Send</button>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  );
}
