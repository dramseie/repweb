import React, { useEffect, useMemo, useState } from 'react';
import { loadRuntime, saveRuntime, type SaveRuntimeRequest } from './runtimeApi';
import type { RuntimeField, RuntimeItem, RuntimePayload } from './types';

type AnswerValue = string | string[] | number | boolean | Record<string, unknown> | null;
type AnswerState = Record<string, AnswerValue>;
type ResponseStatus = 'in_progress' | 'submitted' | string;

interface QuestionnaireRunnerProps {
  ciKey?: string;
  questionnaireId?: number | null;
  adapter?: RuntimeAdapter;
}

interface ItemNode {
  item: RuntimeItem;
  children: ItemNode[];
  depth: number;
}

interface RuntimeAdapter {
  load: () => Promise<RuntimePayload>;
  save: (payload: SaveRuntimeRequest) => Promise<RuntimePayload>;
}

function buildItemTree(items: RuntimeItem[]): ItemNode[] {
  const nodes = new Map<number, ItemNode>();
  const roots: ItemNode[] = [];

  items.forEach((item) => {
    nodes.set(item.id, { item, children: [], depth: 0 });
  });

  items.forEach((item) => {
    const node = nodes.get(item.id);
    if (!node) return;
    if (item.parentId !== null) {
      const parent = nodes.get(item.parentId);
      if (parent) {
        node.depth = parent.depth + 1;
        parent.children.push(node);
        return;
      }
    }
    node.depth = 0;
    roots.push(node);
  });

  const sortNodes = (list: ItemNode[]) => {
    list.sort((a, b) => {
      const sortA = typeof a.item.sort === 'number' ? a.item.sort : 0;
      const sortB = typeof b.item.sort === 'number' ? b.item.sort : 0;
      if (sortA !== sortB) return sortA - sortB;
      return a.item.id - b.item.id;
    });
    list.forEach((child) => {
      child.children.forEach((grandChild) => {
        grandChild.depth = child.depth + 1;
      });
      sortNodes(child.children);
    });
  };

  sortNodes(roots);
  return roots;
}

function flattenTree(nodes: ItemNode[]): Array<{ item: RuntimeItem; depth: number }> {
  const result: Array<{ item: RuntimeItem; depth: number }> = [];
  const dfs = (node: ItemNode) => {
    result.push({ item: node.item, depth: node.depth });
    node.children.forEach(dfs);
  };
  nodes.forEach(dfs);
  return result;
}

function normalizeOptions(options: RuntimeField['options']): Array<{ label: string; value: string }> {
  if (!options) return [];
  return options
    .map((opt) => {
      if (typeof opt === 'string') {
        return { label: opt, value: opt };
      }
      if (opt && typeof opt === 'object') {
        const hasValue = Object.prototype.hasOwnProperty.call(opt, 'value');
        const rawValue = hasValue ? (opt as { value?: unknown }).value : undefined;
        const val = rawValue == null ? '' : String(rawValue);
        const label = (opt as { label?: unknown }).label != null ? String((opt as { label?: unknown }).label) : val;
        return { label, value: val || label };
      }
      return { label: '', value: '' };
    })
    .filter((entry) => entry.value !== '');
}

function extractAnswerState(payload: RuntimePayload): AnswerState {
  const state: AnswerState = {};
  payload.response.answers.forEach((answer) => {
    const key = answer.fieldId ? `f-${answer.fieldId}` : `i-${answer.itemId}`;
    if (answer.valueJson !== null && answer.valueJson !== undefined) {
      state[key] = answer.valueJson as AnswerValue;
    } else if (answer.valueText !== null && answer.valueText !== undefined) {
      state[key] = answer.valueText;
    }
  });
  return state;
}

function isEmptyValue(value: AnswerValue): boolean {
  if (value === null || value === undefined) return true;
  if (typeof value === 'string') return value.trim() === '';
  if (Array.isArray(value)) return value.length === 0;
  if (typeof value === 'object') return Object.keys(value).length === 0;
  return false;
}

