import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';

type AddressLike = string | string[] | Record<string, unknown> | null | undefined;

type MailSummary = {
  id: number;
  subject?: string | null;
  from?: string | null;
  createdAt: string;
  snippet?: string | null;
  preview?: string | null;
  tags?: string[] | Record<string, string>;
};

type MailAttachment = {
  id: number | string;
  filename: string;
  sizeBytes?: number | null;
  url?: string | null;
  downloadUrl?: string | null;
};

type MailDetail = MailSummary & {
  to?: AddressLike;
  cc?: AddressLike;
  bcc?: AddressLike;
  bodyHtml?: string | null;
  bodyText?: string | null;
  attachments?: MailAttachment[] | null;
};

const formatDateTime = (value: string) => {
  if (!value) return '';
  const iso = value.includes('T') ? value : value.replace(' ', 'T');
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
};

const extractSnippet = (msg: Partial<MailSummary>) => {
  const raw =
    (msg.snippet as string | undefined) ??
    (msg.preview as string | undefined) ??
    (msg as any)?.bodyText ??
    '';
  const value = typeof raw === 'string' ? raw : Array.isArray(raw) ? raw.join(' ') : '';
  return value.replace(/\s+/g, ' ').trim();
};

const humanSize = (bytes?: number | null) => {
  if (!bytes || bytes <= 0) return '';
  const units = ['B', 'KB', 'MB', 'GB'];
  const index = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
  const value = bytes / Math.pow(1024, index);
  return `${value >= 10 ? value.toFixed(0) : value.toFixed(1)} ${units[index]}`;
};

const escapePlain = (value: string) =>
  value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\r?\n/g, '<br />');

const normalizeAddresses = (value: AddressLike) => {
  if (!value) return '';
  if (Array.isArray(value)) return value.join(', ');
  if (typeof value === 'string') return value;
  if (typeof value === 'object') {
    return Object.values(value)
      .filter(Boolean)
      .map((entry) => String(entry))
      .join(', ');
  }
  return String(value);
};

