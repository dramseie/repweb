import React, { useEffect, useRef, useState } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin, { Draggable } from '@fullcalendar/interaction';

const toYmd = (date) => {
  const d = new Date(date);
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const toDateTimeLocal = (date) => {
  if (!date) return '';
  const d = new Date(date);
  if (Number.isNaN(d.getTime())) return '';
  const hours = String(d.getHours()).padStart(2, '0');
  const minutes = String(d.getMinutes()).padStart(2, '0');
  return `${toYmd(d)}T${hours}:${minutes}`;
};

const parseTime = (value) => {
  const [h, m] = String(value).split(':').map((part) => parseInt(part, 10));
  if (Number.isNaN(h) || Number.isNaN(m)) return 0;
  return h * 60 + m;
};

const sumHours = (ranges) => {
  const totalMinutes = ranges.reduce((acc, range) => {
    const [start, end] = range;
    const minutes = Math.max(0, parseTime(end) - parseTime(start));
    return acc + minutes;
  }, 0);
  return (totalMinutes / 60).toFixed(2);
};

const formatRanges = (ranges) => ranges.map(([start, end]) => `${start}-${end}`).join(' / ');

const buildEventTitle = (template, range) => `${template.label} ${range[0]}-${range[1]}`;

const buildComment = (template) => `${template.label}`;

const TimesheetCalendar = ({ contracts, initialHours }) => {
  const [templates, setTemplates] = useState(() => [
    {
      id: 'remoteoffice',
      label: 'RemoteOffice',
      category: 'RemoteOffice',
      ranges: [
        ['08:45', '12:00'],
        ['12:45', '18:00'],
      ],
      color: '#0d6efd',
    },
    {
      id: 'onsite',
      label: 'OnSite',
      category: 'OnSite',
      ranges: [
        ['08:45', '12:00'],
        ['12:45', '18:00'],
      ],
      color: '#fd7e14',
    },
    {
      id: 'vacation',
      label: 'Vacation',
      category: 'Vacation',
      allDay: true,
      hours: '8.50',
      ranges: [],
      color: '#20c997',
      requiresContract: false,
    },
    {
      id: 'sickness',
      label: 'Sickness',
      category: 'Sickness',
      allDay: true,
      hours: '8.50',
      ranges: [],
      color: '#dc3545',
      requiresContract: false,
    },
  ]);

  const [isTemplateEditorOpen, setIsTemplateEditorOpen] = useState(false);
  const [templateDraft, setTemplateDraft] = useState(null);

  const openTemplateEditor = (template) => {
    setTemplateDraft({ ...template });
    setIsTemplateEditorOpen(true);
  };

  const closeTemplateEditor = () => {
    setIsTemplateEditorOpen(false);
    setTemplateDraft(null);
  };

  const saveTemplateEditor = () => {
    if (!templateDraft?.id) return;
    setTemplates((prev) =>
      prev.map((template) =>
        template.id === templateDraft.id
          ? { ...template, color: templateDraft.color, category: templateDraft.category }
          : template
      )
    );
    closeTemplateEditor();
  };

  const createTemplate = () => {
    const newTemplate = {
      id: `template-${Date.now()}`,
      label: 'New Template',
      category: 'RemoteOffice',
      ranges: [
        ['08:45', '12:00'],
        ['12:45', '18:00'],
      ],
      color: '#6c757d',
    };
    setTemplates((prev) => [...prev, newTemplate]);
    openTemplateEditor(newTemplate);
  };

  const deleteTemplate = () => {
    if (!templateDraft?.id) return;
    setTemplates((prev) => prev.filter((template) => template.id !== templateDraft.id));
    closeTemplateEditor();
  };

  const [selectedContractId, setSelectedContractId] = useState(
    contracts?.[0]?.id ? String(contracts[0].id) : ''
  );

  const [events, setEvents] = useState(() =>
    (initialHours || []).map((entry) => {
      const hasTime = entry.startTime && entry.endTime;
      const start = hasTime ? `${entry.workDate}T${entry.startTime}` : entry.workDate;
      const end = hasTime ? `${entry.workDate}T${entry.endTime}` : undefined;
      const baseLabel = entry.contractLabel || entry.category || 'Global';
      return {
        id: String(entry.id),
        title: hasTime
          ? `${baseLabel} · ${entry.startTime}-${entry.endTime}`
          : `${entry.hours}h — ${baseLabel}${entry.comment ? ` · ${entry.comment}` : ''}`,
        start,
        end,
        allDay: !hasTime,
        backgroundColor: '#198754',
        borderColor: '#198754',
        extendedProps: {
          contractId: entry.contractId,
          contractLabel: entry.contractLabel,
          hours: entry.hours,
          comment: entry.comment || '',
          category: entry.category || '',
        },
      };
    })
  );

  const listRef = useRef(null);
  const trashRef = useRef(null);
  const calendarRef = useRef(null);

  useEffect(() => {
    const api = calendarRef.current?.getApi?.();
    if (!api) return undefined;
    let rafId = 0;
    const refresh = () => {
      api.updateSize();
    };
    rafId = requestAnimationFrame(refresh);
    const timer = setTimeout(refresh, 150);
    return () => {
      if (rafId) cancelAnimationFrame(rafId);
      clearTimeout(timer);
    };
  }, []);

  useEffect(() => {
    if (!listRef.current) return undefined;

    const draggable = new Draggable(listRef.current, {
      itemSelector: '.ts-template-item',
      eventData: (eventEl) => {
        const templateId = eventEl.getAttribute('data-template-id');
        const template = templates.find((item) => item.id === templateId);
        if (!template) return null;
        return {
          allDay: true,
          backgroundColor: template.color,
          borderColor: template.color,
          extendedProps: {
            templateId: template.id,
            category: template.category || '',
            comment: buildComment(template),
          },
        };
      },
    });

    return () => {
      draggable.destroy();
    };
  }, [templates]);

  const handleEventReceive = async (info) => {
    const template = templates.find((item) => item.id === info.event.extendedProps.templateId);
    if (!template) {
      info.event.remove();
      return;
    }

    const requiresContract = template.requiresContract !== false;
    const contractId = selectedContractId;
    if (!contractId && requiresContract) {
      info.event.remove();
      alert('Select a contract before dropping hours.');
      return;
    }
    info.event.remove();

    const workDate = toYmd(info.event.start || new Date());
    const commentValue = buildComment(template);
    const categoryValue = template.category || '';

    try {
      if (template.allDay) {
        const payload = new URLSearchParams({
          workDate,
          hours: template.hours || '8.50',
          category: categoryValue,
          comment: commentValue,
        });
        if (contractId) payload.set('contractId', contractId);

        const response = await fetch('/timesheet/hours', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: payload.toString(),
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const entry = await response.json();
        const contract = contracts.find((item) => String(item.id) === String(contractId));
        const contractLabel = contract?.label || entry.contractLabel || 'Global';

        setEvents((prev) => [
          ...prev,
          {
            id: String(entry.id),
            title: `${template.label} · ${workDate}`,
            start: workDate,
            allDay: true,
            backgroundColor: template.color,
            borderColor: template.color,
            extendedProps: {
              contractId: entry.contractId || contractId || '',
              contractLabel: entry.contractLabel || contractLabel,
              hours: entry.hours,
              comment: entry.comment || '',
              category: entry.category || categoryValue,
            },
          },
        ]);
      } else {
        const responses = await Promise.all(
          template.ranges.map(async (range) => {
            const payload = new URLSearchParams({
              contractId,
              workDate,
              startTime: range[0],
              endTime: range[1],
              category: categoryValue,
              comment: commentValue,
            });

            const response = await fetch('/timesheet/hours', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
              },
              body: payload.toString(),
            });

            if (!response.ok) {
              throw new Error(`HTTP ${response.status}`);
            }

            return response.json();
          })
        );

        const contract = contracts.find((item) => String(item.id) === String(contractId));
        const contractLabel = contract?.label || 'Contract';

        setEvents((prev) => [
          ...prev,
          ...responses.map((entry, index) => {
            const range = template.ranges[index];
            return {
              id: String(entry.id),
              title: `${contractLabel} · ${range[0]}-${range[1]}`,
              start: `${workDate}T${range[0]}`,
              end: `${workDate}T${range[1]}`,
              allDay: false,
              backgroundColor: template.color,
              borderColor: template.color,
              extendedProps: {
                contractId,
                contractLabel,
                hours: entry.hours,
                comment: entry.comment || '',
                category: entry.category || categoryValue,
              },
            };
          }),
        ]);
      }
    } catch (error) {
      alert('Failed to save hours.');
    }
  };

  const handleEventChange = async (event) => {
    if (!event?.id) return;
    const start = event.start;
    const end = event.end;
    if (!start || !end) return;

    const workDate = toYmd(start);
    const startTime = `${String(start.getHours()).padStart(2, '0')}:${String(start.getMinutes()).padStart(2, '0')}`;
    const endTime = `${String(end.getHours()).padStart(2, '0')}:${String(end.getMinutes()).padStart(2, '0')}`;

    const payload = new URLSearchParams({
      workDate,
      startTime,
      endTime,
    });

    try {
      const response = await fetch(`/timesheet/hours/${encodeURIComponent(event.id)}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: payload.toString(),
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();
      if (result?.startTime && result?.endTime) {
        const label = event.extendedProps?.contractLabel || event.title.split(' · ')[0];
        event.setProp('title', `${label} · ${result.startTime}-${result.endTime}`);
      }
    } catch (error) {
      alert('Failed to update hours.');
    }
  };

  const handleEventDragStop = async (info) => {
    const trash = trashRef.current;
    if (!trash) return;
    const trashRect = trash.getBoundingClientRect();
    const x = info.jsEvent.clientX;
    const y = info.jsEvent.clientY;
    const inTrash = x >= trashRect.left && x <= trashRect.right && y >= trashRect.top && y <= trashRect.bottom;
    if (!inTrash) return;

    const eventId = info.event?.id;
    if (!eventId) return;

    try {
      const response = await fetch(`/timesheet/hours/${encodeURIComponent(eventId)}/delete`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      info.event.remove();
    } catch (error) {
      alert('Failed to delete hours.');
    }
  };

  const handleEventClick = (info) => {
    const event = info?.event;
    if (!event) return;

    const form = document.getElementById('timesheet-hours-form');
    if (!form) return;

    const contractId = event.extendedProps?.contractId ? String(event.extendedProps.contractId) : '';
    const workDate = event.start ? toYmd(event.start) : '';
    const startDateTime = event.start ? toDateTimeLocal(event.start) : '';
    const endDateTime = event.end ? toDateTimeLocal(event.end) : '';

    const setValue = (name, value) => {
      const field = form.querySelector(`[name="${name}"]`);
      if (field) field.value = value ?? '';
    };

    setValue('contractId', contractId);
    if (event.allDay && workDate) {
      const hoursValue = parseFloat(event.extendedProps?.hours || '0');
      const startBase = new Date(`${workDate}T08:00:00`);
      const endBase = Number.isFinite(hoursValue)
        ? new Date(startBase.getTime() + hoursValue * 3600 * 1000)
        : startBase;
      setValue('startDateTime', toDateTimeLocal(startBase));
      setValue('endDateTime', toDateTimeLocal(endBase));
    } else {
      setValue('startDateTime', startDateTime);
      setValue('endDateTime', endDateTime);
    }
    setValue('category', event.extendedProps?.category || '');
    setValue('comment', event.extendedProps?.comment || '');
    setValue('entryId', event.id || '');

    if (contractId) {
      setSelectedContractId(contractId);
    }

    const updateBase = form.getAttribute('data-update-base') || '';
    const createAction = form.getAttribute('data-create-action') || form.getAttribute('action') || '';
    if (event.id && updateBase) {
      form.setAttribute('action', `${updateBase}/${encodeURIComponent(event.id)}`);
    } else if (createAction) {
      form.setAttribute('action', createAction);
    }

    const submitButton = document.getElementById('timesheet-hours-submit');
    if (submitButton) {
      const editLabel = submitButton.getAttribute('data-edit-label') || 'Update Hours';
      submitButton.textContent = editLabel;
    }
  };

  return (
    <div className="row g-3">
      <div className="col-12 col-lg-3">
        <div className="border rounded p-3 h-100">
          <label className="form-label">Contract</label>
          <select
            className="form-select"
            value={selectedContractId}
            onChange={(event) => setSelectedContractId(event.target.value)}
          >
            <option value="">Select contract</option>
            {contracts.map((contract) => (
              <option key={contract.id} value={contract.id}>
                {contract.label}
              </option>
            ))}
          </select>
          <div className="form-text">Drag a template onto the calendar.</div>
          <hr />
          <div className="d-flex justify-content-between align-items-center mb-2">
            <div className="fw-semibold">Templates</div>
            <button type="button" className="btn btn-sm btn-outline-primary" onClick={createTemplate}>
              +
            </button>
          </div>
          <div ref={listRef} className="d-flex flex-column gap-2">
            {templates.map((template) => {
              const hoursValue = template.allDay ? template.hours || '8.50' : sumHours(template.ranges);
              return (
                <div
                  key={template.id}
                  className="ts-template-item border rounded p-2 bg-light"
                  data-template-id={template.id}
                  style={{ cursor: 'grab' }}
                >
                  <div className="d-flex justify-content-between align-items-start gap-2">
                    <div>
                      <div className="fw-semibold">{template.label}</div>
                      <div className="text-muted small">
                        {template.allDay ? 'All day' : formatRanges(template.ranges)}
                      </div>
                      <div className="small">{hoursValue}h</div>
                      <div className="text-muted small">Category: {template.category || '—'}</div>
                    </div>
                    <button
                      type="button"
                      className="btn btn-sm btn-outline-secondary"
                      onClick={() => openTemplateEditor(template)}
                    >
                      Edit
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
          <div
            ref={trashRef}
            className="border border-danger rounded text-danger text-center py-2 mt-3"
            style={{ background: '#fff5f5' }}
          >
            Drop here to delete
          </div>
        </div>
      </div>
      <div className="col-12 col-lg-9">
        <FullCalendar
          ref={calendarRef}
          plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
          initialView="timeGridWeek"
          headerToolbar={{
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay',
          }}
          firstDay={1}
          locale="en-CA"
          titleFormat={(arg) => {
            const rawStart = arg?.start || arg?.date;
            const rawEnd = arg?.end;
            const viewType = arg?.view?.type;
            if (!rawStart) return '';
            const start = rawStart instanceof Date ? rawStart : new Date(rawStart);
            if (Number.isNaN(start.getTime())) return '';
            if (viewType === 'timeGridDay') {
              return toYmd(start);
            }
            if (!rawEnd) return toYmd(start);
            const end = rawEnd instanceof Date ? rawEnd : new Date(rawEnd);
            if (Number.isNaN(end.getTime())) return toYmd(start);
            const endInclusive = new Date(end.getTime() - 24 * 60 * 60 * 1000);
            return `${toYmd(start)} — ${toYmd(endInclusive)}`;
          }}
          dayHeaderContent={(arg) => (arg?.date ? toYmd(arg.date) : '')}
          slotLabelFormat={{ hour: '2-digit', minute: '2-digit', hour12: false }}
          eventTimeFormat={{ hour: '2-digit', minute: '2-digit', hour12: false }}
          height="auto"
          editable
          droppable
          eventReceive={handleEventReceive}
          eventDrop={(info) => handleEventChange(info.event)}
          eventResize={(info) => handleEventChange(info.event)}
          eventDragStop={handleEventDragStop}
          eventClick={handleEventClick}
          events={events}
          slotMinTime="06:00:00"
          slotMaxTime="21:00:00"
        />
      </div>
      {isTemplateEditorOpen && templateDraft && (
        <>
          <div className="modal fade show d-block" tabIndex="-1" role="dialog" aria-modal="true">
            <div className="modal-dialog modal-dialog-centered" role="document">
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">Edit Template</h5>
                  <button type="button" className="btn-close" aria-label="Close" onClick={closeTemplateEditor} />
                </div>
                <div className="modal-body">
                  <div className="mb-3">
                    <label className="form-label">Template</label>
                    <input type="text" className="form-control" value={templateDraft.label} disabled />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Hours</label>
                    <div className="form-control bg-light">
                      {templateDraft.allDay
                        ? 'All day'
                        : formatRanges(templateDraft.ranges || [])}
                    </div>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Category</label>
                    <input
                      type="text"
                      className="form-control"
                      value={templateDraft.category || ''}
                      onChange={(event) =>
                        setTemplateDraft((prev) => ({ ...prev, category: event.target.value }))
                      }
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Color</label>
                    <input
                      type="color"
                      className="form-control form-control-color"
                      value={templateDraft.color || '#0d6efd'}
                      onChange={(event) =>
                        setTemplateDraft((prev) => ({ ...prev, color: event.target.value }))
                      }
                    />
                  </div>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-outline-danger me-auto" onClick={deleteTemplate}>
                    Delete
                  </button>
                  <button type="button" className="btn btn-outline-secondary" onClick={closeTemplateEditor}>
                    Cancel
                  </button>
                  <button type="button" className="btn btn-primary" onClick={saveTemplateEditor}>
                    Save
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div className="modal-backdrop fade show" />
        </>
      )}
    </div>
  );
};

export default TimesheetCalendar;