function areAnswerValuesEqual(current: AnswerValue | undefined, next: AnswerValue): boolean {
  if (Array.isArray(current) && Array.isArray(next)) {
    if (current.length !== next.length) return false;
    return current.every((value, index) => value === next[index]);
  }
  if (typeof current === 'object' && current !== null && typeof next === 'object' && next !== null && !Array.isArray(next)) {
    const keysCurrent = Object.keys(current);
    const keysNext = Object.keys(next as Record<string, unknown>);
    if (keysCurrent.length !== keysNext.length) return false;
    return keysCurrent.every((key) => (current as Record<string, unknown>)[key] === (next as Record<string, unknown>)[key]);
  }
  return current === next;
}

function buildOutgoingAnswers(payload: RuntimePayload, state: AnswerState): Array<{ itemId: number; fieldId: number | null; value: AnswerValue }> {
  const outgoing: Array<{ itemId: number; fieldId: number | null; value: AnswerValue }> = [];

  payload.items.forEach((item) => {
    const key = `i-${item.id}`;
    if (Object.prototype.hasOwnProperty.call(state, key)) {
      const value = state[key];
      if (!isEmptyValue(value)) {
        outgoing.push({ itemId: item.id, fieldId: null, value });
      }
    }
  });

  payload.fields.forEach((field) => {
    const key = `f-${field.id}`;
    if (Object.prototype.hasOwnProperty.call(state, key)) {
      const value = state[key];
      if (!isEmptyValue(value)) {
        outgoing.push({ itemId: field.itemId, fieldId: field.id, value });
      }
    }
  });

  return outgoing;
}

function asBoolean(value: AnswerValue): boolean {
  if (typeof value === 'boolean') return value;
  if (typeof value === 'number') return value !== 0;
  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase();
    return ['1', 'true', 'yes', 'y'].includes(normalized);
  }
  return false;
}