export default function MailApp() {
  const [messages, setMessages] = useState<MailSummary[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [selected, setSelected] = useState<MailDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadingMessage, setLoadingMessage] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [query, setQuery] = useState('');
  const [to, setTo] = useState('');
  const [subject, setSubject] = useState('');
  const [composeHtml, setComposeHtml] = useState('');
  const [sending, setSending] = useState(false);
  const [toast, setToast] = useState<{ kind: 'success' | 'error'; text: string } | null>(null);

  const composeRef = useRef<HTMLDivElement | null>(null);
  const selectedIdRef = useRef<number | null>(null);

  useEffect(() => {
    selectedIdRef.current = selectedId;
  }, [selectedId]);

  useEffect(() => {
    if (!toast) return;
    const timer = window.setTimeout(() => setToast(null), 4500);
    return () => window.clearTimeout(timer);
  }, [toast]);

  const showToast = useCallback((kind: 'success' | 'error', text: string) => {
    setToast({ kind, text });
  }, []);

  const loadMessage = useCallback(
    async (id: number) => {
      setSelectedId(id);
      setLoadingMessage(true);
      try {
        const response = await fetch(`/api/mig/mail/${id}`);
        if (!response.ok) throw new Error('Unable to open message.');
        const detail = (await response.json()) as MailDetail;
        setSelected(detail);
      } catch (err) {
        showToast('error', err instanceof Error ? err.message : 'Unable to open message.');
      } finally {
        setLoadingMessage(false);
      }
    },
    [showToast]
  );

  const fetchInbox = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await fetch('/api/mig/mail/');
      if (!response.ok) throw new Error('Unable to load inbox.');
      const payload = await response.json();
      const items = Array.isArray(payload?.items) ? (payload.items as MailSummary[]) : [];
      setMessages(items);

      if (items.length === 0) {
        setSelected(null);
        setSelectedId(null);
        return;
      }

      const current = selectedIdRef.current;
      const next =
        current && items.some((item) => item.id === current) ? current : items[0].id;
      await loadMessage(next);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to load inbox.');
    } finally {
      setLoading(false);
    }
  }, [loadMessage]);

  useEffect(() => {
    fetchInbox();
  }, [fetchInbox]);

  const filtered = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (!needle) return messages;
    return messages.filter((msg) => {
      const haystack = [
        msg.subject ?? '',
        msg.from ?? '',
        extractSnippet(msg),
        formatDateTime(msg.createdAt),
      ];
      return haystack.some((part) => part.toLowerCase().includes(needle));
    });
  }, [messages, query]);

  const bodyMarkup = useMemo(() => {
    if (!selected) return { __html: '' };
    if (selected.bodyHtml) return { __html: selected.bodyHtml };
    if (selected.bodyText) return { __html: `<div class="mail-body-text">${escapePlain(selected.bodyText)}</div>` };
    return { __html: '<p class="mail-empty">No message content.</p>' };
  }, [selected]);

  const attachments = useMemo(() => {
    if (!selected?.attachments) return [];
    return selected.attachments.filter(Boolean) as MailAttachment[];
  }, [selected]);

  const onSubmitCompose = useCallback(
    async (event: React.FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      if (sending) return;

      const recipients = to
        .split(',')
        .map((entry) => entry.trim())
        .filter(Boolean);

      if (recipients.length === 0) {
        showToast('error', 'Add at least one recipient.');
        return;
      }

      try {
        setSending(true);
        const payload = {
          to: recipients,
          subject: subject.trim(),
          html: composeHtml.trim() || null,
        };
        const response = await fetch('/api/mig/mail/send', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (!response.ok || data.error) {
          throw new Error(data.error || 'Send failed.');
        }

        setTo('');
        setSubject('');
        setComposeHtml('');
        showToast('success', 'Message queued for delivery.');
        await fetchInbox();
      } catch (err) {
        showToast('error', err instanceof Error ? err.message : 'Unable to send message.');
      } finally {
        setSending(false);
      }
    },
    [composeHtml, fetchInbox, sending, showToast, subject, to]
  );

  const scrollToCompose = useCallback(() => {
    composeRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, []);

  const messageCount = messages.length;
  const heroSubtitle = selected
    ? `Viewing message from ${selected.from || 'Unknown sender'}`
    : 'Select a message to preview it.';

  return (
    <div className="mail-app">
      <header className="mail-hero">
        <div>
          <span className="mail-eyebrow">Communications</span>
          <h1>Mail Center</h1>
          <div className="mail-hero__meta">
            <span>{messageCount} {messageCount === 1 ? 'message' : 'messages'}</span>
            <span>{heroSubtitle}</span>
            {loading && <span className="mail-hero__spinner">Refreshing...</span>}
          </div>
          {error && <div className="mail-status mail-status--error">{error}</div>}
        </div>
        <div className="mail-hero__actions">
          <button className="psr-button psr-button--ghost" onClick={fetchInbox} type="button">
            Refresh
          </button>
          <button className="psr-button psr-button--primary" onClick={scrollToCompose} type="button">
            Compose
          </button>
        </div>
      </header>

      <div className="mail-shell">
        <aside className="mail-card mail-inbox">
          <div className="mail-inbox__header">
            <div className="mail-inbox__title">
              <h2>Inbox</h2>
              <span className="mail-inbox__count">{messageCount}</span>
            </div>
            <div className="mail-inbox__search">
              <input
                className="mail-search"
                placeholder="Search mail"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
              />
            </div>
          </div>
          <div className="mail-inbox__list">
            {loading && !messages.length && (
              <div className="mail-empty">Loading inbox...</div>
            )}
            {!loading && filtered.length === 0 && (
              <div className="mail-empty">No messages match your search.</div>
            )}
            {filtered.map((msg) => {
              const snippet = extractSnippet(msg);
              const isActive = msg.id === selectedId;
              return (
                <button
                  key={msg.id}
                  className={`mail-message${isActive ? ' mail-message--active' : ''}`}
                  type="button"
                  onClick={() => loadMessage(msg.id)}
                >
                  <div className="mail-message__subject">{msg.subject || '(No subject)'}</div>
                  <div className="mail-message__meta">
                    <span>{msg.from || 'Unknown sender'}</span>
                    <span>{formatDateTime(msg.createdAt)}</span>
                  </div>
                  {snippet && <div className="mail-message__snippet">{snippet}</div>}
                </button>
              );
            })}
          </div>
        </aside>

        <main className="mail-card mail-reader">
          {loadingMessage && (
            <div className="mail-reader__loading">Loading message...</div>
          )}
          {!loadingMessage && selected && (
            <div className="mail-detail">
              <header className="mail-detail__header">
                <h2 className="mail-detail__subject">{selected.subject || '(No subject)'}</h2>
                <div className="mail-detail__meta">
                  <span>From: {selected.from || 'Unknown sender'}</span>
                  {normalizeAddresses(selected.to) && (
                    <span>To: {normalizeAddresses(selected.to)}</span>
                  )}
                  {normalizeAddresses(selected.cc) && (
                    <span>Cc: {normalizeAddresses(selected.cc)}</span>
                  )}
                  <span>Received: {formatDateTime(selected.createdAt)}</span>
                </div>
                {selected.tags && (
                  <div className="mail-detail__tags">
                    {Array.isArray(selected.tags)
                      ? selected.tags.map((tag) => (
                          <span key={tag} className="mail-badge">
                            {tag}
                          </span>
                        ))
                      : Object.entries(selected.tags).map(([key, value]) => (
                          <span key={key} className="mail-badge">
                            {key}: {value}
                          </span>
                        ))}
                  </div>
                )}
              </header>
              <section className="mail-detail__body" dangerouslySetInnerHTML={bodyMarkup} />
              {attachments.length > 0 && (
                <section className="mail-attachments">
                  <h3>Attachments</h3>
                  <ul>
                    {attachments.map((att) => {
                      const href = att.url ?? att.downloadUrl ?? null;
                      return (
                        <li key={att.id} className="mail-attachment">
                          <span className="mail-attachment__icon" aria-hidden="true">ATT</span>
                          <span className="mail-attachment__name">{att.filename}</span>
                          {att.sizeBytes ? (
                            <span className="mail-attachment__size">{humanSize(att.sizeBytes)}</span>
                          ) : null}
                          {href ? (
                            <a className="mail-attachment__link" href={href} target="_blank" rel="noreferrer">
                              Download
                            </a>
                          ) : null}
                        </li>
                      );
                    })}
                  </ul>
                </section>
              )}
            </div>
          )}
          {!loadingMessage && !selected && (
            <div className="mail-reader__placeholder">
              <h2>Select a conversation</h2>
              <p>Choose a message from the inbox to view its contents.</p>
            </div>
          )}
        </main>

        <aside ref={composeRef} className="mail-card mail-compose">
          <header className="mail-compose__header">
            <h2>Compose</h2>
            <p>Draft a new message or paste content from an external editor.</p>
          </header>
          <form className="mail-compose__form" onSubmit={onSubmitCompose}>
            <label className="mail-field">
              <span>To</span>
              <input
                className="mail-input"
                placeholder="Recipient email, comma separated"
                value={to}
                onChange={(event) => setTo(event.target.value)}
              />
            </label>
            <label className="mail-field">
              <span>Subject</span>
              <input
                className="mail-input"
                placeholder="Add a short subject"
                value={subject}
                onChange={(event) => setSubject(event.target.value)}
              />
            </label>
            <label className="mail-field mail-field--textarea">
              <span>Message</span>
              {/* Replace this textarea with a rich text editor (Quill, TipTap, Trumbowyg, etc.) */}
              <textarea
                className="mail-textarea"
                rows={10}
                value={composeHtml}
                onChange={(event) => setComposeHtml(event.target.value)}
                placeholder="Type your message..."
              />
            </label>
            <div className="mail-compose__actions">
              <button
                type="submit"
                className="psr-button psr-button--primary"
                disabled={sending}
              >
                {sending ? 'Sending...' : 'Send'}
              </button>
            </div>
          </form>
        </aside>
      </div>

      {toast && (
        <div className={`mail-toast mail-toast--${toast.kind}`}>
          <span>{toast.text}</span>
          <button type="button" onClick={() => setToast(null)} aria-label="Dismiss">
            Close
          </button>
        </div>
      )}
    </div>
  );
}
