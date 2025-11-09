// assets/react/qw/QuestionnaireEditor.tsx

import React, { useEffect, useMemo, useState } from 'react';
import QuestionnaireRunner from './QuestionnaireRunner';
import { createResponse, listCis, listQuestionnaires, listResponses } from './api';
import { loadResponseRuntime, saveResponseRuntime } from './runtimeApi';
import type { CiSummary, QuestionnaireSummary, ResponseSummary } from './types';

interface QuestionnaireEditorProps {
  tenantId: number;
}

const QuestionnaireEditor: React.FC<QuestionnaireEditorProps> = ({ tenantId }) => {
  const [cis, setCis] = useState<CiSummary[]>([]);
  const [ciSearch, setCiSearch] = useState('');
  const [selectedCiId, setSelectedCiId] = useState<number | null>(null);
  const [questionnaires, setQuestionnaires] = useState<QuestionnaireSummary[]>([]);
  const [selectedQuestionnaireId, setSelectedQuestionnaireId] = useState<number | null>(null);
  const [responses, setResponses] = useState<ResponseSummary[]>([]);
  const [selectedResponseId, setSelectedResponseId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loadingCis, setLoadingCis] = useState<boolean>(true);
  const [loadingQuestionnaires, setLoadingQuestionnaires] = useState<boolean>(false);
  const [loadingResponses, setLoadingResponses] = useState<boolean>(false);
  const [creatingVersion, setCreatingVersion] = useState<boolean>(false);

  useEffect(() => {
    let cancelled = false;
    setLoadingCis(true);
    listCis(tenantId)
      .then((data: CiSummary[]) => {
        if (cancelled) return;
        setCis(data);
        if (data.length && selectedCiId === null) {
          setSelectedCiId(data[0].id);
        }
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        const message = err instanceof Error ? err.message : 'Failed to load configuration items';
        setError(message);
      })
      .finally(() => {
        if (!cancelled) {
          setLoadingCis(false);
        }
      });
    return () => {
      cancelled = true;
    };
  }, [tenantId]);

  useEffect(() => {
    if (selectedCiId === null) {
      setQuestionnaires([]);
      setSelectedQuestionnaireId(null);
      return;
    }
    let cancelled = false;
    setLoadingQuestionnaires(true);
    listQuestionnaires({ tenantId, ciId: selectedCiId })
      .then((data: QuestionnaireSummary[]) => {
        if (cancelled) return;
        setQuestionnaires(data);
        if (!data.some((q) => q.id === selectedQuestionnaireId)) {
          setSelectedQuestionnaireId(data.length ? data[0].id : null);
        }
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        const message = err instanceof Error ? err.message : 'Failed to load questionnaires';
        setError(message);
      })
      .finally(() => {
        if (!cancelled) {
          setLoadingQuestionnaires(false);
        }
      });
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tenantId, selectedCiId]);

  useEffect(() => {
    if (selectedQuestionnaireId === null) {
      setResponses([]);
      setSelectedResponseId(null);
      return;
    }
    let cancelled = false;
    setLoadingResponses(true);
    listResponses(selectedQuestionnaireId)
      .then((data: ResponseSummary[]) => {
        if (cancelled) return;
        setResponses(data);
        if (!data.some((entry) => entry.id === selectedResponseId)) {
          const inProgress = data.find((entry) => entry.status === 'in_progress');
          const fallback = inProgress ?? data[0] ?? null;
          setSelectedResponseId(fallback ? fallback.id : null);
        }
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        const message = err instanceof Error ? err.message : 'Failed to load versions';
        setError(message);
      })
      .finally(() => {
        if (!cancelled) {
          setLoadingResponses(false);
        }
      });
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedQuestionnaireId]);

  const filteredCis = useMemo(() => {
    const needle = ciSearch.trim().toLowerCase();
    if (!needle) {
      return cis;
    }
    return cis.filter((ci) =>
      ci.ciKey.toLowerCase().includes(needle) || ci.ciName.toLowerCase().includes(needle)
    );
  }, [cis, ciSearch]);

  const activeQuestionnaire = useMemo(
    () => questionnaires.find((q) => q.id === selectedQuestionnaireId) ?? null,
    [questionnaires, selectedQuestionnaireId]
  );

  const activeResponse = useMemo(
    () => responses.find((r) => r.id === selectedResponseId) ?? null,
    [responses, selectedResponseId]
  );

  const runtimeAdapter = useMemo(() => {
    if (!selectedResponseId) {
      return null;
    }
    const responseId = selectedResponseId;
    return {
      load: () => loadResponseRuntime(responseId),
      save: (payload: Parameters<typeof saveResponseRuntime>[1]) => saveResponseRuntime(responseId, payload),
    };
  }, [selectedResponseId]);

  const handleCreateVersion = async (clone: boolean) => {
    if (!selectedQuestionnaireId) {
      return;
    }
    setCreatingVersion(true);
    try {
      const payload = clone && selectedResponseId ? { cloneFrom: selectedResponseId } : undefined;
      const created = await createResponse(selectedQuestionnaireId, payload);
      const updated = await listResponses(selectedQuestionnaireId);
      setResponses(updated);
      const targetId: number | null = typeof created?.id === 'number' ? created.id : null;
      if (targetId) {
        setSelectedResponseId(targetId);
      }
      setError(null);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to create version';
      setError(message);
    } finally {
      setCreatingVersion(false);
    }
  };

  return (
    <div className="qw-editor-shell">
      <div className="qw-editor-sidebar">
        <div className="qw-editor-panel">
          <h2 className="qw-editor-title">Configuration Items</h2>
          <div className="qw-editor-search">
            <input
              type="search"
              placeholder="Search by key or name"
              value={ciSearch}
              onChange={(event) => setCiSearch(event.target.value)}
              disabled={loadingCis}
            />
          </div>
          <div className="qw-editor-list">
            {filteredCis.map((ci) => (
              <div
                key={ci.id}
                className={`qw-editor-item${ci.id === selectedCiId ? ' is-active' : ''}`}
                onClick={() => {
                  setSelectedCiId(ci.id);
                  setSelectedQuestionnaireId(null);
                  setSelectedResponseId(null);
                  setError(null);
                }}
              >
                <div>{ci.ciName}</div>
                <div style={{ fontSize: '11px', color: '#6b7280', marginTop: '4px' }}>{ci.ciKey}</div>
              </div>
            ))}
            {!filteredCis.length && !loadingCis && <div style={{ fontSize: '12px', color: '#6b7280' }}>No CI matches this search.</div>}
          </div>
        </div>

        <div className="qw-editor-panel">
          <h2 className="qw-editor-title">Questionnaires</h2>
          <div className="qw-editor-list" style={{ maxHeight: '220px' }}>
            {loadingQuestionnaires && <div style={{ fontSize: '12px', color: '#6b7280' }}>Loading questionnaires…</div>}
            {!loadingQuestionnaires && questionnaires.map((questionnaire) => (
              <div
                key={questionnaire.id}
                className={`qw-editor-item${questionnaire.id === selectedQuestionnaireId ? ' is-active' : ''}`}
                onClick={() => {
                  setSelectedQuestionnaireId(questionnaire.id);
                  setSelectedResponseId(null);
                }}
              >
                <div>{questionnaire.title}</div>
                <div style={{ fontSize: '11px', color: '#6b7280', marginTop: '4px' }}>
                  {questionnaire.status} · v{questionnaire.version ?? 1}
                </div>
              </div>
            ))}
            {!loadingQuestionnaires && !questionnaires.length && (
              <div style={{ fontSize: '12px', color: '#6b7280' }}>No questionnaires found for this CI.</div>
            )}
          </div>
        </div>

        <div className="qw-editor-panel">
          <h2 className="qw-editor-title">Versions</h2>
          <div className="qw-editor-versions">
            {loadingResponses && <div style={{ fontSize: '12px', color: '#6b7280' }}>Loading versions…</div>}
            {!loadingResponses && responses.map((response) => (
              <div
                key={response.id}
                className={`qw-editor-version${response.id === selectedResponseId ? ' is-active' : ''}`}
                onClick={() => setSelectedResponseId(response.id)}
              >
                <div>Response #{response.id}</div>
                <div className="qw-editor-meta">
                  <span>{response.status}</span>
                  {response.submittedAt && <span>Submitted {new Date(response.submittedAt).toLocaleDateString()}</span>}
                  {!response.submittedAt && response.startedAt && <span>Started {new Date(response.startedAt).toLocaleDateString()}</span>}
                </div>
              </div>
            ))}
            {!loadingResponses && !responses.length && (
              <div style={{ fontSize: '12px', color: '#6b7280' }}>No versions yet. Create one to start.</div>
            )}
          </div>
          <div className="qw-editor-actions">
            <button type="button" onClick={() => handleCreateVersion(false)} disabled={!selectedQuestionnaireId || creatingVersion}>
              New Version
            </button>
            <button
              type="button"
              className="secondary"
              onClick={() => handleCreateVersion(true)}
              disabled={!selectedQuestionnaireId || !selectedResponseId || creatingVersion}
            >
              Clone Selected
            </button>
          </div>
          {error && (
            <div style={{ marginTop: '10px', color: '#b91c1c', fontSize: '12px' }}>{error}</div>
          )}
        </div>
      </div>

      <div className="qw-editor-main">
        {runtimeAdapter && activeQuestionnaire && activeResponse ? (
          <QuestionnaireRunner adapter={runtimeAdapter} />
        ) : (
          <div className="qw-editor-empty">
            <div style={{ fontSize: '18px', fontWeight: 600, color: '#1f2937' }}>Select a version to begin</div>
            <p style={{ maxWidth: '360px', fontSize: '13px' }}>
              Choose a configuration item, pick the questionnaire you want to work on, then create or select a
              response version to load the editor.
            </p>
          </div>
        )}
      </div>
    </div>
  );
};

export default QuestionnaireEditor;