const QuestionnaireRunner: React.FC<QuestionnaireRunnerProps> = ({ ciKey, questionnaireId = null, adapter }) => {
  const [payload, setPayload] = useState<RuntimePayload | null>(null);
  const [answers, setAnswers] = useState<AnswerState>({});
  const [status, setStatus] = useState<ResponseStatus>('in_progress');
  const [loading, setLoading] = useState<boolean>(true);
  const [saving, setSaving] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [feedback, setFeedback] = useState<string | null>(null);
  const [dirty, setDirty] = useState<boolean>(false);
  const [activeItemId, setActiveItemId] = useState<number | null>(null);
  const [reloadToken, setReloadToken] = useState(0);

  const effectiveLoad = React.useCallback(() => {
    if (adapter) {
      return adapter.load();
    }
    if (ciKey) {
      return loadRuntime(ciKey, questionnaireId);
    }
    return Promise.reject(new Error('No questionnaire selected'));
  }, [adapter, ciKey, questionnaireId]);

  const effectiveSave = React.useCallback(
    (savePayload: SaveRuntimeRequest) => {
      if (adapter) {
        return adapter.save(savePayload);
      }
      if (ciKey) {
        return saveRuntime(ciKey, savePayload, questionnaireId);
      }
      return Promise.reject(new Error('No questionnaire selected'));
    },
    [adapter, ciKey, questionnaireId]
  );

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    setFeedback(null);

    effectiveLoad()
      .then((data) => {
        if (cancelled) return;
        setPayload(data);
        setAnswers(extractAnswerState(data));
        setStatus((data.response.status || 'in_progress') as ResponseStatus);
        setDirty(false);
        const firstQuestion = data.items.find((item) => item.type === 'question');
        setActiveItemId(firstQuestion ? firstQuestion.id : null);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        const message = err instanceof Error ? err.message : 'Failed to load questionnaire';
        setError(message);
        setPayload(null);
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [effectiveLoad, reloadToken]);

  useEffect(() => {
    if (!feedback) return undefined;
    const timer = window.setTimeout(() => setFeedback(null), 4000);
    return () => window.clearTimeout(timer);
  }, [feedback]);

  const orderedItems = useMemo(() => {
    if (!payload) return [] as Array<{ item: RuntimeItem; depth: number }>;
    const tree = buildItemTree(payload.items);
    return flattenTree(tree);
  }, [payload]);

  useEffect(() => {
    if (!orderedItems.length) return undefined;
    const elements = orderedItems
      .map(({ item }) => document.getElementById(`item-${item.id}`))
      .filter((el): el is HTMLElement => Boolean(el));

    if (!elements.length) return undefined;

    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries.filter((entry) => entry.isIntersecting);
        if (visible.length === 0) return;
        visible.sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
        const top = visible[0];
        const id = Number(top.target.getAttribute('data-item-id'));
        if (!Number.isNaN(id)) {
          setActiveItemId(id);
        }
      },
      { root: null, rootMargin: '-30% 0px -50% 0px', threshold: 0.2 }
    );

    elements.forEach((el) => observer.observe(el));
    return () => observer.disconnect();
  }, [orderedItems]);

  const fieldsByItem = useMemo(() => {
    const map = new Map<number, RuntimeField[]>();
    payload?.fields.forEach((field) => {
      const current = map.get(field.itemId) || [];
      current.push(field);
      map.set(field.itemId, current);
    });
    map.forEach((list) => list.sort((a, b) => a.id - b.id));
    return map;
  }, [payload]);

  const updateAnswer = (key: string, value: AnswerValue) => {
    let changed = false;
    setAnswers((prev) => {
      const prevValue = prev[key];
      const shouldDelete = isEmptyValue(value);
      if (shouldDelete) {
        if (prevValue === undefined) {
          return prev;
        }
        const next = { ...prev };
        delete next[key];
        changed = true;
        return next;
      }
      if (areAnswerValuesEqual(prevValue, value)) {
        return prev;
      }
      changed = true;
      return { ...prev, [key]: value };
    });
    if (changed) {
      setDirty(true);
      setFeedback(null);
    }
  };

  const handleSave = async (nextStatus: 'in_progress' | 'submitted') => {
    if (!payload) return;
    setSaving(true);
    setError(null);
    try {
      const outgoing = buildOutgoingAnswers(payload, answers);
      const updated = await effectiveSave({
        answers: outgoing.map((entry) => ({
          itemId: entry.itemId,
          fieldId: entry.fieldId,
          value: entry.value,
        })),
        status: nextStatus,
      });
      setPayload(updated);
      setAnswers(extractAnswerState(updated));
      setStatus((updated.response.status || nextStatus) as ResponseStatus);
      setDirty(false);
      setFeedback(nextStatus === 'submitted' ? 'Questionnaire submitted' : 'Draft saved');
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to save questionnaire';
      setError(message);
    } finally {
      setSaving(false);
    }
  };

  const handleNavClick = (itemId: number, event: React.MouseEvent<HTMLAnchorElement>) => {
    event.preventDefault();
    const element = document.getElementById(`item-${itemId}`);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    setActiveItemId(itemId);
  };

  const reload = () => setReloadToken((token) => token + 1);

  const renderField = (item: RuntimeItem, field: RuntimeField) => {
    const key = `f-${field.id}`;
    const current = answers[key];
    const options = normalizeOptions(field.options);
    const commonLabel = field.label || null;
    const disabled = saving;

    const help = field.help ? <div className="form-text">{field.help}</div> : null;
    const placeholder = field.placeholder ?? undefined;

    switch (field.uiType) {
      case 'textarea':
      case 'wysiwyg':
        return (
          <div className="mb-3" key={field.id}>
            {commonLabel && (
              <label className="form-label" htmlFor={`field-${field.id}`}>
                {commonLabel}
              </label>
            )}
            <textarea
              id={`field-${field.id}`}
              className="form-control"
              rows={4}
              placeholder={placeholder}
              value={typeof current === 'string' ? current : ''}
              onChange={(e) => updateAnswer(key, e.target.value)}
              disabled={disabled}
            />
            {help}
          </div>
        );
      case 'select':
        return (
          <div className="mb-3" key={field.id}>
            {commonLabel && (
              <label className="form-label" htmlFor={`field-${field.id}`}>
                {commonLabel}
              </label>
            )}
            <select
              id={`field-${field.id}`}
              className="form-select"
              value={typeof current === 'string' ? current : current ? String(current) : ''}
              onChange={(e) => updateAnswer(key, e.target.value || null)}
              disabled={disabled}
            >
              <option value="">Select…</option>
              {options.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
            {help}
          </div>
        );
      case 'multiselect': {
        const currentValues = Array.isArray(current) ? current.map(String) : [];
        return (
          <div className="mb-3" key={field.id}>
            {commonLabel && (
              <label className="form-label" htmlFor={`field-${field.id}`}>
                {commonLabel}
              </label>
            )}
            <select
              id={`field-${field.id}`}
              className="form-select"
              multiple
              value={currentValues}
              onChange={(e) => {
                const selected = Array.from(e.target.selectedOptions).map((option) => option.value);
                updateAnswer(key, selected);
              }}
              disabled={disabled}
            >
              {options.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
            {help}
          </div>
        );
      }
      case 'radio': {
        const currentValue = typeof current === 'string' ? current : current ? String(current) : '';
        return (
          <div className="mb-3" key={field.id}>
            {commonLabel && <div className="form-label">{commonLabel}</div>}
            {options.map((option) => (
              <div className="form-check" key={option.value}>
                <input
                  className="form-check-input"
                  type="radio"
                  name={`field-${field.id}`}
                  id={`field-${field.id}-opt-${option.value}`}
                  value={option.value}
                  checked={currentValue === option.value}
                  onChange={(e) => updateAnswer(key, e.target.value)}
                  disabled={disabled}
                />
                <label className="form-check-label" htmlFor={`field-${field.id}-opt-${option.value}`}>
                  {option.label}
                </label>
              </div>
            ))}
            {help}
          </div>
        );
      }
      case 'checkbox': {
        if (options.length > 0) {
          const values = Array.isArray(current) ? current.map(String) : [];
          return (
            <div className="mb-3" key={field.id}>
              {commonLabel && <div className="form-label">{commonLabel}</div>}
              {options.map((option) => {
                const checked = values.includes(option.value);
                return (
                  <div className="form-check" key={option.value}>
                    <input
                      className="form-check-input"
                      type="checkbox"
                      id={`field-${field.id}-opt-${option.value}`}
                      value={option.value}
                      checked={checked}
                      onChange={(e) => {
                        const next = new Set(values);
                        if (e.target.checked) {
                          next.add(option.value);
                        } else {
                          next.delete(option.value);
                        }
                        updateAnswer(key, Array.from(next));
                      }}
                      disabled={disabled}
                    />
                    <label className="form-check-label" htmlFor={`field-${field.id}-opt-${option.value}`}>
                      {option.label}
                    </label>
                  </div>
                );
              })}
              {help}
            </div>
          );
        }
        return (
          <div className="form-check form-switch mb-3" key={field.id}>
            <input
              className="form-check-input"
              type="checkbox"
              id={`field-${field.id}`}
              checked={asBoolean(current)}
              onChange={(e) => updateAnswer(key, e.target.checked)}
              disabled={disabled}
            />
            <label className="form-check-label" htmlFor={`field-${field.id}`}>
              {commonLabel ?? item.title}
            </label>
            {help}
          </div>
        );
      }
      case 'slider': {
        const min = field.minValue ?? 0;
        const max = field.maxValue ?? 100;
        const step = field.stepValue ?? 1;
        const numericValue = typeof current === 'number' ? current : Number(current ?? min);
        return (
          <div className="mb-3" key={field.id}>
            {commonLabel && (
              <label className="form-label" htmlFor={`field-${field.id}`}>
                {commonLabel}
              </label>
            )}
            <input
              id={`field-${field.id}`}
              type="range"
              className="form-range"
              min={min}
              max={max}
              step={step}
              value={Number.isFinite(numericValue) ? numericValue : min}
              onChange={(e) => updateAnswer(key, Number(e.target.value))}
              disabled={disabled}
            />
            <div className="small text-muted">Current value: {Number.isFinite(numericValue) ? numericValue : min}</div>
            {help}
          </div>
        );
      }
      case 'integer':
      case 'date':
      case 'time':
      case 'color':
      case 'toggle':
      case 'input':
      case 'autocomplete':
      case 'chainselect':
      case 'daterange':
      case 'image':
      case 'file':
      case 'voice':
      case 'video':
      default: {
        if (field.uiType === 'toggle') {
          return (
            <div className="form-check form-switch mb-3" key={field.id}>
              <input
                className="form-check-input"
                type="checkbox"
                id={`field-${field.id}`}
                checked={asBoolean(current)}
                onChange={(e) => updateAnswer(key, e.target.checked)}
                disabled={disabled}
              />
              <label className="form-check-label" htmlFor={`field-${field.id}`}>
                {commonLabel ?? item.title}
              </label>
              {help}
            </div>
          );
        }

        const inputType = field.uiType === 'integer'
          ? 'number'
          : field.uiType === 'date'
            ? 'date'
            : field.uiType === 'time'
              ? 'time'
              : field.uiType === 'color'
                ? 'color'
                : 'text';

        const value = typeof current === 'string' || typeof current === 'number'
          ? current
          : '';

        return (
          <div className="mb-3" key={field.id}>
            {commonLabel && (
              <label className="form-label" htmlFor={`field-${field.id}`}>
                {commonLabel}
              </label>
            )}
            <input
              id={`field-${field.id}`}
              type={inputType}
              className="form-control"
              placeholder={placeholder}
              value={value as string | number}
              onChange={(e) => {
                const nextValue = inputType === 'number' ? (e.target.value === '' ? null : Number(e.target.value)) : e.target.value;
                updateAnswer(key, nextValue as AnswerValue);
              }}
              disabled={disabled}
            />
            {help}
          </div>
        );
      }
    }
  };

  if (loading && !payload) {
    return (
      <div className="qw-runner-shell">
        <div className="qw-runner-main w-100 d-flex align-items-center justify-content-center" style={{ minHeight: '320px' }}>
          <div className="text-center">
            <div className="spinner-border text-primary" role="status">
              <span className="visually-hidden">Loading…</span>
            </div>
            <div className="mt-3 text-muted">Loading questionnaire…</div>
          </div>
        </div>
      </div>
    );
  }

  if (!payload) {
    return (
      <div className="qw-runner-shell">
        <div className="qw-runner-main w-100">
          <div className="alert alert-danger d-flex align-items-center" role="alert">
            <i className="bi bi-exclamation-triangle me-2" aria-hidden="true" />
            <div>{error ?? 'Unable to load questionnaire.'}</div>
          </div>
          <button className="btn btn-outline-primary" type="button" onClick={reload}>
            Retry
          </button>
        </div>
      </div>
    );
  }

  const statusLabel = status === 'submitted' ? 'Submitted' : 'In progress';
  const statusBadgeClass = status === 'submitted' ? 'bg-success' : 'bg-secondary';
  const disableSubmit = saving;

  const questionItems = orderedItems.filter(({ item }) => item.type === 'question');

  return (
    <div className="qw-runner-shell">
      <aside className="qw-runner-nav">
        <div className="mb-3">
          <div className="qw-nav-title">CI Context</div>
          <div className="fw-semibold">{payload.ci.name}</div>
          <div className="text-muted small">{payload.ci.key}</div>
          {payload.ci.application && (
            <div className="text-muted small mt-1">
              <div>Application: {payload.ci.application.appName}</div>
              {payload.ci.application.environment && (
                <div>Environment: {payload.ci.application.environment}</div>
              )}
            </div>
          )}
        </div>

        <div className="mb-3">
          <div className="qw-nav-title">Status</div>
          <span className={`badge ${statusBadgeClass}`}>{statusLabel}</span>
          {dirty && <div className="small text-warning mt-2">Unsaved changes</div>}
        </div>

        <div className="d-grid gap-2 mb-4">
          <button
            type="button"
            className="btn btn-outline-primary"
            onClick={() => handleSave('in_progress')}
            disabled={saving}
          >
            {saving ? 'Saving…' : 'Save Draft'}
          </button>
          <button
            type="button"
            className="btn btn-success"
            onClick={() => handleSave('submitted')}
            disabled={disableSubmit}
          >
            {status === 'submitted' ? 'Submit Again' : 'Submit'}
          </button>
        </div>

        <div>
          <div className="qw-nav-title">Sections</div>
          {questionItems.length === 0 && (
            <div className="text-muted small">No questions defined.</div>
          )}
          {orderedItems.map(({ item, depth }) => {
            const label = item.outline ? `${item.outline} ${item.title}` : item.title;
            return (
              <a
                key={item.id}
                href={`#item-${item.id}`}
                className={`qw-nav-item ${activeItemId === item.id ? 'is-active' : ''}`}
                style={{ paddingLeft: 12 + depth * 12 }}
                onClick={(event) => handleNavClick(item.id, event)}
              >
                {label}
              </a>
            );
          })}
        </div>
      </aside>

      <main className="qw-runner-main">
        <div className="qw-runner-header">
          <h1>{payload.questionnaire.title}</h1>
          <div className="qw-runner-meta">
            <span>CI: {payload.ci.name}</span>
            <span>Key: {payload.ci.key}</span>
            <span>Status: {statusLabel}</span>
            {payload.questionnaire.description && (
              <span>{payload.questionnaire.description}</span>
            )}
          </div>
        </div>

        {error && (
          <div className="alert alert-danger d-flex align-items-center" role="alert">
            <i className="bi bi-exclamation-circle me-2" aria-hidden="true" />
            <div>{error}</div>
          </div>
        )}

        {feedback && (
          <div className="alert alert-success d-flex align-items-center" role="alert">
            <i className="bi bi-check-circle me-2" aria-hidden="true" />
            <div>{feedback}</div>
          </div>
        )}

        {orderedItems.map(({ item, depth }) => {
          const sectionId = `item-${item.id}`;
          const itemFields = fieldsByItem.get(item.id) || [];
          const offsetStyle = depth > 0 ? { marginLeft: depth * 12 } : undefined;

          if (item.type === 'header') {
            return (
              <section
                key={item.id}
                id={sectionId}
                data-item-id={item.id}
                className="mb-4"
                style={offsetStyle}
              >
                <div className="border-start border-4 border-primary bg-light ps-3 py-2 rounded-3">
                  <h2 className="h4 mb-1">{item.title}</h2>
                  {item.help && <p className="text-muted small mb-0">{item.help}</p>}
                </div>
              </section>
            );
          }

          if (itemFields.length === 0) {
            const key = `i-${item.id}`;
            const current = answers[key];
            return (
              <section
                key={item.id}
                id={sectionId}
                data-item-id={item.id}
                className="mb-4"
                style={offsetStyle}
              >
                <div className="card shadow-sm">
                  <div className="card-body">
                    <div className="d-flex justify-content-between align-items-baseline mb-2">
                      <h3 className="h5 mb-0">{item.title}</h3>
                      {item.required && <span className="badge text-bg-danger">Required</span>}
                    </div>
                    {item.help && <p className="text-muted small">{item.help}</p>}
                    <textarea
                      className="form-control"
                      rows={4}
                      placeholder="Add your notes…"
                      value={typeof current === 'string' ? current : ''}
                      onChange={(e) => updateAnswer(key, e.target.value)}
                      disabled={saving}
                    />
                  </div>
                </div>
              </section>
            );
          }

          return (
            <section
              key={item.id}
              id={sectionId}
              data-item-id={item.id}
              className="mb-4"
              style={offsetStyle}
            >
              <div className="card shadow-sm">
                <div className="card-body">
                  <div className="d-flex justify-content-between align-items-baseline mb-2">
                    <h3 className="h5 mb-0">{item.title}</h3>
                    {item.required && <span className="badge text-bg-danger">Required</span>}
                  </div>
                  {item.help && <p className="text-muted small">{item.help}</p>}
                  {itemFields.map((field) => renderField(item, field))}
                </div>
              </div>
            </section>
          );
        })}
      </main>
    </div>
  );
};

export default QuestionnaireRunner;
